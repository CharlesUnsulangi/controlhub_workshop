<?php

namespace App\Support;

/**
 * Konteks tenant aktif per-request (sumber kebenaran tunggal).
 * Diisi oleh middleware IdentifyTenant dari user login + session branch.
 * Dibaca oleh global scope CompanyScope & BranchScope (trait BelongsToCompany/Branch).
 *
 * - companyId NULL + superAdmin true  => lintas tenant (tak difilter company).
 * - branchId  NULL                    => lihat semua branch milik company.
 */
class Tenancy
{
    public ?int $companyId = null;

    public ?int $branchId = null;

    public bool $superAdmin = false;

    public function setCompany(?int $companyId): void
    {
        $this->companyId = $companyId;
        $this->superAdmin = is_null($companyId);
    }

    public function setBranch(?int $branchId): void
    {
        $this->branchId = $branchId;
    }

    public function companyId(): ?int
    {
        return $this->companyId;
    }

    public function branchId(): ?int
    {
        return $this->branchId;
    }

    /** Apakah query tenant-model harus difilter company_id. */
    public function scopesCompany(): bool
    {
        return ! is_null($this->companyId);
    }

    public function isSuperAdmin(): bool
    {
        return $this->superAdmin;
    }
}
