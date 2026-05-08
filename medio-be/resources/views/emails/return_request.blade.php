<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Pengajuan Return</title>
</head>
<body style="margin:0; padding:0; background:#f5f2ee; font-family: 'Segoe UI', Arial, sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f5f2ee; padding: 40px 20px;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0" border="0" style="background: white; border-top: 4px solid #dc2626; max-width: 600px; width: 100%;">
          <tr>
            <td style="padding: 40px 40px 32px; background: #1a1209; text-align: center;">
              <p style="margin:0; font-size: 11px; font-weight: 800; letter-spacing: 4px; color: #c19a51; text-transform: uppercase; margin-bottom: 8px;">Optik Medio Admin</p>
              <h1 style="margin:0; font-size: 22px; color: white; font-weight: 800;">Pengajuan Pengembalian Barang</h1>
            </td>
          </tr>
          <tr>
            <td style="padding: 40px;">
              <p style="color: #5a5248; margin: 0 0 24px;">Ada pengajuan return baru dari pelanggan.</p>

              <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 24px;">
                <tr>
                  <td style="padding: 8px 0; border-bottom: 1px solid #f0ebe4; color: #8a7a60; font-size: 14px;">Pelanggan</td>
                  <td style="padding: 8px 0; border-bottom: 1px solid #f0ebe4; color: #1a1209; font-weight: 700; font-size: 14px; text-align: right;">{{ $customer->name }} ({{ $customer->email }})</td>
                </tr>
                <tr>
                  <td style="padding: 8px 0; border-bottom: 1px solid #f0ebe4; color: #8a7a60; font-size: 14px;">Nomor Pesanan</td>
                  <td style="padding: 8px 0; border-bottom: 1px solid #f0ebe4; color: #1a1209; font-weight: 700; font-size: 14px; text-align: right;">#{{ $order->order_number }}</td>
                </tr>
                <tr>
                  <td style="padding: 8px 0; border-bottom: 1px solid #f0ebe4; color: #8a7a60; font-size: 14px;">Alasan</td>
                  <td style="padding: 8px 0; border-bottom: 1px solid #f0ebe4; color: #1a1209; font-weight: 700; font-size: 14px; text-align: right;">{{ $returnRequest->reason }}</td>
                </tr>
                <tr>
                  <td style="padding: 8px 0; color: #8a7a60; font-size: 14px; vertical-align: top;">Keterangan</td>
                  <td style="padding: 8px 0; color: #1a1209; font-size: 14px; text-align: right;">{{ $returnRequest->description ?? '-' }}</td>
                </tr>
              </table>

              <p style="color: #8a7a60; font-size: 13px; margin: 24px 0 0; text-align: center;">
                Harap segera tindaklanjuti melalui panel admin Filament.
              </p>
            </td>
          </tr>
          <tr>
            <td style="padding: 24px 40px; background: #f5f2ee; text-align: center; border-top: 1px solid rgba(193,154,81,0.15);">
              <p style="margin: 0; font-size: 11px; color: #8a7a60;">© {{ date('Y') }} Optik Medio Admin Panel</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
