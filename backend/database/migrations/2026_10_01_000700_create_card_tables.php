<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A card is an identity credential, never a store of value or personal data.
    // Card ID, user ID and chip UID are all distinct; secrets are stored only as keyed HMACs.
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->string('status', 24);
            $table->char('number_last4', 4);
            $table->char('number_hash', 64)->unique();
            $table->char('chip_uid_hash', 64)->nullable()->unique();
            $table->char('activation_code_hash', 64)->nullable()->unique();
            $table->timestampTz('activation_expires_at')->nullable();
            $table->unsignedSmallInteger('credential_version')->default(1);
            $table->foreignUlid('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->ulid('replaces_card_id')->nullable();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('frozen_at')->nullable();
            $table->timestampTz('lost_reported_at')->nullable();
            $table->timestampTz('replacement_requested_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'id']);
        });

        // Self-reference is added after the primary key exists.
        Schema::table('cards', function (Blueprint $table) {
            $table->foreign('replaces_card_id')->references('id')->on('cards')->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX cards_one_virtual_per_user ON cards (user_id) WHERE type = 'virtual'");
            DB::statement("ALTER TABLE cards ADD CONSTRAINT cards_type_check CHECK (type IN ('virtual', 'physical'))");
        }

        Schema::create('card_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('card_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 32);
            $table->string('ip_address', 45)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['card_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_events');
        Schema::dropIfExists('cards');
    }
};
