<?php

namespace App\Services;

/**
 * Builds the final SMS content from an optional admin-defined template.
 *
 * Placeholders supported in templates:
 *  - {nom}   → recipient name
 *  - {lien}  → direct gallery URL
 *  - {code}  → share code
 *
 * If no template is provided, falls back to DEFAULT_TEMPLATE.
 * Always strips accents so Brevo can send via GSM-7 (1 segment for up to 160 chars).
 */
class SmsTemplateService
{
    public const DEFAULT_TEMPLATE = 'Bonjour {nom}, voici l\'acces a vos photos : {lien} (code: {code}). Oceane Torres';

    public function build(?string $template, string $recipientName, string $url, string $code): string
    {
        $body = trim($template ?? '');
        if ($body === '') {
            $body = self::DEFAULT_TEMPLATE;
        }

        $replaced = strtr($body, [
            '{nom}' => $recipientName,
            '{lien}' => $url,
            '{code}' => $code,
        ]);

        return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $replaced) ?: $replaced;
    }
}
