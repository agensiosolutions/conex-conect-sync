"""Pune tokenul Conex în wp-config-ul site-ului local, fără să-l afișeze nicăieri.

Îl citește din `.env`-ul proiectului clientului și îl trimite direct lui WP-CLI. Tokenul nu trece prin linia de
comandă pe care o scrie omul, nu apare în ieșire și nu ajunge în repo.

Rulare:
    python -I tools/token-local.py D:/Work/Repos/cez-auto/.env
    python -I tools/token-local.py D:/Work/Repos/cez-auto/.env --site cezauto-dev
"""
import argparse
import pathlib
import re
import subprocess
import sys

sys.stdout.reconfigure(encoding='utf-8', errors='replace')

p = argparse.ArgumentParser()
p.add_argument('env', help='Calea către fișierul .env cu tokenul.')
p.add_argument('--site', default='cezauto-dev', help='Numele site-ului din Local.')
args = p.parse_args()

text = pathlib.Path(args.env).read_text(encoding='utf-8-sig')
m = re.search(r'token[^=]*=\s*["\']?([A-Za-z0-9\-]+)["\']?', text, re.I)
if not m:
    sys.exit('Nu găsesc tokenul în ' + args.env)
token = m.group(1)

wrapper = pathlib.Path(__file__).resolve().parent / 'wp-local.sh'
rezultat = subprocess.run(
    ['sh', str(wrapper), 'config', 'set', 'CCS_API_TOKEN', token, '--type=constant'],
    capture_output=True,
    text=True,
    env={'CCS_SITE': args.site, 'PATH': __import__('os').environ.get('PATH', ''),
         'HOME': __import__('os').environ.get('HOME', ''),
         'USERPROFILE': __import__('os').environ.get('USERPROFILE', '')},
)

# Orice ar scrie WP-CLI, nu lăsăm tokenul să iasă pe ecran.
iesire = (rezultat.stdout + rezultat.stderr).replace(token, '<token>')
print(iesire.strip())
sys.exit(rezultat.returncode)
