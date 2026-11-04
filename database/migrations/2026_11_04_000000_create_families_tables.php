<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('families', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        // One family per user for now: the service token carries a single family_id.
        Schema::create('family_members', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('family_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('role', 32);
            $table->timestamps();
        });

        Schema::create('family_invitations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('family_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->string('role', 32);
            // SHA-256 of the token in the emailed link; the token itself is never stored.
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['family_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_invitations');
        Schema::dropIfExists('family_members');
        Schema::dropIfExists('families');
    }
};
