# Mollie-factuurincasso

Deze optionele integratie gebruikt het publieke AdminInvoice-contract. De Payment-kern blijft onafhankelijk; Invoice importeert geen Mollie- of Payment-implementatie. De provider wordt ontdekt onder `Integration/Invoice/InvoiceCollectionExtension.php`. Slechts één incassoprovider mag actief zijn.

## Instellen

- Beide modules en de juiste tenant moeten actief zijn. Installeer de entities via een gecontroleerde Autowire-scan na databasebackup; review schema- en synchronisatierapporten.
- Tabellen: `admin_invoice_debit_customer` en `admin_invoice_direct_debit`; Invoice beheert zelf `admin_invoice_processing`. Unieke tenant-/factuursleutels en providerbetaling-ID's beschermen tegen dubbele verwerking.
- Gebruik bij voorkeur `__ADMIN_INVOICE_MOLLIE_KEY__` met een `test_`-key voor testen. Zonder aparte key wordt alleen een bestaande algemene `live_`-key gebruikt. Opgeslagen machtigingen en betalingen blijven gebonden aan profiel en test/live-modus.
- Activeer iDEAL en SEPA Direct Debit in hetzelfde Mollie-profiel. Configureer `administration_invoice_mollie_redirect_url` met een publieke HTTPS-bedankpagina.
- Webhookbasis: `__DOMAIN__/api/AdminInvoiceDirectDebit`, eventueel expliciet via `__ADMIN_INVOICE_MOLLIE_WEBHOOK_BASE_URL__`. Die volledige basis moet publiek bereikbaar zijn via HTTPS. De endpointadapter in `App/Api/AdminInvoiceDirectDebit.php` moet bij hergebruik in een ander project ook worden geïnstalleerd.
- **Factuurincasso** beheert de globale schakelaar (standaard uit) en aankondigingstermijn (standaard 14 dagen). Op **Klant bewerken** wordt de toestemmingslink gemaakt en gedeeld. De klant betaalt eenmalig EUR 0,01 via iDEAL; alleen een betaalde eerste betaling en geldige SEPA-machtiging schakelen die klant in.

## Verwerking en herstel

Concepten worden nooit geïncasseerd. Een definitieve factuur krijgt een exact bedrag en geplande datum. De aankondiging gaat naar het opgeslagen factuur-e-mailadres; een omgeleide live-mailcatchall wordt geweigerd. Pas na bevestigde verzending wordt de planning vrijgegeven. De PDF bevat geen betaallink of overschrijfinstructie. De datum respecteert zowel de aankondigingstermijn als de vervaldatum.

De dagtaak start opeisbare incasso's met een vaste idempotentiesleutel en claimt een factuur vóór de provider-aanroep. Vóór aanmaak worden machtiging, profiel, openstaand bedrag en concurrerende betaalroute gecontroleerd. Webhooks halen de betaling server-side bij Mollie op en verifiëren tenant, factuur, klant, machtiging, profiel, modus, bedrag en valuta. Ontvangsten en chargebacks worden via de bestaande Payment-use-case geboekt; een teruggedraaide chargeback krijgt een afzonderlijke correctie.

Een onbekende uitkomst wordt `Controle nodig`, niet automatisch herhaald. Het factuurpaneel kan uitsluitend een bestaand, overeenkomend Mollie-betaling-ID koppelen om die aanmaak te herstellen. Definitief mislukte incasso's worden niet automatisch opnieuw afgeschreven. Pauzeren blokkeert nog niet gestarte incasso's; het annuleert geen betaling die al bij Mollie/de bank loopt. Test/live wisselen migreert geen machtigingen.

De cent voor toestemming is bewust geen ontvangst op een maandfactuur. Verwerk dit eenmalige bedrag apart in de boekhouding. Geef vóór livegebruik duidelijk aan waarvoor toestemming wordt gevraagd en controleer de toepasselijke SEPA-afspraken. Een kortere aankondigingstermijn mag alleen worden ingesteld als die met de klant is afgesproken.

Worker: `php Flexgrid/Modules/AdminInvoice/bin/process-invoices.php`; deze verwerkt bestaande facturen en incasso's, maar maakt geen concepten. Projecten mogen dit combineren met hun eigen schemataak. In Central Rental doet `App/RentalAgency/bin/run-recurring-billing.php` beide.

Tests: `tests/InvoiceDirectDebitTest.php` gebruikt SQLite en een fake-provider; er worden geen echte betalingen aangemaakt. Test vervolgens de volledige testomgeving inclusief bereikbare webhook en daadwerkelijk ontvangen mail voordat live wordt ingeschakeld.

Bronnen: [Mollie recurring payments](https://docs.mollie.com/docs/recurring-payments), [Create payment](https://docs.mollie.com/reference/create-payment), [Get mandate](https://docs.mollie.com/reference/get-mandate).
