<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_clients', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('name', 200);
            $table->json('redirect_uris');
            $table->json('grant_types');
            $table->string('token_endpoint_auth_method', 40)->default('none');
            $table->string('scope')->nullable();
            $table->string('client_uri')->nullable();
            $table->string('logo_uri')->nullable();
            $table->json('registration_meta')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_clients');
    }
};
