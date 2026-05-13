<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ReferralCode;
use App\Models\ShippingAddress;
use App\Models\StockAdjustment;
use App\Models\StoreBranch;
use App\Models\User;
use App\Models\Warranty;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminShowcaseSeeder extends Seeder
{
    public function run(): void
    {
        // ─── 1. Store Branch ────────────────────────────────────────────────
        $branch = StoreBranch::updateOrCreate(
            ['code' => 'MEDIO-MAIN'],
            [
                'name'                 => 'Optik Medio - Cabang Utama',
                'address'              => 'Pasar, Bandarsari, Kec. Padang Ratu',
                'city'                 => 'Lampung Tengah',
                'province'             => 'Lampung',
                'phone'                => '0813-1196-9585',
                'email'                => 'main@optikmedio.id',
                'maps_url'             => 'https://maps.google.com/?q=Optik+Medio',
                'latitude'             => -5.0873184,
                'longitude'            => 104.9618755,
                'operating_hours'      => [
                    'mon' => '08:00-20:30',
                    'tue' => '08:00-20:30',
                    'wed' => '08:00-20:30',
                    'thu' => '08:00-20:30',
                    'fri' => '08:00-20:30',
                    'sat' => '08:00-20:30',
                    'sun' => '09:00-18:00',
                ],
                'appointment_capacity' => 15,
                'is_active'            => true,
            ]
        );

        // ─── 2. Categories ──────────────────────────────────────────────────
        $categories = [
            ['name' => 'Kacamata Frame', 'slug' => 'kacamata-frame', 'description' => 'Frame kacamata berbagai model dan material', 'is_active' => true],
            ['name' => 'Kacamata Hitam', 'slug' => 'kacamata-hitam', 'description' => 'Sunglasses untuk perlindungan UV', 'is_active' => true],
            ['name' => 'Lensa Kacamata', 'slug' => 'lensa-kacamata', 'description' => 'Katalog merek lensa resep yang tersedia di Optik Medio', 'is_active' => true],
            ['name' => 'Lensa Kontak', 'slug' => 'lensa-kontak', 'description' => 'Lensa kontak harian, bulanan, dan berwarna', 'is_active' => true],
            ['name' => 'Aksesoris', 'slug' => 'aksesoris', 'description' => 'Aksesoris perawatan kacamata', 'is_active' => true],
        ];

        $categoryModels = [];
        foreach ($categories as $cat) {
            $categoryModels[$cat['slug']] = Category::updateOrCreate(
                ['slug' => $cat['slug']],
                $cat
            );
        }

        // ─── 3. Products ────────────────────────────────────────────────────
        $products = [
            [
                'category_slug'        => 'kacamata-frame',
                'name'                 => 'Ray-Ban RB5154 Clubmaster Classic',
                'slug'                 => 'ray-ban-rb5154-clubmaster-classic',
                'brand'                => 'Ray-Ban',
                'price'                => 1850000,
                'stock'                => 12,
                'low_stock_threshold'  => 3,
                'weight'               => 150,
                'sku'                  => 'RB-5154-BLK',
                'gender'               => 'unisex',
                'frame_shape'          => 'browline',
                'frame_material'       => 'acetate',
                'frame_color'          => 'Black',
                'is_active'            => true,
                'is_best_seller'       => true,
                'is_new'               => false,
                'description'          => 'Frame ikonik Ray-Ban Clubmaster dengan desain browline klasik. Cocok untuk semua bentuk wajah.',
            ],
            [
                'category_slug'        => 'kacamata-frame',
                'name'                 => 'Oakley OX8046 Crosslink Zero',
                'slug'                 => 'oakley-ox8046-crosslink-zero',
                'brand'                => 'Oakley',
                'price'                => 2350000,
                'stock'                => 8,
                'low_stock_threshold'  => 3,
                'weight'               => 120,
                'sku'                  => 'OAK-8046-BLU',
                'gender'               => 'male',
                'frame_shape'          => 'rectangle',
                'frame_material'       => 'metal',
                'frame_color'          => 'Matte Blue',
                'is_active'            => true,
                'is_best_seller'       => false,
                'is_new'               => true,
                'description'          => 'Frame sport Oakley dengan teknologi O-Matter yang ringan dan fleksibel.',
            ],
            [
                'category_slug'        => 'kacamata-frame',
                'name'                 => 'Silhouette Titan Minimal Art',
                'slug'                 => 'silhouette-titan-minimal-art',
                'brand'                => 'Silhouette',
                'price'                => 4200000,
                'stock'                => 4,
                'low_stock_threshold'  => 2,
                'weight'               => 80,
                'sku'                  => 'SIL-TITAN-GLD',
                'gender'               => 'unisex',
                'frame_shape'          => 'oval',
                'frame_material'       => 'titanium',
                'frame_color'          => 'Gold',
                'is_active'            => true,
                'is_best_seller'       => true,
                'is_new'               => false,
                'description'          => 'Frame titanium ultra-ringan dari Silhouette Austria. Tanpa sekrup, tanpa engsel konvensional.',
            ],
            [
                'category_slug'        => 'kacamata-hitam',
                'name'                 => 'Ray-Ban RB3025 Aviator Classic',
                'slug'                 => 'ray-ban-rb3025-aviator-classic',
                'brand'                => 'Ray-Ban',
                'price'                => 2100000,
                'stock'                => 15,
                'low_stock_threshold'  => 5,
                'weight'               => 130,
                'sku'                  => 'RB-3025-GLD',
                'gender'               => 'unisex',
                'frame_shape'          => 'aviator',
                'frame_material'       => 'metal',
                'frame_color'          => 'Gold',
                'is_active'            => true,
                'is_best_seller'       => true,
                'is_new'               => false,
                'description'          => 'Kacamata hitam aviator ikonik Ray-Ban dengan lensa G-15 yang melindungi dari sinar UV.',
            ],
            [
                'category_slug'        => 'kacamata-hitam',
                'name'                 => 'Oakley Holbrook OO9102',
                'slug'                 => 'oakley-holbrook-oo9102',
                'brand'                => 'Oakley',
                'price'                => 1950000,
                'stock'                => 2,
                'low_stock_threshold'  => 3,
                'weight'               => 140,
                'sku'                  => 'OAK-9102-BLK',
                'gender'               => 'male',
                'frame_shape'          => 'square',
                'frame_material'       => 'acetate',
                'frame_color'          => 'Matte Black',
                'is_active'            => true,
                'is_best_seller'       => false,
                'is_new'               => false,
                'description'          => 'Kacamata hitam Oakley Holbrook dengan lensa Plutonite yang memblokir 100% sinar UV.',
            ],
            [
                'category_slug'        => 'lensa-kontak',
                'name'                 => 'Acuvue Oasys 1-Day (30 lensa)',
                'slug'                 => 'acuvue-oasys-1-day-30',
                'brand'                => 'Acuvue',
                'price'                => 385000,
                'stock'                => 25,
                'low_stock_threshold'  => 5,
                'weight'               => 50,
                'sku'                  => 'ACU-OASYS-30',
                'gender'               => 'unisex',
                'frame_shape'          => null,
                'frame_material'       => null,
                'frame_color'          => null,
                'is_active'            => true,
                'is_best_seller'       => true,
                'is_new'               => false,
                'description'          => 'Lensa kontak harian Acuvue Oasys dengan teknologi HydraLuxe untuk kenyamanan sepanjang hari.',
            ],
            [
                'category_slug'        => 'lensa-kacamata',
                'name'                 => 'Essilor Crizal Sapphire HR',
                'slug'                 => 'essilor-crizal-sapphire-hr',
                'brand'                => 'Essilor',
                'price'                => 0,
                'stock'                => 0,
                'low_stock_threshold'  => 0,
                'weight'               => 0,
                'sku'                  => 'LENS-ESS-CRIZAL-SAPPHIRE-HR',
                'gender'               => 'unisex',
                'frame_shape'          => null,
                'frame_material'       => null,
                'frame_color'          => null,
                'is_active'            => true,
                'is_best_seller'       => false,
                'is_new'               => false,
                'is_not_for_sale'      => true,
                'description'          => 'Lensa resep premium dengan perlindungan refleksi tinggi, dipilih sesuai kebutuhan resep dan frame pelanggan.',
            ],
            [
                'category_slug'        => 'lensa-kacamata',
                'name'                 => 'Zeiss DuraVision Platinum',
                'slug'                 => 'zeiss-duravision-platinum',
                'brand'                => 'Zeiss',
                'price'                => 0,
                'stock'                => 0,
                'low_stock_threshold'  => 0,
                'weight'               => 0,
                'sku'                  => 'LENS-ZEISS-DURAVISION-PLATINUM',
                'gender'               => 'unisex',
                'frame_shape'          => null,
                'frame_material'       => null,
                'frame_color'          => null,
                'is_active'            => true,
                'is_best_seller'       => false,
                'is_new'               => false,
                'is_not_for_sale'      => true,
                'description'          => 'Katalog lensa Zeiss untuk konsultasi kebutuhan koreksi penglihatan dan perlindungan permukaan lensa.',
            ],
            [
                'category_slug'        => 'lensa-kacamata',
                'name'                 => 'Hoya BlueControl',
                'slug'                 => 'hoya-bluecontrol',
                'brand'                => 'Hoya',
                'price'                => 0,
                'stock'                => 0,
                'low_stock_threshold'  => 0,
                'weight'               => 0,
                'sku'                  => 'LENS-HOYA-BLUECONTROL',
                'gender'               => 'unisex',
                'frame_shape'          => null,
                'frame_material'       => null,
                'frame_color'          => null,
                'is_active'            => true,
                'is_best_seller'       => false,
                'is_new'               => false,
                'is_not_for_sale'      => true,
                'description'          => 'Pilihan lensa Hoya untuk penggunaan layar digital, tersedia melalui konsultasi dan pemasangan di optik.',
            ],
            [
                'category_slug'        => 'aksesoris',
                'name'                 => 'Kain Microfiber Premium Optik Medio',
                'slug'                 => 'kain-microfiber-premium',
                'brand'                => 'Optik Medio',
                'price'                => 35000,
                'stock'                => 0,
                'low_stock_threshold'  => 10,
                'weight'               => 20,
                'sku'                  => 'ACC-MICRO-001',
                'gender'               => null,
                'frame_shape'          => null,
                'frame_material'       => null,
                'frame_color'          => null,
                'is_active'            => true,
                'is_best_seller'       => false,
                'is_new'               => false,
                'description'          => 'Kain microfiber premium untuk membersihkan lensa tanpa meninggalkan goresan.',
            ],
        ];

        $productModels = [];
        foreach ($products as $p) {
            $catId = $categoryModels[$p['category_slug']]->id;
            unset($p['category_slug']);
            $product = Product::updateOrCreate(
                ['slug' => $p['slug']],
                array_merge($p, ['category_id' => $catId])
            );
            $productModels[$product->sku] = $product;
        }

        // ─── 4. Stock Adjustments (riwayat stok) ────────────────────────────
        $admin = User::where('role', 'admin')->first();
        $rayban = $productModels['RB-5154-BLK'];
        $oakleyHolbrook = $productModels['OAK-9102-BLK'];

        // Simulasi riwayat stok untuk Ray-Ban
        StockAdjustment::create([
            'product_id'      => $rayban->id,
            'adjusted_by'     => $admin->id,
            'quantity_before' => 0,
            'quantity_change' => 20,
            'quantity_after'  => 20,
            'reason'          => 'import',
            'notes'           => 'Stok awal dari supplier',
            'created_at'      => now()->subDays(30),
            'updated_at'      => now()->subDays(30),
        ]);
        StockAdjustment::create([
            'product_id'      => $rayban->id,
            'adjusted_by'     => $admin->id,
            'quantity_before' => 20,
            'quantity_change' => -8,
            'quantity_after'  => 12,
            'reason'          => 'order_placed',
            'notes'           => 'Penjualan bulan lalu',
            'created_at'      => now()->subDays(10),
            'updated_at'      => now()->subDays(10),
        ]);

        // Simulasi stok habis untuk Oakley Holbrook (low stock)
        StockAdjustment::create([
            'product_id'      => $oakleyHolbrook->id,
            'adjusted_by'     => $admin->id,
            'quantity_before' => 10,
            'quantity_change' => -8,
            'quantity_after'  => 2,
            'reason'          => 'order_placed',
            'notes'           => 'Penjualan tinggi bulan ini',
            'created_at'      => now()->subDays(5),
            'updated_at'      => now()->subDays(5),
        ]);

        // ─── 5. Demo Customers ───────────────────────────────────────────────
        $customers = [
            [
                'name'           => 'Budi Santoso',
                'email'          => 'budi@example.com',
                'password'       => Hash::make('password'),
                'role'           => 'user',
                'loyalty_points' => 2500,
                'phone'          => '081234567890',
            ],
            [
                'name'           => 'Siti Rahayu',
                'email'          => 'siti@example.com',
                'password'       => Hash::make('password'),
                'role'           => 'user',
                'loyalty_points' => 750,
                'phone'          => '082345678901',
            ],
            [
                'name'           => 'Ahmad Fauzi',
                'email'          => 'ahmad@example.com',
                'password'       => Hash::make('password'),
                'role'           => 'user',
                'loyalty_points' => 12000,
                'phone'          => '083456789012',
            ],
            [
                'name'           => 'Dewi Lestari',
                'email'          => 'dewi@example.com',
                'password'       => Hash::make('password'),
                'role'           => 'user',
                'loyalty_points' => 500,
                'phone'          => '084567890123',
            ],
        ];

        $customerModels = [];
        foreach ($customers as $c) {
            $customerModels[$c['email']] = User::firstOrCreate(
                ['email' => $c['email']],
                $c
            );
        }

        // ─── 6. Shipping Addresses ───────────────────────────────────────────
        $addresses = [];
        foreach ($customerModels as $email => $customer) {
            $addr = ShippingAddress::firstOrCreate(
                ['user_id' => $customer->id, 'is_default' => true],
                [
                    'recipient_name' => $customer->name,
                    'phone'          => $customer->phone ?? '08123456789',
                    'province'       => 'Lampung',
                    'province_id'    => '18',
                    'city'           => 'Bandar Lampung',
                    'city_id'        => '109',
                    'district'       => 'Kedaton',
                    'district_id'    => '1091',
                    'postal_code'    => '35148',
                    'address'        => 'Jl. Raden Intan No. ' . rand(1, 100) . ', Bandar Lampung',
                    'is_default'     => true,
                ]
            );
            $addresses[$email] = $addr;
        }

        // ─── 7. Payment Method ───────────────────────────────────────────────
        $paymentMethod = PaymentMethod::where('code', 'xendit_invoice')->first()
            ?? PaymentMethod::where('code', 'manual_bank_transfer')->first();

        // ─── 8. Orders (berbagai status untuk Kanban) ────────────────────────
        $orderStatuses = [
            'unpaid',
            'unpaid',
            'paid',
            'paid',
            'waiting_prescription_review',
            'prescription_verified',
            'lens_processing',
            'processing',
            'processing',
            'shipped',
            'shipped',
            'delivered',
            'completed',
            'cancelled',
            'refunded',
        ];

        $customerList = array_values($customerModels);
        $productList  = array_values($productModels);

        foreach ($orderStatuses as $i => $status) {
            $customer = $customerList[$i % count($customerList)];
            $address  = $addresses[$customer->email] ?? array_values($addresses)[0];
            $product  = $productList[$i % count($productList)];

            $orderNumber = 'ORD-' . strtoupper(Str::random(8));

            // Cek apakah sudah ada order dengan status ini dari customer ini
            $existingCount = Order::where('user_id', $customer->id)
                ->where('status', $status)
                ->count();
            if ($existingCount >= 2) {
                continue;
            }

            $qty      = rand(1, 3);
            $subtotal = $product->price * $qty;
            $shipping = 15000;
            $total    = $subtotal + $shipping;

            $order = Order::create([
                'order_number'       => $orderNumber,
                'user_id'            => $customer->id,
                'shipping_address_id'=> $address->id,
                'status'             => $status,
                'subtotal'           => $subtotal,
                'shipping_cost'      => $shipping,
                'total_price'        => $total,
                'courier'            => 'JNE',
                'courier_service'    => 'REG',
                'tracking_number'    => in_array($status, ['shipped', 'delivered', 'completed']) ? 'JNE' . strtoupper(Str::random(10)) : null,
                'notes'              => null,
                'paid_at'            => in_array($status, ['paid', 'processing', 'shipped', 'delivered', 'completed']) ? now()->subDays(rand(1, 10)) : null,
                'shipped_at'         => in_array($status, ['shipped', 'delivered', 'completed']) ? now()->subDays(rand(1, 5)) : null,
                'delivered_at'       => in_array($status, ['delivered', 'completed']) ? now()->subDays(rand(0, 2)) : null,
                'created_at'         => now()->subDays(rand(0, 14)),
                'updated_at'         => now()->subDays(rand(0, 3)),
            ]);

            OrderItem::create([
                'order_id'      => $order->id,
                'product_id'    => $product->id,
                'product_name'  => $product->name,
                'product_price' => $product->price,
                'quantity'      => $qty,
                'weight'        => $product->weight * $qty,
                'subtotal'      => $subtotal,
            ]);

            // Payment record — skip if already exists for this order
            if ($paymentMethod && ! Payment::where('order_id', $order->id)->exists()) {
                $payStatus = in_array($status, ['paid', 'processing', 'shipped', 'delivered', 'completed'])
                    ? 'success'
                    : ($status === 'cancelled' ? 'cancelled' : 'pending');

                Payment::create([
                    'order_id'          => $order->id,
                    'payment_method_id' => $paymentMethod->id,
                    'transaction_id'    => 'TXN-' . strtoupper(Str::random(12)),
                    'provider'          => $paymentMethod->provider,
                    'gross_amount'      => $total,
                    'status'            => $payStatus,
                    'paid_at'           => in_array($payStatus, ['success'])
                        ? now()->subDays(rand(1, 10))
                        : null,
                    'created_at'        => $order->created_at,
                    'updated_at'        => $order->updated_at,
                ]);
            }
        }

        // ─── 9. Warranties ───────────────────────────────────────────────────
        $deliveredOrders = Order::whereIn('status', ['delivered', 'completed'])
            ->with('items')
            ->limit(5)
            ->get();

        foreach ($deliveredOrders as $order) {
            $item = $order->items->first();
            if (! $item) {
                continue;
            }

            $existing = Warranty::where('order_id', $order->id)->exists();
            if ($existing) {
                continue;
            }

            Warranty::create([
                'warranty_number'    => Warranty::generateNumber(),
                'user_id'            => $order->user_id,
                'order_id'           => $order->id,
                'order_item_id'      => $item->id,
                'product_name'       => $item->product_name,
                'product_sku'        => $item->product?->sku ?? '-',
                'purchase_date'      => $order->delivered_at ?? now()->subDays(rand(1, 30)),
                'warranty_expires_at'=> now()->addMonths(12),
                'warranty_months'    => 12,
                'status'             => 'active',
                'notes'              => 'Garansi resmi Optik Medio 12 bulan.',
            ]);
        }

        // ─── 10. Appointments ────────────────────────────────────────────────
        $appointmentData = [
            [
                'customer'     => $customerModels['budi@example.com'],
                'service_type' => 'eye_test',
                'status'       => 'confirmed',
                'date_offset'  => 2,
                'time'         => '10:00:00',
            ],
            [
                'customer'     => $customerModels['siti@example.com'],
                'service_type' => 'fitting',
                'status'       => 'pending',
                'date_offset'  => 3,
                'time'         => '14:00:00',
            ],
            [
                'customer'     => $customerModels['ahmad@example.com'],
                'service_type' => 'consultation',
                'status'       => 'completed',
                'date_offset'  => -2,
                'time'         => '09:00:00',
            ],
            [
                'customer'     => $customerModels['dewi@example.com'],
                'service_type' => 'pickup',
                'status'       => 'pending',
                'date_offset'  => 5,
                'time'         => '11:00:00',
            ],
            [
                'customer'     => $customerModels['budi@example.com'],
                'service_type' => 'lens_replacement',
                'status'       => 'confirmed',
                'date_offset'  => 7,
                'time'         => '15:30:00',
            ],
        ];

        foreach ($appointmentData as $apt) {
            $existing = Appointment::where('user_id', $apt['customer']->id)
                ->where('service_type', $apt['service_type'])
                ->exists();
            if ($existing) {
                continue;
            }

            Appointment::create([
                'appointment_number' => Appointment::generateNumber(),
                'user_id'            => $apt['customer']->id,
                'branch_id'          => $branch->id,
                'appointment_date'   => now()->addDays($apt['date_offset'])->toDateString(),
                'appointment_time'   => $apt['time'],
                'service_type'       => $apt['service_type'],
                'status'             => $apt['status'],
                'customer_name'      => $apt['customer']->name,
                'customer_phone'     => $apt['customer']->phone ?? '08123456789',
                'notes'              => 'Dibuat via seeder untuk demo admin.',
                'admin_notes'        => $apt['status'] === 'confirmed' ? 'Sudah dikonfirmasi oleh admin.' : null,
                'confirmed_at'       => $apt['status'] === 'confirmed' ? now()->subHours(rand(1, 24)) : null,
                'completed_at'       => $apt['status'] === 'completed' ? now()->subDays(1) : null,
            ]);
        }

        // ─── 11. Referral Codes ──────────────────────────────────────────────
        foreach ($customerModels as $email => $customer) {
            $existing = ReferralCode::where('user_id', $customer->id)->exists();
            if ($existing) {
                continue;
            }

            ReferralCode::create([
                'user_id'        => $customer->id,
                'code'           => strtoupper(Str::random(8)),
                'total_uses'     => rand(0, 5),
                'reward_inviter' => 500,
                'reward_invitee' => 250,
                'is_active'      => true,
            ]);
        }
    }
}
