-- Relevé de production — phase 1 du plan de refonte.
-- Les 14 blocs de l'audit, plus les 3 requêtes ajoutées par les passes de relecture.
-- À jouer sur une COPIE restaurée, jamais sur la production.
--
-- ON_ERROR_STOP est volontairement à off : le schéma réel diverge des migrations
-- (audit §3bis.14), une table ou colonne absente doit produire une erreur visible
-- dans le relevé sans interrompre le reste.

\set ON_ERROR_STOP off
\pset pager off
\pset format aligned
\timing off

\echo '## A. Structure réelle'
\echo ''
\echo '### A1. Colonnes, types, nullabilité, défauts'
\echo '```'
SELECT table_name, column_name, data_type, is_nullable, column_default,
       character_maximum_length AS len, numeric_precision AS prec, numeric_scale AS scale
FROM information_schema.columns
WHERE table_schema = 'public'
ORDER BY table_name, ordinal_position;
\echo '```'

\echo '### A2. Colonnes booléennes — type RÉEL (décision D1)'
\echo '```'
SELECT table_name, column_name, data_type
FROM information_schema.columns
WHERE table_schema = 'public'
  AND (column_name LIKE 'is_%' OR column_name LIKE 'has_%'
       OR column_name IN ('requires_shipping','gdpr_consent','cgv_accepted','include_digital'))
ORDER BY data_type, table_name, column_name;
\echo '```'

\echo '### A3. Noms RÉELS des contraintes CHECK'
\echo '```'
SELECT conrelid::regclass AS tbl, conname, pg_get_constraintdef(oid) AS def
FROM pg_constraint WHERE contype = 'c' ORDER BY 1, 2;
\echo '```'

\echo '### A4. Clés étrangères et ON DELETE réels'
\echo '```'
SELECT conrelid::regclass AS tbl, conname, pg_get_constraintdef(oid) AS def
FROM pg_constraint WHERE contype = 'f' ORDER BY 1, 2;
\echo '```'

\echo '### A5. Index présents'
\echo '```'
SELECT tablename, indexname, indexdef FROM pg_indexes
WHERE schemaname = 'public' ORDER BY tablename, indexname;
\echo '```'

\echo '### A6. Migrations effectivement appliquées'
\echo '```'
SELECT batch, migration FROM migrations ORDER BY id;
\echo '```'

\echo '### A7. Colonnes JSON — type reel json ou jsonb (item #38)'
\echo '```'
SELECT table_name, column_name, data_type
FROM information_schema.columns
WHERE table_schema = 'public' AND data_type IN ('json','jsonb')
ORDER BY data_type, table_name;
\echo '```'

\echo '### A8. Fuseau des colonnes temporelles (contrainte C6)'
\echo '```'
SELECT data_type, count(*) AS colonnes
FROM information_schema.columns
WHERE table_schema = 'public' AND data_type LIKE 'timestamp%'
GROUP BY data_type;
\echo '```'


\echo '## B. Volumétrie et tables mortes'
\echo '```'
SELECT relname AS table_name, n_live_tup AS lignes_estimees
FROM pg_stat_user_tables ORDER BY n_live_tup DESC;
\echo '```'
\echo '### B2. Tables présumées mortes — comptages exacts'
\echo '```'
SELECT 'subscriptions' t, count(*) FROM subscriptions
UNION ALL SELECT 'subscription_items', count(*) FROM subscription_items
UNION ALL SELECT 'availability_slots', count(*) FROM availability_slots
UNION ALL SELECT 'availability_patterns', count(*) FROM availability_patterns
UNION ALL SELECT 'client_forms', count(*) FROM client_forms
UNION ALL SELECT 'gift_cards', count(*) FROM gift_cards
UNION ALL SELECT 'password_reset_tokens', count(*) FROM password_reset_tokens
UNION ALL SELECT 'notifications', count(*) FROM notifications
UNION ALL SELECT 'jobs', count(*) FROM jobs
UNION ALL SELECT 'failed_jobs', count(*) FROM failed_jobs
ORDER BY 1;
\echo '```'
\echo '### B3. Colonnes Cashier réellement vides ?'
\echo '```'
SELECT count(*) AS users_avec_donnees_stripe FROM users
WHERE stripe_id IS NOT NULL OR pm_type IS NOT NULL
   OR pm_last_four IS NOT NULL OR trial_ends_at IS NOT NULL;
