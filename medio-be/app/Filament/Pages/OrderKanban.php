<?php

namespace App\Filament\Pages;

use App\Models\Order;
use App\Models\Product;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Actions\Action;

class OrderKanban extends Page
{
    protected static string | \BackedEnum | null $navigationIcon  = 'heroicon-o-view-columns';
    protected string $view            = 'filament.pages.order-kanban';
    protected static ?string $navigationLabel = 'Order Kanban';
    protected static string | \UnitEnum | null $navigationGroup = 'Penjualan';
    protected static ?int    $navigationSort  = 3;
    protected static ?string $title           = 'Order Kanban Board';

    /**
     * Kolom kanban yang ditampilkan beserta warna badge-nya.
     */
    public static function getColumns(): array
    {
        $styles = [
            'unpaid'                      => ['color' => 'bg-yellow-100 text-yellow-800', 'icon' => '⏳'],
            'paid'                        => ['color' => 'bg-blue-100 text-blue-800', 'icon' => '💳'],
            'waiting_prescription_review' => ['color' => 'bg-orange-100 text-orange-800', 'icon' => '📝'],
            'prescription_verified'       => ['color' => 'bg-sky-100 text-sky-800', 'icon' => '🔎'],
            'lens_processing'             => ['color' => 'bg-fuchsia-100 text-fuchsia-800', 'icon' => '🧪'],
            'processing'                  => ['color' => 'bg-purple-100 text-purple-800', 'icon' => '⚙️'],
            'shipped'                     => ['color' => 'bg-indigo-100 text-indigo-800', 'icon' => '🚚'],
            'delivered'                   => ['color' => 'bg-green-100 text-green-800', 'icon' => '📦'],
            'completed'                   => ['color' => 'bg-emerald-100 text-emerald-800', 'icon' => '✅'],
            'cancelled'                   => ['color' => 'bg-red-100 text-red-800', 'icon' => '❌'],
            'refunded'                    => ['color' => 'bg-gray-200 text-gray-800', 'icon' => '↩️'],
        ];

        return collect(Order::statusOptions())
            ->map(fn (string $label, string $status): array => [
                'label' => $label,
                'color' => $styles[$status]['color'] ?? 'bg-gray-100 text-gray-800',
                'icon' => $styles[$status]['icon'] ?? '•',
            ])
            ->all();
    }

    /**
     * Ambil orders per status untuk kanban.
     * Hanya tampilkan 20 order terbaru per kolom agar tidak berat.
     */
    public function getOrdersByStatus(): array
    {
        $statuses = array_keys(self::getColumns());
        $result   = [];

        foreach ($statuses as $status) {
            $result[$status] = Order::with(['user', 'payment.paymentMethod'])
                ->where('status', $status)
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (Order $order) => [
                    'id'           => $order->id,
                    'order_number' => $order->order_number,
                    'customer'     => $order->user?->name ?? 'Guest',
                    'total'        => 'Rp ' . number_format((float) $order->total_price, 0, ',', '.'),
                    'payment'      => $order->payment?->paymentMethod?->name ?? $order->payment?->provider ?? '-',
                    'created_at'   => $order->created_at?->diffForHumans(),
                    'status'       => $order->status,
                    'view_url'     => route('filament.admin.resources.orders.view', $order),
                ])
                ->toArray();
        }

        return $result;
    }

    /**
     * Hitung total per status untuk header kolom.
     */
    public function getStatusCounts(): array
    {
        $counts = Order::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return $counts;
    }

    public function updateOrderStatus(int $orderId, string $status): void
    {
        if (! Order::hasStatus($status)) {
            Notification::make()
                ->title('Status pesanan tidak valid.')
                ->danger()
                ->send();

            return;
        }

        $order = Order::with('items')->find($orderId);

        if (! $order || $order->status === $status) {
            return;
        }

        if ($status === 'cancelled' && in_array($order->status, ['unpaid', 'paid', 'processing'], true)) {
            foreach ($order->items as $item) {
                if (! $item->parent_item_id) {
                    Product::where('id', $item->product_id)->increment('stock', $item->quantity);
                }
            }
        }

        $order->update([
            'status' => $status,
            ...Order::statusTimestampPayload($status),
        ]);

        Notification::make()
            ->title('Status pesanan diperbarui.')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->dispatch('$refresh')),
        ];
    }
}
