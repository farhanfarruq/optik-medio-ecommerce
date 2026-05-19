<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\Discount;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. App Settings
        AppSetting::updateOrCreate(['key' => 'store_name'],  ['group' => 'general',  'value' => 'Optik Medio', 'type' => 'string']);
        AppSetting::updateOrCreate(['key' => 'store_address'], ['group' => 'general', 'value' => 'Pasar, Bandarsari, Kec. Padang Ratu, Kabupaten Lampung Tengah, Lampung 34175', 'type' => 'string']);
        AppSetting::updateOrCreate(['key' => 'store_phone'], ['group' => 'general', 'value' => '0813-1196-9585', 'type' => 'string']);
        AppSetting::updateOrCreate(['key' => 'store_opening_hours'], ['group' => 'general', 'value' => 'Buka setiap hari, tutup pukul 20.30', 'type' => 'string']);
        AppSetting::updateOrCreate(['key' => 'store_location_url'], ['group' => 'general', 'value' => 'https://www.google.com/maps/place/Optik+Medio/@-5.0873184,104.9593006,17z/data=!4m16!1m9!3m8!1s0x2e474dfd0f3db101:0xfdf2736fd871343f!2sOptik+Medio!8m2!3d-5.0873184!4d104.9618755!9m1!1b1!16s%2Fg%2F11tsn13pql!3m5!1s0x2e474dfd0f3db101:0xfdf2736fd871343f!8m2!3d-5.0873184!4d104.9618755!16s%2Fg%2F11tsn13pql?entry=ttu&g_ep=EgoyMDI2MDQyNi4wIKXMDSoASAFQAw%3D%3D', 'type' => 'string']);
        AppSetting::updateOrCreate(['key' => 'store_testimonials'], [
            'group' => 'general', 
            'value' => json_encode([
                ['name' => 'Ryan Fajar', 'review' => 'Koleksi frame terkini, keren keren..', 'rating' => 5],
                ['name' => 'Ronaldi Putra', 'review' => 'Optik nya bagus, mewah, pelayanan nya ramah.. Owner nya jujur dan berpengalaman..', 'rating' => 5]
            ]), 
            'type' => 'json'
        ]);
        AppSetting::updateOrCreate(['key' => 'tax_rate'],    ['group' => 'finance',   'value' => '11',                  'type' => 'integer']);
        AppSetting::updateOrCreate(['key' => 'loyalty_conversion'], ['group' => 'loyalty', 'value' => '100',            'type' => 'integer']);

        // 2. Discounts
        Discount::firstOrCreate(['code' => 'WELCOME2026'], [
            'type'       => 'percentage',
            'value'      => 10,
            'start_date' => now()->subDays(10),
            'end_date'   => now()->addMonths(2),
            'is_active'  => true,
        ]);
        Discount::firstOrCreate(['code' => 'FLAT50K'], [
            'type'      => 'fixed',
            'value'     => 50000,
            'is_active' => true,
        ]);

        // 3. Admin User
        User::firstOrCreate(['email' => 'admin@toko.com'], [
            'name'     => 'Admin Optik',
            'password' => Hash::make('password'),
            'role'     => 'admin',
        ]);

        // 4. Demo Customer
        User::firstOrCreate(['email' => 'customer@toko.com'], [
            'name'           => 'Customer Setia',
            'password'       => Hash::make('password'),
            'role'           => 'user',
            'loyalty_points' => 1500,
        ]);

        $this->call(ArticleSeeder::class);
        $this->call(BankSeeder::class);
        $this->call(BannerSeeder::class);
        $this->call(ExpeditionSeeder::class);
        $this->call(FaqSeeder::class);
        $this->call(LevelMemberSeeder::class);
        $this->call(PaymentMethodSeeder::class);
        $this->call(AdminShowcaseSeeder::class);
        $this->call(ProductCatalogSeeder::class);
        $this->call(ProductPhotoSeeder::class);
        $this->call(OpticalConfigurationSeeder::class);
        $this->call(ShippingRateSeeder::class);
        
    }
}
