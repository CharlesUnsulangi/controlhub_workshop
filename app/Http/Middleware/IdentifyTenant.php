<?php

namespace App\Http\Middleware;

use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menyetel konteks tenant aktif (Tenancy) dari user login + branch di sesi.
 * Jalan setelah autentikasi (sesi) → semua global scope membaca konteks ini,
 * sehingga begitu MASUK data sudah terpisah per Company dan per Branch.
 */
class IdentifyTenant
{
    public function __construct(private Tenancy $tenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $this->tenancy->setCompany($user->company_id);

            // Branch aktif: pilihan header (sesi) → fallback default user.
            $branchId = session('active_branch_id') ?: $user->default_branch_id;
            $this->tenancy->setBranch($branchId ? (int) $branchId : null);
        }

        return $next($request);
    }
}