\echo '```'


\echo '## C. 🔴 Chemins des photos — LE point bloquant de la phase 4'
\echo '```'
SELECT count(*) total,
       count(*) FILTER (WHERE is_processed = false)              AS non_traitees,
       count(*) FILTER (WHERE file_path_hd IS NULL)              AS sans_hd,
       count(*) FILTER (WHERE file_path_preview IS NULL)         AS sans_preview,
       count(*) FILTER (WHERE file_path_thumbnail_clean IS NULL) AS sans_thumb_clean,
       count(*) FILTER (WHERE file_path_web IS NOT NULL)         AS avec_web_mort,
       count(*) FILTER (WHERE file_path_watermark IS NOT NULL)   AS avec_watermark_mort,
       count(*) FILTER (WHERE metadata->>'supabase_path' IS NOT NULL) AS heritage_supabase,
       count(*) FILTER (WHERE metadata->>'storage_path' IS NOT NULL)  AS avec_storage_path,
       count(*) FILTER (WHERE is_video = true)                   AS videos
FROM photos;
\echo '```'
\echo '### C2. Les 4 populations de résolution (§10.8)'
\echo '```'
SELECT CASE
         WHEN file_path_hd IS NOT NULL              THEN 'A - file_path_hd (sain)'
         WHEN metadata->>'storage_path' IS NOT NULL  THEN 'B - metadata storage_path'
         WHEN metadata->>'supabase_path' IS NOT NULL THEN 'C - metadata supabase_path (heritage)'
         WHEN file_path LIKE 'http%'                THEN 'D - file_path URL absolue (le pire)'
         ELSE                                            'D - file_path seul'
       END AS population,
       count(*)
FROM photos GROUP BY 1 ORDER BY 1;
\echo '```'
\echo '### C3. Photos résolues par le repli le plus faible — liste à traiter à la main'
\echo '```'
SELECT id, gallery_id, title, file_path
FROM photos
WHERE file_path_hd IS NULL
  AND metadata->>'storage_path' IS NULL
  AND metadata->>'supabase_path' IS NULL
ORDER BY gallery_id LIMIT 200;
\echo '```'
\echo '### C4. Photos irrécupérables DÉJÀ ACHETÉES (ne jamais supprimer, §10.8)'
\echo '```'
SELECT count(DISTINCT p.id) AS photos_achetees_a_chemin_faible
FROM photos p
JOIN order_items oi ON oi.photo_id = p.id
JOIN orders o ON o.id = oi.order_id AND o.status = 'paid'
WHERE p.file_path_hd IS NULL
  AND p.metadata->>'storage_path' IS NULL;
\echo '```'


\echo '## D. Valeurs réelles des colonnes non contraintes'
\echo '```'
SELECT 'gallery_product_types' src, product_type AS valeur, count(*) FROM gallery_product_types GROUP BY 1,2
UNION ALL SELECT 'cart_items', product_type, count(*) FROM cart_items GROUP BY 1,2
UNION ALL SELECT 'order_items', product_type, count(*) FROM order_items GROUP BY 1,2
ORDER BY 1,2;
\echo '```'
\echo '```'
SELECT 'school_sessions.status' col, status AS valeur, count(*) FROM school_sessions GROUP BY 1,2
UNION ALL SELECT 'orders.print_status', coalesce(print_status,'(null)'), count(*) FROM orders GROUP BY 1,2
UNION ALL SELECT 'orders.status', status, count(*) FROM orders GROUP BY 1,2
UNION ALL SELECT 'clients.source', source, count(*) FROM clients GROUP BY 1,2
UNION ALL SELECT 'notifications.type', type, count(*) FROM notifications GROUP BY 1,2
ORDER BY 1,2;
\echo '```'
\echo '### D2. payments — le commentaire du schéma est faux (§7.4)'
\echo '```'
SELECT provider, coalesce(type,'(null)') AS type, status, count(*)
FROM payments GROUP BY 1,2,3 ORDER BY 4 DESC;
\echo '```'


