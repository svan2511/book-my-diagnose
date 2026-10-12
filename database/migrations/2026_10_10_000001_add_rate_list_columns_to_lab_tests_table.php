<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_tests', function (Blueprint $table) {
            $table->string('serial_no', 32)->nullable()->after('code');
            $table->text('sample_detail')->nullable()->after('sample_type');
            $table->string('storage_temp', 16)->nullable()->after('sample_detail');
            $table->string('method', 128)->nullable()->after('storage_temp');
            $table->string('schedule', 64)->nullable()->after('method');
            $table->string('reporting', 64)->nullable()->after('schedule');
            $table->text('general_instructions')->nullable()->after('preparation_instructions');
            $table->string('source_sheet', 32)->nullable()->after('general_instructions');
            $table->string('code_prefix', 8)->nullable()->after('source_sheet');
            $table->boolean('is_nabl')->default(false)->after('code_prefix');
            $table->boolean('is_outsourced')->default(false)->after('is_nabl');
            $table->boolean('is_discontinued')->default(false)->after('is_outsourced');
            $table->index('code_prefix');
            $table->index('source_sheet');
        });
    }

    public function down(): void
    {
        Schema::table('lab_tests', function (Blueprint $table) {
            $table->dropIndex(['code_prefix']);
            $table->dropIndex(['source_sheet']);
            $table->dropColumn([
                'serial_no', 'sample_detail', 'storage_temp', 'method',
                'schedule', 'reporting', 'general_instructions',
                'source_sheet', 'code_prefix', 'is_nabl',
                'is_outsourced', 'is_discontinued',
            ]);
        });
    }
};
