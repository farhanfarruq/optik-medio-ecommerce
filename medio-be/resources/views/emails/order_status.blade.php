<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Pesanan - Optik Medio</title>
</head>
<body style="margin: 0; padding: 0; background-color: #F5F2EE; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #F5F2EE;">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width: 480px; margin: 0 auto; background: white; box-shadow: 0 4px 24px rgba(0,0,0,0.08);">

                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #1a1209 0%, #3d2c0e 100%); padding: 32px 40px; text-align: center;">
                            <h1 style="margin: 0; color: #c19a51; font-size: 20px; font-weight: 800; letter-spacing: 2px;">
                                OPTIK MEDIO
                            </h1>
                            <p style="margin: 6px 0 0; color: rgba(255,255,255,0.6); font-size: 11px; letter-spacing: 3px; text-transform: uppercase;">
                                Premium Eyewear
                            </p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 8px; color: #1a1209; font-size: 22px; font-weight: 700;">
                                Halo, {{ $order->user->name }}!
                            </p>

                            <!-- Dynamic Status Block -->
                            @if($eventType === 'payment_verified')
                                <div style="background: #f0fdf4; border: 1px solid #86efac; padding: 16px 20px; margin: 16px 0 24px; text-align: center;">
                                    <p style="margin: 0; color: #15803d; font-size: 20px; font-weight: 800;">
                                        ✅ Pembayaran Anda Telah Terverifikasi
                                    </p>
                                </div>
                            @elseif($eventType === 'processing')
                                <div style="background: #eff6ff; border: 1px solid #93c5fd; padding: 16px 20px; margin: 16px 0 24px; text-align: center;">
                                    <p style="margin: 0; color: #1d4ed8; font-size: 20px; font-weight: 800;">
                                        🔄 Pesanan Anda Sedang Diproses
                                    </p>
                                </div>
                            @elseif($eventType === 'cancelled')
                                <div style="background: #fef2f2; border: 1px solid #fca5a5; padding: 16px 20px; margin: 16px 0 24px; text-align: center;">
                                    <p style="margin: 0; color: #dc2626; font-size: 20px; font-weight: 800;">
                                        ❌ Pesanan Anda Dibatalkan
                                    </p>
                                </div>
                            @elseif($eventType === 'delivered')
                                <div style="background: #f0fdf4; border: 1px solid #86efac; padding: 16px 20px; margin: 16px 0 24px; text-align: center;">
                                    <p style="margin: 0; color: #15803d; font-size: 20px; font-weight: 800;">
                                        📦 Pesanan Anda Telah Diterima
                                    </p>
                                </div>
                            @elseif($eventType === 'completed')
                                <div style="background: #f0fdf4; border: 1px solid #86efac; padding: 16px 20px; margin: 16px 0 24px; text-align: center;">
                                    <p style="margin: 0; color: #15803d; font-size: 20px; font-weight: 800;">
                                        ✅ Pesanan Anda Selesai
                                    </p>
                                </div>
                            @endif

                            <!-- Order Details -->
                            <div style="background: #faf9f7; border: 1px solid #e8e0d0; padding: 16px 20px; margin: 0 0 24px;">
                                <p style="margin: 0 0 10px; color: #8a7a60; font-size: 10px; font-weight: 700; letter-spacing: 3px; text-transform: uppercase;">
                                    Detail Pesanan
                                </p>
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                    <tr>
                                        <td style="color: #8a7a60; font-size: 12px; padding-bottom: 6px; width: 40%;">No. Pesanan</td>
                                        <td style="color: #1a1209; font-size: 12px; font-weight: 600; padding-bottom: 6px;">{{ $order->order_number }}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #8a7a60; font-size: 12px;">Total Pembayaran</td>
                                        <td style="color: #1a1209; font-size: 12px; font-weight: 700;">
                                            Rp {{ number_format($order->total_price, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Contextual Note -->
                            @if($eventType === 'payment_verified')
                                <p style="margin: 0 0 28px; color: #8a7a60; font-size: 14px; line-height: 1.6;">
                                    Pesanan Anda akan segera kami proses.
                                </p>
                            @elseif($eventType === 'processing')
                                <p style="margin: 0 0 28px; color: #8a7a60; font-size: 14px; line-height: 1.6;">
                                    Pesanan Anda sedang disiapkan oleh tim kami.
                                </p>
                            @elseif($eventType === 'cancelled')
                                <p style="margin: 0 0 28px; color: #8a7a60; font-size: 14px; line-height: 1.6;">
                                    Jika Anda sudah melakukan pembayaran, dana akan dikembalikan dalam <strong>3–7 hari kerja</strong>.
                                </p>
                            @elseif($eventType === 'delivered')
                                <p style="margin: 0 0 28px; color: #8a7a60; font-size: 14px; line-height: 1.6;">
                                    Terima kasih telah berbelanja di Optik Medio! Jangan lupa berikan ulasan produk Anda.
                                </p>
                            @elseif($eventType === 'completed')
                                <p style="margin: 0 0 28px; color: #8a7a60; font-size: 14px; line-height: 1.6;">
                                    Pesanan Anda otomatis kami tandai selesai karena sudah berstatus diterima selama 3 hari tanpa perubahan status.
                                </p>
                            @endif

                            <!-- CTA Button -->
                            <div style="text-align: center;">
                                <a href="{{ config('app.frontend_url') }}/orders/{{ $order->id }}"
                                   style="display: inline-block; background: linear-gradient(135deg, #1a1209 0%, #3d2c0e 100%); color: #c19a51; text-decoration: none; font-size: 12px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; padding: 14px 32px;">
                                    Lihat Detail Pesanan
                                </a>
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 24px 40px; border-top: 1px solid #f0ece4; text-align: center;">
                            <p style="margin: 0; color: #c19a51; font-size: 10px; font-weight: 700; letter-spacing: 2px;">
                                &copy; {{ date('Y') }} OPTIK MEDIO
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
