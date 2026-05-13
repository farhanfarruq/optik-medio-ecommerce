<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesan dari Optik Medio</title>
</head>
<body style="margin: 0; padding: 0; background-color: #F5F2EE; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #F5F2EE;">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width: 480px; margin: 0 auto; background: white; box-shadow: 0 4px 24px rgba(0,0,0,0.08);">

                    <tr>
                        <td style="background: linear-gradient(135deg, #1a1209 0%, #3d2c0e 100%); padding: 32px 40px; text-align: center;">
                            <h1 style="margin: 0; color: #c19a51; font-size: 20px; font-weight: 800; letter-spacing: 2px;">
                                OPTIK MEDIO
                            </h1>
                            <p style="margin: 6px 0 0; color: rgba(255,255,255,0.6); font-size: 11px; letter-spacing: 3px; text-transform: uppercase;">
                                Customer Care
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 8px; color: #8a7a60; font-size: 11px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase;">
                                Pesan untuk pesanan
                            </p>
                            <h2 style="margin: 0 0 24px; color: #1a1209; font-size: 24px; line-height: 1.3;">
                                #{{ $order->order_number }}
                            </h2>

                            <div style="background: #faf9f7; border: 1px solid #e8e0d0; padding: 16px 20px; margin: 0 0 24px;">
                                <p style="margin: 0 0 10px; color: #8a7a60; font-size: 10px; font-weight: 700; letter-spacing: 3px; text-transform: uppercase;">
                                    Detail Pesanan
                                </p>
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                    <tr>
                                        <td style="color: #8a7a60; font-size: 12px; padding-bottom: 6px; width: 40%;">Pelanggan</td>
                                        <td style="color: #1a1209; font-size: 12px; font-weight: 600; padding-bottom: 6px;">{{ $order->user->name }}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #8a7a60; font-size: 12px; padding-bottom: 6px;">Status</td>
                                        <td style="color: #1a1209; font-size: 12px; font-weight: 600; padding-bottom: 6px;">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #8a7a60; font-size: 12px;">Total</td>
                                        <td style="color: #1a1209; font-size: 12px; font-weight: 600;">Rp {{ number_format((float) $order->total_price, 0, ',', '.') }}</td>
                                    </tr>
                                </table>
                            </div>

                            <div style="border-left: 4px solid #c19a51; background: #fffdf7; padding: 18px 20px; margin: 0 0 28px;">
                                <p style="margin: 0; color: #1a1209; font-size: 14px; line-height: 1.7; white-space: pre-line;">{{ $customMessage }}</p>
                            </div>

                            <div style="text-align: center;">
                                <a href="{{ config('app.frontend_url') }}/orders/{{ $order->id }}"
                                   style="display: inline-block; background: linear-gradient(135deg, #1a1209 0%, #3d2c0e 100%); color: #c19a51; text-decoration: none; font-size: 12px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; padding: 14px 32px;">
                                    Lihat Detail Pesanan
                                </a>
                            </div>
                        </td>
                    </tr>

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
