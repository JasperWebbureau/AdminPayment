# Changelog

## 0.6.0 - 2026-09-28

- Optionele Mollie-betaallink per definitieve, onbetaalde factuur toegevoegd via de consumer-owned Invoice-integratie.
- Publieke webhookadapter controleert betaaldstatus, linklidmaatschap, valuta en exact bedrag bij Mollie voordat een idempotente AdminPayment-ontvangst wordt geboekt.
- Zonder aparte facturatie-API-sleutel blijft de functie uit; boekingsbetalingen en generieke Invoice-kern blijven ongewijzigd.

## 0.5.0 - 2026-09-20

- Handmatige Banking-zoekadapter toegevoegd voor tenantgebonden definitieve facturen.
- Zoekresultaten tonen factuurnummer, klant, datum, totaal en het na allocaties actuele openstaande bedrag.
- Deelbetalingen zijn selecteerbaar met waarschuwing; een bankbedrag boven het openstaande bedrag wordt niet koppelbaar teruggegeven en blijft door de processor geblokkeerd.

## 0.4.0 - 2026-09-20

- Banking-matchprocessor toegevoegd voor expliciet bevestigde factuurvoorstellen.
- De processor hercontroleert het actuele openstaande bedrag onder een row lock en voorkomt bankgestuurde overbetaling.
- De ontvangst gebruikt de publieke banktransactie-id als externe idempotentiesleutel, maakt één allocation en synchroniseert de factuurstatus.
- Payment, allocation, Invoice-status en Banking-status delen door de geneste transactieadapter één commit of rollback.

## 0.3.0 - 2026-09-19

- Optionele Banking-kandidaatadapter toegevoegd voor positieve banktransacties en openstaande facturen.
- Openstaand bedrag houdt rekening met bestaande Payment-allocaties.
- Bedrag-, factuurnummer- en klantnaamherkenning leveren een transparante betrouwbaarheid en reden op.
- De adapter leest uitsluitend en voert nog geen automatische betaling of allocatie uit.

## 0.2.0 - 2026-09-18

- Declaratieve AJAX-betaalregistratie toegevoegd aan het definitieve factuurdetail, zonder eigen JavaScripttransport.
- Openstaand bedrag wordt server-side voorgesteld; ontvangst en terugbetaling delen dezelfde gevalideerde use-case.
- Tenantgebonden betaalhistorie toegevoegd via de gedeelde Flexgrid `TableRenderer`.
- Factuurtotaal, ontvangen bedrag, openstaand/te veel ontvangen en actuele betaalstatus worden na registratie gedeeltelijk vernieuwd.
- AdminPayment levert een optionele `InvoiceDetailExtension`; AdminInvoice importeert nog steeds geen concrete Payment-class.
- Gecrediteerde facturen tonen historie maar accepteren geen nieuwe betaling.
- Schema-, PDO-, UI-, modulegrens- en PHP 7.3-tests uitgebreid en geslaagd.

## 0.1.0 - 2026-09-18

- Zelfstandige `Payment`- en `PaymentAllocation`-domeinmodellen toegevoegd.
- Bedragen gebruiken signed `BIGINT` minor units en allocaties gebruiken opaque publieke target-id's.
- Ontvangsten, gedeeltelijke betalingen, meerdere betalingen, overbetaling en begrensde refunds zijn gemodelleerd.
- `source + external_id` ondersteunt tenantgebonden idempotente registratie.
- Expliciete `RegisterInvoicePayment`-integratie toegevoegd bovenop het publieke Invoice-betaalcontract.
- Paymentopslag, allocation en Invoice-betaalstatus delen één transactie.
- Autowire-records, PDO-adapter, frameworkvrije tests en voorbereide schema-/PDO-integratietests toegevoegd.
