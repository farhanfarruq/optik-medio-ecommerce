<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Pengajuan Return - Optik Medio</title>
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
                                Halo, {{ $returnRequest->user->name }}!
                            </p>
                            <p style="margin: 0 0 28px; color: #8a7a60; font-size: 14px; line-height: 1.6;">
                                Ada pembaruan mengenai pengajuan return Anda untuk pesanan
                                <strong style="color: #1a1209;">{{ $returnRequest->order->order_number }}</strong>.
                            </p>

                            <!-- Status Badge -->
                            @if($returnRequest->status === 'approved')
                                <div style="background: #f0fdf4; border: 1px solid #86efac; padding: 16px 20px; margin: 0 0 24px; text-align: center;">
                                    <p style="margin: 0 0 4px; color: #15803d; font-size: 11px; font-weight: 700; letter-spacing: 3px; text-transform: uppercase;">
                                        Status Pengajuan
                                    </p>
                                    <p style="margin: 0; color: #15803d; font-size: 20px; font-weight: 800;">
                                        ✅ Disetujui
                                    </p>
                                </div>
                            @else
                                <div style="background: #fef2f2; border: 1px solid #fca5a5; padding: 16px 20px; margin: 0 0 24px; text-align: center;">
                                    <p style="margin: 0 0 4px; color: #dc2626; font-size: 11px; font-weight: 700; letter-spacing: 3px; text-transform: uppercase;">
                                        Status Pengajuan
                                    </p>
                                    <p style="margin: 0; color: #dc2626; font-size: 20px; font-weight: 800;">
                                        ❌ Ditolak
                                    </p>
                                </div>
                            @endif

                            <!-- Return Details -->
                            <div style="background: #faf9f7; border: 1px solid #e8e0d0; padding: 16px 20px; margin: 0 0 24px;">
                                <p style="margin: 0 0 10px; color: #8a7a60; font-size: 10px; font-weight: 700; letter-spacing: 3px; text-transform: uppercase;">
                                    Detail Pengajuan
                                </p>
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                    <tr>
                                        <td style="color: #8a7a60; font-size: 12px; padding-bottom: 6px; width: 40%;">No. Pesanan</td>
                                        <td style="color: #1a1209; font-size: 12px; font-weight: 600; padding-bottom: 6px;">{{ $returnRequest->order->order_number }}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #8a7a60; font-size: 12px; padding-bottom: 6px; vertical-align: top;">Alasan Return</td>
                                        <td style="color: #1a1209; font-size: 12px; font-weight: 600; padding-bottom: 6px;">{{ $returnRequest->reason }}</td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Admin Notes -->
                            @if(!empty($returnRequest->admin_notes))
                                <div style="border-left: 3px solid #c19a51; padding: 12px 16px; margin: 0 0 24px; background: #fffdf7;">
                                    <p style="margin: 0 0 6px; color: #8a7a60; font-size: 10px; font-weight: 700; letter-spacing: 3px; text-transform: uppercase;">
                                        Catatan dari Tim Kami
                                    </p>
                                    <p style="margin: 0; color: #1a1209; font-size: 13px; line-height: 1.6;">
                                        {{ $returnRequest->admin_notes }}
                                    </p>
                                </div>
                            @endif

                            <!-- Status Message -->
                            @if($returnRequest->status === 'approved')
                                <p style="margin: 0 0 28px; color: #8a7a60; font-size: 14px; line-height: 1.6;">
                                    Pengajuan return Anda telah disetujui. Tim kami akan menghubungi Anda untuk proses selanjutnya.
                                </p>
                            @else
                                <p style="margin: 0 0 28px; color: #8a7a60; font-size: 14px; line-height: 1.6;">
                                    Mohon maaf, pengajuan return Anda tidak dapat kami proses saat ini.
                                </p>
                            @endif

                            <!-- CTA Button -->
                            <div style="text-align: center;">
                                <a href="{{ config('app.frontend_url') }}/orders/{{ $returnRequest->order_id }}"
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
