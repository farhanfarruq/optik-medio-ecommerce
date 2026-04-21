<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ShippingAddress;
use App\Models\User;
use App\Models\AppSetting;
use App\Models\Discount;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. App Settings
        AppSetting::create(['key' => 'store_name', 'group' => 'general', 'value' => 'Optik Medio Premium', 'type' => 'string']);
        AppSetting::create(['key' => 'tax_rate', 'group' => 'finance', 'value' => '11', 'type' => 'integer']);
        AppSetting::create(['key' => 'loyalty_conversion', 'group' => 'loyalty', 'value' => '100', 'type' => 'integer']); // 1 point per 100 IDR

        // 2. Discounts
        Discount::create([
            'code' => 'WELCOME2026',
            'type' => 'percentage',
            'value' => 10,
            'start_date' => now()->subDays(10),
            'end_date' => now()->addMonths(2),
            'is_active' => true,
        ]);
        Discount::create([
            'code' => 'FLAT50K',
            'type' => 'fixed',
            'value' => 50000,
            'is_active' => true,
        ]);

        // 3. Users
        $admin = User::create([
            'name'     => 'Admin Optik',
            'email'    => 'admin@toko.com',
            'password' => Hash::make('password'),
            'role'     => 'admin',
        ]);

        $users = [];
        $users[] = User::create([
            'name'     => 'Customer Setia',
            'email'    => 'customer@toko.com',
            'password' => Hash::make('password'),
            'role'     => 'user',
            'loyalty_points' => 1500,
        ]);

        for ($i = 0; $i < 20; $i++) {
            $users[] = User::create([
                'name' => 'User ' . $i,
                'email' => 'user'.$i.'@example.com',
                'password' => Hash::make('password'),
                'role' => 'user',
                'loyalty_points' => rand(0, 500),
            ]);
        }

        // 4. Categories
        $categories = [
            ['name' => 'Lensa Kacamata', 'slug' => 'lensa-kacamata'],
            ['name' => 'Frame Pria', 'slug' => 'frame-pria'],
            ['name' => 'Frame Wanita', 'slug' => 'frame-wanita'],
            ['name' => 'Kacamata Hitam (Sunglasses)', 'slug' => 'sunglasses'],
            ['name' => 'Softlens', 'slug' => 'softlens'],
            ['name' => 'Aksesoris & Perawatan', 'slug' => 'aksesoris'],
        ];

        $categoryModels = [];
        foreach ($categories as $cat) {
            $categoryModels[] = Category::create([...$cat, 'is_active' => true]);
        }

        // 5. Products
        $productNames = [
            'Frame' => ['Ray-Ban Aviator', 'Oakley Holbrook', 'Gucci GG0053S', 'Tom Ford FT5294', 'Prada PR 01OS', 'Police SPL999', 'Levis L500', 'Bvlgari BV8194B', 'Dior Stellaire', 'Chanel CH5414'],
            'Lensa' => ['Essilor Crizal Sapphire', 'Zeiss DriveSafe', 'Hoya Sync III', 'Kodak Clean&CleAR', 'Lensa Anti-Radiasi Blueray', 'Lensa Photocromic Grey', 'Lensa Progresif Varilux', 'Lensa Bifokal Standar'],
            'Softlens' => ['Acuvue Oasys', 'Air Optix Colors', 'FreshLook ColorBlends', 'Bausch + Lomb Biotrue', 'X2 Bio Color', 'Omega Softlens', 'Ice Nude Softlens'],
            'Sunglasses' => ['Ray-Ban Wayfarer', 'Oakley Frogskins', 'Gentle Monster MyMa', 'Hawkers One', 'Vans Spicoli'],
            'Aksesoris' => ['Cairan Pembersih Lensa Optik', 'Lap Microfiber Premium', 'Kotak Kacamata Kulit', 'Tali Kacamata Olahraga', 'Obeng Mini Kacamata']
        ];

        $productModels = [];
        foreach ($productNames as $catName => $items) {
            $catId = collect($categoryModels)->first(fn($c) => str_contains(strtolower($c->name), strtolower(explode(' ', $catName)[0])))->id ?? 1;
            
            foreach ($items as $item) {
                $isPrescription = in_array($catName, ['Lensa', 'Softlens']);
                $price = rand(10, 250) * 10000;
                
                $productModels[] = Product::create([
                    'category_id' => $catId,
                    'name' => $item,
                    'slug' => Str::slug($item) . '-' . Str::random(4),
                    'description' => 'Produk optik berkualitas tinggi: ' . $item,
                    'brand' => explode(' ', $item)[0],
                    'price' => $price,
                    'stock' => rand(5, 100),
                    'weight' => rand(50, 300),
                    'is_active' => true,
                    'is_prescription_required' => $isPrescription,
                ]);
            }
        }

        // 6. Orders & Historical Data (Spread over 12 months)
        $statuses = ['unpaid', 'paid', 'processing', 'shipped', 'delivered', 'cancelled'];
        
        for ($i = 0; $i < 150; $i++) {
            $user = $users[array_rand($users)];
            $status = $statuses[array_rand($statuses)];
            // Bias towards 'delivered' for better charts
            if (rand(1, 10) > 4) $status = 'delivered';

            $createdAt = Carbon::now()->subDays(rand(1, 365));
            $shippedAt = in_array($status, ['shipped', 'delivered']) ? $createdAt->copy()->addDays(rand(1, 2)) : null;
            $deliveredAt = $status === 'delivered' ? $createdAt->copy()->addDays(rand(3, 5)) : null;

            $shippingAddress = ShippingAddress::create([
                'user_id' => $user->id,
                'recipient_name' => $user->name,
                'province' => 'DKI Jakarta',
                'province_id' => '6',
                'city' => 'Jakarta Pusat',
                'city_id' => '152',
                'district' => 'Gambir',
                'district_id' => '2000',
                'address' => 'Jl. Sudirman No ' . rand(1, 100),
                'postal_code' => '10220',
                'phone' => '08123456789',
            ]);

            $order = Order::create([
                'order_number' => 'ORD-' . strtoupper(Str::random(8)),
                'user_id' => $user->id,
                'shipping_address_id' => $shippingAddress->id,
                'status' => $status,
                'subtotal' => 0,
                'shipping_cost' => rand(1, 5) * 10000,
                'total_price' => 0,
                'courier' => 'JNE',
                'courier_service' => 'REG',
                'tracking_number' => in_array($status, ['shipped', 'delivered']) ? 'JN' . rand(100000000, 999999999) : null,
                'shipped_at' => $shippedAt,
                'delivered_at' => $deliveredAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $numItems = rand(1, 3);
            $subtotal = 0;
            for ($j = 0; $j < $numItems; $j++) {
                $product = $productModels[array_rand($productModels)];
                $qty = rand(1, 2);
                $price = $product->price;
                $subtotal += ($price * $qty);
                
                $prescription = $product->is_prescription_required ? json_encode([
                    'od_sph' => -1.00, 'od_cyl' => -0.50, 'od_axis' => 180,
                    'os_sph' => -1.50, 'os_cyl' => 0, 'os_axis' => 0,
                    'pd' => 62
                ]) : null;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_price' => $price,
                    'quantity' => $qty,
                    'weight' => $product->weight,
                    'subtotal' => $price * $qty,
                    'prescription' => $prescription,
                ]);
            }

            $order->update([
                'subtotal' => $subtotal,
                'total_price' => $subtotal + $order->shipping_cost,
            ]);

            if (in_array($status, ['paid', 'processing', 'shipped', 'delivered'])) {
                Payment::create([
                    'order_id' => $order->id,
                    'external_id' => 'inv_' . $order->order_number,
                    'payment_method' => 'BANK_TRANSFER',
                    'amount' => $order->total_price,
                    'status' => 'PAID',
                    'paid_at' => $createdAt->copy()->addHours(rand(1, 5)),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }
        }
    }
}
