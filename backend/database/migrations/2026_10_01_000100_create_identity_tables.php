<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What other people may see.
        Schema::create('profiles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('username', 30)->unique();
            $table->string('display_name', 50);
            $table->string('bio', 280)->nullable();
            $table->string('location', 80)->nullable();
            $table->string('avatar_path')->nullable();
            $table->timestampsTz();
        });

        // Never returned by public endpoints. Values are encrypted at rest with APP_KEY.
        Schema::create('private_profiles', function (Blueprint $table) {
            $table->foreignUlid('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->text('legal_name')->nullable();
            $table->text('phone')->nullable();
            $table->text('date_of_birth')->nullable();
            $table->timestampsTz();
        });

        Schema::create('privacy_settings', function (Blueprint $table) {
            $table->foreignUlid('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->boolean('profile_searchable')->default(true);
            $table->boolean('show_location')->default(true);
            $table->string('who_can_follow', 20)->default('everyone');
            $table->string('who_can_message', 20)->default('followers');
            $table->string('default_post_visibility', 20)->default('public');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_settings');
        Schema::dropIfExists('private_profiles');
        Schema::dropIfExists('profiles');
    }
};
