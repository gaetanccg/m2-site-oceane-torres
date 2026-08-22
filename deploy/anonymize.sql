-- Anonymisation d'une copie restaurée, pour alimenter la base de développement.
-- À jouer UNIQUEMENT sur une copie jetable, jamais sur la production.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- PRINCIPE : pseudonymisation DÉTERMINISTE, dérivée de la valeur d'origine.
--
-- Une même adresse réelle produit toujours la même adresse fictive, dans toutes
-- les tables. C'est indispensable : le relevé croise `users.email`,
-- `clients.email`, `orders.guest_email`, `galleries.assigned_email` et
-- `reservations.guest_email` pour compter les personnes distinctes (bloc O).
-- Une anonymisation aléatoire rendrait ces jointures et ces comptages faux.
--
-- CE QUI EST PRÉSERVÉ À L'IDENTIQUE, parce que le relevé et la phase 4 en
-- dépendent : tous les UUID, les 7 colonnes de chemin de `photos`,
-- `metadata->storage_path`, les montants, les dates, les statuts,
-- `order_number` et `invoice_number` (continuité des séquences), et la
-- NULLITÉ de chaque colonne — une adresse de livraison absente signifie
-- « commande sans tirage », c'est une information.
--
-- CE QUI EST RÉGÉNÉRÉ : `share_code` et `access_token`. Ce sont des accès
-- vivants aux galeries de vraies clientes ; ils n'ont pas à vivre sur un poste
-- de développement. Corollaire : la répétition générale de la phase 4, qui doit
-- vérifier qu'une galerie s'ouvre avec son ANCIEN code, se fait sur une copie
-- NON anonymisée.
-- ─────────────────────────────────────────────────────────────────────────────

\set ON_ERROR_STOP on

BEGIN;

CREATE OR REPLACE FUNCTION anon_hash(v text) RETURNS text AS $$
    SELECT substr(md5(lower(btrim(v))), 1, 10);
$$ LANGUAGE sql IMMUTABLE;

CREATE OR REPLACE FUNCTION anon_email(v text) RETURNS text AS $$
    SELECT CASE WHEN v IS NULL OR btrim(v) = '' THEN v
                ELSE 'p' || anon_hash(v) || '@example.test' END;
$$ LANGUAGE sql IMMUTABLE;

CREATE OR REPLACE FUNCTION anon_digits(v text, n int) RETURNS text AS $$
    SELECT lpad((abs(('x' || substr(md5(lower(btrim(v))), 1, 8))::bit(32)::bigint)
                 % (10::bigint ^ n)::bigint)::text, n, '0');
$$ LANGUAGE sql IMMUTABLE;

CREATE OR REPLACE FUNCTION anon_phone(v text) RETURNS text AS $$
    SELECT CASE WHEN v IS NULL OR btrim(v) = '' THEN v
                ELSE '06' || anon_digits(v, 8) END;
$$ LANGUAGE sql IMMUTABLE;

CREATE OR REPLACE FUNCTION anon_label(prefix text, v text) RETURNS text AS $$
    SELECT CASE WHEN v IS NULL OR btrim(v) = '' THEN v
                ELSE prefix || ' ' || upper(substr(anon_hash(v), 1, 6)) END;
$$ LANGUAGE sql IMMUTABLE;

CREATE OR REPLACE FUNCTION anon_code(v text, n int) RETURNS text AS $$
    SELECT upper(substr(translate(md5('code:' || coalesce(v, random()::text)),
                                  'ghijklmnopqrstuv', '0123456789ABCDEF'), 1, n));
$$ LANGUAGE sql VOLATILE;


