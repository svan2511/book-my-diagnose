<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('phone', 16)->unique();
            $table->string('email')->nullable();
            $table->string('gender', 8)->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('phone');
            $table->index('email');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
