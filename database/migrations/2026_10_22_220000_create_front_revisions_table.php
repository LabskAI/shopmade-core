<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('front_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('company', 64);
            $table->string('file');
            $table->longText('content')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor');
            $table->string('via', 32);
            $table->string('note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['company', 'file', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('front_revisions');
    }
};
