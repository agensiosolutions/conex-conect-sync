# -*- coding: utf-8 -*-
"""
Descarcă iconițele Lucide o dată și scrie `plugin/includes/iconite.php`.

Datele se aduc separat, cu curl, nu din script: API-ul Iconify răspunde 403 la cereri fără User-Agent, iar un
fișier local face generarea repetabilă și fără rețea.

    curl -s "https://api.iconify.design/lucide.json?icons=archive,arrow-down,arrow-up,check,circle-check,\
circle-dot,circle-help,circle-x,download,eye,eye-off,folder-tree,info,layout-dashboard,minus,refresh-cw,\
rotate-ccw,shopping-cart,shuffle,tag,triangle-alert,users,x" -o lucide.json

    python -I tools/adu-iconite.py lucide.json
"""
import json
import sys

if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8')

# Cheia e numele nostru, folosit deja în tot panoul; valoarea e numele din Lucide. Păstrăm numele noastre pentru
# că ele descriu rolul din panou, nu desenul: un alt pachet de iconițe se schimbă rescriind numai fișierul ăsta.
HARTA = {
    'update':         'refresh-cw',
    'image-rotate':   'rotate-ccw',
    'tag':            'tag',
    'archive':        'archive',
    'download':       'download',
    'category':       'folder-tree',
    'admin-users':    'users',
    'hidden':         'eye-off',
    'visibility':     'eye',
    'editor-help':    'circle-help',
    'warning':        'triangle-alert',
    'yes':            'check',
    'yes-alt':        'circle-check',
    'dismiss':        'circle-x',
    'no-alt':         'x',
    'minus':          'minus',
    'info-outline':   'info',
    'arrow-up-alt':   'arrow-up',
    'arrow-down-alt': 'arrow-down',
    'marker':         'circle-dot',
    'randomize':      'shuffle',
    'cart':           'shopping-cart',
    'admin-home':     'layout-dashboard',
    # Iconițele de navigație stau în `admin.php`, nu lângă celelalte. Le-am ratat la primul inventar tocmai
    # fiindcă am căutat într-un singur fișier.
    'dashboard':      'layout-dashboard',
    'list-view':      'list',
}

SABLON = '''<?php
/**
 * Iconițele panoului.
 *
 * Lucide, 23 de iconițe, lipite aici ca SVG. Nu de pe un CDN: o pagină de admin care așteaptă un server străin
 * ca să își deseneze iconițele se strică atunci când serverul ăla are o zi proastă, iar magazinul n-are de ce
 * să depindă de el. Cele 23 de iconițe ocupă 5,4 KB.
 *
 * Înainte foloseam Dashicons, setul livrat cu WordPress. E din 2012, desenat la 20px, și se vedea: eticheta de
 * preț era o formă plină fără contur, iar categoria un dosar generic. Lucide e desenat pe o grilă de 24 cu
 * contur de 2px și rămâne citibil la 14px, mărimea la care îl folosim cel mai des.
 *
 * Cheile sunt numele vechi, de la Dashicons. Nu e lene: ele descriu rolul din panou, nu desenul, deci un alt
 * pachet se schimbă rescriind numai fișierul ăsta, fără să umbli în locurile de apel.
 *
 * Generat cu `tools/adu-iconite.py`, din API-ul Iconify. Nu se scrie de mână.
 *
 * @package conex-conect-sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Conținutul fiecărei iconițe, fără eticheta `svg`.
 *
 * @return array nume => markup.
 */
function ccs_iconite() {
\treturn array(
__ICONITE__
\t);
}

/**
 * O iconiță, gata de pus în pagină.
 *
 * `currentColor` peste tot, deci iconița ia culoarea textului din jur și nu trebuie colorată separat nicăieri.
 * `aria-hidden`, fiindcă toate iconițele noastre stau lângă un text care spune deja același lucru.
 *
 * @param string $nume   Numele iconiței.
 * @param int    $marime Latura, în pixeli.
 * @param string $clasa  Clase în plus.
 * @return string HTML, sau gol dacă iconița nu există.
 */
function ccs_icon( $nume, $marime = 16, $clasa = '' ) {
\t$iconite = ccs_iconite();
\tif ( ! isset( $iconite[ $nume ] ) ) {
\t\treturn '';
\t}

\treturn sprintf(
\t\t'<svg class="ccs-icon%1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%3$s</svg>',
\t\t$clasa ? ' ' . esc_attr( $clasa ) : '',
\t\t(int) $marime,
\t\t$iconite[ $nume ]
\t);
}
'''


def main():
    cale = sys.argv[1] if len(sys.argv) > 1 else 'lucide.json'
    with open(cale, encoding='utf-8') as f:
        date = json.load(f)

    icons = date.get('icons', {})
    aliases = date.get('aliases', {})

    def corp(nume, adanc=0):
        if adanc > 5:
            raise SystemExit('alias în cerc: ' + nume)
        if nume in icons:
            return icons[nume]['body']
        if nume in aliases:
            return corp(aliases[nume]['parent'], adanc + 1)
        raise SystemExit('lipsește iconița: ' + nume)

    linii = []
    for al_nostru in sorted(HARTA):
        markup = corp(HARTA[al_nostru]).replace("'", "\\'")
        linii.append("\t\t'%s' => '%s'," % (al_nostru, markup))

    php = SABLON.replace('__ICONITE__', '\n'.join(linii))

    iesire = 'D:/Work/Repos/conex-conect-sync/plugin/includes/iconite.php'
    with open(iesire, 'w', encoding='utf-8', newline='\n') as f:
        f.write(php)

    print('%d iconițe scrise, %.1f KB' % (len(HARTA), len(php) / 1024))
    return 0


if __name__ == '__main__':
    sys.exit(main())
