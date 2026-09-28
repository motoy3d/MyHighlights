<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * failed_jobs に uuid 列を追加する。
 *
 * Laravel 8以降の既定の失敗ジョブドライバは database-uuids で、
 * 失敗を記録する際に uuid 列へ書き込む。
 * このテーブルは Laravel 5.6 世代の定義のままで uuid 列を持たないため、
 * ジョブが失敗した時に「Unknown column 'uuid'」で記録に失敗してしまう。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('failed_jobs', 'uuid')) {
            return;
        }

        Schema::table('failed_jobs', function (Blueprint $table) {
            // 既存行はNULLになるが、MySQL/MariaDBのUNIQUEはNULLを重複扱いしない
            $table->string('uuid')->nullable()->after('id')->unique();
        });
    }

    public function down(): void
    {
        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
