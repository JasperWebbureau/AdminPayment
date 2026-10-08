<?php $h = static function ($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }; ?>
<section class="admin-page">
    <div class="admin-page-header"><div><h1>Factuurincasso</h1><p>Incasseer goedgekeurde facturen nadat de klant toestemming heeft gegeven en de incasso is aangekondigd.</p></div></div>
    <grid>
        <div class="panel" style="--cw:12">
            <div class="panel__header"><h3>Geplande en uitgevoerde incasso’s</h3></div>
            <div class="panel__body"><?=new \Flexgrid\Html\Table\TableRenderer($table)?></div>
        </div>
        <div class="panel" style="--cw:6;--cw-sm:12">
            <div class="panel__header"><h3>Incassovoorkeuren</h3></div>
            <div class="panel__body">
                <p>Een incassomachtiging stel je per klant in op de klantpagina. Incasso begint nooit voor conceptfacturen. De factuurmail bevat het bedrag en de geplande datum.</p>
                <form ajax="true" action="<?=$h($actions['save'])?>" method="post">
                    <label class="admin-field" style="--cw:12"><span>Automatische incasso actief</span><input type="checkbox" name="enabled" value="1"<?=$enabled ? ' checked' : ''?>><small>Uit pauzeert nieuwe en nog niet gestarte incasso’s. Een betaling die al bij de bank loopt, wordt hiermee niet geannuleerd.</small></label>
                    <label class="admin-field" style="--cw:12"><span>Dagen tussen aankondiging en incasso</span><input type="number" name="notice_days" min="1" max="60" required value="<?=$h($days)?>"><small>Standaard 14 dagen. Gebruik een kortere termijn alleen als dat met de klant is afgesproken. De factuurvervaldatum wordt ook gerespecteerd.</small></label>
                    <div style="--cw:12"><button class="button button-publish" type="submit">Incassovoorkeuren opslaan</button></div>
                </form>
            </div>
        </div>
        <div class="panel" style="--cw:6;--cw-sm:12">
            <div class="panel__header"><h3>Verwerking</h3></div>
            <div class="panel__body">
                <?php if (!$configured) { ?><p>Configureer eerst de Mollie-key voor factuurincasso en activeer iDEAL en SEPA Direct Debit in hetzelfde Mollie-profiel.</p>
                <?php } elseif (!$ready) { ?><p>Controleer de incassotabellen via de gecontroleerde Autowire-scan en stel een publiek HTTPS-adres in voor de incassowebhook.</p>
                <?php } else { ?>
                <p>De dagtaak controleert betaalstatussen en start aangekondigde incasso’s zodra hun datum is bereikt. Mislukte of onduidelijke incasso’s worden niet automatisch opnieuw gestart.</p>
                <button class="button button-secondary" type="button" ajax="true" action="<?=$h($actions['run'])?>" use-waiting-icon alert="Geplande incasso’s met een bereikte datum nu verwerken?">Nu incasso’s verwerken</button>
                <?php } ?>
            </div>
        </div>
    </grid>
</section>
