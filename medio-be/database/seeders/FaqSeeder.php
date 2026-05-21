<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faqs = [
            [
                'question' => 'Bagaimana cara menentukan ukuran kacamata yang pas?',
                'answer' => 'Anda bisa melihat angka yang tertera di gagang kacamata lama Anda (misal: 52-18-140) atau menggunakan fitur Virtual Try-On kami. Kami juga menyediakan panduan ukuran lengkap di setiap halaman produk.',
                'category' => 'Produk',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'question' => 'Apakah bisa memesan kacamata dengan resep dokter (minus/silinder)?',
                'answer' => 'Tentu saja! Anda cukup mengunggah foto resep dokter saat melakukan pemesanan atau mengisi data resep secara manual di form yang telah kami sediakan.',
                'category' => 'Lensa & Resep',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'question' => 'Berapa lama proses pembuatan kacamata resep?',
                'answer' => 'Proses pemasangan lensa resep standar membutuhkan waktu 1-3 hari kerja sebelum pesanan dikirimkan kepada Anda.',
                'category' => 'Pesanan',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'question' => 'Apa itu lensa Blue Light/Anti Radiasi?',
                'answer' => 'Lensa Blue Light dirancang khusus untuk menyaring sinar biru berbahaya dari layar gadget, sehingga mata Anda tidak cepat lelah dan membantu menjaga kualitas tidur.',
                'category' => 'Produk',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'question' => 'Bagaimana jika kacamata yang saya terima tidak pas atau tidak sesuai?',
                'answer' => 'Kami memberikan garansi 7 hari untuk penyesuaian atau pengembalian jika produk yang Anda terima mengalami cacat produksi atau tidak sesuai dengan pesanan Anda.',
                'category' => 'Layanan',
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'question' => 'Metode pembayaran apa saja yang tersedia?',
                'answer' => 'Kami menerima pembayaran melalui Transfer Bank (Virtual Account), E-Wallet (OVO, Dana, ShopeePay), dan Kartu Kredit melalui payment gateway Xendit yang aman.',
                'category' => 'Pembayaran',
                'sort_order' => 6,
                'is_active' => true,
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::updateOrCreate(
                ['question' => $faq['question']],
                $faq
            );
        }
    }
}
