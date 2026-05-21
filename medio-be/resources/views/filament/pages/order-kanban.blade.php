<x-filament-panels::page>
    @php
        $columns    = $this->getColumns();
        $orders     = $this->getOrdersByStatus();
        $counts     = $this->getStatusCounts();
    @endphp

    <div class="overflow-x-auto pb-4">
        <div class="flex gap-4 min-w-max">
            @foreach ($columns as $status => $col)
                @php
                    $statusOrders = $orders[$status] ?? [];
                    $count        = $counts[$status] ?? 0;
                @endphp

                <div class="w-72 flex-shrink-0">
                    {{-- Kolom Header --}}
                    <div class="flex items-center justify-between mb-3 px-1">
                        <div class="flex items-center gap-2">
                            <span class="text-base">{{ $col['icon'] }}</span>
                            <h3 class="font-bold text-sm text-gray-700">{{ $col['label'] }}</h3>
                        </div>
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold bg-gray-200 text-gray-700">
                            {{ $count }}
                        </span>
                    </div>

                    {{-- Kartu Order --}}
                    <div class="space-y-2 min-h-[200px]">
                        @forelse ($statusOrders as $order)
                            <div class="bg-white rounded-lg border border-gray-200 p-3 shadow-sm hover:shadow-md hover:border-gray-300 transition-all">
                                <div class="flex items-start justify-between gap-2 mb-2">
                                    <span class="font-bold text-xs text-gray-900 truncate">{{ $order['order_number'] }}</span>
                                    <span class="text-[10px] px-1.5 py-0.5 rounded-full font-semibold whitespace-nowrap {{ $col['color'] }}">
                                        {{ $col['label'] }}
                                    </span>
                                </div>
                                <p class="text-xs text-gray-600 truncate mb-1">
                                    <span class="font-medium">{{ $order['customer'] }}</span>
                                </p>
                                <p class="text-xs font-bold text-gray-900 mb-1">{{ $order['total'] }}</p>
                                <div class="flex items-center justify-between">
                                     <span class="text-[10px] text-gray-400">{{ $order['payment'] }}</span>
                                     <span class="text-[10px] text-gray-400">{{ $order['created_at'] }}</span>
                                 </div>
                                <div class="mt-3 grid grid-cols-[1fr_auto] gap-2">
                                    <select
                                        wire:change="updateOrderStatus({{ $order['id'] }}, $event.target.value)"
                                        wire:loading.attr="disabled"
                                        class="w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                    >
                                        @foreach ($columns as $targetStatus => $targetCol)
                                            <option value="{{ $targetStatus }}" @selected($order['status'] === $targetStatus)>
                                                {{ $targetCol['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <a
                                        href="{{ $order['view_url'] }}"
                                        class="inline-flex items-center justify-center rounded-md border border-gray-300 px-2.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                    >
                                        Detail
                                    </a>
                                </div>
                            </div>
                        @empty
                            <div class="flex items-center justify-center h-24 border-2 border-dashed border-gray-200 rounded-lg">
                                <p class="text-xs text-gray-400">Tidak ada pesanan</p>
                            </div>
                        @endforelse

                        @if ($count > count($statusOrders))
                            <div class="text-center py-2">
                                <a
                                    href="{{ route('filament.admin.resources.orders.index', ['tableFilters[status][value]' => $status]) }}"
                                    class="text-xs text-primary-600 hover:underline font-medium"
                                >
                                    +{{ $count - count($statusOrders) }} pesanan lainnya →
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
