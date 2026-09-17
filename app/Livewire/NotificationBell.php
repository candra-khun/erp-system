<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Bell notifikasi in-app di topbar (PRD 4.2 — notifikasi otomatis stok rendah).
 */
class NotificationBell extends Component
{
    public bool $open = false;

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    /**
     * @return Collection<int, DatabaseNotification>
     */
    #[Computed]
    public function notifications()
    {
        $user = auth()->user();

        if ($user === null) {
            return collect();
        }

        return $user->notifications()->limit(10)->get();
    }

    #[Computed]
    public function unreadCount(): int
    {
        $user = auth()->user();

        if ($user === null) {
            return 0;
        }

        return $user->unreadNotifications()->count();
    }

    public function markAsRead(string $id): void
    {
        $user = auth()->user();

        if ($user === null) {
            return;
        }

        $user->notifications()->whereKey($id)->first()?->markAsRead();

        unset($this->notifications, $this->unreadCount);
    }

    public function markAllAsRead(): void
    {
        $user = auth()->user();

        if ($user === null) {
            return;
        }

        $user->unreadNotifications->markAsRead();

        unset($this->notifications, $this->unreadCount);
    }

    public function render(): View
    {
        return view('livewire.notification-bell');
    }
}