\echo '## E. Orphelins des 6 colonnes de type FK sans contrainte'
\echo '```'
SELECT 'reservations.availability_slot_id' ctl, count(*) FROM reservations r
  WHERE r.availability_slot_id IS NOT NULL
    AND NOT EXISTS (SELECT 1 FROM availability_slots s WHERE s.id = r.availability_slot_id)
UNION ALL
SELECT 'availability_slots.reservation_id', count(*) FROM availability_slots s
  WHERE s.reservation_id IS NOT NULL
    AND NOT EXISTS (SELECT 1 FROM reservations r WHERE r.id = s.reservation_id)
UNION ALL
SELECT 'photo_uploads.photo_id', count(*) FROM photo_uploads u
  WHERE u.photo_id IS NOT NULL
    AND NOT EXISTS (SELECT 1 FROM photos p WHERE p.id = u.photo_id)
UNION ALL
SELECT 'gift_cards.payment_id', count(*) FROM gift_cards g
  WHERE g.payment_id IS NOT NULL
    AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.id = g.payment_id);
\echo '```'


\echo '## F. Backfills incomplets et cohérence métier'
\echo '```'
SELECT 'orders user_id sans guest_email' ctl, count(*) FROM orders
  WHERE user_id IS NOT NULL AND guest_email IS NULL
UNION ALL
SELECT 'commandes payees sans facture', count(*) FROM orders o WHERE o.status = 'paid'
  AND NOT EXISTS (SELECT 1 FROM invoices i WHERE i.order_id = o.id)
UNION ALL
SELECT 'factures sans fichier genere', count(*) FROM invoices
  WHERE file_path IS NULL OR generated_at IS NULL
UNION ALL
SELECT 'paniers actifs multiples par user', count(*) FROM (
  SELECT user_id FROM carts WHERE status = 'active' AND user_id IS NOT NULL
  GROUP BY user_id HAVING count(*) > 1) x
UNION ALL
SELECT 'galeries sans photo', count(*) FROM galleries g
  WHERE NOT EXISTS (SELECT 1 FROM photos p WHERE p.gallery_id = g.id)
UNION ALL
SELECT 'totaux de commande incoherents', count(*) FROM orders
  WHERE abs(total - (subtotal - discount_amount + shipping_fee)) > 0.01;
\echo '```'
\echo '### F2. 🔴 #69 — combien de factures sans PDF ? (attendu : TOUTES)'
\echo '```'
SELECT count(*) AS factures_total,
       count(*) FILTER (WHERE file_path IS NULL)     AS sans_chemin,
       count(*) FILTER (WHERE generated_at IS NULL)  AS sans_date_generation,
       min(created_at) AS premiere, max(created_at) AS derniere
FROM invoices;
\echo '```'


\echo '## G. 🔴 Compte admin du seeder (action de sécurité immédiate)'
\echo ''
\echo '> ⚠️ Ce bloc cherche une adresse LITTÉRALE. Sur une copie anonymisée il renvoie'
\echo '> toujours 0, ce qui ne prouve rien. À jouer sur une copie NON anonymisée.'
\echo ''
\echo '```'
SELECT id, email, role, created_at, updated_at, email_verified_at FROM users
WHERE email IN ('admin@oceanetorres.fr', 'client@test.com');
\echo '```'
\echo '```'
SELECT id, email, role, created_at, email_verified_at FROM users WHERE role = 'admin' ORDER BY created_at;
\echo '```'


\echo '## H. Travail restant des backfills photo (§5.14)'
\echo '```'
SELECT 'photos non traitees' ctl, count(*) FROM photos WHERE is_processed = false
UNION ALL
SELECT 'telechargeables sans vignette clean', count(*) FROM photos
  WHERE is_downloadable = true AND file_path_thumbnail_clean IS NULL AND is_video = false;
