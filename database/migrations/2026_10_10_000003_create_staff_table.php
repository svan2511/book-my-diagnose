<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 120);
            // Canonical 10-digit Indian mobile (normalized in FormRequest).
            $table->string('phone', 16)->unique();
            $table->string('role', 32)->default('collector');
            $table->string('area', 180)->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->index('phone');
            $table->index('status');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
