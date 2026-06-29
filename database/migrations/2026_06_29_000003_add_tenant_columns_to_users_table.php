<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu tabel users untuk semua tenant.
 * - company_id NULL  => super admin (lintas tenant, panel System).
 * - default_branch_id => branch awal saat login (konteks lokasi).
 * FK ditambahkan hanya di pgsql (sqlite tak mendukung ADD FK via ALTER).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('id');
            $table->unsignedBigInteger('default_branch_id')->nullable()->after('company_id');
            $table->boolean('is_active')->default(true)->after('default_branch_id');

            $table->index('company_id');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('company_id')->references('id')->on('wks_core_companies')->nullOnDelete();
                $table->foreign('default_branch_id')->references('id')->on('wks_ms_branches')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $table->dropForeign(['company_id']);
                $table->dropForeign(['default_branch_id']);
            }
            $table->dropIndex(['company_id']);
            $table->dropColumn(['company_id', 'default_branch_id', 'is_active']);
        });
    }
};
