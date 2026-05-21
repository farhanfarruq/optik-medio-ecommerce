<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><title>Booking Baru — Optik Medio Admin</title>
<style>
  body{font-family:'Segoe UI',Arial,sans-serif;background:#f5f2ee;margin:0;padding:20px;color:#1a1209}
  .wrap{max-width:560px;margin:0 auto;background:#fff;border:1px solid #e8e0d4}
  .hdr{background:#1a1209;padding:24px 32px}.hdr h1{color:#c19a51;margin:0;font-size:18px}.hdr p{color:#8a7a60;margin:4px 0 0;font-size:12px}
  .badge{display:inline-block;padding:4px 12px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin-bottom:16px;background:rgba(234,179,8,.1);color:#ca8a04}
  .body{padding:32px}
  .row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f0ece4;font-size:14px}
  .row:last-child{border-bottom:none}
  .lbl{color:#8a7a60;font-size:12px;text-transform:uppercase;letter-spacing:.08em}
  .val{font-weight:600;text-align:right}
  .cta{display:block;margin:24px 0 0;padding:14px 24px;background:#1a1209;color:#fff;text-decoration:none;text-align:center;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.1em}
  .ftr{padding:16px 32px;background:#f5f2ee;font-size:11px;color:#8a7a60;text-align:center}
</style></head>
<body><div class="wrap">
  <div class="hdr"><h1>Optik Medio — Admin</h1><p>Notifikasi sistem otomatis</p></div>
  <div class="body">
    <span class="badge">Booking Baru</span>
    <p style="font-size:15px;font-weight:700;margin:0 0 20px">Ada booking konsultasi baru yang perlu dikonfirmasi.</p>
    <div style="background:#f9f7f4;padding:16px;margin-bottom:16px">
      <div class="row"><span class="lbl">Layanan</span><span class="val">{{ $appointment->service_type }}</span></div>
      <div class="row"><span class="lbl">Nama</span><span class="val">{{ $appointment->customer_name }}</span></div>
      <div class="row"><span class="lbl">Telepon</span><span class="val">{{ $appointment->customer_phone }}</span></div>
      @if($appointment->preferred_date)<div class="row"><span class="lbl">Tanggal Pilihan</span><span class="val">{{ \Carbon\Carbon::parse($appointment->preferred_date)->format('d M Y') }}</span></div>@endif
      @if($appointment->preferred_time)<div class="row"><span class="lbl">Waktu Pilihan</span><span class="val">{{ $appointment->preferred_time }}</span></div>@endif
      @if($appointment->notes)<div class="row"><span class="lbl">Catatan</span><span class="val" style="max-width:300px;text-align:right">{{ $appointment->notes }}</span></div>@endif
    </div>
    <a href="{{ config('app.url') }}/admin/appointments/{{ $appointment->id }}/edit" class="cta">Konfirmasi Booking →</a>
  </div>
  <div class="ftr">Email ini dikirim otomatis oleh sistem Optik Medio. Jangan balas email ini.</div>
</div></body></html>
