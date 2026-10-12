<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_tests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('category_id')->constrained('test_categories')->restrictOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->text('preparation_instructions')->nullable();
            $table->string('sample_type', 64)->default('Blood');
            $table->decimal('price', 10, 2);
            $table->decimal('mrp', 10, 2)->nullable();
            $table->unsignedInteger('turnaround_hours')->default(24);
            $table->unsignedInteger('parameter_count')->default(1);
            $table->boolean('home_collection_available')->default(true);
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->index('code');
            $table->index('category_id');
            $table->index('status');
            $table->index('name');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_tests');
    }
};
