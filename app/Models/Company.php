<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant induk (Core). Tidak ber-company_id (ini akar tenant),
 * jadi TIDAK memakai trait BelongsToCompany.
 */
class Company extends Model
{
    protected $table = 'wks_core_companies';

    protected $guarded = [];

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
