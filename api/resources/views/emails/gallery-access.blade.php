<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Votre galerie photo est prete</title>
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
                                    Bonjour {{ $recipientName }},
                                </h2>

                                <p style="margin: 0 0 20px 0; color: #555555; font-size: 16px; line-height: 1.7;">
                                    Votre galerie photo <strong style="color: #D4AF37;">"{{ $gallery->title }}"</strong> est maintenant disponible !
                                </p>

                                <p style="margin: 0 0 25px 0; color: #555555; font-size: 16px; line-height: 1.7;">
                                    Vous pouvez y acceder a tout moment en utilisant le code ci-dessous.
                                </p>

                                <!-- Code Box -->
                                <div style="background-color: #f9f9f9; border: 2px dashed #D4AF37; border-radius: 8px; padding: 25px; margin: 25px 0; text-align: center;">
                                    <p style="margin: 0 0 10px 0; color: #666666; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">
                                        Votre code d'acces
                                    </p>
                                    <p style="margin: 0; font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #0a0708; font-family: monospace;">
                                        {{ $shareCode }}
                                    </p>
                                </div>

                                <!-- CTA Button -->
                                <table role="presentation" style="width: 100%; margin: 30px 0;">
                                    <tr>
                                        <td style="text-align: center;">
                                            <a href="{{ $galleryUrl }}" style="display: inline-block; background-color: #D4AF37; color: #0a0708; padding: 16px 40px; text-decoration: none; border-radius: 4px; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">
                                                Acceder a ma galerie
                                            </a>
                                        </td>
                                    </tr>
                                </table>

                                <!-- Instructions -->
                                <div style="background-color: #fafafa; border-radius: 6px; padding: 20px; margin: 25px 0;">
                                    <h3 style="margin: 0 0 15px 0; color: #333333; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">
                                        Comment acceder a votre galerie ?
                                    </h3>
                                    @if ($isDirectLink)
                                        <p style="margin: 0 0 10px 0; color: #555555; font-size: 14px; line-height: 1.8;">
                                            Cliquez simplement sur le bouton ci-dessus pour acceder directement a la galerie.
                                        </p>
                                        <p style="margin: 0; color: #888888; font-size: 13px; line-height: 1.6;">
                                            En cas de probleme avec le lien, vous pouvez aussi vous rendre sur le site et entrer votre code <strong>{{ $shareCode }}</strong>.
                                        </p>
                                    @else
                                        <ol style="margin: 0; padding-left: 20px; color: #555555; font-size: 14px; line-height: 1.8;">
                                            <li>Cliquez sur le bouton ci-dessus ou rendez-vous sur le site</li>
                                            <li>Entrez votre code d'acces : <strong>{{ $shareCode }}</strong></li>
                                            <li>Vous pourrez alors consultez vos photos !</li>
                                        </ol>
                                    @endif
                                </div>

                                <p style="margin: 25px 0 0 0; color: #555555; font-size: 16px; line-height: 1.7;">
                                    N'hesitez pas a me contacter si vous avez des questions concernant votre galerie.
                                </p>

                                <!-- Signature -->
                                <div style="margin-top: 40px; padding-top: 25px; border-top: 1px solid #e0e0e0;">
                                    <p style="margin: 0; color: #333333; font-size: 16px;">
                                        A tres bientot,<br>
                                        <strong style="color: #D4AF37;">Océane Torres</strong>
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
                                    Conservez cet email, il contient votre code d'acces personnel.
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