\echo '```'
\echo '### H2. Distribution des causes d''échec d''upload'
\echo '```'
SELECT status, count(*) FROM photo_uploads GROUP BY 1 ORDER BY 2 DESC;
\echo '```'
\echo '```'
SELECT left(coalesce(error_message,'(null)'), 90) AS cause, count(*)
FROM photo_uploads WHERE status = 'failed' GROUP BY 1 ORDER BY 2 DESC LIMIT 30;
\echo '```'


\echo '## I. Activité commerciale (§7.14-A)'
\echo '```'
SELECT status, count(*) AS n, sum(total) AS montant, min(created_at), max(created_at)
FROM orders GROUP BY status ORDER BY n DESC;
\echo '```'
\echo '### I2. Chiffre d''affaires par mois'
\echo '```'
SELECT date_trunc('month', paid_at) AS mois, count(*) AS commandes,
       sum(subtotal) AS sous_total, sum(discount_amount) AS remises,
       sum(shipping_fee) AS port, sum(total) AS ca
FROM orders WHERE status = 'paid' GROUP BY 1 ORDER BY 1;
\echo '```'
\echo '### I3. Mix produit'
\echo '```'
SELECT oi.product_type, count(*) AS lignes, sum(oi.quantity) AS unites,
       sum(oi.price * oi.quantity) AS montant
FROM order_items oi JOIN orders o ON o.id = oi.order_id
WHERE o.status = 'paid' GROUP BY 1 ORDER BY 4 DESC;
\echo '```'
\echo '### I4. Volume de bons cadeaux vendus (décision D4)'
\echo '```'
SELECT status, count(*), sum(amount) AS emis, sum(remaining_amount) AS encours
FROM gift_cards GROUP BY 1 ORDER BY 1;
\echo '```'


\echo '## J. 🔴 Portée du bug de quantité (§7.14-B)'
\echo '```'
SELECT count(DISTINCT o.id) AS commandes_touchees,
       min(o.created_at) AS premiere, max(o.created_at) AS derniere
FROM orders o JOIN order_items oi ON oi.order_id = o.id
WHERE o.status = 'paid' AND oi.quantity > 1;
\echo '```'
\echo '### J2. Détail avec le numéro de facture concerné'
\echo '```'
SELECT o.order_number, i.invoice_number, o.paid_at,
       sum(oi.price)               AS somme_lignes_facture,
       sum(oi.price * oi.quantity) AS somme_reelle
FROM orders o
JOIN order_items oi ON oi.order_id = o.id
LEFT JOIN invoices i ON i.order_id = o.id
WHERE o.status = 'paid'
GROUP BY o.id, o.order_number, i.invoice_number, o.paid_at
HAVING sum(oi.price) <> sum(oi.price * oi.quantity)
ORDER BY o.paid_at DESC;
\echo '```'


\echo '## K. Cohérence comptable (§7.14-C)'
\echo '```'
SELECT o.order_number, o.subtotal, sum(oi.price * oi.quantity) AS calcule
FROM orders o JOIN order_items oi ON oi.order_id = o.id
GROUP BY o.id, o.order_number, o.subtotal
HAVING abs(o.subtotal - sum(oi.price * oi.quantity)) > 0.01
ORDER BY 1 LIMIT 100;
\echo '```'
\echo '### K2. Continuité de la séquence de facturation (contrainte C2)'
\echo '```'
WITH n AS (
  SELECT invoice_number,
         cast(substring(invoice_number FROM 'F-\d{4}-(\d+)') AS integer) AS seq,
         substring(invoice_number FROM 'F-(\d{4})') AS annee
  FROM invoices
)
SELECT annee, min(seq), max(seq), count(*), max(seq) - min(seq) + 1 - count(*) AS trous
FROM n GROUP BY annee ORDER BY annee;
\echo '```'
\echo '### K3. Idem pour les numéros de commande (contrainte C3)'
\echo '```'
WITH n AS (
  SELECT cast(substring(order_number FROM 'OT-\d{4}-(\d+)') AS integer) AS seq,
         substring(order_number FROM 'OT-(\d{4})') AS annee
  FROM orders
)
SELECT annee, min(seq), max(seq), count(*), max(seq) - min(seq) + 1 - count(*) AS trous
FROM n GROUP BY annee ORDER BY annee;
\echo '```'


