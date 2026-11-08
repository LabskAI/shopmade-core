<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('company', 64);
            $table->string('form', 100);
            $table->string('name', 120);
            $table->string('email');
            $table->string('phone', 40)->nullable();
            $table->text('message');
            $table->string('page_url', 2048)->nullable();
            $table->string('lang', 2)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['company', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
