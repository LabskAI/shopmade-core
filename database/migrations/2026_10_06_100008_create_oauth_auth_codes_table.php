<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_auth_codes', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('client_id', 64)->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('scopes');
            $table->text('redirect_uri');
            $table->string('code_challenge', 128);
            $table->string('code_challenge_method', 10);
            $table->string('resource')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_auth_codes');
    }
};
