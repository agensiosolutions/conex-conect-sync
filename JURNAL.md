# Jurnal conex-conect-sync

## 19 noiembrie 2026

Prima zi de cod. Oferta Cez Auto a fost acceptată pe 17 noiembrie, deci pluginul are primul client plătitor.

**Ce s-a hotărât.** Repo separat, nu în proiectul Cez Auto: pluginul e produs, nu lucrare. Dezvoltare locală pe o
copie a bazei Cez Auto, pentru că toată valoarea stă în potrivirea datelor reale. Stil de cod ca la `agensio-consent`,
cu `includes/` și prefix `ccs_`, nu PSR-4 cum propunea `PLAN.md`: consecvența cu celălalt plugin al agenției
cântărește mai mult decât structura din plan, iar Iulian le întreține pe amândouă.

**Răsturnarea de plan.** `PLAN.md` e scris cu Piese Total ca prim client, dar ei n-au răspuns după trei runde de
follow-up, iar Cez Auto a acceptat. Cele două magazine cer lucruri foarte diferite: la Piese Total potrivirea cere
fuzzy matching, aliasuri de brand și ecran de revizuire, la Cez Auto e `codEAN` exact cu zero ambiguități. Construim
pentru Cez Auto, dar cu interfețele deschise, ca mașinăria de la Piese Total să se adauge fără rescriere.

**Ce s-a scris.** Scheletul pluginului, clientul de API, setările cu regula de preț și trei comenzi WP-CLI. Clientul
de API conține tot ce am măsurat empiric pe API-ul real în octombrie, cu explicația lângă fiecare regulă, pentru că
niciuna nu e în documentație.

`Lecție:` Când un furnizor îți dă un API, măsoară-i comportamentul înainte să construiești pe el, la ore diferite și
pe mai multe zile. La Conex, documentația nu spunea niciuna dintre cele cinci reguli care contează: `total` nu e
numărul de rânduri, o pagină peste final dă 404 în loc de listă goală, endpoint-urile secundare răspund doar noaptea,
rata de eșec crește pe parcursul nopții (0% la 19:04, 100% la 21:52 pe cel mai greu endpoint), iar un parametru de
filtrare schimbă capitalizarea cheilor. Simptomul după care recunoști nevoia: un import care raportează constant
„zero modificări” sau un `total` care nu se potrivește cu ce numeri tu în răspuns.

`Lecție:` Într-un import de la furnizor, un fetch eșuat nu trebuie confundat niciodată cu „datele au dispărut”. Dacă
o pagină nu vine, întreaga preluare trebuie să eșueze fără să scrie nimic. Altfel o eroare 500 a furnizorului la ora
3 noaptea trece produsele pe stoc zero în masă, iar magazinul se golește singur. Simptomul: cod de sincronizare care
calculează diferențe între „ce am primit” și „ce era”, fără să verifice întâi că a primit tot.

`Lecție:` Adaosul procentual singur se comportă prost la produsele ieftine. Un clips de 0,22 lei cost ajunge la 1 leu,
adică +359% adaos, dar 0,78 lei marjă brută, sub costul de procesare a comenzii. Regula corectă e
`max(cost × adaos, cost + marjă_minimă_lei, preț_minim)`. Pe 2.493 de produse măsurate, o marjă minimă de 4 lei
rezolvă toate cele 255 de cazuri problematice și costă 543 de lei, adică 0,13% din valoarea catalogului. Simptomul:
produse cu adaos procentual uriaș și preț absolut derizoriu.
