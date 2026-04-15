# Commandes SSH - API Oceane Torres

## 📋 Table des matières

- [Vérification des données](#vérification-des-données)
- [Traitement manuel des commandes](#traitement-manuel-des-commandes)
- [Traitement des photos](#traitement-des-photos)
- [Nettoyage du stockage MinIO](#nettoyage-du-stockage-minio)

---

## Vérification des données

### Vérifier si les données de téléchargement ont été générées

Cette commande permet de vérifier l'état d'une commande et ses données associées.

```bash
sudo docker exec -it api-php php artisan tinker --execute="
  \$o = App\Models\Order::where('order_number','OT-2026-xxxxx')->first();
  echo 'Status: '.\$o->status.PHP_EOL;
  echo 'Paid at: '.\$o->paid_at.PHP_EOL;
  echo 'Email: '.\$o->customer_email.PHP_EOL;
  echo 'Download token: '.(\$o->metadata['download_token'] ?? 'NONE').PHP_EOL;
  \$inv = App\Models\Invoice::where('order_id',\$o->id)->first();
  echo 'Invoice: '.(\$inv ? \$inv->invoice_number : 'NONE').PHP_EOL;
  "
```

**Résultat attendu :**

```
Status: pending
Paid at: xxxx-xx-xx xx:xx:xx
Email: email@mail.fr
Download token: NONE
Invoice: NONE
```

---

## Traitement manuel des commandes

### Générer les données manquantes pour une commande

Cette commande effectue les 3 étapes manquantes :

1. Génération du token de téléchargement
2. Génération de la facture PDF
3. Envoi de l'email de confirmation

```bash
sudo docker exec -it api-php php artisan tinker --execute="
  \$order = App\Models\Order::where('order_number','OT-2026-xxxxx')->first();
  \$order->load('items.photo');

  // 1. Generate download token
  \$order->generateDownloadToken();
  echo 'Download token generated'.PHP_EOL;

  // 2. Generate invoice PDF
  \$invoiceService = app(App\Services\InvoiceService::class);
  \$invoice = App\Models\Invoice::where('order_id', \$order->id)->first();
  \$pdf = Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.invoice', ['order' => \$order, 'invoice' => \$invoice]);
  \$filePath = 'invoices/'.\$order->id.'/'.\$invoice->invoice_number.'.pdf';
  Illuminate\Support\Facades\Storage::disk('minio')->put(\$filePath, \$pdf->output());
  \$invoice->update(['file_path' => \$filePath]);
  echo 'Invoice PDF generated: '.\$filePath.PHP_EOL;

  // 3. Send confirmation email
  \$order->refresh();
  \$downloadToken = \$order->metadata['download_token'] ?? null;
  \$downloadUrl = config('app.frontend_url').'/commande/'.\$order->id.'?token='.\$downloadToken;
  Illuminate\Support\Facades\Mail::to(\$order->customer_email)->queue(new App\Mail\OrderConfirmationMail(\$order, \$downloadUrl, \$invoice));
  echo 'Email queued to: '.\$order->customer_email.PHP_EOL;
  "
```

**Note :** Pensez à remplacer `OT-2026-xxxxx` par le numéro de commande concerné.

---

## Traitement des photos

### Regénérer les watermarks de toutes les photos

Retraite toutes les photos existantes (preview + thumbnail avec nouveaux watermarks). Les anciens fichiers preview/thumbnail deviennent orphelins sur MinIO.

```bash
sudo docker exec -it api-php php artisan photos:process-existing --force
```

### Regénérer les watermarks d'une seule galerie

```bash
sudo docker exec -it api-php php artisan photos:process-existing --force --gallery=ID_DE_LA_GALERIE
```

### Regénérer uniquement les photos non traitées

Sans `--force`, ne traite que les photos où `is_processed = false`.

```bash
sudo docker exec -it api-php php artisan photos:process-existing
```

**Options :**
- `--force` : retraite aussi les photos déjà traitées
- `--gallery=UUID` : limite à une galerie spécifique
- `--batch=25` : nombre de photos par lot (défaut: 25)

---

## Nettoyage du stockage MinIO

### Voir les fichiers orphelins (dry run)

Liste les fichiers orphelins sans rien supprimer. Détecte :
1. **Galeries supprimées** — dossiers entiers sur MinIO dont la galerie n'existe plus en BDD
2. **Fichiers orphelins** — anciens preview/thumbnail non référencés après un `--force`

```bash
sudo docker exec -it api-php php artisan storage:clean-orphans
```

### Supprimer les fichiers orphelins

```bash
sudo docker exec -it api-php php artisan storage:clean-orphans --delete
```

### Nettoyer une seule galerie

```bash
sudo docker exec -it api-php php artisan storage:clean-orphans --gallery=ID_DE_LA_GALERIE --delete
```

**Note :** Toujours lancer sans `--delete` d'abord pour vérifier ce qui sera supprimé.

---

## 📝 Notes importantes

- Les commandes utilisent `docker exec` pour accéder au conteneur Laravel
- Le conteneur est nommé `api-php`
- Les commandes utilisent `php artisan tinker` pour exécuter du code PHP directement
