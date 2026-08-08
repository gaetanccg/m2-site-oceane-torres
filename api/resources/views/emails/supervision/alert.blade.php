<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Alerte de supervision</title>
    </head>
    <body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f5f5;">
        <table role="presentation" style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 40px 20px;">
                    <table role="presentation" style="max-width: 640px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                        <tr>
                            <td style="background-color: {{ $snapshot->status->value === 'down' ? '#c62828' : '#ed6c02' }}; padding: 26px 40px;">
                                <h1 style="margin: 0; color: #ffffff; font-size: 21px; font-weight: 600;">
                                    ⚠️ Alerte de supervision
                                </h1>
                                <p style="margin: 6px 0 0 0; color: #ffffff; font-size: 13px; opacity: 0.9;">
                                    Statut global : {{ $snapshot->status->value }} · {{ $environment }} · v{{ $version }}
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding: 32px 40px 8px 40px;">
                                <p style="margin: 0 0 22px 0; color: #555555; font-size: 15px; line-height: 1.6;">
                                    {{ count($alerts) > 1 ? count($alerts).' anomalies ont été détectées' : 'Une anomalie a été détectée' }}
                                    sur l'API le {{ now()->timezone('Europe/Paris')->format('d/m/Y à H:i') }}.
                                </p>

                                @foreach ($alerts as $alert)
                                    <div style="background-color: #fff8e1; border-left: 4px solid #ed6c02; border-radius: 4px; padding: 16px 18px; margin: 0 0 16px 0;">
                                        <p style="margin: 0 0 8px 0; color: #333333; font-size: 15px; font-weight: 600;">
                                            {{ $alert['label'] }}
                                        </p>
                                        <p style="margin: 0 0 10px 0; color: #555555; font-size: 14px; line-height: 1.6;">
                                            {{ $alert['action'] }}
                                        </p>
                                        <p style="margin: 0; color: #999999; font-size: 11px; font-family: monospace;">
                                            {{ $alert['reason'] }}
                                        </p>
                                    </div>
                                @endforeach
                            </td>
                        </tr>

                        <tr>
                            <td style="padding: 16px 40px 8px 40px;">
                                <h2 style="margin: 0; color: #333333; font-size: 15px; font-weight: 600;">
                                    État des sondes
                                </h2>
                                @include('emails.supervision.partials.checks')
                            </td>
                        </tr>

                        <tr>
                            <td style="padding: 22px 40px 32px 40px;">
                                <p style="margin: 0; color: #888888; font-size: 12px; line-height: 1.6;">
                                    Détail complet : <code>GET /api/health/details</code> (jeton de supervision)
                                    ou admin → Logs.<br>
                                    Un même motif n'est pas renvoyé plus d'une fois par heure. Cet email est
                                    généré automatiquement, il ne contient aucune donnée personnelle.
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
