<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first();
        if (!$admin) {
            return;
        }

        $articles = [
            [
                'title' => 'Cara Memilih Frame Sesuai Bentuk Wajah',
                'excerpt' => 'Temukan panduan lengkap untuk mendapatkan kacamata yang paling pas dan menunjang penampilan Anda berdasarkan bentuk wajah.',
                'content' => '<h2>Mengenal Bentuk Wajah Anda</h2><p>Memilih kacamata bukan hanya soal fungsi penglihatan, tapi juga soal estetika. Wajah bulat cocok dengan frame kotak, sedangkan wajah kotak lebih pas dengan frame bulat atau oval.</p><h3>1. Wajah Bulat</h3><p>Gunakan frame dengan sudut tajam untuk memberikan kontur.</p><h3>2. Wajah Kotak</h3><p>Gunakan frame bulat untuk memperhalus garis wajah.</p>',
                'featured_image' => 'articles/blog_feature_1.png',
                'tags' => ['Tips', 'Fashion', 'Wajah'],
                'published_at' => now(),
                'is_published' => true,
            ],
            [
                'title' => 'Pentingnya Perlindungan Lensa Blueray',
                'excerpt' => 'Lindungi mata Anda dari radiasi layar digital dengan teknologi lensa terkini dari Optik Medio.',
                'content' => '<h2>Apa itu Blue Light?</h2><p>Sinar biru berasal dari layar gadget yang kita gunakan setiap hari. Paparan berlebih dapat menyebabkan mata lelah dan gangguan tidur.</p><h3>Manfaat Lensa Blueray</h3><ul><li>Mengurangi ketegangan mata</li><li>Meningkatkan kualitas tidur</li><li>Melindungi kesehatan retina jangka panjang</li></ul>',
                'featured_image' => 'articles/blog_feature_2.png',
                'tags' => ['Kesehatan', 'Lensa', 'Teknologi'],
                'published_at' => now()->subDay(),
                'is_published' => true,
            ],
            [
                'title' => 'Update Tren Kacamata 2026',
                'excerpt' => 'Jelajahi gaya terbaru yang akan mendominasi tahun ini, mulai dari gaya retro hingga futuristik.',
                'content' => '<h2>Tren Eyewear 2026</h2><p>Tahun ini, kita melihat kembalinya gaya retro 70-an dengan sentuhan modern. Material ramah lingkungan juga semakin populer.</p><h3>1. Eco-Friendly Acetate</h3><p>Frame yang terbuat dari bahan daur ulang namun tetap terlihat mewah.</p><h3>2. Bold Oversized</h3><p>Ukuran besar kembali menjadi primadona untuk penampilan yang berani.</p>',
                'featured_image' => 'articles/blog_feature_3.png',
                'tags' => ['Tren', '2026', 'Lifestyle'],
                'published_at' => now()->subDays(2),
                'is_published' => true,
            ],
            [
                'title' => 'Cara Merawat Kacamata Agar Awet',
                'excerpt' => 'Tips jitu merawat kacamata Anda agar lensa tidak mudah gores dan frame tetap kokoh seperti baru.',
                'content' => '<h2>Langkah Mudah Perawatan Harian</h2><p>Kacamata yang terawat dengan baik akan bertahan lebih lama dan memberikan penglihatan yang lebih jernih. Berikut panduannya:</p><h3>1. Gunakan Kain Microfiber</h3><p>Jangan pernah membersihkan lensa menggunakan ujung baju atau tisu wajah karena serat kasarnya bisa membuat goresan halus.</p><h3>2. Cuci dengan Sabun Pencuci Piring</h3><p>Cara terbaik mencuci kacamata adalah dengan air mengalir dan setetes cairan pencuci piring (jangan sabun mandi), lalu keringkan perlahan.</p>',
                'featured_image' => 'articles/blog_feature_1.png',
                'tags' => ['Tips', 'Perawatan', 'Tutorial'],
                'published_at' => now()->subDays(5),
                'is_published' => true,
            ],
            [
                'title' => 'Perbedaan Lensa Photocromic dan Blueray',
                'excerpt' => 'Bingung memilih antara Photocromic atau Blueray? Mari bahas perbedaannya untuk menemukan lensa yang pas untuk kebutuhan Anda.',
                'content' => '<h2>Mengenal Fungsi Keduanya</h2><p>Banyak pelanggan bingung membedakan fungsi kedua lensa populer ini. Padahal, fungsinya sangat berbeda meski sama-sama melindungi mata.</p><h3>Lensa Photocromic</h3><p>Fokus utama lensa ini adalah menangkal sinar UV. Lensa akan berubah menjadi gelap saat terkena sinar matahari, ibarat memiliki kacamata baca dan kacamata hitam sekaligus.</p><h3>Lensa Blueray</h3><p>Fokus utamanya adalah memblokir sinar biru dari layar digital. Sangat cocok bagi Anda yang bekerja di depan komputer atau HP seharian penuh.</p>',
                'featured_image' => 'articles/blog_feature_2.png',
                'tags' => ['Edukasi', 'Lensa', 'Produk'],
                'published_at' => now()->subDays(8),
                'is_published' => true,
            ],
        ];

        foreach ($articles as $data) {
            Article::updateOrCreate(
                ['slug' => Str::slug($data['title'])],
                array_merge($data, ['author_id' => $admin->id])
            );
        }
    }
}
