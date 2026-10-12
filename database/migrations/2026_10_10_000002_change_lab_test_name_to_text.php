<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Real catalog names reach ~400 chars (rate list packages), so a
        // 255-char varchar is too small on MySQL/PostgreSQL. TEXT is portable
        // across SQLite/MySQL/PostgreSQL. The old btree index on name is
        // dropped with it — all app searches use LIKE '%…%' which can't use
        // a btree index anyway.
        Schema::table('lab_tests', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->text('name')->change();
        });
    }

    public function down(): void
    {
        Schema::table('lab_tests', function (Blueprint $table) {
            $table->string('name', 255)->change();
            $table->index('name');
        });
    }
};
