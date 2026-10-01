<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->string('visibility', 20)->default('public');
            $table->string('moderation_status', 20)->default('visible');
            $table->unsignedInteger('comments_count')->default(0);
            $table->unsignedInteger('reactions_count')->default(0);
            $table->timestampTz('edited_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'id']);
            $table->index(['moderation_status', 'visibility', 'id']);
        });

        // Metadata only; the bytes live in object storage.
        Schema::create('post_media', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('post_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 32);
            $table->string('path');
            $table->string('mime_type', 64);
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedInteger('size_bytes');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestampsTz();

            $table->index(['post_id', 'position']);
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('post_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->string('moderation_status', 20)->default('visible');
            $table->timestampsTz();

            $table->index(['post_id', 'id']);
        });

        Schema::create('reactions', function (Blueprint $table) {
            $table->foreignUlid('post_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('like');
            $table->timestampTz('created_at')->useCurrent();
            $table->primary(['post_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('follows', function (Blueprint $table) {
            $table->foreignUlid('follower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('followee_id')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->primary(['follower_id', 'followee_id']);
            $table->index(['followee_id', 'created_at']);
        });

        Schema::create('blocks', function (Blueprint $table) {
            $table->foreignUlid('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->primary(['blocker_id', 'blocked_id']);
            $table->index('blocked_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE follows ADD CONSTRAINT follows_not_self CHECK (follower_id <> followee_id)');
            DB::statement('ALTER TABLE blocks ADD CONSTRAINT blocks_not_self CHECK (blocker_id <> blocked_id)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('follows');
        Schema::dropIfExists('reactions');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('post_media');
        Schema::dropIfExists('posts');
    }
};
