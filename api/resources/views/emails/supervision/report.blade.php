<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Rapport de santé quotidien</title>
    </head>
    <body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f5f5;">
        <table role="presentation" style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 40px 20px;">
                    <table role="presentation" style="max-width: 640px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                        <tr>
                            <td style="background-color: {{ $snapshot->isHealthy() ? '#2e7d32' : ($snapshot->status->value === 'down' ? '#c62828' : '#ed6c02') }}; padding: 26px 40px;">
                                <h1 style="margin: 0; color: #ffffff; font-size: 21px; font-weight: 600;">
                                    {{ $snapshot->isHealthy() ? '✅' : '⚠️' }} Rapport de santé quotidien
                                </h1>
                                <p style="margin: 6px 0 0 0; color: #ffffff; font-size: 13px; opacity: 0.9;">
                                    Statut global : {{ $snapshot->status->value }} · {{ $environment }} · v{{ $version }}
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding: 32px 40px 8px 40px;">
                                <p style="margin: 0 0 6px 0; color: #555555; font-size: 15px; line-height: 1.6;">
                                    @if ($snapshot->isHealthy())
                                        Tous les composants supervisés répondent normalement.
                                    @else
                                        {{ count($snapshot->failing()) }} composant(s) hors du vert — voir le détail ci-dessous.
                                    @endif
                                </p>
                                <p style="margin: 0; color: #888888; font-size: 12px;">
                                    Relevé du {{ now()->timezone('Europe/Paris')->format('d/m/Y à H:i') }}
                                    · sondes exécutées en {{ $snapshot->durationMs }} ms
                                </p>

                                @include('emails.supervision.partials.checks')
                            </td>
                        </tr>

                        <tr>
                            <td style="padding: 22px 40px 32px 40px;">
                                <p style="margin: 0; color: #888888; font-size: 12px; line-height: 1.6;">
                                    Si ce rapport cesse d'arriver, c'est que le scheduler ne tourne plus :
                                    vérifier le conteneur <code>api-scheduler</code>.<br>
                                    Cet email est généré automatiquement, il ne contient aucune donnée personnelle.
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
