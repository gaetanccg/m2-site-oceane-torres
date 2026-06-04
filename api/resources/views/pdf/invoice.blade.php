<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture {{ $invoice->invoice_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.5;
        }
        .header {
            background-color: #0a0708;
            color: #D4AF37;
            padding: 30px 40px;
            text-align: center;
        }
        .header h1 {
            font-size: 20px;
            font-weight: 300;
            letter-spacing: 3px;
            text-transform: uppercase;
        }
        .content {
            padding: 40px;
        }
        .invoice-title {
            font-size: 22px;
            color: #0a0708;
            margin-bottom: 5px;
        }
        .invoice-number {
            font-size: 14px;
            color: #666;
            margin-bottom: 30px;
        }
        .info-grid {
            width: 100%;
            margin-bottom: 30px;
        }
        .info-grid td {
            vertical-align: top;
            width: 50%;
            padding: 0 10px;
        }
        .info-box {
            background-color: #f9f9f9;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 10px;
        }
        .info-box h3 {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #999;
            margin-bottom: 8px;
        }
        .info-box p {
            font-size: 12px;
            color: #333;
            margin-bottom: 3px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .items-table thead th {
            background-color: #0a0708;
            color: #D4AF37;
            padding: 10px 12px;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .items-table thead th:last-child {
            text-align: right;
        }
        .items-table tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid #eee;
            font-size: 11px;
        }
        .items-table tbody td:last-child {
            text-align: right;
            font-weight: 600;
        }
        .items-table .gallery-name {
            color: #888;
            font-size: 10px;
        }
        .items-table .product-type {
            display: inline-block;
            padding: 2px 6px;
            background-color: #f0f0f0;
            border-radius: 3px;
            font-size: 9px;
            color: #666;
            margin-top: 3px;
        }
        .total-row {
            border-top: 2px solid #0a0708;
        }
        .total-row td {
            padding: 15px 12px;
            font-size: 14px;
            font-weight: 700;
        }
        .total-row td:last-child {
            color: #D4AF37;
        }
        .payment-info {
            background-color: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
        }
        .payment-info h3 {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #0369a1;
            margin-bottom: 8px;
        }
        .payment-info p {
            font-size: 11px;
            color: #555;
            margin-bottom: 3px;
        }
        .legal {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 9px;
            color: #999;
            line-height: 1.6;
        }
        .footer {
            background-color: #0a0708;
            color: #999;
            padding: 20px 40px;
            text-align: center;
            font-size: 10px;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Océane Torres Photographie</h1>
    </div>

    <div class="content">
        <h2 class="invoice-title">Facture</h2>
        <p class="invoice-number">N° {{ $invoice->invoice_number }} — {{ $invoice->generated_at->format('d/m/Y') }}</p>

        <table class="info-grid">
            <tr>
                <td>
                    <div class="info-box">
                        <h3>Émetteur</h3>
                        <p><strong>{{ config('invoice.business_name') }}</strong></p>
                        <p>{{ config('invoice.address') }}</p>
                        <p>{{ config('invoice.region') }}</p>
                        <p>SIRET : {{ config('invoice.siret') }}</p>
                        <p>{{ config('invoice.email') }}</p>
                    </div>
                </td>
                <td>
                    <div class="info-box">
                        <h3>Client</h3>
                        <p><strong>{{ $order->customer_name ?: 'Client' }}</strong></p>
                        <p>{{ $order->customer_email }}</p>
                    </div>
                </td>
            </tr>
        </table>

        <table class="info-grid">
            <tr>
                <td>
                    <div class="info-box">
                        <h3>Commande</h3>
                        <p>N° {{ $order->order_number }}</p>
                        <p>Date : {{ $order->paid_at ? $order->paid_at->format('d/m/Y à H:i') : $order->created_at->format('d/m/Y à H:i') }}</p>
                    </div>
                </td>
                <td>
                    <div class="info-box">
                        <h3>Paiement</h3>
                        <p>Carte bancaire via SumUp</p>
                        @if($order->sumup_transaction_id)
                        <p>Réf. : {{ $order->sumup_transaction_id }}</p>
                        @endif
                    </div>
                </td>
            </tr>
        </table>

        <table class="items-table">
            <thead>
                <tr>
                    <th>Désignation</th>
                    <th style="text-align: right;">Prix TTC</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                <tr>
                    <td>
                        {{ $item->photo_title ?? 'Photo' }}
                        @if($item->gallery_title)
                        <br><span class="gallery-name">{{ $item->gallery_title }}</span>
                        @endif
                        <br><span class="product-type">{{ $item->getProductTypeLabel() }}</span>
                    </td>
                    <td>{{ number_format($item->price, 2, ',', ' ') }} €</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                @if($order->discount_amount > 0 || $order->shipping_fee > 0)
                <tr>
                    <td style="text-align: right; color: #666;">Sous-total</td>
                    <td>{{ number_format($order->subtotal, 2, ',', ' ') }} €</td>
                </tr>
                @endif
                @if($order->discount_amount > 0)
                <tr>
                    <td style="text-align: right; color: #666;">
                        Remise@if($order->gift_code) (code {{ $order->gift_code }})@endif
                    </td>
                    <td>-{{ number_format($order->discount_amount, 2, ',', ' ') }} €</td>
                </tr>
                @endif
                @if($order->shipping_fee > 0)
                <tr>
                    <td style="text-align: right; color: #666;">Frais de port</td>
                    <td>{{ number_format($order->shipping_fee, 2, ',', ' ') }} €</td>
                </tr>
                @endif
                <tr class="total-row">
                    <td><strong>Total TTC</strong></td>
                    <td>{{ number_format($order->total, 2, ',', ' ') }} €</td>
                </tr>
            </tfoot>
        </table>

        <div class="legal">
            <p><strong>TVA non applicable, art. 293 B du CGI</strong></p>
            <p>{{ config('invoice.legal_status') }} — {{ config('invoice.business_name') }}</p>
            <p>SIRET : {{ config('invoice.siret') }}</p>
            <p>{{ config('invoice.address') }} — {{ config('invoice.region') }}</p>
            <p>{{ config('invoice.email') }}</p>
            <p style="margin-top: 8px;">
                Facture générée le {{ $invoice->generated_at->format('d/m/Y à H:i') }}
            </p>
        </div>
    </div>
</body>
</html>
