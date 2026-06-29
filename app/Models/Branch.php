<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Lokasi/cabang milik Company. Ter-scope company_id otomatis.
 */
class Branch extends Model
{
    use BelongsToCompany;

    protected $table = 'wks_ms_branches';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }
}
