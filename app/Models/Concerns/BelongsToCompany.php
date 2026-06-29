<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Models\Scopes\CompanyScope;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dipakai SEMUA tabel milik tenant (punya kolom company_id).
 * - Global scope CompanyScope (isolasi otomatis saat query).
 * - Auto-isi company_id dari tenant aktif saat create.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (Model $model): void {
            if (empty($model->company_id)) {
                $companyId = app(Tenancy::class)->companyId();
                if (! is_null($companyId)) {
                    $model->company_id = $companyId;
                }
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
