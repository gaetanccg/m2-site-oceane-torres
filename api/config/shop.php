<?php

return [
    /*
     * Frais de port facturés une seule fois dès qu'une commande contient au
     * moins un tirage papier. Exprimé en euros.
     */
    'shipping_fee_print' => env('SHOP_SHIPPING_FEE_PRINT', 2.00),
];
