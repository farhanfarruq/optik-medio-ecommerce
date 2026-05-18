<x-filament-panels::page>
    <div class="space-y-6">
        <div class="p-5 bg-blue-50 border border-blue-200 rounded-lg">
            <h3 class="font-bold text-blue-800 mb-2">Format CSV Import</h3>
            <p class="text-sm text-blue-700 mb-3">
                Pakai file hasil <strong>Export CSV</strong> sebagai template agar header rapi dan lengkap.
                Import membaca kolom berdasarkan nama header, jadi urutan kolom boleh berubah.
            </p>
            <code class="block text-xs bg-white border border-blue-200 rounded p-3 text-blue-900 font-mono">
                id, name, slug, sku, brand, category, condition, description, image_paths, image_urls, price, stock, low_stock_threshold, weight, dimensions, gender, frame_shape, frame_material, frame_color, face_size_fit, lens_width, bridge_width, temple_length, frame_width, tags, campaign_tags, is_active, is_not_for_sale, is_best_seller, is_featured, is_new, recommendation_priority, is_prescription_required, prescription_rules, google_product_category, gtin, mpn, meta_title, meta_description, canonical_slug, og_image
            </code>
            <ul class="mt-3 text-xs text-blue-700 space-y-1 list-disc ml-4">
                <li><strong>name</strong> — Nama produk (wajib)</li>
                <li><strong>sku</strong> — SKU unik (opsional, jika ada akan update produk existing)</li>
                <li><strong>slug</strong> — Slug produk. Jika kosong, sistem membuat otomatis.</li>
                <li><strong>brand</strong> dan <strong>category</strong> — Merek dan kategori produk</li>
                <li><strong>category</strong> — Nama kategori (akan dibuat jika belum ada)</li>
                <li><strong>price</strong> — Harga dalam Rupiah (wajib)</li>
                <li><strong>image_paths</strong> — Path gambar di storage, pisahkan banyak gambar dengan tanda <code>;</code></li>
                <li><strong>image_urls</strong> — URL gambar untuk monitoring. Jika URL mengarah ke <code>/storage/...</code>, import bisa mengubahnya kembali menjadi path.</li>
                <li><strong>tags</strong> dan <strong>campaign_tags</strong> — Pisahkan banyak tag dengan tanda <code>;</code></li>
                <li><strong>dimensions</strong> dan <strong>prescription_rules</strong> — Format JSON, contoh <code>{"panjang":18,"lebar":8,"tinggi":6}</code></li>
                <li><strong>is_active</strong>, <strong>is_featured</strong>, dan field boolean lain — Gunakan <code>1</code>/<code>0</code></li>
            </ul>
        </div>

        @if($importResult)
        <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
            <p class="text-sm font-semibold text-green-800">{{ $importResult }}</p>
        </div>
        @endif

        <div class="p-5 bg-gray-50 border border-gray-200 rounded-lg">
            <h3 class="font-bold text-gray-700 mb-2">Export Produk</h3>
            <p class="text-sm text-gray-600">
                Klik tombol <strong>Export CSV</strong> di atas untuk mengunduh semua produk aktif dalam format CSV.
                File sudah menyertakan URL gambar agar mudah dimonitor di Excel atau Google Sheets.
            </p>
        </div>
    </div>
</x-filament-panels::page>
