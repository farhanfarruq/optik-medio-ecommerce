<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Dikirim</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #1e3a8a, #3b82f6); padding: 40px 30px; text-align: center; }
        .header h1 { color: white; margin: 0; font-size: 28px; }
        .header p { color: rgba(255,255,255,0.85); margin: 8px 0 0; font-size: 16px; }
        .body { padding: 30px; }
        .tracking-box { background: #eff6ff; border: 2px solid #3b82f6; border-radius: 10px; padding: 20px; text-align: center; margin: 20px 0; }
        .tracking-box .label { font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; }
        .tracking-box .resi { font-size: 24px; font-weight: bold; color: #1e3a8a; letter-spacing: 2px; margin: 8px 0; }
        .tracking-box .courier { font-size: 14px; color: #3b82f6; font-weight: 600; }
        .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .items-table th { background: #f9fafb; padding: 10px; text-align: left; font-size: 12px; color: #6b7280; text-transform: uppercase; }
        .items-table td { padding: 12px 10px; border-bottom: 1px solid #f3f4f6; }
        .total-row { font-weight: bold; color: #1e3a8a; }
        .footer { background: #f9fafb; padding: 20px 30px; text-align: center; color: #9ca3af; font-size: 13px; }
        .btn { display: inline-block; background: #3b82f6; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚚 Pesanan Sedang Dikirim!</h1>
            <p>Pesanan #{{ $order->order_number }} dalam perjalanan ke Anda</p>
        </div>
        <div class="body">
            <p>Halo <strong>{{ $order->user->name }}</strong>,</p>
            <p>Kabar gembira! Pesanan Anda sudah dalam perjalanan menuju alamat pengiriman.</p>

            <div class="tracking-box">
                <div class="label">Nomor Resi Pengiriman</div>
                <div class="resi">{{ $order->tracking_number }}</div>
                <div class="courier">{{ strtoupper($order->courier) }} — {{ $order->courier_service }}</div>
            </div>

            <p>Alamat pengiriman:</p>
            <p style="background:#f9fafb; padding:12px; border-radius:8px; color:#374151;">
                <strong>{{ $order->shippingAddress->recipient_name }}</strong><br>
                {{ $order->shippingAddress->address }},
                {{ $order->shippingAddress->district }},
                {{ $order->shippingAddress->city }},
                {{ $order->shippingAddress->province }}
                {{ $order->shippingAddress->postal_code }}
            </p>

            <h3 style="color:#1e3a8a;">Item yang dikirim:</h3>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Qty</th>
                        <th>Harga</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $item)
                    <tr>
                        <td>{{ $item->product_name }}</td>
                        <td>{{ $item->quantity }}x</td>
                        <td>Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                    <tr class="total-row">
                        <td colspan="2">Total</td>
                        <td>Rp {{ number_format($order->total_price, 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>

            <div style="text-align:center;">
                <a href="{{ config('app.frontend_url') }}/orders/{{ $order->id }}" class="btn">
                    Lacak Pesanan Saya
                </a>
            </div>

            <p style="color:#6b7280; font-size:14px;">
                Jika ada pertanyaan, hubungi kami melalui WhatsApp atau email yang tertera di website.<br>
                Terima kasih telah berbelanja di <strong>Optik Medio</strong>!
            </p>
        </div>
        <div class="footer">
            © {{ date('Y') }} Optik Medio — Semua hak dilindungi<br>
            <small>Email ini dikirim otomatis, mohon tidak membalas email ini.</small>
        </div>
    </div>
</body>
</html>
