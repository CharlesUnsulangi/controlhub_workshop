<?php

namespace App\Models\Scopes;

use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope: filter otomatis branch_id = branch aktif (pemilih header).
 * Branch BUKAN tenant kedua — hanya difilter bila ada branch aktif di sesi.
 * Tanpa branch aktif => lihat semua branch milik company (sudah ter-scope company).
 */
class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenancy = app(Tenancy::class);

        if (! is_null($tenancy->branchId())) {
            $builder->where($model->getTable().'.branch_id', $tenancy->branchId());
        }
    }
}
