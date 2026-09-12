<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\SalesTransaction;
use App\Models\Warehouse;
use App\Services\ReportService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.erp', ['title' => 'Dashboard'])]
class Dashboard extends Component
{
    public string $warehouseId = '';

    public function render()
    {
        $service = app(ReportService::class);
        $wId = $this->warehouseId !== '' ? (int) $this->warehouseId : null;

        $stats = $service->getDashboardStats($wId);

        // Transaksi batal/expired disaring dari statistik utama (status completed saja)
        $recentSales = SalesTransaction::with(['customer', 'warehouse'])
            ->where('status', 'completed')
            ->latest()
            ->take(5)
            ->get();

        $topProducts = app(ReportService::class)->getSalesByProduct(
            now()->startOfMonth()->toDateString(),
            now()->toDateString(),
            $wId,
        );
        $topProducts = array_slice($topProducts, 0, 5);

        return view('livewire.dashboard', [
            'stats' => $stats,
            'recentSales' => $recentSales,
            'topProducts' => $topProducts,
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
