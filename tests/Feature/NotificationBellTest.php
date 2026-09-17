<?php

namespace Tests\Feature;

use App\Livewire\NotificationBell;
use App\Models\User;
use App\Notifications\LowStockAlertNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    private function alert(): LowStockAlertNotification
    {
        return new LowStockAlertNotification([
            'product_id' => 1,
            'sku' => 'SKU-1',
            'name' => 'Produk Uji',
            'warehouse_id' => 1,
            'warehouse' => 'Gudang Utama',
            'quantity' => 2,
            'reorder_point' => 10,
            'deficit' => 8,
        ]);
    }

    public function test_bell_shows_empty_state_without_notifications(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(NotificationBell::class)
            ->call('toggle')
            ->assertSee('Tidak ada notifikasi.');
    }

    public function test_bell_lists_latest_notification_message(): void
    {
        $user = User::factory()->create();
        $user->notifyNow($this->alert(), ['database']);

        Livewire::actingAs($user)
            ->test(NotificationBell::class)
            ->call('toggle')
            ->assertSee('Produk Uji')
            ->assertSee('Tandai semua dibaca');
    }

    public function test_badge_caps_displayed_count(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 12) as $i) {
            $user->notifyNow($this->alert(), ['database']);
        }

        Livewire::actingAs($user)
            ->test(NotificationBell::class)
            ->call('toggle')
            ->assertSeeHtml('9+');
    }

    public function test_mark_as_read_updates_single_notification(): void
    {
        $user = User::factory()->create();
        $user->notifyNow($this->alert(), ['database']);

        $notification = $user->notifications()->firstOrFail();
        $this->assertNull($notification->read_at);

        Livewire::actingAs($user)
            ->test(NotificationBell::class)
            ->call('markAsRead', $notification->id)
            ->assertHasNoErrors();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_all_as_read_clears_unread_notifications(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 3) as $i) {
            $user->notifyNow($this->alert(), ['database']);
        }

        $this->assertSame(3, $user->unreadNotifications()->count());

        Livewire::actingAs($user)
            ->test(NotificationBell::class)
            ->call('markAllAsRead')
            ->assertHasNoErrors();

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_notifications_are_scoped_to_authenticated_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $other->notifyNow($this->alert(), ['database']);

        Livewire::actingAs($user)
            ->test(NotificationBell::class)
            ->call('toggle')
            ->assertSee('Tidak ada notifikasi.');
    }
}
