"""Pune tokenul Conex în wp-config-ul unui site de pe server, fără să-l afișeze nicăieri.

Diferența față de `token-local.py`: acolo tokenul se dă lui WP-CLI ca argument, ceea ce e în regulă pe calculatorul
tău. Pe un server partajat, argumentele proceselor se pot citi din `/proc/<pid>/cmdline`, deci tokenul merge prin
intrarea standard a unui PHP rulat pe server, nu prin linia de comandă și nici prin variabile de mediu.

Scriptul face întâi o copie a lui `wp-config.php`, apoi adaugă constanta înainte de linia „stop editing”. Dacă
constanta există deja, nu atinge nimic.

Rulare:
    python -I tools/token-live.py D:/Work/Repos/cez-auto/.env --gazda cezauto
"""
import argparse
import pathlib
import re
import subprocess
import sys

sys.stdout.reconfigure(encoding='utf-8', errors='replace')

p = argparse.ArgumentParser()
p.add_argument('env', help='Calea către fișierul .env cu tokenul.')
p.add_argument('--gazda', default='cezauto', help='Aliasul SSH din ~/.ssh/config.')
p.add_argument('--cale', default='public_html', help='Unde stă WordPress pe server.')
args = p.parse_args()

text = pathlib.Path(args.env).read_text(encoding='utf-8-sig')
m = re.search(r'token[^=]*=\s*["\']?([A-Za-z0-9\-]+)["\']?', text, re.I)
if not m:
    sys.exit('Nu găsesc tokenul în ' + args.env)
token = m.group(1)

# Codul rulează pe server. Tokenul vine pe stdin, deci nu apare în `ps`.
php = r'''
$tok = trim( stream_get_contents( STDIN ) );
if ( '' === $tok ) { fwrite( STDERR, "Nu am primit niciun token.\n" ); exit( 1 ); }

$cale = 'wp-config.php';
$src  = file_get_contents( $cale );
if ( false === $src ) { fwrite( STDERR, "Nu pot citi wp-config.php.\n" ); exit( 1 ); }

if ( false !== strpos( $src, 'CCS_API_TOKEN' ) ) { echo "Constanta exista deja, nu am schimbat nimic.\n"; exit( 0 ); }

$marker = "/* That's all, stop editing! Happy publishing. */";
if ( false === strpos( $src, $marker ) ) { fwrite( STDERR, "Nu gasesc linia 'stop editing' in wp-config.php.\n" ); exit( 1 ); }

$copie = 'wp-config.php.inainte-de-ccs';
if ( ! file_exists( $copie ) && ! copy( $cale, $copie ) ) { fwrite( STDERR, "Nu pot face copia lui wp-config.php.\n" ); exit( 1 ); }

$linie = "/* Tokenul API Conex, folosit de pluginul Conex Conect Sync. */\n"
       . "define( 'CCS_API_TOKEN', '" . addslashes( $tok ) . "' );\n\n";
$nou = str_replace( $marker, $linie . $marker, $src );

if ( ! file_put_contents( $cale, $nou ) ) { fwrite( STDERR, "Nu pot scrie wp-config.php.\n" ); exit( 1 ); }
echo "Constanta CCS_API_TOKEN a fost adaugata. Copie: $copie\n";
'''

comanda = 'cd ' + args.cale + " && php -r " + "'" + php.replace("'", "'\\''") + "'"
rezultat = subprocess.run(
    ['ssh', args.gazda, comanda],
    input=token,
    capture_output=True,
    text=True,
    encoding='utf-8',
)

# Orice ar ieși, tokenul nu ajunge pe ecran.
iesire = (rezultat.stdout + rezultat.stderr).replace(token, '<token>')
for linie in iesire.splitlines():
    if 'post-quantum' in linie or 'WARNING' in linie or 'store now' in linie or 'openssh.com' in linie:
        continue
    print(linie)

sys.exit(rezultat.returncode)
