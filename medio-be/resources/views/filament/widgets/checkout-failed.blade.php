<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">❌ Alasan Checkout Gagal (7 Hari Terakhir)</x-slot>

        @php $rows = $this->getRows(); @endphp

        @if(empty($rows))
            <p class="text-sm text-gray-500 py-4 text-center">Belum ada data checkout gagal.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-2 px-3 font-semibold text-gray-700">#</th>
                            <th class="text-left py-2 px-3 font-semibold text-gray-700">Alasan Gagal</th>
                            <th class="text-right py-2 px-3 font-semibold text-gray-700">Frekuensi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($rows as $i => $row)
                            <tr class="hover:bg-gray-50">
                                <td class="py-2 px-3 text-gray-400 text-xs">{{ $i + 1 }}</td>
                                <td class="py-2 px-3 font-medium text-gray-900">
                                    {{ $row->fail_reason ?? '(tidak diketahui)' }}
                                </td>
                                <td class="py-2 px-3 text-right">
                                    <span class="inline-flex items-center justify-center px-2 py-0.5 rounded text-xs font-bold bg-red-100 text-red-800">
                                        {{ $row->total }}x
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
