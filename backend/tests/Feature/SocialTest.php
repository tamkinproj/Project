<?php

namespace Tests\Feature;

use App\Modules\Identity\Enums\FollowPolicy;
use App\Modules\Identity\Models\User;
use App\Modules\Social\Enums\ModerationStatus;
use App\Modules\Social\Enums\PostVisibility;
use App\Modules\Social\Models\Comment;
use App\Modules\Social\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SocialTest extends TestCase
{
    private function follow(User $follower, User $followee): void
    {
        DB::table('follows')->insert(['follower_id' => $follower->id, 'followee_id' => $followee->id, 'created_at' => now()]);
    }

    private function block(User $blocker, User $blocked): void
    {
        DB::table('blocks')->insert(['blocker_id' => $blocker->id, 'blocked_id' => $blocked->id, 'created_at' => now()]);
    }

    public function test_post_visibility_is_enforced_for_every_viewer(): void
    {
        $author = User::factory()->create();
        $follower = User::factory()->create();
        $stranger = User::factory()->create();
        $this->follow($follower, $author);

        $public = Post::factory()->for($author, 'author')->create();
        $followersOnly = Post::factory()->for($author, 'author')->visibility(PostVisibility::Followers)->create();
        $onlyMe = Post::factory()->for($author, 'author')->visibility(PostVisibility::OnlyMe)->create();

        $matrix = [
            [$author, $public, 200], [$author, $followersOnly, 200], [$author, $onlyMe, 200],
            [$follower, $public, 200], [$follower, $followersOnly, 200], [$follower, $onlyMe, 404],
            [$stranger, $public, 200], [$stranger, $followersOnly, 404], [$stranger, $onlyMe, 404],
        ];

        foreach ($matrix as [$viewer, $post, $status]) {
            $this->actingWithToken($viewer)->getJson("/api/v1/posts/{$post->id}")->assertStatus($status);
        }
    }

    public function test_blocking_hides_posts_in_both_directions(): void
    {
        $author = User::factory()->create();
        $blocked = User::factory()->create();
        $post = Post::factory()->for($author, 'author')->create();
        $theirPost = Post::factory()->for($blocked, 'author')->create();
        $this->block($author, $blocked);

        $this->actingWithToken($blocked)->getJson("/api/v1/posts/{$post->id}")->assertNotFound();
        $this->actingWithToken($author)->getJson("/api/v1/posts/{$theirPost->id}")->assertNotFound();
        $this->actingWithToken($blocked)->postJson("/api/v1/posts/{$post->id}/comments", ['body' => 'hi'])->assertNotFound();
    }

    public function test_hidden_posts_and_posts_by_suspended_users_disappear(): void
    {
        $viewer = User::factory()->create();
        $hidden = Post::factory()->create(['moderation_status' => ModerationStatus::Hidden]);
        $suspendedAuthorPost = Post::factory()->for(User::factory()->suspended(), 'author')->create();

        $this->actingWithToken($viewer)->getJson("/api/v1/posts/{$hidden->id}")->assertNotFound();
        $this->actingWithToken($viewer)->getJson("/api/v1/posts/{$suspendedAuthorPost->id}")->assertNotFound();
        $this->actingWithToken($viewer)->getJson('/api/v1/feed?scope=everyone')->assertJsonCount(0, 'data');
    }

    public function test_feed_scopes(): void
    {
        $viewer = User::factory()->create();
        $followed = User::factory()->create();
        $other = User::factory()->create();
        $this->follow($viewer, $followed);
        $own = Post::factory()->for($viewer, 'author')->create();
        $fromFollowed = Post::factory()->for($followed, 'author')->create();
        $fromOther = Post::factory()->for($other, 'author')->create();

        $following = collect($this->actingWithToken($viewer)->getJson('/api/v1/feed')->json('data'))->pluck('id');
        $everyone = collect($this->actingWithToken($viewer)->getJson('/api/v1/feed?scope=everyone')->json('data'))->pluck('id');

        $this->assertEqualsCanonicalizing([$own->id, $fromFollowed->id], $following->all());
        $this->assertEqualsCanonicalizing([$own->id, $fromFollowed->id, $fromOther->id], $everyone->all());
    }

    public function test_feed_paginates_with_a_cursor(): void
    {
        $viewer = User::factory()->create();
        Post::factory()->count(25)->for($viewer, 'author')->create();

        $first = $this->actingWithToken($viewer)->getJson('/api/v1/feed')->assertOk();
        $this->assertCount(20, $first->json('data'));
        $cursor = $first->json('meta.next_cursor');
        $this->assertNotNull($cursor);

        $second = $this->actingWithToken($viewer)->getJson('/api/v1/feed?cursor='.$cursor)->assertOk();
        $this->assertCount(5, $second->json('data'));
        $this->assertEmpty(array_intersect($first->json('data.*.id'), $second->json('data.*.id')));
    }

    public function test_creating_a_post_with_images_re_encodes_them(): void
    {
        $user = User::factory()->create();

        $response = $this->actingWithToken($user)->post('/api/v1/posts', [
            'body' => "  Jumu'ah mubarak  ",
            'images' => [UploadedFile::fake()->image('a.jpg', 3000, 1500), UploadedFile::fake()->image('b.png', 400, 400)],
        ], ['Accept' => 'application/json'])->assertCreated();

        $response->assertJsonPath('data.body', "Jumu'ah mubarak")
            ->assertJsonPath('data.visibility', 'public')
            ->assertJsonCount(2, 'data.media')
            ->assertJsonPath('data.media.0.width', 2048);

        $paths = DB::table('post_media')->pluck('path');
        $paths->each(fn ($p) => Storage::disk('media')->assertExists($p));
        $this->assertTrue($paths->every(fn ($p) => str_ends_with($p, '.webp')));
    }

    public function test_post_uses_the_default_visibility_from_privacy_settings(): void
    {
        $user = User::factory()->create();
        $user->privacy->update(['default_post_visibility' => 'followers']);

        $this->actingWithToken($user)->postJson('/api/v1/posts', ['body' => 'hi'])->assertCreated()->assertJsonPath('data.visibility', 'followers');
    }

    public function test_empty_posts_and_too_many_images_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingWithToken($user)->postJson('/api/v1/posts', ['body' => ''])->assertUnprocessable();
        $this->actingWithToken($user)->post('/api/v1/posts', [
            'images' => array_map(fn ($i) => UploadedFile::fake()->image("{$i}.jpg"), range(1, 5)),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('images');
    }

    public function test_only_the_author_can_edit_or_delete_a_post(): void
    {
        $author = User::factory()->create();
        $intruder = User::factory()->create();
        $post = Post::factory()->for($author, 'author')->create(['body' => 'original']);

        $this->actingWithToken($intruder)->patchJson("/api/v1/posts/{$post->id}", ['body' => 'hacked'])->assertNotFound();
        $this->actingWithToken($intruder)->deleteJson("/api/v1/posts/{$post->id}")->assertNotFound();
        $this->assertSame('original', $post->fresh()->body);

        $this->actingWithToken($author)->patchJson("/api/v1/posts/{$post->id}", ['body' => 'edited'])
            ->assertOk()->assertJsonPath('data.body', 'edited');
        $this->assertNotNull($post->fresh()->edited_at);

        $this->actingWithToken($author)->deleteJson("/api/v1/posts/{$post->id}")->assertNoContent();
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_comments_update_counters_and_notify_the_author(): void
    {
        $author = User::factory()->create();
        $commenter = User::factory()->create();
        $post = Post::factory()->for($author, 'author')->create();

        $this->actingWithToken($commenter)->postJson("/api/v1/posts/{$post->id}/comments", ['body' => 'Barakallahu feek'])->assertCreated();
        $this->actingWithToken($author)->postJson("/api/v1/posts/{$post->id}/comments", ['body' => 'Wa feek'])->assertCreated();

        $this->assertSame(2, $post->fresh()->comments_count);
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $author->id)->where('type', 'social.commented')->count());

        $list = $this->actingWithToken($commenter)->getJson("/api/v1/posts/{$post->id}/comments")->assertOk();
        $this->assertSame(['Barakallahu feek', 'Wa feek'], $list->json('data.*.body'));
    }

    public function test_comment_deletion_rights(): void
    {
        $postAuthor = User::factory()->create();
        $commenter = User::factory()->create();
        $bystander = User::factory()->create();
        $post = Post::factory()->for($postAuthor, 'author')->create();
        $first = Comment::forceCreate(['post_id' => $post->id, 'user_id' => $commenter->id, 'body' => 'one']);
        $second = Comment::forceCreate(['post_id' => $post->id, 'user_id' => $commenter->id, 'body' => 'two']);
        $post->forceFill(['comments_count' => 2])->save();

        $this->actingWithToken($bystander)->deleteJson("/api/v1/comments/{$first->id}")->assertNotFound();
        $this->actingWithToken($commenter)->deleteJson("/api/v1/comments/{$first->id}")->assertNoContent();
        $this->actingWithToken($postAuthor)->deleteJson("/api/v1/comments/{$second->id}")->assertNoContent();

        $this->assertSame(0, $post->fresh()->comments_count);
    }

    public function test_reactions_are_idempotent(): void
    {
        $author = User::factory()->create();
        $fan = User::factory()->create();
        $post = Post::factory()->for($author, 'author')->create();

        $this->actingWithToken($fan)->putJson("/api/v1/posts/{$post->id}/reaction")->assertOk()->assertJsonPath('reactions_count', 1);
        $this->actingWithToken($fan)->putJson("/api/v1/posts/{$post->id}/reaction")->assertOk()->assertJsonPath('reactions_count', 1);
        $this->actingWithToken($fan)->getJson("/api/v1/posts/{$post->id}")->assertJsonPath('data.viewer.reaction', 'like');
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $author->id)->count());

        $this->actingWithToken($fan)->deleteJson("/api/v1/posts/{$post->id}/reaction")->assertJsonPath('reactions_count', 0);
        $this->actingWithToken($fan)->deleteJson("/api/v1/posts/{$post->id}/reaction")->assertJsonPath('reactions_count', 0);
    }

    public function test_follow_rules(): void
    {
        $me = User::factory()->create();
        $open = User::factory()->create();
        $closed = User::factory()->create();
        $closed->privacy->update(['who_can_follow' => FollowPolicy::Nobody]);

        $this->actingWithToken($me)->postJson('/api/v1/profiles/'.$this->username($open).'/follow')->assertOk();
        $this->actingWithToken($me)->postJson('/api/v1/profiles/'.$this->username($open).'/follow')->assertOk();
        $this->actingWithToken($me)->postJson('/api/v1/profiles/'.$this->username($closed).'/follow')->assertForbidden();
        $this->actingWithToken($me)->postJson('/api/v1/profiles/'.$this->username($me).'/follow')->assertUnprocessable();

        $this->assertSame(1, DB::table('follows')->where('follower_id', $me->id)->count());
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $open->id)->where('type', 'social.followed')->count());

        $profile = $this->actingWithToken($open)->getJson('/api/v1/profiles/'.$this->username($me))->assertOk();
        $profile->assertJsonPath('data.relationship.follows_you', true)->assertJsonPath('data.counts.following', 1);
    }

    public function test_blocking_severs_follows_and_prevents_new_ones(): void
    {
        $me = User::factory()->create();
        $them = User::factory()->create();
        $this->follow($me, $them);
        $this->follow($them, $me);

        $this->actingWithToken($me)->postJson('/api/v1/profiles/'.$this->username($them).'/block')->assertNoContent();

        $this->assertSame(0, DB::table('follows')->count());
        $this->actingWithToken($them)->postJson('/api/v1/profiles/'.$this->username($me).'/follow')->assertNotFound();
        $this->actingWithToken($me)->getJson('/api/v1/me/blocks')->assertJsonPath('data.0.username', $this->username($them));

        $this->actingWithToken($me)->deleteJson('/api/v1/profiles/'.$this->username($them).'/block')->assertNoContent();
        $this->assertSame(0, DB::table('blocks')->count());
    }

    public function test_notifications_hide_blocked_actors_and_are_private_to_their_owner(): void
    {
        $me = User::factory()->create();
        $fan = User::factory()->create();
        $troll = User::factory()->create();
        $post = Post::factory()->for($me, 'author')->create();
        $this->actingWithToken($fan)->putJson("/api/v1/posts/{$post->id}/reaction")->assertOk();
        $this->actingWithToken($troll)->postJson("/api/v1/posts/{$post->id}/comments", ['body' => 'meh'])->assertCreated();
        $this->block($me, $troll);

        $items = $this->actingWithToken($me)->getJson('/api/v1/notifications?category=social')->assertOk()->json('data');
        $this->assertCount(1, $items);
        $this->assertSame($this->username($fan), $items[0]['actor']['username']);

        $notificationId = $items[0]['id'];
        $this->actingWithToken($fan)->postJson("/api/v1/notifications/{$notificationId}/read")->assertNotFound();
        $this->actingWithToken($fan)->postJson('/api/v1/notifications/not-a-uuid/read')->assertNotFound();
        $this->actingWithToken($me)->postJson("/api/v1/notifications/{$notificationId}/read")->assertNoContent();
    }

    public function test_text_is_cleaned_but_arabic_and_emoji_are_preserved(): void
    {
        $user = User::factory()->create();
        $body = "السلام عليكم \u{200F}👨\u{200D}👩\u{200D}👧\x07\r\n\n\n\nnext";

        $this->actingWithToken($user)->postJson('/api/v1/posts', ['body' => $body])
            ->assertCreated()
            ->assertJsonPath('data.body', "السلام عليكم \u{200F}👨\u{200D}👩\u{200D}👧\n\nnext");
    }
}
