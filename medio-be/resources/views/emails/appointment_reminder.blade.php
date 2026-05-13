<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pengingat Appointment</title>
</head>
<body style="margin:0; padding:0; background:#f5f2ee; font-family: 'Segoe UI', Arial, sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f5f2ee; padding: 40px 20px;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0" border="0" style="background: white; border-top: 4px solid #c19a51; max-width: 600px; width: 100%;">
          <tr>
            <td style="padding: 40px 40px 32px; text-align: center; background: #1a1209;">
              <p style="margin:0; font-size: 12px; font-weight: 800; letter-spacing: 0.3em; color: #c19a51; text-transform: uppercase;">Optik Medio</p>
              <h1 style="margin: 12px 0 0; font-size: 22px; font-weight: 900; color: white;">📅 Pengingat Appointment</h1>
            </td>
          </tr>
          <tr>
            <td style="padding: 40px;">
              <p style="margin: 0 0 16px; color: #5a5248; font-size: 15px;">
                Halo <strong>{{ $appointment->customer_name }}</strong>,
              </p>
              <p style="margin: 0 0 24px; color: #5a5248; font-size: 15px; line-height: 1.6;">
                Ini adalah pengingat bahwa Anda memiliki appointment di Optik Medio <strong>besok</strong>.
              </p>

              <!-- Detail Appointment -->
              <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background: #fffdf7; border: 1px solid rgba(193,154,81,0.3); border-radius: 8px; margin-bottom: 24px;">
                <tr>
                  <td style="padding: 20px 24px;">
                    <table width="100%" cellpadding="0" cellspacing="0" border="0">
                      <tr>
                        <td style="padding: 6px 0; font-size: 13px; color: #8a7a60; width: 40%;">No. Appointment</td>
                        <td style="padding: 6px 0; font-size: 13px; font-weight: 700; color: #1a1209;">{{ $appointment->appointment_number }}</td>
                      </tr>
                      <tr>
                        <td style="padding: 6px 0; font-size: 13px; color: #8a7a60;">Cabang</td>
                        <td style="padding: 6px 0; font-size: 13px; font-weight: 700; color: #1a1209;">{{ $appointment->branch->name }}</td>
                      </tr>
                      <tr>
                        <td style="padding: 6px 0; font-size: 13px; color: #8a7a60;">Alamat</td>
                        <td style="padding: 6px 0; font-size: 13px; color: #5a5248;">{{ $appointment->branch->address }}, {{ $appointment->branch->city }}</td>
                      </tr>
                      <tr>
                        <td style="padding: 6px 0; font-size: 13px; color: #8a7a60;">Tanggal</td>
                        <td style="padding: 6px 0; font-size: 13px; font-weight: 700; color: #1a1209;">
                          {{ \Carbon\Carbon::parse($appointment->appointment_date)->translatedFormat('l, d F Y') }}
                        </td>
                      </tr>
                      <tr>
                        <td style="padding: 6px 0; font-size: 13px; color: #8a7a60;">Waktu</td>
                        <td style="padding: 6px 0; font-size: 13px; font-weight: 700; color: #1a1209;">
                          {{ substr($appointment->appointment_time, 0, 5) }} WIB
                        </td>
                      </tr>
                      <tr>
                        <td style="padding: 6px 0; font-size: 13px; color: #8a7a60;">Layanan</td>
                        <td style="padding: 6px 0; font-size: 13px; font-weight: 700; color: #c19a51;">{{ $appointment->service_label }}</td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>

              @if($appointment->branch->phone)
              <p style="margin: 0 0 24px; color: #5a5248; font-size: 14px;">
                📞 Hubungi cabang: <strong>{{ $appointment->branch->phone }}</strong>
              </p>
              @endif

              @if($appointment->branch->maps_url)
              <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 24px;">
                <tr>
                  <td align="center">
                    <a href="{{ $appointment->branch->maps_url }}"
                       style="display: inline-block; padding: 12px 28px; background: #1a1209; color: white; text-decoration: none; font-size: 12px; font-weight: 800; letter-spacing: 0.15em; text-transform: uppercase;">
                      📍 Lihat Lokasi di Maps
                    </a>
                  </td>
                </tr>
              </table>
              @endif

              <p style="margin: 0; color: #8a7a60; font-size: 13px; text-align: center;">
                Jika perlu membatalkan, silakan hubungi kami atau batalkan melalui aplikasi.
              </p>
            </td>
          </tr>
          <tr>
            <td style="padding: 24px 40px; background: #f5f2ee; text-align: center; border-top: 1px solid #e5e0d8;">
              <p style="margin: 0; font-size: 11px; color: #8a7a60;">© {{ date('Y') }} Optik Medio. Semua hak dilindungi.</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
