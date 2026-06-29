<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'company_id', 'default_branch_id', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function defaultBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'default_branch_id');
    }

    /** company_id NULL = super admin (lintas tenant). */
    public function isSuperAdmin(): bool
    {
        return is_null($this->company_id);
    }

    /**
     * Pintu masuk panel. Super admin => panel System; user tenant => panel tenant.
     * Panel `audit` independen (peran Auditor) — gating peran rinci menyusul via Shield.
     * (Pemetaan peran↔panel lengkap di docs/PANELS.md §12.)
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'system') {
            return $this->isSuperAdmin();
        }

        // TODO(Shield): batasi panel per peran —
        //   'audit'     → Auditor (+ Owner/Admin lihat)
        //   'kontrabon' → Finance/AP (verifikasi faktur supplier)
        //   'kasir'     → Kasir (pembayaran supplier; SoD: verifikator ≠ pembayar)
        return ! $this->isSuperAdmin() && $this->is_active;
    }
}
