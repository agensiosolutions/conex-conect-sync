#!/bin/sh
# WP-CLI pe site-ul local din Local by Flywheel, fără „Open site shell”.
#
# Local nu pune `wp` în PATH și nu expune un CLI pentru site-uri, dar are tot ce trebuie:
#   - PHP-ul lui, în lightning-services (PHP de sistem n-are extensia mysqli)
#   - php.ini-ul randat al site-ului, în run/<id>/conf/php/php.ini (fără el, PHP nu încarcă mysqli)
#   - wp-cli.phar, în resources/extraResources
#
# Rulare:  sh tools/wp-local.sh <comanda wp>
# Exemplu: sh tools/wp-local.sh plugin list
#
# Dacă Local repornește site-ul cu alt id de run, îl află singur din folderul care pomenește site-ul.

set -eu

SITE_NUME="${CCS_SITE:-cezauto-dev}"
SITE="$HOME/Local Sites/$SITE_NUME/app/public"
PHP=$(ls -d "$HOME"/AppData/Roaming/Local/lightning-services/php-8.*/bin/win64/php.exe 2>/dev/null | tail -1)
WP="$HOME/AppData/Local/Programs/Local/resources/extraResources/bin/wp-cli/wp-cli.phar"

[ -d "$SITE" ] || { echo "Nu găsesc site-ul: $SITE"; exit 1; }
[ -x "$PHP" ] || { echo "Nu găsesc PHP-ul din Local."; exit 1; }
[ -f "$WP" ]  || { echo "Nu găsesc wp-cli.phar din Local."; exit 1; }

# php.ini-ul randat al site-ului, din folderul de run care pomenește numele site-ului.
INI=''
for d in "$HOME"/AppData/Roaming/Local/run/*/; do
	if [ -f "$d/conf/php/php.ini" ] && grep -rqs "$SITE_NUME" "$d/conf" 2>/dev/null; then
		INI="$d/conf/php/php.ini"
		break
	fi
done
[ -n "$INI" ] || { echo "Site-ul pare oprit: nu găsesc php.ini randat. Pornește-l din Local."; exit 1; }

# `wp db import`, `db query` și `db export` cheamă binarele clientului MySQL, pe care Local nu le pune în PATH.
# Fără ele, WP-CLI se oprește la „Failed to get current SQL modes”.
for b in "$HOME"/AppData/Roaming/Local/lightning-services/mysql-8.*/bin/win64/bin "$HOME"/AppData/Roaming/Local/lightning-services/mariadb-*/bin/win64/bin; do
	[ -d "$b" ] && PATH="$b:$PATH"
done
export PATH

# Imagick nu e livrat de Local pe Windows; avertismentul lui nu ne interesează, GD are deja WebP.
exec "$PHP" -c "$INI" "$WP" --path="$SITE" "$@" 2>&1 | grep -v "php_imagick.dll"
