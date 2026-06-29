<?php

namespace App\Models\Scopes;

use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope: filter otomatis company_id = tenant aktif.
 * Super admin (tanpa company aktif) => tidak difilter (lintas tenant).
 * Inilah jaminan isolasi tenant (RISK R1).
 */
class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenancy = app(Tenancy::class);

        if ($tenancy->scopesCompany()) {
            $builder->where($model->getTable().'.company_id', $tenancy->companyId());
        }
    }
}
