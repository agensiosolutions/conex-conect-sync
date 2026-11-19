# conex-conect-sync

Plugin WooCommerce care sincronizează un magazin cu **Conex Conect**, API-ul Conex Distribution.

## De ce există

Partenerii Conex primesc un feed FTP cu produse, prețuri și stocuri. La Cez Auto, feed-ul s-a oprit din 26 noiembrie
2025 fără ca cineva să afle: importul manual rula lună de lună pe un fișier înghețat, raportând „zero modificări”.
Rezultatul, măsurat în octombrie 2026: 2.176 de produse afișate ca disponibile fără să fie, 341 ascunse deși erau în
stoc, 63 vândute sub cost.

API-ul rezolvă asta: răspunde permanent, dă portofoliul complet (21.002 produse la Cez Auto, față de 6.825 în feed) și
se poate interoga la 30 de minute.

## Clienți

| Magazin | Stare | Portofoliu | Potrivire |
|---|---|---|---|
| Cez Auto (cezauto.ro) | ofertă acceptată 17.11.2026, 4.000 lei | 21.002 produse | `codEAN` exact, zero ambiguități |
| Piese Total (piesetotal.ro) | ofertă trimisă 24.09.2026, fără răspuns | 8.919 produse | fuzzy, aliasuri de brand, 28,8% legate |

Fiecare partener are alt portofoliu pe același API, cu alt token.

## Acces, fără secrete

- Cod: acest repo. Plugin în `plugin/`, se livrează ca arhivă din `dist/`.
- Site-urile și aliasurile SSH: `flota.json`.
- Tokenul Conex: în admin sau în `CCS_API_TOKEN` din `wp-config.php`. Niciodată în repo.
- Serverul Cez Auto: `ssh cezauto` (Hosterion, cPanel, PHP 8.1.34, WordPress în `public_html`).
  GD și Imagick au WebP și AVIF; 446 GB liberi pe volum.

## Decizii mari

- **19.11.2026:** repo separat, nu în `cez-auto`, pentru că pluginul e produs, nu lucrare pentru un client.
- **19.11.2026:** dezvoltare locală pe o copie a bazei Cez Auto; staging înainte de live.
- **19.11.2026:** stil `includes/` cu prefix `ccs_`, ca la `agensio-consent`, în locul PSR-4 din `PLAN.md`.
- **Construim pentru Cez Auto, cu interfețele deschise.** Mașinăria de potrivire fuzzy de care are nevoie Piese Total
  se adaugă fără rescriere, dar nu se construiește pe bugetul Cez Auto.
