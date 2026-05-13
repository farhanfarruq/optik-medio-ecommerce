<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Info -->
        <div class="p-5 bg-blue-50 border border-blue-200 rounded-lg">
            <h3 class="font-bold text-blue-800 mb-2">📋 Format CSV Import</h3>
            <p class="text-sm text-blue-700 mb-3">File CSV harus memiliki kolom berikut (baris pertama = header):</p>
            <code class="block text-xs bg-white border border-blue-200 rounded p-3 text-blue-900 font-mono">
                name, sku, brand, category, price, stock, weight
            </code>
            <ul class="mt-3 text-xs text-blue-700 space-y-1 list-disc ml-4">
                <li><strong>name</strong> — Nama produk (wajib)</li>
                <li><strong>sku</strong> — SKU unik (opsional, jika ada akan update produk existing)</li>
                <li><strong>brand</strong> — Merek produk</li>
                <li><strong>category</strong> — Nama kategori (akan dibuat jika belum ada)</li>
                <li><strong>price</strong> — Harga dalam Rupiah (wajib)</li>
                <li><strong>stock</strong> — Jumlah stok</li>
                <li><strong>weight</strong> — Berat dalam gram (default: 300)</li>
            </ul>
        </div>

        @if($importResult)
        <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
            <p class="text-sm font-semibold text-green-800">✅ {{ $importResult }}</p>
        </div>
        @endif

        <!-- Export Info -->
        <div class="p-5 bg-gray-50 border border-gray-200 rounded-lg">
            <h3 class="font-bold text-gray-700 mb-2">📤 Export Produk</h3>
            <p class="text-sm text-gray-600">
                Klik tombol <strong>Export CSV</strong> di atas untuk mengunduh semua produk aktif dalam format CSV.
                File dapat dibuka di Excel atau Google Sheets.
            </p>
        </div>
    </div>
</x-filament-panels::page>
