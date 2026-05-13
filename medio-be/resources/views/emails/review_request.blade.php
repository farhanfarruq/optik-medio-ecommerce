<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bagikan Ulasan Anda</title>
</head>
<body style="margin:0; padding:0; background:#f5f2ee; font-family: 'Segoe UI', Arial, sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f5f2ee; padding: 40px 20px;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0" border="0" style="background: white; border-top: 4px solid #c19a51; max-width: 600px; width: 100%;">
          <!-- Header -->
          <tr>
            <td style="padding: 40px 40px 32px; text-align: center; background: #1a1209;">
              <p style="margin:0; font-size: 12px; font-weight: 800; letter-spacing: 0.3em; color: #c19a51; text-transform: uppercase;">Optik Medio</p>
              <h1 style="margin: 12px 0 0; font-size: 22px; font-weight: 900; color: white;">Bagaimana Pengalaman Anda?</h1>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="padding: 40px;">
              <p style="margin: 0 0 16px; color: #5a5248; font-size: 15px;">
                Halo <strong>{{ $order->user->name }}</strong>,
              </p>
              <p style="margin: 0 0 24px; color: #5a5248; font-size: 15px; line-height: 1.6;">
                Pesanan <strong>#{{ $order->order_number }}</strong> Anda sudah diterima. Kami harap produk yang Anda terima sesuai harapan!
              </p>
              <p style="margin: 0 0 24px; color: #5a5248; font-size: 15px; line-height: 1.6;">
                Luangkan 1 menit untuk memberikan ulasan. Ulasan Anda sangat membantu pelanggan lain dalam memilih produk terbaik.
              </p>

              <!-- Products -->
              @foreach($order->items->take(3) as $item)
              <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 12px; border: 1px solid #f0ece4; border-radius: 8px;">
                <tr>
                  <td style="padding: 12px 16px;">
                    <p style="margin: 0; font-size: 14px; font-weight: 700; color: #1a1209;">{{ $item->product_name }}</p>
                    <p style="margin: 4px 0 0; font-size: 12px; color: #8a7a60;">Rp {{ number_format($item->product_price, 0, ',', '.') }}</p>
                  </td>
                </tr>
              </table>
              @endforeach

              <!-- CTA -->
              <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top: 32px;">
                <tr>
                  <td align="center">
                    <a href="{{ config('app.frontend_url') }}/orders/{{ $order->id }}"
                       style="display: inline-block; padding: 14px 36px; background: linear-gradient(135deg, #1a1209, #3d2c0e); color: white; text-decoration: none; font-size: 13px; font-weight: 800; letter-spacing: 0.15em; text-transform: uppercase;">
                      Tulis Ulasan Sekarang
                    </a>
                  </td>
                </tr>
              </table>

              <p style="margin: 32px 0 0; color: #8a7a60; font-size: 13px; text-align: center;">
                Terima kasih telah berbelanja di Optik Medio 🙏
              </p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="padding: 24px 40px; background: #f5f2ee; text-align: center; border-top: 1px solid #e5e0d8;">
              <p style="margin: 0; font-size: 11px; color: #8a7a60;">
                © {{ date('Y') }} Optik Medio. Semua hak dilindungi.
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
