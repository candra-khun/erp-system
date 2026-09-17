<?php

namespace Tests\Feature;

use App\Jobs\CheckReorderAlertsJob;
use App\Jobs\RefreshDailySummaryJob;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\LowStockAlertNotification;
use App\Services\ReportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReorderAlertSchedulerTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Cache::flush();

        $this->warehouse = Warehouse::factory()->create(['is_active' => true]);

        $this->product = Product::factory()->create([
            'product_category_id' => ProductCategory::factory(),
            'base_unit_id' => ProductUnit::factory(),
            'reorder_point' => 10,
            'reorder_alert_enabled' => true,
            'is_active' => true,
        ]);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $roleName)->firstOrFail());

        return $user;
    }

    public function test_job_notifies_users_that_can_manage_purchases(): void
    {
        Notification::fake();

        Stock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 4,
        ]);

        $purchasing = $this->userWithRole('staff_pembelian');
        $cashier = $this->userWithRole('kasir');

        (new CheckReorderAlertsJob)->handle();

        Notification::assertSentTo($purchasing, LowStockAlertNotification::class);
        Notification::assertNotSentTo($cashier, LowStockAlertNotification::class);
    }

    public function test_job_does_not_notify_when_stock_is_above_reorder_point(): void
    {
        Notification::fake();

        Stock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 25,
        ]);

        $this->userWithRole('staff_pembelian');

        (new CheckReorderAlertsJob)->handle();

        Notification::assertNothingSent();
    }

    public function test_job_does_not_duplicate_alerts_within_24_hours(): void
    {
        Notification::fake();

        Stock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 2,
        ]);

        $this->userWithRole('staff_pembelian');

        (new CheckReorderAlertsJob)->handle();
        (new CheckReorderAlertsJob)->handle();

        Notification::assertSentToTimes(
            User::whereHas('roles', fn ($q) => $q->where('name', 'staff_pembelian'))->first(),
            LowStockAlertNotification::class,
            1,
        );
    }

    public function test_job_ignores_products_with_alerts_disabled(): void
    {
        Notification::fake();

        $this->product->update(['reorder_alert_enabled' => false]);

        Stock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 1,
        ]);

        $this->userWithRole('staff_pembelian');

        (new CheckReorderAlertsJob)->handle();

        Notification::assertNothingSent();
    }

    public function test_daily_summary_job_refreshes_summary_rows(): void
    {
        (new RefreshDailySummaryJob)->handle(app(ReportService::class));

        $this->assertDatabaseHas('report_daily_summaries', [
            'summary_date' => now()->toDateString(),
            'warehouse_id' => null,
        ]);

        $this->assertDatabaseHas('report_daily_summaries', [
            'summary_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
        ]);
    }

    public function test_scheduler_registers_reorder_and_summary_jobs(): void
    {
        $this->app->make(Kernel::class)->bootstrap();

        $descriptions = collect($this->app->make(Schedule::class)->events())
            ->map(fn ($event) => (string) ($event->description ?? ''))
            ->implode('|');

        $this->assertStringContainsString('reorder-alerts-check', $descriptions);
        $this->assertStringContainsString('daily-summary-refresh', $descriptions);
    }
}
