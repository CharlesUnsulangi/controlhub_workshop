<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MASTER — Branch (lokasi/cabang) milik Company.
 * Branch = scope kedua (BUKAN tenant kedua). Lihat docs/PANELS.md §1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wks_ms_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('wks_core_companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('status', 15)->default('active');
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wks_ms_branches');
    }
};