\echo '## L. Santé du tunnel de paiement (§7.14-D)'
\echo '```'
SELECT count(*) FILTER (WHERE status = 'paid')    AS payees,
       count(*) FILTER (WHERE status = 'failed')  AS echouees,
       count(*) FILTER (WHERE status = 'expired') AS abandonnees,
       count(*) FILTER (WHERE status = 'pending') AS en_cours,
       round(100.0 * count(*) FILTER (WHERE status = 'paid') / nullif(count(*),0), 1) AS taux_pct
FROM orders;
\echo '```'
\echo '### L2. Cause des échecs — NULL transaction_id = refus émetteur immédiat'
\echo '```'
SELECT sumup_transaction_id IS NULL AS refus_immediat, count(*)
FROM orders WHERE status = 'failed' GROUP BY 1;
\echo '```'
\echo '### L3. Discrimination par délai de résolution'
\echo '```'
SELECT CASE
         WHEN extract(epoch FROM (updated_at - created_at)) < 60   THEN '1 - moins d1 min (decline)'
         WHEN extract(epoch FROM (updated_at - created_at)) < 900  THEN '2 - 1-15 min (3DS)'
         WHEN extract(epoch FROM (updated_at - created_at)) < 3600 THEN '3 - 15-60 min'
         ELSE                                                           '4 - plus d1 h (expire)'
       END AS fenetre, count(*)
FROM orders WHERE status = 'failed' GROUP BY 1 ORDER BY 1;
\echo '```'
\echo '### L4. Efficacité de la réconciliation (preuve que le scheduler tourne)'
\echo '```'
SELECT count(*) AS payees_plus_de_15min_apres_creation FROM orders
WHERE status = 'paid' AND paid_at - created_at > interval '15 minutes';
\echo '```'


\echo '## M. Anomalies structurelles (§7.14-E)'
\echo '### M1. Commandes MIXTES scolaire / non-scolaire — invisibles en back-office'
\echo '```'
SELECT o.id, o.order_number, o.status, o.created_at
FROM orders o
WHERE EXISTS (
        SELECT 1 FROM order_items oi JOIN photos p ON p.id = oi.photo_id
        JOIN galleries g ON g.id = p.gallery_id
        WHERE oi.order_id = o.id AND g.school_session_id IS NOT NULL)
  AND EXISTS (
        SELECT 1 FROM order_items oi2 WHERE oi2.order_id = o.id
          AND oi2.product_type <> 'print_scolaire')
ORDER BY o.created_at DESC;
\echo '```'
\echo '### M2. Doublons de paiement'
\echo '```'
SELECT order_id, count(*) FROM payments
WHERE order_id IS NOT NULL GROUP BY order_id HAVING count(*) > 1;
\echo '```'
\echo '### M3. Tirages payés jamais expédiés (backlog de production)'
\echo '```'
SELECT o.order_number, o.paid_at, o.shipping_city
FROM orders o
WHERE o.status = 'paid' AND o.print_status IS DISTINCT FROM 'shipped'
  AND EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = o.id
                AND oi.product_type LIKE 'print_%')
ORDER BY o.paid_at;
\echo '```'
\echo '### M4. Codes promo — utilisation réelle vs quota (contrainte R7)'
\echo '```'
SELECT gc.code, gc.type, gc.value, gc.max_uses,
       count(*) FILTER (WHERE o.status = 'paid') AS utilisations_payees,
       sum(o.discount_amount) FILTER (WHERE o.status = 'paid') AS remise_totale
FROM gift_codes gc LEFT JOIN orders o ON o.gift_code_id = gc.id
GROUP BY gc.id, gc.code, gc.type, gc.value, gc.max_uses ORDER BY 5 DESC NULLS LAST;
\echo '```'
\echo '### M5. Commandes gratuites (chemin confirm-free)'
\echo '```'
SELECT count(*) AS commandes_a_zero FROM orders WHERE status = 'paid' AND total = 0;
\echo '```'
\echo '### M6. 🔴 Trace d''un passage de ClientController::destroy (§12.3)'
\echo '```'
SELECT count(*) AS commandes_payees_anonymisees FROM orders
WHERE guest_first_name = 'Supprimé' AND status = 'paid';
\echo '```'


