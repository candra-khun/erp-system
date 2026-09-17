<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockAlertNotification extends Notification
{
    use Queueable;

    /**
     * @param  array{product_id: int, sku: string, name: string, warehouse_id: int, warehouse: string, quantity: float, reorder_point: float, deficit: float}  $alert
     */
    public function __construct(public array $alert) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'low_stock',
            'product_id' => $this->alert['product_id'],
            'sku' => $this->alert['sku'],
            'product_name' => $this->alert['name'],
            'warehouse_id' => $this->alert['warehouse_id'],
            'warehouse_name' => $this->alert['warehouse'],
            'current_stock' => $this->alert['quantity'],
            'reorder_point' => $this->alert['reorder_point'],
            'deficit' => $this->alert['deficit'],
            'message' => sprintf(
                'Stok %s (%s) di %s tinggal %s, di bawah reorder point %s.',
                $this->alert['name'],
                $this->alert['sku'],
                $this->alert['warehouse'],
                number_format($this->alert['quantity'], 0, ',', '.'),
                number_format($this->alert['reorder_point'], 0, ',', '.'),
            ),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Peringatan Stok Rendah: '.$this->alert['name'])
            ->line($this->toArray($notifiable)['message'])
            ->line('Segera lakukan pemesanan ulang ke supplier.')
            ->action('Lihat Peringatan Stok', url('/reorder-alerts'));
    }
}
