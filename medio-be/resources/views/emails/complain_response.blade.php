<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Komplain - Optik Medio</title>
</head>
<body style="margin: 0; padding: 0; background-color: #F5F2EE; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #F5F2EE;">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width: 520px; margin: 0 auto; background: white; box-shadow: 0 4px 24px rgba(0,0,0,0.08);">

                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #1a1209 0%, #3d2c0e 100%); padding: 32px 40px; text-align: center;">
                            <h1 style="margin: 0; color: #c19a51; font-size: 20px; font-weight: 800; letter-spacing: 2px;">OPTIK MEDIO</h1>
                            <p style="margin: 6px 0 0; color: rgba(255,255,255,0.6); font-size: 11px; letter-spacing: 3px; text-transform: uppercase;">Premium Eyewear</p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 6px; color: #1a1209; font-size: 22px; font-weight: 700;">
                                Halo, {{ $complain->user->name }}!
                            </p>
                            <p style="margin: 0 0 28px; color: #8a7a60; font-size: 14px; line-height: 1.6;">
                                Ada pembaruan mengenai komplain Anda di Optik Medio.
                            </p>

                            <!-- Complaint Info -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: #faf9f7; border: 1px solid #e8e2d8; margin-bottom: 24px;">
                                <tr>
                                    <td style="padding: 20px 24px;">
                                        <p style="margin: 0 0 4px; color: #8a7a60; font-size: 10px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase;">Subjek Komplain</p>
                                        <p style="margin: 0 0 16px; color: #1a1209; font-size: 15px; font-weight: 600;">{{ $complain->subject }}</p>

                                        @if ($complain->order)
                                        <p style="margin: 0 0 4px; color: #8a7a60; font-size: 10px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase;">Nomor Pesanan</p>
                                        <p style="margin: 0 0 16px; color: #1a1209; font-size: 14px;">{{ $complain->order->order_number }}</p>
                                        @endif

                                        <p style="margin: 0 0 4px; color: #8a7a60; font-size: 10px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase;">Status</p>
                                        @php
                                            $statusColor = match($complain->status) {
                                                'in_progress' => '#2563eb',
                                                'resolved'    => '#16a34a',
                                                'rejected'    => '#dc2626',
                                                default       => '#d97706',
                                            };
                                            $statusLabel = match($complain->status) {
                                                'open'        => 'Menunggu Tindakan',
                                                'in_progress' => 'Sedang Diproses',
                                                'resolved'    => 'Telah Diselesaikan',
                                                'rejected'    => 'Tidak Dapat Diproses',
                                                default       => $complain->status,
                                            };
                                        @endphp
                                        <p style="margin: 0; display: inline-block; color: {{ $statusColor }}; font-size: 13px; font-weight: 700;">
                                            ● {{ $statusLabel }}
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            @if ($complain->admin_notes)
                            <!-- Admin Response -->
                            <div style="border-left: 3px solid #c19a51; padding: 16px 20px; background: #fffdf7; margin-bottom: 24px;">
                                <p style="margin: 0 0 8px; color: #8a7a60; font-size: 10px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase;">Respons dari Tim Optik Medio</p>
                                <p style="margin: 0; color: #1a1209; font-size: 14px; line-height: 1.7; white-space: pre-line;">{{ $complain->admin_notes }}</p>
                            </div>
                            @endif

                            <p style="margin: 0 0 20px; color: #8a7a60; font-size: 13px; line-height: 1.6;">
                                Anda dapat melihat detail lengkap komplain ini melalui halaman pesanan di website kami.
                            </p>

                            <a href="{{ config('app.frontend_url') }}/orders/{{ $complain->order_id }}"
                               style="display: inline-block; background: linear-gradient(135deg, #1a1209 0%, #3d2c0e 100%); color: white; text-decoration: none; padding: 12px 28px; font-size: 12px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase;">
                                Lihat Detail Pesanan →
                            </a>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 24px 40px; border-top: 1px solid #f0ece4; text-align: center;">
                            <p style="margin: 0 0 4px; color: #b0a590; font-size: 12px;">Ada pertanyaan? Balas email ini atau hubungi kami.</p>
                            <p style="margin: 0; color: #c19a51; font-size: 10px; font-weight: 700; letter-spacing: 2px;">&copy; {{ date('Y') }} OPTIK MEDIO</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
