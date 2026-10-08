<?php
$h = static function ($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); };
$status = $debitCustomer['status'] ?? '';
$labels = ['creating' => 'Aanvraag wordt verwerkt', 'link_ready' => 'Wacht op toestemming van klant',
    'valid' => !empty($debitCustomer['enabled']) ? 'Automatische incasso ingeschakeld' : 'Automatische incasso gepauzeerd',
    'invalid' => 'Machtiging niet meer geldig', 'failed' => 'Toestemmingsbetaling mislukt',
    'expired' => 'Toestemmingslink verlopen', 'canceled' => 'Toestemmingsbetaling afgebroken', 'attention' => 'Controle nodig'];
?>
<div class="panel" style="--cw:6;--cw-sm:12">
    <div class="panel__header"><h3>Automatische incasso</h3></div>
    <div class="panel__body">
        <?php if (!$ready) { ?><p>Mollie-incasso moet eerst worden ingesteld onder <a href="/Flexgrid/AdminInvoiceCollection/index">Factuurincasso</a>.</p>
        <?php } else { ?>
        <p><?=$h($labels[$status] ?? 'Nog geen incassomachtiging')?></p>
        <?php if (($debitCustomer['mode'] ?? '') === 'test') { ?><p><strong>Testmodus:</strong> er wordt geen echt geld geïncasseerd.</p><?php } ?>
        <?php if ($debitCustomer === null && $active) { ?>
            <p>Maak een toestemmingslink en deel die met de klant. De klant betaalt eenmalig EUR 0,01 via iDEAL en geeft daarmee toestemming voor toekomstige factuurincasso. Na een geldige machtiging wordt incasso voor nieuwe facturen ingeschakeld.</p>
            <button class="button button-secondary" type="button" ajax="true" action="<?=$h($actions['setup'])?>" customer_public_id="<?=$h($customerId)?>" use-waiting-icon>Toestemmingslink maken</button>
        <?php } elseif ($debitCustomer !== null) { ?>
            <?php if ($active && in_array($status, ['failed', 'canceled', 'expired', 'invalid'], true)) { ?><p>Vraag opnieuw toestemming aan; de klant betaalt opnieuw eenmalig EUR 0,01.</p><button class="button button-secondary" type="button" ajax="true" action="<?=$h($actions['setup'])?>" customer_public_id="<?=$h($customerId)?>" use-waiting-icon>Nieuwe toestemmingslink maken</button><?php } ?>
            <?php if ($status === 'link_ready' && !empty($debitCustomer['setup_url'])) { ?><p><a class="button button-secondary" href="<?=$h($debitCustomer['setup_url'])?>" target="_blank" rel="noopener noreferrer">Toestemmingslink openen</a></p><p><code><?=$h($debitCustomer['setup_url'])?></code></p><?php } ?>
            <?php if (!empty($debitCustomer['setup_payment_id'])) { ?><button class="button button-secondary" type="button" ajax="true" action="<?=$h($actions['syncCustomer'])?>" customer_public_id="<?=$h($customerId)?>" use-waiting-icon>Machtiging controleren</button><?php } ?>
            <?php if ($status === 'valid') { ?>
            <form ajax="true" action="<?=$h($actions['customerEnabled'])?>" method="post">
                <input type="hidden" name="customer_public_id" value="<?=$h($customerId)?>">
                <label class="admin-field" style="--cw:12"><span>Deze klant automatisch incasseren</span><input type="checkbox" name="enabled" value="1"<?=!empty($debitCustomer['enabled']) ? ' checked' : ''?>><small>Pauzeren stopt nog niet gestarte incasso's. Een lopende bankbetaling wordt hiermee niet ingetrokken.</small></label>
                <div style="--cw:12"><button class="button button-publish" type="submit">Incassovoorkeur opslaan</button></div>
            </form>
            <?php } ?>
            <?php if (!empty($debitCustomer['error'])) { ?><p><?=$h($debitCustomer['error'])?></p><?php } ?>
        <?php } ?>
        <?php } ?>
    </div>
</div>
