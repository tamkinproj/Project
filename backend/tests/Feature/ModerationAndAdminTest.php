<?php

namespace Tests\Feature;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\User;
use App\Modules\Social\Enums\ModerationStatus;
use App\Modules\Social\Enums\PostVisibility;
use App\Modules\Social\Models\Post;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class ModerationAndAdminTest extends TestCase
{
    public function test_members_can_report_content_once_and_never_their_own(): void
    {
        $reporter = User::factory()->create();
        $post = Post::factory()->create();
        $own = Post::factory()->for($reporter, 'author')->create();
        $payload = ['target_type' => 'post', 'target_id' => $post->id, 'reason' => 'spam'];

        $first = $this->actingWithToken($reporter)->postJson('/api/v1/reports', $payload)->assertCreated();
        $second = $this->actingWithToken($reporter)->postJson('/api/v1/reports', $payload)->assertCreated();
        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertSame(1, DB::table('reports')->count());

        $this->actingWithToken($reporter)->postJson('/api/v1/reports', ['target_type' => 'post', 'target_id' => $own->id, 'reason' => 'spam'])->assertUnprocessable();
        $this->actingWithToken($reporter)->postJson('/api/v1/reports', ['target_type' => 'user', 'target_id' => $this->username($reporter), 'reason' => 'spam'])->assertUnprocessable();
    }

    public function test_private_posts_cannot_be_reported_by_others(): void
    {
        $reporter = User::factory()->create();
        $private = Post::factory()->visibility(PostVisibility::OnlyMe)->create();

        $this->actingWithToken($reporter)->postJson('/api/v1/reports', ['target_type' => 'post', 'target_id' => $private->id, 'reason' => 'spam'])->assertNotFound();
    }

    public function test_a_blocked_user_can_still_be_reported(): void
    {
        $reporter = User::factory()->create();
        $harasser = User::factory()->create();
        DB::table('blocks')->insert(['blocker_id' => $harasser->id, 'blocked_id' => $reporter->id, 'created_at' => now()]);

        $this->actingWithToken($reporter)->postJson('/api/v1/reports', ['target_type' => 'user', 'target_id' => $this->username($harasser), 'reason' => 'harassment'])->assertCreated();
    }

    public function test_admin_endpoints_reject_ordinary_members(): void
    {
        $member = User::factory()->create();

        foreach (['overview', 'users', 'reports', 'cards', 'audit-logs', 'system/health', 'roles'] as $path) {
            $this->actingWithToken($member)->getJson("/api/v1/admin/{$path}")->assertForbidden();
        }
    }

    public function test_each_role_only_reaches_its_own_endpoints(): void
    {
        $moderator = $this->adminWithRole('moderator');
        $auditor = $this->adminWithRole('auditor');

        $this->actingWithToken($moderator)->getJson('/api/v1/admin/reports')->assertOk();
        $this->actingWithToken($moderator)->getJson('/api/v1/admin/audit-logs')->assertForbidden();
        $this->actingWithToken($moderator)->getJson('/api/v1/admin/roles')->assertForbidden();

        $this->actingWithToken($auditor)->getJson('/api/v1/admin/audit-logs')->assertOk();
        $this->actingWithToken($auditor)->getJson('/api/v1/admin/reports')->assertForbidden();
        $this->actingWithToken($auditor)->postJson('/api/v1/admin/users/'.$moderator->id.'/suspend', ['reason' => 'x'])->assertForbidden();
    }

    public function test_hiding_reported_content_closes_every_open_report_on_it(): void
    {
        $moderator = $this->adminWithRole('moderator');
        $post = Post::factory()->create();
        $reporters = User::factory()->count(2)->create();
        foreach ($reporters as $reporter) {
            $this->actingWithToken($reporter)->postJson('/api/v1/reports', ['target_type' => 'post', 'target_id' => $post->id, 'reason' => 'hate'])->assertCreated();
        }
        $reportId = DB::table('reports')->value('id');

        $queue = $this->actingWithToken($moderator)->getJson('/api/v1/admin/reports')->assertOk();
        $this->assertCount(2, $queue->json('data'));
        $queue->assertJsonPath('data.0.target.body', $post->body);

        $this->actingWithToken($moderator)->postJson("/api/v1/admin/reports/{$reportId}/resolve", ['decision' => 'hide_content', 'note' => 'Hate speech'])
            ->assertOk()->assertJsonPath('data.status', 'actioned');

        $this->assertSame(ModerationStatus::Hidden, $post->fresh()->moderation_status);
        $this->assertSame(0, DB::table('reports')->where('status', 'open')->count());
        $this->assertDatabaseHas('moderation_actions', ['action' => 'hide_post', 'target_id' => $post->id, 'moderator_id' => $moderator->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'moderation.hide_post', 'actor_id' => $moderator->id]);

        $this->actingWithToken($reporters[0])->getJson("/api/v1/posts/{$post->id}")->assertNotFound();

        $this->actingWithToken($moderator)->postJson("/api/v1/admin/reports/{$reportId}/resolve", ['decision' => 'dismiss'])->assertUnprocessable();
    }

    public function test_suspension_revokes_sessions_and_is_reversible(): void
    {
        $moderator = $this->adminWithRole('moderator');
        $target = User::factory()->create();
        $token = $this->tokenFor($target);

        $this->actingWithToken($moderator)->postJson("/api/v1/admin/users/{$target->id}/suspend", ['reason' => 'Spam'])->assertOk();

        $this->assertSame(AccountStatus::Suspended, $target->fresh()->status);
        $this->withBearer($token)->getJson('/api/v1/me')->assertUnauthorized();
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $target->id, 'type' => 'security.account_suspended']);

        $this->actingWithToken($moderator)->postJson("/api/v1/admin/users/{$target->id}/unsuspend")->assertOk();
        $this->assertSame(AccountStatus::Active, $target->fresh()->status);
    }

    public function test_moderators_cannot_suspend_administrators_or_themselves(): void
    {
        $moderator = $this->adminWithRole('moderator');
        $otherAdmin = $this->adminWithRole('auditor');

        $this->actingWithToken($moderator)->postJson("/api/v1/admin/users/{$otherAdmin->id}/suspend", ['reason' => 'x'])->assertForbidden();
        $this->actingWithToken($moderator)->postJson("/api/v1/admin/users/{$moderator->id}/suspend", ['reason' => 'x'])->assertUnprocessable();
        $this->assertSame(AccountStatus::Active, $otherAdmin->fresh()->status);
    }

    public function test_role_assignment_prevents_escalation(): void
    {
        $super = $this->adminWithRole('super_admin');
        $member = User::factory()->create();

        $this->actingWithToken($super)->postJson("/api/v1/admin/users/{$member->id}/roles", ['role' => 'moderator'])->assertOk();
        $this->actingWithToken($super)->deleteJson("/api/v1/admin/users/{$super->id}/roles/super_admin")->assertForbidden();

        // A role manager who is not a super admin cannot mint super admins.
        $manager = User::factory()->create();
        DB::table('role_permissions')->insert(['role_id' => DB::table('roles')->where('slug', 'auditor')->value('id'), 'permission' => 'roles.manage']);
        $this->actingWithToken($super)->postJson("/api/v1/admin/users/{$manager->id}/roles", ['role' => 'auditor'])->assertOk();
        $this->actingWithToken($manager)->postJson("/api/v1/admin/users/{$member->id}/roles", ['role' => 'super_admin'])->assertForbidden();

        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.role_granted', 'actor_id' => $super->id, 'subject_id' => $member->id]);
    }

    public function test_admin_user_view_excludes_private_profile_data(): void
    {
        $support = $this->adminWithRole('moderator');
        $member = User::factory()->create();
        $member->privateProfile()->create(['legal_name' => 'Hidden Legal Name', 'phone' => '+639171234567']);

        $json = $this->actingWithToken($support)->getJson("/api/v1/admin/users/{$member->id}")->assertOk()->getContent();

        $this->assertStringNotContainsString('Hidden Legal Name', $json);
        $this->assertStringNotContainsString('639171234567', $json);
    }

    public function test_audit_logs_are_append_only_in_code_and_in_the_database(): void
    {
        $log = app(AuditLogger::class)->record('test.event');

        try {
            $log->update(['action' => 'tampered']);
            $this->fail('Model update should have been refused.');
        } catch (LogicException) {
        }

        $this->expectException(QueryException::class);
        DB::table('audit_logs')->where('id', $log->id)->update(['action' => 'tampered']);
    }

    public function test_audit_logs_cannot_be_deleted_directly(): void
    {
        $log = app(AuditLogger::class)->record('test.event');

        $this->expectException(QueryException::class);
        DB::table('audit_logs')->where('id', $log->id)->delete();
    }

    public function test_system_health_reports_each_dependency(): void
    {
        $auditor = $this->adminWithRole('auditor');

        $response = $this->actingWithToken($auditor)->getJson('/api/v1/admin/system/health')->assertOk();

        $response->assertJsonStructure(['status', 'checks' => ['database' => ['status', 'latency_ms'], 'redis', 'queue', 'storage'], 'app']);
        $this->assertSame('ok', $response->json('checks.database.status'));
        $this->assertSame('ok', $response->json('checks.storage.status'));
    }

    public function test_audit_log_viewer_filters_by_action(): void
    {
        $auditor = $this->adminWithRole('auditor');
        $member = User::factory()->create(['email' => 'member@example.com']);
        $this->postJson('/api/v1/auth/login', ['email' => 'member@example.com', 'password' => 'correct-horse-battery'])->assertOk();

        $rows = $this->actingWithToken($auditor)->getJson('/api/v1/admin/audit-logs?action=auth.login')->assertOk()->json('data');

        $this->assertNotEmpty($rows);
        $this->assertTrue(collect($rows)->every(fn ($r) => str_starts_with($r['action'], 'auth.login')));
        $this->assertSame($member->id, $rows[0]['actor_id']);
        $this->assertNotEmpty(AuditLog::where('action', 'admin.role_granted')->get());
    }
}
