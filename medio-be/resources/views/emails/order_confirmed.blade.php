<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pesanan Dikonfirmasi</title>
</head>
<body style="margin:0; padding:0; background:#f5f2ee; font-family: 'Segoe UI', Arial, sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f5f2ee; padding: 40px 20px;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0" border="0" style="background: white; border-top: 4px solid #c19a51; max-width: 600px; width: 100%;">
          <!-- Header -->
          <tr>
            <td style="padding: 40px 40px 32px; text-align: center; background: #1a1209;">
              <p style="margin:0; font-size: 12px; font-weight: 800; letter-spacing: 4px; color: #c19a51; text-transform: uppercase; margin-bottom: 8px;">Optik Medio</p>
              <h1 style="margin:0; font-size: 24px; color: white; font-weight: 800;">Pesanan Berhasil Dibuat!</h1>
            </td>
          </tr>
          <!-- Body -->
          <tr>
            <td style="padding: 40px;">
              <p style="color: #5a5248; margin: 0 0 24px;">Halo <strong>{{ $order->user->name }}</strong>, terima kasih telah berbelanja di Optik Medio.</p>

              <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background: #f9f7f4; border: 1px solid rgba(193,154,81,0.2); margin-bottom: 24px;">
                <tr>
                  <td style="padding: 20px;">
                    <p style="margin: 0 0 8px; font-size: 11px; color: #c19a51; font-weight: 800; text-transform: uppercase; letter-spacing: 2px;">Nomor Pesanan</p>
                    <p style="margin: 0; font-size: 22px; color: #1a1209; font-weight: 800;">#{{ $order->order_number }}</p>
                  </td>
                </tr>
              </table>

              <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 24px;">
                <tr>
                  <td style="padding: 8px 0; border-bottom: 1px solid #f0ebe4; color: #8a7a60; font-size: 14px;">Status</td>
                  <td style="padding: 8px 0; border-bottom: 1px solid #f0ebe4; color: #1a1209; font-weight: 700; font-size: 14px; text-align: right;">Menunggu Pembayaran</td>
                </tr>
                <tr>
                  <td style="padding: 8px 0; border-bottom: 1px solid #f0ebe4; color: #8a7a60; font-size: 14px;">Total Pembayaran</td>
                  <td style="padding: 8px 0; border-bottom: 1px solid #f0ebe4; color: #1a1209; font-weight: 700; font-size: 14px; text-align: right;">Rp {{ number_format($order->total_price, 0, ',', '.') }}</td>
                </tr>
                <tr>
                  <td style="padding: 8px 0; color: #8a7a60; font-size: 14px;">Kurir</td>
                  <td style="padding: 8px 0; color: #1a1209; font-weight: 700; font-size: 14px; text-align: right;">{{ strtoupper($order->courier) }} {{ $order->courier_service }}</td>
                </tr>
              </table>

              @if($order->payment?->checkout_url)
              <div style="text-align: center; margin: 32px 0;">
                <a href="{{ $order->payment->checkout_url }}"
                   style="background: linear-gradient(135deg, #1a1209, #3d2c0e); color: white; text-decoration: none; padding: 16px 40px; font-weight: 800; font-size: 13px; letter-spacing: 2px; text-transform: uppercase; display: inline-block;">
                  Bayar Sekarang
                </a>
              </div>
              @endif

              <p style="color: #8a7a60; font-size: 13px; margin: 24px 0 0; text-align: center;">
                Jika ada pertanyaan, hubungi kami di <a href="mailto:{{ config('mail.from.address') }}" style="color: #c19a51;">{{ config('mail.from.address') }}</a>
              </p>
            </td>
          </tr>
          <!-- Footer -->
          <tr>
            <td style="padding: 24px 40px; background: #f5f2ee; text-align: center; border-top: 1px solid rgba(193,154,81,0.15);">
              <p style="margin: 0; font-size: 11px; color: #8a7a60;">© {{ date('Y') }} Optik Medio. Semua hak dilindungi.</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
