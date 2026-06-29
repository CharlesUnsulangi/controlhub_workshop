<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panel KONTRABON (/kontrabon) — Hutang Supplier / Accounts Payable (modul `wks_ap_`).
 * Diurus divisi FINANCE/AP: terima & verifikasi faktur supplier ("tukar faktur"),
 * 3-way match (PO + GRN), catat PPN & no. faktur pajak, tetapkan jatuh tempo → hutang.
 * Pembayaran ada di panel terpisah /kasir (KasirPanelProvider). Lihat docs/PANELS.md §7, §9.
 */
class KontrabonPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('kontrabon')
            ->path('kontrabon')
            ->brandName('ControlHub — Kontrabon (Hutang Supplier)')
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->discoverResources(in: app_path('Filament/Kontrabon/Resources'), for: 'App\Filament\Kontrabon\Resources')
            ->discoverPages(in: app_path('Filament/Kontrabon/Pages'), for: 'App\Filament\Kontrabon\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Kontrabon/Widgets'), for: 'App\Filament\Kontrabon\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                \App\Http\Middleware\IdentifyTenant::class,
            ]);
    }
}
