<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Nouvelle commande avec tirage - {{ $order->order_number }}</title>
    </head>
    <body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f5f5;">
        <table role="presentation" style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 40px 20px;">
                    <table role="presentation" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                        <!-- Header -->
                        <tr>
                            <td style="background-color: #D4AF37; padding: 30px 40px; text-align: center;">
                                <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 600;">
                                    🖨️ Nouvelle commande avec tirage
                                </h1>
                            </td>
                        </tr>

                        <!-- Content -->
                        <tr>
                            <td style="padding: 40px;">
                                <p style="margin: 0 0 20px 0; color: #555555; font-size: 16px; line-height: 1.7;">
                                    Une nouvelle commande contenant des tirages papier vient d'être passée.
                                </p>

                                <!-- Order Info Box -->
                                <div style="background-color: #f9f9f9; border-left: 4px solid #D4AF37; border-radius: 4px; padding: 20px; margin: 25px 0;">
                                    <p style="margin: 0 0 10px 0; color: #666666; font-size: 14px;">
                                        <strong>Commande :</strong> {{ $order->order_number }}
                                    </p>
                                    <p style="margin: 0 0 10px 0; color: #666666; font-size: 14px;">
                                        <strong>Client :</strong> {{ $order->customer_name ?: 'Non renseigné' }}
                                    </p>
                                    <p style="margin: 0 0 10px 0; color: #666666; font-size: 14px;">
                                        <strong>Email :</strong> {{ $order->customer_email }}
                                    </p>
                                    <p style="margin: 0 0 6px 0; color: #666666; font-size: 14px;">
                                        Sous-total : {{ number_format($order->subtotal, 2, ',', ' ') }} €
                                    </p>
                                    @if($order->shipping_fee > 0)
                                        <p style="margin: 0 0 10px 0; color: #666666; font-size: 14px;">
                                            Frais de port : +{{ number_format($order->shipping_fee, 2, ',', ' ') }} €
                                        </p>
                                    @endif
                                    <p style="margin: 0; color: #D4AF37; font-size: 18px; font-weight: 600;">
                                        Total : {{ number_format($order->total, 2, ',', ' ') }} €
                                    </p>
                                </div>

                                @if($order->shipping_address_line1)
                                    <!-- Shipping Address Box -->
                                    <div style="background-color: #fff9e6; border: 2px solid #D4AF37; border-radius: 4px; padding: 20px; margin: 25px 0;">
                                        <h3 style="margin: 0 0 12px 0; color: #8a6d1b; font-size: 15px; text-transform: uppercase; letter-spacing: 1px;">
                                            📦 Adresse d'expédition
                                        </h3>
                                        <p style="margin: 0 0 4px 0; color: #333; font-size: 15px; line-height: 1.6;">
                                            <strong>{{ $order->customer_name ?: 'Client' }}</strong>
                                        </p>
                                        <p style="margin: 0; color: #333; font-size: 14px; line-height: 1.7;">
                                            {{ $order->shipping_address_line1 }}<br>
                                            @if($order->shipping_address_line2)
                                                {{ $order->shipping_address_line2 }}<br>
                                            @endif
                                            {{ $order->shipping_postal_code }} {{ $order->shipping_city }}<br>
                                            <span style="color: #888;">France</span>
                                        </p>
                                        @if($order->shipping_phone)
                                            <p style="margin: 12px 0 0 0; color: #555; font-size: 14px;">
                                                <strong>Téléphone :</strong>
                                                <a href="tel:{{ $order->shipping_phone }}" style="color: #8a6d1b; text-decoration: none;">{{ $order->shipping_phone }}</a>
                                            </p>
                                        @endif
                                    </div>
                                @endif

                                <!-- Print Items -->
                                <h3 style="margin: 25px 0 15px 0; color: #333333; font-size: 16px; text-transform: uppercase; letter-spacing: 1px;">
                                    Tirages à imprimer
                                </h3>
                                <table role="presentation" style="width: 100%; border-collapse: collapse;">
                                    @foreach($order->items as $item)
                                        @if($item->isPrint())
                                        <tr>
                                            <td style="padding: 12px; border: 1px solid #eee; background-color: #fff9e6;">
                                                <strong style="color: #333;">{{ $item->photo_title ?? 'Photo' }}</strong>
                                                @if($item->gallery_title)
                                                <br><small style="color: #888;">{{ $item->gallery_title }}</small>
                                                @endif
                                                <br>
                                                <span style="display: inline-block; margin-top: 5px; padding: 3px 8px; background-color: #D4AF37; color: white; border-radius: 4px; font-size: 12px;">
                                                    {{ $item->getProductTypeLabel() }}
                                                </span>
                                            </td>
                                            <td style="padding: 12px; border: 1px solid #eee; text-align: right; background-color: #fff9e6;">
                                                <span style="color: #333333; font-size: 14px; font-weight: 600;">
                                                    {{ number_format($item->price, 2, ',', ' ') }} €
                                                </span>
                                            </td>
                                        </tr>
                                        @endif
                                    @endforeach
                                </table>

                                @if($order->digitalItems()->count() > 0)
                                <!-- Digital Items (info only) -->
                                <h3 style="margin: 25px 0 15px 0; color: #333333; font-size: 14px;">
                                    Fichiers numériques (téléchargeables automatiquement)
                                </h3>
                                <ul style="margin: 0; padding-left: 20px; color: #666;">
                                    @foreach($order->digitalItems() as $item)
                                    <li style="margin-bottom: 5px;">{{ $item->photo_title ?? 'Photo' }}</li>
                                    @endforeach
                                </ul>
                                @endif

                                <!-- Action Button -->
                                <table role="presentation" style="width: 100%; margin: 30px 0;">
                                    <tr>
                                        <td style="text-align: center;">
                                            <a href="{{ config('app.frontend_url') }}/admin/orders" style="display: inline-block; background-color: #0a0708; color: #ffffff; padding: 16px 40px; text-decoration: none; border-radius: 4px; font-size: 14px; font-weight: 600;">
                                                Voir la commande dans l'admin
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <!-- Footer -->
                        <tr>
                            <td style="background-color: #f5f5f5; padding: 20px 40px; text-align: center;">
                                <p style="margin: 0; color: #999999; font-size: 12px;">
                                    Notification automatique - Océane Torres Photographie
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