\echo '## N. File d''attente et preuve que la purge RGPD tourne (§8.14)'
\echo '```'
SELECT count(*) FILTER (WHERE reserved_at IS NULL)     AS en_attente,
       count(*) FILTER (WHERE reserved_at IS NOT NULL) AS reserves,
       min(created_at) AS plus_ancien
FROM jobs;
\echo '```'
\echo '### N2. Échecs par type de job'
\echo '```'
SELECT payload::json->>'displayName' AS job,
       count(*) AS n, min(failed_at) AS premier, max(failed_at) AS dernier
FROM failed_jobs GROUP BY 1 ORDER BY 2 DESC;
\echo '```'
\echo '### N3. 🔴 Les échecs INVISIBLES pour la supervision (§8.4)'
\echo '```'
SELECT 'school_sessions' t, status, count(*), max(updated_at) FROM school_sessions
  WHERE status = 'failed' GROUP BY 1,2
UNION ALL SELECT 'school_session_exports', status, count(*), max(updated_at)
  FROM school_session_exports WHERE status = 'failed' GROUP BY 1,2
UNION ALL SELECT 'privacy_exports', status, count(*), max(updated_at)
  FROM privacy_exports WHERE status = 'failed' GROUP BY 1,2;
\echo '```'
\echo '### N4. Preuve indirecte que la purge RGPD tourne — non nul = elle ne tourne pas'
\echo '```'
SELECT 'contact_messages > 12 mois' ctl, count(*) FROM contact_messages
  WHERE created_at < now() - interval '12 months'
UNION ALL SELECT 'download_logs > 12 mois', count(*) FROM download_logs
  WHERE downloaded_at < now() - interval '12 months'
UNION ALL SELECT 'paniers expires > 30 j', count(*) FROM carts
  WHERE status = 'expired' AND updated_at < now() - interval '30 days';
\echo '```'


\echo '## O. Carte des identités (§10.3)'
\echo '```'
SELECT
  (SELECT count(*) FROM users)                                     AS users,
  (SELECT count(*) FROM clients)                                   AS clients,
  (SELECT count(*) FROM clients WHERE user_id IS NOT NULL)          AS clients_avec_compte,
  (SELECT count(*) FROM clients WHERE user_id IS NULL)              AS clients_sans_compte,
  (SELECT count(DISTINCT lower(guest_email)) FROM orders
     WHERE guest_email IS NOT NULL)                                 AS emails_commandes,
  (SELECT count(*) FROM orders WHERE user_id IS NULL
     AND guest_email IS NOT NULL)                                   AS commandes_invite,
  (SELECT count(*) FROM galleries WHERE user_id IS NULL
     AND assigned_email IS NOT NULL)                                AS galeries_en_attente,
  (SELECT count(*) FROM reservations WHERE user_id IS NULL
     AND guest_email IS NOT NULL)                                   AS reservations_invite;
\echo '```'
\echo '### O2. Emails orphelins — connus du système sans fiche users ni clients'
\echo '```'
SELECT count(*) AS emails_orphelins FROM (
  SELECT DISTINCT lower(e.email) AS email FROM (
    SELECT guest_email AS email FROM orders       WHERE guest_email    IS NOT NULL
    UNION SELECT assigned_email FROM galleries    WHERE assigned_email IS NOT NULL
    UNION SELECT guest_email    FROM reservations WHERE guest_email    IS NOT NULL
    UNION SELECT email          FROM contact_messages
  ) e
  WHERE NOT EXISTS (SELECT 1 FROM users   u WHERE lower(u.email) = lower(e.email))
    AND NOT EXISTS (SELECT 1 FROM clients c WHERE lower(c.email) = lower(e.email))
) x;
\echo '```'

\echo ''
\echo '---'
\echo ''
\echo 'Fin du relevé.'
