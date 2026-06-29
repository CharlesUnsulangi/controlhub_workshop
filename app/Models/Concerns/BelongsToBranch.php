<?php

namespace App\Models\Concerns;

use App\Models\Branch;
use App\Models\Scopes\BranchScope;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dipakai tabel transaksi yang ber-branch (punya kolom branch_id).
 * - Global scope BranchScope (filter per lokasi aktif).
 * - Auto-isi branch_id dari branch aktif saat create.
 * Selalu dipakai BERSAMA BelongsToCompany (company dulu, lalu branch).
 */
trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope(new BranchScope);

        static::creating(function (Model $model): void {
            if (empty($model->branch_id)) {
                $branchId = app(Tenancy::class)->branchId();
                if (! is_null($branchId)) {
                    $model->branch_id = $branchId;
                }
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
