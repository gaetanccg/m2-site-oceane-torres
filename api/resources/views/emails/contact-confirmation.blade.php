<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Confirmation de votre message</title>
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

                        <!-- Content -->
                        <tr>
                            <td style="padding: 40px;">
                                <h2 style="margin: 0 0 20px 0; color: #333333; font-size: 22px; font-weight: 400;">
                                    Bonjour {{ $senderName }},
                                </h2>

                                <p style="margin: 0 0 20px 0; color: #555555; font-size: 16px; line-height: 1.7;">
                                    Merci pour votre message ! Je l'ai bien reçu et je vous répondrai dans les plus brefs délais, généralement sous 24 heures.
                                </p>

                                <!-- Message Recap -->
                                <div style="background-color: #f9f9f9; border-radius: 6px; padding: 25px; margin: 25px 0;">
                                    <h3 style="margin: 0 0 15px 0; color: #333333; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">
                                        Recapitulatif de votre message
                                    </h3>

                                    <table role="presentation" style="width: 100%;">
                                        <tr>
                                            <td style="padding-bottom: 15px;">
                                                <strong style="color: #666666; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Sujet</strong><br>
                                                <span style="color: #333333; font-size: 15px;">{{ $messageSubject }}</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong style="color: #666666; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Message</strong><br>
                                                <div style="color: #333333; font-size: 14px; line-height: 1.6; margin-top: 8px; white-space: pre-wrap;">{{ $messageContent }}</div>
                                            </td>
                                        </tr>
                                    </table>
                                </div>

                                <p style="margin: 25px 0 0 0; color: #555555; font-size: 16px; line-height: 1.7;">
                                    En attendant, n'hesitez pas a decouvrir mon travail sur Instagram ou a consulter mes prestations sur mon site.
                                </p>

                                <!-- Social Links -->
                                <table role="presentation" style="width: 100%; margin-top: 30px;">
                                    <tr>
                                        <td style="text-align: center;">
                                            <a href="https://instagram.com/oceanetorresphotographie" style="display: inline-block; background-color: #0a0708; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 4px; font-size: 13px; font-weight: 500; text-transform: uppercase; letter-spacing: 1px; margin: 0 8px;">
                                                Instagram
                                            </a>
                                            <a href="https://oceanetorresphotographie.fr/prestations" style="display: inline-block; background-color: #D4AF37; color: #0a0708; padding: 14px 28px; text-decoration: none; border-radius: 4px; font-size: 13px; font-weight: 500; text-transform: uppercase; letter-spacing: 1px; margin: 0 8px;">
                                                Prestations
                                            </a>
                                        </td>
                                    </tr>
                                </table>

                                <!-- Signature -->
                                <div style="margin-top: 40px; padding-top: 25px; border-top: 1px solid #e0e0e0;">
                                    <p style="margin: 0; color: #333333; font-size: 16px;">
                                        A tres bientot,<br>
                                        <strong style="color: #D4AF37;">Oceane</strong>
                                    </p>
                                </div>
                            </td>
                        </tr>

                        <!-- Footer -->
                        <tr>
                            <td style="background-color: #0a0708; padding: 25px 40px; text-align: center;">
                                <p style="margin: 0 0 10px 0; color: #999999; font-size: 12px;">
                                    Oceane Torres Photographie<br>
                                    Auvergne-Rhone-Alpes
                                </p>
                                <p style="margin: 0; color: #666666; font-size: 11px;">
                                    Cet email est une confirmation automatique, merci de ne pas y repondre directement.
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