-- ── Identités ───────────────────────────────────────────────────────────────
-- Mot de passe unique et connu : « MotDePasseDev2026! ». Le hash reste en $2y$
-- pour que le contrôle « 0 mot de passe ne commençant pas par $2y$ » du plan de
-- validation (niveau 3) reste vérifiable.
UPDATE users SET
    first_name     = anon_label('Prenom', first_name),
    last_name      = anon_label('Nom', last_name),
    email          = anon_email(email),
    phone          = anon_phone(phone),
    password       = '$2y$12$.T8GILyanKU.wGIJqqgL4.KrMcjM7/Q06WM5RX7GTHFGFtXS1tlQ.',
    remember_token = NULL,
    address_line1  = CASE WHEN address_line1 IS NULL THEN NULL ELSE anon_digits(id::text, 2) || ' rue de la Paix' END,
    address_line2  = CASE WHEN address_line2 IS NULL THEN NULL ELSE 'Batiment ' || upper(substr(anon_hash(id::text), 1, 1)) END,
    postal_code    = CASE WHEN postal_code IS NULL THEN NULL ELSE '42' || anon_digits(id::text, 3) END,
    city           = CASE WHEN city IS NULL THEN NULL ELSE 'Villeneuve' END;

UPDATE clients SET
    name  = anon_label('Client', name),
    email = anon_email(email),
    phone = anon_phone(phone),
    notes = CASE WHEN notes IS NULL THEN NULL ELSE 'note interne anonymisee' END;


-- ── Boutique ────────────────────────────────────────────────────────────────
UPDATE orders SET
    guest_email            = anon_email(guest_email),
    guest_first_name       = anon_label('Prenom', guest_first_name),
    guest_last_name        = anon_label('Nom', guest_last_name),
    shipping_phone         = anon_phone(shipping_phone),
    shipping_address_line1 = CASE WHEN shipping_address_line1 IS NULL THEN NULL ELSE anon_digits(id::text, 2) || ' rue de la Paix' END,
    shipping_address_line2 = CASE WHEN shipping_address_line2 IS NULL THEN NULL ELSE 'Batiment ' || upper(substr(anon_hash(id::text), 1, 1)) END,
    shipping_postal_code   = CASE WHEN shipping_postal_code IS NULL THEN NULL ELSE '42' || anon_digits(id::text, 3) END,
    shipping_city          = CASE WHEN shipping_city IS NULL THEN NULL ELSE 'Villeneuve' END;

UPDATE carts SET guest_email = anon_email(guest_email);

-- Titres dénormalisés sur les lignes de commande : ils portent des prénoms
-- d'enfants pour les sessions scolaires. Dérivés de la même valeur que
-- photos.title, donc restent cohérents avec elle.
UPDATE order_items SET
    photo_title   = anon_label('Photo', photo_title),
    gallery_title = anon_label('Galerie', gallery_title);

UPDATE gift_cards SET
    recipient_name  = anon_label('Destinataire', recipient_name),
    recipient_email = anon_email(recipient_email),
    message         = CASE WHEN message IS NULL THEN NULL ELSE 'message anonymise' END;


-- ── Galeries et photos ──────────────────────────────────────────────────────
-- Les titres de galerie et de photo portent des noms de clientes et, pour le
-- scolaire, des prénoms d'enfants — la donnée la plus sensible du jeu.
UPDATE galleries SET
    title          = anon_label('Galerie', title),
    description    = CASE WHEN description IS NULL THEN NULL ELSE 'description anonymisee' END,
    assigned_email = anon_email(assigned_email),
    class_name     = anon_label('Classe', class_name),
    sms_template   = CASE WHEN sms_template IS NULL THEN NULL ELSE 'Bonjour {nom}, vos photos : {lien} (code {code})' END,
    access_token   = anon_code(id::text || 'tok', 64),
    share_code     = anon_code(id::text || 'shr', 6);

UPDATE photos SET
    title       = anon_label('Photo', title),
    description = CASE WHEN description IS NULL THEN NULL ELSE 'description anonymisee' END;

-- metadata : seule `original_filename` est remplacee. storage_path, mime_type,
-- size, width, height, processed_at et shared sont conserves tels quels — le
-- bloc C du releve et la reconciliation de la phase 4 en dependent.
UPDATE photos SET metadata = (
        (metadata::jsonb) || jsonb_build_object(
            'original_filename',
            'photo-' || anon_hash(metadata->>'original_filename') || '.jpg')
    )::json
WHERE metadata->>'original_filename' IS NOT NULL;

UPDATE photo_uploads SET
    original_filename = 'photo-' || anon_hash(original_filename) || '.jpg',
    error_message     = CASE WHEN error_message IS NULL THEN NULL ELSE left(error_message, 60) END;

