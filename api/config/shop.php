<?php

return [
    /*
     * Frais de port facturés une seule fois dès qu'une commande contient au
     * moins un tirage papier. Exprimé en euros.
     */
    'shipping_fee_print' => env('SHOP_SHIPPING_FEE_PRINT', 2.00),

    /*
     * Sert les vignettes/previews DÉJÀ traitées (watermarkées) directement depuis
     * MinIO via une URL signée, au lieu de les streamer à travers PHP-FPM. Décharge
     * complètement le backend du transfert d'octets sur les galeries à 200+ photos.
     * Mettre à false pour repasser sur le proxy /api/images/... (rollback immédiat,
     * sans redéploiement).
     */
    'serve_images_direct' => env('SHOP_SERVE_IMAGES_DIRECT', true),
];
