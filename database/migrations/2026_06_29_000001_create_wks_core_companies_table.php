<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CORE — tenant induk. TANPA company_id (ini akar tenant).
 * Lihat docs/DATABASE.md §1 & MODULES.md §3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wks_core_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 30)->unique();
            $table->string('npwp', 30)->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('timezone', 64)->default('Asia/Makassar');
            $table->string('logo_path')->nullable();
            $table->string('status', 15)->default('active'); // active | suspended
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wks_core_companies');
    }
};
