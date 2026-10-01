<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reportable_type', 32);
            $table->ulid('reportable_id');
            $table->string('reason', 32);
            $table->string('details', 1000)->nullable();
            $table->string('status', 20)->default('open');
            $table->foreignUlid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->string('resolution_note', 1000)->nullable();
            $table->timestampsTz();

            $table->index(['status', 'id']);
            $table->index(['reportable_type', 'reportable_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            // One open report per reporter per target; re-reporting after resolution is allowed.
            DB::statement("CREATE UNIQUE INDEX reports_one_open_per_reporter ON reports (reporter_id, reportable_type, reportable_id) WHERE status = 'open'");
        }

        // Moderation history shown to administrators.
        Schema::create('moderation_actions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('moderator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32);
            $table->string('target_type', 32);
            $table->ulid('target_id');
            $table->foreignUlid('report_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 1000)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['target_type', 'target_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_actions');
        Schema::dropIfExists('reports');
    }
};
