#!/bin/sh
# Pregătește o copie locală a bazei unui magazin, pentru dezvoltare.
#
# Ia doar ce trebuie ca să lucrezi la sincronizarea de produse: produsele, categoriile, atributele și setările.
# NU ia date personale: comenzi, clienți, sesiuni, recenzii, intrări de formulare, jurnale de email, tabelele
# Wordfence (care conțin IP-uri, secrete 2FA și passkeys) și conturile de utilizator.
#
# Structura se ia pentru TOATE tabelele, ca WordPress să pornească fără erori; datele doar pentru lista albă.
# După import rulează `baza-locala-curatare.sql`, care șterge secretele rămase în `options`.
#
# Rulare:  sh tools/baza-locala.sh <alias-ssh> <cale-wordpress> <fisier-iesire.sql>
# Exemplu: sh tools/baza-locala.sh cezauto /home/cezauto/public_html ~/dumps/cezauto-dev.sql

set -eu

ALIAS="${1:?alias SSH}"
CALE="${2:?calea catre WordPress}"
IESIRE="${3:?fisierul de iesire}"

# Tabelele ale căror DATE se copiază. Restul rămân goale, doar cu structură.
# Fără prefix: scriptul îl află singur de pe server.
TABELE_CU_DATE="
posts postmeta
terms termmeta term_taxonomy term_relationships
options
woocommerce_attribute_taxonomies
wc_product_meta_lookup wc_product_attributes_lookup wc_category_lookup
wc_tax_rate_classes woocommerce_tax_rates woocommerce_tax_rate_locations
woocommerce_shipping_zones woocommerce_shipping_zone_methods woocommerce_shipping_zone_locations
"

# Lista de mai sus e scrisă pe mai multe rânduri ca să se citească; aici o aducem pe un singur rând, pentru că
# newline-urile ar rupe comanda trimisă prin ssh.
TABELE_CU_DATE=$(echo $TABELE_CU_DATE)

echo "Pregătesc dump-ul de pe $ALIAS ..."

# Totul se face pe server, într-o singură sesiune: credențialele sunt citite din wp-config cu `wp config get`,
# folosite și uitate. Nu se afișează și nu ajung niciodată local.
# Capcana Hosterion: DB_HOST e `localhost:3306`, iar mysqldump are nevoie de gazdă și port separat.
ssh "$ALIAS" "
set -eu
cd '$CALE'

PREFIX=\$(wp config get table_prefix --quiet)
DB=\$(wp config get DB_NAME --quiet)
U=\$(wp config get DB_USER --quiet)
P=\$(wp config get DB_PASSWORD --quiet)
H=\$(wp config get DB_HOST --quiet)
GAZDA=\${H%%:*}
PORT=\${H#*:}
# Atenție: \`[ test ] && x\` ca ultimă comandă a liniei întoarce 1 când testul e fals, iar cu \`set -e\` oprește
# scriptul. Aici testul e fals exact în cazul normal (gazdă cu port), deci trebuie \`if\`, nu \`&&\`.
if [ \"\$PORT\" = \"\$H\" ]; then PORT=3306; fi

CU_DATE=''
for t in $TABELE_CU_DATE; do CU_DATE=\"\$CU_DATE \${PREFIX}\$t\"; done

TMP=\$(mktemp)
# 1. structura tuturor tabelelor, ca WordPress să nu dea erori de tabel lipsă
mysqldump -h \"\$GAZDA\" -P \"\$PORT\" -u \"\$U\" -p\"\$P\" --no-data --skip-add-locks --single-transaction \"\$DB\" > \"\$TMP\"
# 2. datele, doar pentru lista albă
mysqldump -h \"\$GAZDA\" -P \"\$PORT\" -u \"\$U\" -p\"\$P\" --no-create-info --skip-add-locks --single-transaction \
  --default-character-set=utf8mb4 \"\$DB\" \$CU_DATE >> \"\$TMP\"

gzip -c \"\$TMP\"
rm -f \"\$TMP\"
" 2>"$IESIRE.err" | gunzip > "$IESIRE"

if [ ! -s "$IESIRE" ]; then
	echo "Dump-ul a ieșit gol. Erorile de pe server:"
	grep -v 'WARNING\|vulnerable\|openssh.com\|^\*\*\|Using a password' "$IESIRE.err" | head -20
	exit 1
fi
rm -f "$IESIRE.err"

echo "Gata: $IESIRE ($(du -h "$IESIRE" | cut -f1))"
echo "Urmează: importul local, apoi tools/baza-locala-curatare.sql"
