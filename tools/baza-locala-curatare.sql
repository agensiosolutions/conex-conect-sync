-- Curățarea bazei locale după importul dump-ului de producție.
--
-- Dump-ul aduce tabela `options`, de care WordPress are nevoie ca să pornească, dar care conține și secretele
-- magazinului: chei de procesator de plăți, API de facturare, parole SMTP, chei de curier. Pe un mediu de
-- dezvoltare nu au ce căuta, iar dacă rămân acolo, un plugin care pornește din greșeală poate chema API-uri reale.
--
-- Rulare, după import:
--   wp db query < tools/baza-locala-curatare.sql
--
-- Înlocuiește 5BcNNR_ cu prefixul tău dacă lucrezi la alt magazin.

-- Secretele cunoscute, șterse după tipar. Pluginurile le recreează goale la nevoie.
DELETE FROM 5BcNNR_options WHERE option_name LIKE '%_api_key%';
DELETE FROM 5BcNNR_options WHERE option_name LIKE '%_api_secret%';
DELETE FROM 5BcNNR_options WHERE option_name LIKE '%_secret_key%';
DELETE FROM 5BcNNR_options WHERE option_name LIKE '%_access_token%';
DELETE FROM 5BcNNR_options WHERE option_name LIKE '%_private_key%';
DELETE FROM 5BcNNR_options WHERE option_name LIKE 'woocommerce_netopia%';
DELETE FROM 5BcNNR_options WHERE option_name LIKE 'wc_oblio%';
DELETE FROM 5BcNNR_options WHERE option_name LIKE 'woocommerce_oblio%';
DELETE FROM 5BcNNR_options WHERE option_name LIKE 'wp_mail_smtp%';
DELETE FROM 5BcNNR_options WHERE option_name LIKE 'curiero%';
DELETE FROM 5BcNNR_options WHERE option_name LIKE 'wordfence%';
DELETE FROM 5BcNNR_options WHERE option_name LIKE '%license%';
DELETE FROM 5BcNNR_options WHERE option_name LIKE 'elementor_pro_license%';

-- Nimic nu trebuie să plece spre exterior de pe local.
UPDATE 5BcNNR_options SET option_value = 'no'  WHERE option_name = 'woocommerce_allow_tracking';
UPDATE 5BcNNR_options SET option_value = 'yes' WHERE option_name = 'blog_public' AND 1 = 0; -- lăsat explicit, se setează în wp-config

-- Plata și livrarea, oprite: pe local nu vrem să chemăm procesatoare reale.
UPDATE 5BcNNR_options SET option_value = REPLACE(option_value, 's:3:"yes"', 's:2:"no"')
  WHERE option_name LIKE 'woocommerce_%_settings' AND option_value LIKE '%"enabled"%';

-- Cron-ul WordPress nu trebuie să pornească joburi de sincronizare singur, pe o bază de test.
DELETE FROM 5BcNNR_options WHERE option_name = 'cron';