UPDATE school_sessions SET
    title           = anon_label('Session', title),
    gallery_message = CASE WHEN gallery_message IS NULL THEN NULL ELSE 'message anonymise' END,
    sms_template    = CASE WHEN sms_template IS NULL THEN NULL ELSE 'Bonjour {nom}, les photos : {lien}' END;

-- ip_address et user_agent sont des donnees personnelles (audit §3bis.5).
UPDATE download_logs SET
    ip_address = CASE WHEN ip_address IS NULL THEN NULL ELSE '203.0.113.' || anon_digits(ip_address, 2) END,
    user_agent = CASE WHEN user_agent IS NULL THEN NULL ELSE 'Mozilla/5.0 (anonymise)' END;


-- ── Relation client ─────────────────────────────────────────────────────────
UPDATE reservations SET
    guest_name       = anon_label('Client', guest_name),
    guest_email      = anon_email(guest_email),
    guest_phone      = anon_phone(guest_phone),
    notes            = CASE WHEN notes IS NULL THEN NULL ELSE 'note anonymisee' END,
    message          = CASE WHEN message IS NULL THEN NULL ELSE 'message anonymise' END,
    date_preferences = CASE WHEN date_preferences IS NULL THEN NULL ELSE 'preferences anonymisees' END;

UPDATE client_forms SET
    fullname     = anon_label('Client', fullname),
    phone        = anon_phone(phone),
    requirements = CASE WHEN requirements IS NULL THEN NULL ELSE 'besoins anonymises' END,
    message      = CASE WHEN message IS NULL THEN NULL ELSE 'message anonymise' END;

UPDATE contact_messages SET
    name               = anon_label('Visiteur', name),
    email              = anon_email(email),
    phone              = anon_phone(phone),
    subject            = 'sujet anonymise',
    message            = 'message anonymise',
    consent_user_agent = CASE WHEN consent_user_agent IS NULL THEN NULL ELSE 'Mozilla/5.0 (anonymise)' END;

UPDATE notifications SET
    title = anon_label('Notification', title),
    message = 'message anonymise',
    data = CASE WHEN data IS NULL THEN NULL
                ELSE ((data::jsonb) - 'client_name'
                      || jsonb_build_object('client_name', 'Client anonymise'))::json END;


-- ── RGPD ────────────────────────────────────────────────────────────────────
-- subject_value porte l'email ou le telephone recherche.
UPDATE privacy_audit_logs SET
    subject_value = CASE WHEN subject_type = 'email' THEN anon_email(subject_value)
                         WHEN subject_type = 'phone' THEN anon_phone(subject_value)
                         ELSE subject_value END,
    ip_address    = CASE WHEN ip_address IS NULL THEN NULL ELSE '203.0.113.1' END;

UPDATE privacy_exports SET
    subject_value = CASE WHEN subject_type = 'email' THEN anon_email(subject_value)
                         WHEN subject_type = 'phone' THEN anon_phone(subject_value)
                         ELSE subject_value END;


-- ── Infrastructure : vidée, régénérable ─────────────────────────────────────
-- sessions et jetons portent des IP, des user agents et des accreditations
-- vivantes. cache contient des URL signees. Tout se regenere.
TRUNCATE sessions, personal_access_tokens, password_reset_tokens, cache, cache_locks;

-- failed_jobs : le payload sérialisé contient les arguments du job, donc des
-- adresses. On ne garde que displayName, seule donnée lue par la sonde de file
-- et par le bloc N du relevé.
UPDATE failed_jobs SET
    payload   = json_build_object('displayName', payload::json->>'displayName')::text,
    exception = 'exception anonymisee';

UPDATE jobs SET payload = json_build_object('displayName', payload::json->>'displayName')::text;


DROP FUNCTION IF EXISTS anon_email(text);
DROP FUNCTION IF EXISTS anon_phone(text);
DROP FUNCTION IF EXISTS anon_label(text, text);
DROP FUNCTION IF EXISTS anon_digits(text, int);
DROP FUNCTION IF EXISTS anon_code(text, int);
DROP FUNCTION IF EXISTS anon_hash(text);

COMMIT;

ANALYZE;
