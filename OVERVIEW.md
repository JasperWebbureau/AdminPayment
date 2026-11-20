# Algemeen betalingsoverzicht

`/Flexgrid/AdminPayments/index` verschijnt als **Betalingen** in de gemeenschappelijke administratie. Toegang vereist Flexgrid-niveau 5. De pagina toont per gekozen kalendermaand de ontvangsten, negatieve terugbetalingen en het saldo, afzonderlijk per valuta. De tabel bevat de laatste 100 betalingen van die maand en benoemt de bron, omschrijving en referentie. Het overzicht muteert geen betalingen en telt iedere payment één keer, onafhankelijk van het aantal allocaties.

Het leespad is `GetPaymentOverview(TenantContext, PaymentOverviewRepositoryInterface)::execute('YYYY-MM')`. De use-case valideert de maand en geeft een halfopen datumbereik aan de bestaande PDO-adapter door. Iedere query vereist de expliciete tenant. De Payment-kern kent geen concrete Booking- of Webshop-class; alle bronnen worden op dezelfde manier gelezen. Nieuwe consumers kunnen de bestaande Payment-/Allocation-modellen en hun publieke repositorycontract gebruiken voor een eigen integration adapter.

Ontvangen bedragen zijn geen factuuromzet. De factuurindicatoren houden hun eigen betekenis. Het overzicht maakt geen factuur, refund of nieuwe ontvangst aan en heeft geen nieuwe tabellen nodig.

`tests/PaymentOverviewTest.php` controleert tenantdoorgifte, maandvalidatie en jaar-/schrikkeljaargrenzen zonder database. `App/Bookings/tests/run.php` controleert in de projectintegratie ook de echte tenant-/maandfiltering en gezamenlijke booking-/webshoptotalen met positieve ontvangsten en negatieve refunds, uitsluitend met tijdelijke tabellen en rollback.
