# -*- coding: utf-8 -*-
"""
Face lista de hotărâri pentru `wp ccs curata`, din măsurători.

De ce e un script și nu o funcție din plugin: datele vin din Search Console, care cere alt cont și altă
autentificare decât site-ul. Măsurătoarea se face din afară, se uită cineva peste ea, și abia apoi intră în plugin
ca listă de id-uri. Curățarea nu trebuie să fie automată, fiindcă se sprijină pe o judecată, nu pe o regulă.

Intrările, toate JSON, scoase cu `wp eval-file` de pe site și cu MCP-ul Search Console:

  --grupuri   [{grup, id, sku, slug, stoc}]     produsele fără furnizor, din `ccs_retragere_grupuri()`
  --gsc       {rows: [{page, clicks, impressions}]}   16 luni de Search Console, pe pagini
  --comenzi   {id_produs: câte comenzi}
  --recenzii  {id_produs: câte recenzii}

Regula, așa cum a fost hotărâtă pe 26 noiembrie 2026:

  - se șterg produsele rămase din feed-ul oprit care n-au avut nicio afișare în Google în 16 luni, nu s-au vândut
    niciodată și n-au recenzii;
  - se ascund din catalog cele care au măcar o afișare, o comandă sau o recenzie: adresa rămâne vie, dar ies din
    magazin;
  - produsele introduse manual de echipă nu se ating deloc, oricât de invizibile ar fi: nu vin de la furnizor, iar
    hotărârea asupra lor e a magazinului.

Rulare:
  python -I tools/curatenie-lista.py --grupuri g.json --gsc gsc.json --comenzi c.json --recenzii r.json \
      --iesire curatenie.json
"""
import argparse
import collections
import json
import re
import sys


# Consola Windows e pe cp1252, iar diacriticele din rezumat o fac sa cada cu UnicodeEncodeError dupa ce
# fisierul a fost deja scris: pare ca scriptul a esuat, desi treaba era facuta.
if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8')


def incarca(cale):
    with open(cale, encoding='utf-8') as f:
        date = json.load(f)
    # Rezultatele MCP vin împachetate în {"result": "<json ca text>"}.
    if isinstance(date, dict) and isinstance(date.get('result'), str):
        date = json.loads(date['result'])
    return date


def pe_slug(gsc):
    randuri = gsc.get('rows', gsc) if isinstance(gsc, dict) else gsc
    iesire = {}
    for r in randuri:
        url = r.get('page') or (r.get('keys') or [''])[0]
        bucati = [b for b in re.sub(r'[?#].*$', '', url).split('/') if b]
        if not bucati:
            continue
        d = iesire.setdefault(bucati[-1], {'clicks': 0, 'impressions': 0})
        d['clicks'] += r.get('clicks', 0)
        d['impressions'] += r.get('impressions', 0)
    return iesire


def main():
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument('--grupuri', required=True)
    p.add_argument('--gsc', required=True)
    p.add_argument('--comenzi', default=None)
    p.add_argument('--recenzii', default=None)
    p.add_argument('--iesire', required=True)
    p.add_argument('--grup', default='din_feed', help='Grupul care se curăță. Implicit cele din feed-ul vechi.')
    a = p.parse_args()

    grupuri = incarca(a.grupuri)
    cautari = pe_slug(incarca(a.gsc))
    comenzi = {int(k): v for k, v in (incarca(a.comenzi) if a.comenzi else {}).items()}
    recenzii = {int(k): v for k, v in (incarca(a.recenzii) if a.recenzii else {}).items()}

    sterge, ascunde = [], []
    motive = collections.Counter()

    for prod in grupuri:
        if prod['grup'] != a.grup:
            continue
        pid = int(prod['id'])
        d = cautari.get(prod['slug'], {'clicks': 0, 'impressions': 0})
        tine = []
        if d['impressions']:
            tine.append('afișări')
        if comenzi.get(pid):
            tine.append('comandă')
        if recenzii.get(pid):
            tine.append('recenzie')

        if tine:
            ascunde.append(pid)
            motive['ascuns: ' + ' + '.join(tine)] += 1
        else:
            sterge.append(pid)
            motive['șters: nicio urmă'] += 1

    with open(a.iesire, 'w', encoding='utf-8') as f:
        json.dump({'sterge': sterge, 'ascunde': ascunde}, f)

    print('grup: %s' % a.grup)
    print('  de șters   %d' % len(sterge))
    print('  de ascuns  %d' % len(ascunde))
    print()
    for m, n in motive.most_common():
        print('  %-28s %d' % (m, n))
    print()
    print('scris în %s' % a.iesire)
    return 0


if __name__ == '__main__':
    sys.exit(main())
