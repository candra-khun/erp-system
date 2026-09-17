<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithWarehouseAccess;
use App\Models\SalesTransaction;
use App\Services\ReportService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.erp', ['title' => 'Dashboard'])]
class Dashboard extends Component
{
    use InteractsWithWarehouseAccess;

    public string $warehouseId = '';

    public function render()
    {
        $service = app(ReportService::class);

        // Clamp filter gudang ke scope akses user (Admin Cabang = 1 cabang, PRD §2)
        $this->warehouseId = $this->clampWarehouseFilter($this->warehouseId);

        $wId = $this->warehouseId !== '' ? (int) $this->warehouseId : null;
        $restrictedIds = $this->accessibleWarehouseIds();

        $scopeIds = $wId !== null ? [$wId] : $restrictedIds;

        $stats = $service->getDashboardStats($scopeIds);

        // Transaksi batal/expired disaring dari statistik utama (status completed saja)
        $recentSales = SalesTransaction::with(['customer', 'warehouse'])
            ->where('status', 'completed')
            ->when($restrictedIds !== null, fn ($q) => $q->whereIn('warehouse_id', $restrictedIds))
            ->latest()
            ->take(5)
            ->get();

        $topProducts = app(ReportService::class)->getSalesByProduct(
            now()->startOfMonth()->toDateString(),
            now()->toDateString(),
            $scopeIds,
        );
        $topProducts = array_slice($topProducts, 0, 5);

        return view('livewire.dashboard', [
            'stats' => $stats,
            'recentSales' => $recentSales,
            'topProducts' => $topProducts,
            'warehouses' => $this->accessibleWarehouseOptions(),
        ]);
    }
}
