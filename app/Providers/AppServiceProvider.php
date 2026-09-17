<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Observers\MasterDataAuditObserver;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Payment gateway manager (PRD Fase 4 #14) — provider-agnostic.
        $this->app->singleton(PaymentGatewayManager::class);
    }

    public function boot(): void
    {
        // Event listeners are auto-discovered by Laravel via type-hinted handle() methods.
        // No manual registration needed for UpdateStockOnMovement or LogStockMovement.

        // Audit trail for master data changes (PRD 4.1 & 5)
        Product::observe(MasterDataAuditObserver::class);
        ProductPrice::observe(MasterDataAuditObserver::class);
        Supplier::observe(MasterDataAuditObserver::class);
        Customer::observe(MasterDataAuditObserver::class);
        Warehouse::observe(MasterDataAuditObserver::class);
    }
}
