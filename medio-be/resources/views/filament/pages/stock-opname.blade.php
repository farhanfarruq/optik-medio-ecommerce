<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Notes -->
        <div class="p-4 bg-white border border-gray-200 rounded-lg">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Catatan Opname</label>
            <input
                type="text"
                wire:model="notes"
                placeholder="Contoh: Opname bulanan Mei 2026"
                class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            />
        </div>

        <!-- Stock Table -->
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Produk</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">SKU</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700">Stok Sistem</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700">Stok Aktual</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700">Selisih</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($stockData as $productId => $data)
                        @php
                            $actual = (int) ($data['actual_count'] ?? 0);
                            $current = (int) ($data['current_stock'] ?? 0);
                            $diff = $actual - $current;
                        @endphp
                        <tr class="{{ $diff !== 0 ? 'bg-yellow-50' : '' }}">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $data['name'] }}</td>
                            <td class="px-4 py-3 text-gray-500 font-mono text-xs">{{ $data['sku'] }}</td>
                            <td class="px-4 py-3 text-center text-gray-700">{{ $current }}</td>
                            <td class="px-4 py-3 text-center">
                                <input
                                    type="number"
                                    wire:model.lazy="stockData.{{ $productId }}.actual_count"
                                    min="0"
                                    class="w-20 border border-gray-300 rounded px-2 py-1 text-center text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                                />
                            </td>
                            <td class="px-4 py-3 text-center font-bold
                                {{ $diff > 0 ? 'text-green-600' : ($diff < 0 ? 'text-red-600' : 'text-gray-400') }}">
                                {{ $diff > 0 ? '+' . $diff : ($diff < 0 ? $diff : '—') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="text-xs text-gray-500">
            💡 Baris berwarna kuning menunjukkan produk dengan selisih stok. Klik "Simpan Penyesuaian" untuk menerapkan perubahan.
        </p>
    </div>
</x-filament-panels::page>
