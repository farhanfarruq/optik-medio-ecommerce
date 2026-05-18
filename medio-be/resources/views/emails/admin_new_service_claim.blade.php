<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><title>Klaim Servis Baru — Optik Medio Admin</title>
<style>
  body{font-family:'Segoe UI',Arial,sans-serif;background:#f5f2ee;margin:0;padding:20px;color:#1a1209}
  .wrap{max-width:560px;margin:0 auto;background:#fff;border:1px solid #e8e0d4}
  .hdr{background:#1a1209;padding:24px 32px}.hdr h1{color:#c19a51;margin:0;font-size:18px}.hdr p{color:#8a7a60;margin:4px 0 0;font-size:12px}
  .badge{display:inline-block;padding:4px 12px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin-bottom:16px;background:rgba(37,99,235,.1);color:#1d4ed8}
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
    <span class="badge">Klaim Servis Baru</span>
    <p style="font-size:15px;font-weight:700;margin:0 0 20px">Ada klaim servis/garansi baru yang perlu ditangani.</p>
    <div style="background:#f9f7f4;padding:16px;margin-bottom:16px">
      <div class="row"><span class="lbl">No. Klaim</span><span class="val">{{ $claim->claim_number }}</span></div>
      <div class="row"><span class="lbl">Pelanggan</span><span class="val">{{ $claim->user->name }}</span></div>
      <div class="row"><span class="lbl">Email</span><span class="val">{{ $claim->user->email }}</span></div>
      <div class="row"><span class="lbl">Jenis Klaim</span><span class="val">{{ $claim->claim_type }}</span></div>
      @if($claim->warranty)<div class="row"><span class="lbl">No. Garansi</span><span class="val">{{ $claim->warranty->warranty_number }}</span></div>@endif
      <div class="row"><span class="lbl">Ditanggung Garansi</span><span class="val">{{ $claim->is_covered_by_warranty ? 'Ya' : 'Tidak' }}</span></div>
    </div>
    @if($claim->description)
    <p style="font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#8a7a60;margin:0 0 8px">Deskripsi</p>
    <div style="background:#f9f7f4;padding:16px;font-size:13px;line-height:1.6;margin-bottom:16px">{{ $claim->description }}</div>
    @endif
    <a href="{{ config('app.url') }}/admin/service-claims/{{ $claim->id }}/edit" class="cta">Tangani Klaim →</a>
  </div>
  <div class="ftr">Email ini dikirim otomatis oleh sistem Optik Medio. Jangan balas email ini.</div>
</div></body></html>
