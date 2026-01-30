<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Confirmation de commande - {{ $order->order_number }}</title>
    </head>
    <body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f5f5;">
        <table role="presentation" style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 40px 20px;">
                    <table role="presentation" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                        <!-- Header -->
                        <tr>
                            <td style="background-color: #0a0708; padding: 30px 40px; text-align: center;">
                                <h1 style="margin: 0; color: #D4AF37; font-size: 24px; font-weight: 300; letter-spacing: 2px;">
                                    OCÉANE TORRES PHOTOGRAPHIE
                                </h1>
                            </td>
                        </tr>

                        <!-- Content -->
                        <tr>
                            <td style="padding: 40px;">
                                <h2 style="margin: 0 0 20px 0; color: #333333; font-size: 22px; font-weight: 400;">
                                    Merci pour votre commande !
                                </h2>

                                <p style="margin: 0 0 20px 0; color: #555555; font-size: 16px; line-height: 1.7;">
                                    Votre paiement a bien été reçu. Voici le récapitulatif de votre commande.
                                </p>

                                <!-- Order Info Box -->
                                <div style="background-color: #f9f9f9; border: 2px dashed #D4AF37; border-radius: 8px; padding: 25px; margin: 25px 0; text-align: center;">
                                    <p style="margin: 0 0 10px 0; color: #666666; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">
                                        Numéro de commande
                                    </p>
                                    <p style="margin: 0; font-size: 24px; font-weight: bold; letter-spacing: 4px; color: #0a0708; font-family: monospace;">
                                        {{ $order->order_number }}
                                    </p>
                                </div>

                                <!-- Order Items -->
                                <div style="margin: 25px 0;">
                                    <h3 style="margin: 0 0 15px 0; color: #333333; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">
                                        Photos commandées
                                    </h3>
                                    <table role="presentation" style="width: 100%; border-collapse: collapse;">
                                        @foreach($order->items as $item)
                                        <tr>
                                            <td style="padding: 10px 0; border-bottom: 1px solid #eee;">
                                                <span style="color: #555555; font-size: 14px;">
                                                    {{ $item->photo_title ?? 'Photo' }}
                                                    @if($item->gallery_title)
                                                    <br><small style="color: #888;">{{ $item->gallery_title }}</small>
                                                    @endif
                                                </span>
                                            </td>
                                            <td style="padding: 10px 0; border-bottom: 1px solid #eee; text-align: right;">
                                                <span style="color: #333333; font-size: 14px; font-weight: 600;">
                                                    {{ number_format($item->price, 2, ',', ' ') }} €
                                                </span>
                                            </td>
                                        </tr>
                                        @endforeach
                                        <tr>
                                            <td style="padding: 15px 0;">
                                                <span style="color: #333333; font-size: 16px; font-weight: 600;">
                                                    Total
                                                </span>
                                            </td>
                                            <td style="padding: 15px 0; text-align: right;">
                                                <span style="color: #D4AF37; font-size: 18px; font-weight: 700;">
                                                    {{ number_format($order->total, 2, ',', ' ') }} €
                                                </span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>

                                <!-- CTA Button -->
                                <table role="presentation" style="width: 100%; margin: 30px 0;">
                                    <tr>
                                        <td style="text-align: center;">
                                            <a href="{{ $downloadUrl }}" style="display: inline-block; background-color: #D4AF37; color: #0a0708; padding: 16px 40px; text-decoration: none; border-radius: 4px; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">
                                                Télécharger mes photos
                                            </a>
                                        </td>
                                    </tr>
                                </table>

                                <p style="margin: 25px 0 0 0; color: #888888; font-size: 13px; text-align: center; line-height: 1.6;">
                                    Ce lien de téléchargement est valable pendant 7 jours.<br>
                                    Vous pouvez également accéder à vos photos depuis votre espace client.
                                </p>

                                <!-- Signature -->
                                <div style="margin-top: 40px; padding-top: 25px; border-top: 1px solid #e0e0e0;">
                                    <p style="margin: 0; color: #333333; font-size: 16px;">
                                        Merci pour votre confiance,<br>
                                        <strong style="color: #D4AF37;">Océane</strong>
                                    </p>
                                </div>
                            </td>
                        </tr>

                        <!-- Footer -->
                        <tr>
                            <td style="background-color: #0a0708; padding: 25px 40px; text-align: center;">
                                <p style="margin: 0 0 10px 0; color: #999999; font-size: 12px;">
                                    Océane Torres Photographie<br>
                                    Auvergne-Rhône-Alpes
                                </p>
                                <p style="margin: 0; color: #666666; font-size: 11px;">
                                    Conservez cet email, il contient votre lien de téléchargement.
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
