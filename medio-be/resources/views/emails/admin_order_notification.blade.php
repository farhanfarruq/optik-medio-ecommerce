<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifikasi Admin — Optik Medio</title>
<style>
  body { font-family: 'Segoe UI', Arial, sans-serif; background: #f5f2ee; margin: 0; padding: 20px; color: #1a1209; }
  .container { max-width: 560px; margin: 0 auto; background: white; border: 1px solid #e8e0d4; }
  .header { background: #1a1209; padding: 24px 32px; }
  .header h1 { color: #c19a51; margin: 0; font-size: 18px; letter-spacing: 0.05em; }
  .header p { color: #8a7a60; margin: 4px 0 0; font-size: 12px; }
  .badge { display: inline-block; padding: 4px 12px; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 16px; }
  .badge-new { background: rgba(193,154,81,0.15); color: #8a7a60; }
  .badge-payment { background: rgba(37,99,235,0.1); color: #1d4ed8; }
  .badge-complain { background: rgba(239,68,68,0.1); color: #dc2626; }
  .body { padding: 32px; }
  .row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f0ece4; font-size: 14px; }
  .row:last-child { border-bottom: none; }
  .label { color: #8a7a60; font-size: 12px; text-transform: uppercase; letter-spacing: 0.08em; }
  .value { font-weight: 600; text-align: right; }
  .total { font-size: 18px; font-weight: bold; color: #c19a51; }
  .cta { display: block; margin: 24px 0 0; padding: 14px 24px; background: #1a1209; color: white; text-decoration: none; text-align: center; font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.1em; }
  .footer { padding: 16px 32px; background: #f5f2ee; font-size: 11px; color: #8a7a60; text-align: center; }
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <h1>Optik Medio — Admin</h1>
    <p>Notifikasi sistem otomatis</p>
  </div>
  <div class="body">

    @if($eventType === 'new_order')
      <span class="badge badge-new">Order Baru</span>
      <p style="font-size:15px;font-weight:bold;margin:0 0 20px;">Order baru masuk dan menunggu pembayaran.</p>
    @elseif($eventType === 'payment_proof')
      <span class="badge badge-payment">Bukti Transfer</span>
      <p style="font-size:15px;font-weight:bold;margin:0 0 20px;">Customer telah mengunggah bukti transfer. Silakan verifikasi.</p>
    @else
      <span class="badge badge-new">Update Order</span>
      <p style="font-size:15px;font-weight:bold;margin:0 0 20px;">Ada update pada order berikut.</p>
    @endif

    <div style="background:#f9f7f4;padding:16px;margin-bottom:20px;">
      <div class="row"><span class="label">No. Order</span><span class="value">{{ $order->order_number }}</span></div>
      <div class="row"><span class="label">Pelanggan</span><span class="value">{{ $order->user->name }}</span></div>
      <div class="row"><span class="label">Email</span><span class="value">{{ $order->user->email }}</span></div>
      <div class="row"><span class="label">Telepon</span><span class="value">{{ $order->user->phone ?? '-' }}</span></div>
      <div class="row"><span class="label">Status</span><span class="value">{{ strtoupper($order->status) }}</span></div>
      <div class="row"><span class="label">Metode Bayar</span><span class="value">{{ $order->payment_channel ?? ($order->bank?->name ?? 'Xendit') }}</span></div>
      <div class="row"><span class="label">Total</span><span class="value total">Rp {{ number_format($order->total_price, 0, ',', '.') }}</span></div>
    </div>

    @if($order->items->count() > 0)
    <p style="font-size:12px;text-transform:uppercase;letter-spacing:0.08em;color:#8a7a60;margin:0 0 8px;">Produk</p>
    @foreach($order->items as $item)
    <div style="padding:8px 0;border-bottom:1px solid #f0ece4;font-size:13px;">
      {{ $item->product_name }} × {{ $item->quantity }}
      @if($item->configuration_snapshot['lens_option'] ?? null)
        <br><span style="color:#8a7a60;font-size:11px;">+ {{ $item->configuration_snapshot['lens_option']['name'] }}</span>
      @endif
    </div>
    @endforeach
    @endif

    <a href="{{ config('app.url') }}/admin/orders/{{ $order->id }}/edit" class="cta">
      Buka di Admin Panel →
    </a>
  </div>
  <div class="footer">
    Email ini dikirim otomatis oleh sistem Optik Medio. Jangan balas email ini.
  </div>
</div>
</body>
</html>
