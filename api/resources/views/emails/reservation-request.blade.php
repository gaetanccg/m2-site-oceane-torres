<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvelle demande de reservation</title>
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
                                OCEANE TORRES PHOTOGRAPHIE
                            </h1>
                        </td>
                    </tr>

                    <!-- Subject Badge -->
                    <tr>
                        <td style="padding: 30px 40px 0 40px;">
                            <table role="presentation" style="width: 100%;">
                                <tr>
                                    <td>
                                        <span style="display: inline-block; background-color: #D4AF37; color: #0a0708; padding: 8px 16px; border-radius: 4px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">
                                            Nouvelle demande
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 30px 40px;">
                            <h2 style="margin: 0 0 20px 0; color: #333333; font-size: 20px; font-weight: 400;">
                                Nouvelle demande de reservation
                            </h2>

                            <!-- Prestation Info -->
                            <div style="background-color: #0a0708; border-radius: 6px; padding: 20px; margin-bottom: 25px;">
                                <strong style="color: #D4AF37; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Prestation demandee</strong>
                                <p style="margin: 8px 0 0 0; color: #ffffff; font-size: 18px; font-weight: 500;">{{ $prestation->title }}</p>
                                @if($prestation->price)
                                <p style="margin: 5px 0 0 0; color: #D4AF37; font-size: 14px;">{{ number_format($prestation->price, 0, ',', ' ') }} €</p>
                                @endif
                            </div>

                            <!-- Client Info -->
                            <table role="presentation" style="width: 100%; background-color: #f9f9f9; border-radius: 6px; margin-bottom: 25px;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <table role="presentation" style="width: 100%;">
                                            <tr>
                                                <td style="padding-bottom: 12px;">
                                                    <strong style="color: #666666; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Nom</strong><br>
                                                    <span style="color: #333333; font-size: 16px;">{{ $reservation->guest_name }}</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding-bottom: 12px;">
                                                    <strong style="color: #666666; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Email</strong><br>
                                                    <a href="mailto:{{ $reservation->guest_email }}" style="color: #D4AF37; font-size: 16px; text-decoration: none;">{{ $reservation->guest_email }}</a>
                                                </td>
                                            </tr>
                                            @if($reservation->guest_phone)
                                            <tr>
                                                <td>
                                                    <strong style="color: #666666; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Telephone</strong><br>
                                                    <a href="tel:{{ $reservation->guest_phone }}" style="color: #D4AF37; font-size: 16px; text-decoration: none;">{{ $reservation->guest_phone }}</a>
                                                </td>
                                            </tr>
                                            @endif
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Date Preferences -->
                            <div style="margin-bottom: 25px;">
                                <strong style="color: #666666; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 10px;">Disponibilites</strong>
                                <div style="background-color: #ffffff; border: 1px solid #e0e0e0; border-left: 4px solid #D4AF37; border-radius: 4px; padding: 20px;">
                                    <p style="margin: 0; color: #333333; font-size: 15px; line-height: 1.7; white-space: pre-wrap;">{{ $reservation->date_preferences }}</p>
                                </div>
                            </div>

                            <!-- Message -->
                            @if($reservation->message)
                            <div style="margin-bottom: 25px;">
                                <strong style="color: #666666; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 10px;">Message</strong>
                                <div style="background-color: #ffffff; border: 1px solid #e0e0e0; border-left: 4px solid #D4AF37; border-radius: 4px; padding: 20px;">
                                    <p style="margin: 0; color: #333333; font-size: 15px; line-height: 1.7; white-space: pre-wrap;">{{ $reservation->message }}</p>
                                </div>
                            </div>
                            @endif

                            <!-- Action Buttons -->
                            <table role="presentation" style="width: 100%;">
                                <tr>
                                    <td style="text-align: center; padding-top: 10px;">
                                        <a href="mailto:{{ $reservation->guest_email }}?subject=Votre demande de reservation - {{ $prestation->title }}" style="display: inline-block; background-color: #0a0708; color: #ffffff; padding: 14px 32px; text-decoration: none; border-radius: 4px; font-size: 14px; font-weight: 500; text-transform: uppercase; letter-spacing: 1px; margin-right: 10px;">
                                            Repondre
                                        </a>
                                        <a href="https://oceanetorresphotographie.fr/admin/reservations" style="display: inline-block; background-color: #D4AF37; color: #0a0708; padding: 14px 32px; text-decoration: none; border-radius: 4px; font-size: 14px; font-weight: 500; text-transform: uppercase; letter-spacing: 1px;">
                                            Voir dans l'admin
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f5f5f5; padding: 25px 40px; text-align: center; border-top: 1px solid #e0e0e0;">
                            <p style="margin: 0; color: #999999; font-size: 12px;">
                                Cette demande a ete envoyee depuis le formulaire de reservation du site<br>
                                <a href="https://oceanetorresphotographie.fr" style="color: #D4AF37; text-decoration: none;">oceanetorresphotographie.fr</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
