<?php

use Flexgrid\Html\Table\TableRenderer;

$h = function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>
<section class="panel admin-panel admin-payment-panel" style="--cw:12" data-admin-payment-panel>
    <div class="panel__header admin-panel__header admin-payment-panel__header">
        <div>
            <h3><i class="fas fa-money-bill-transfer" aria-hidden="true"></i> Betalingen</h3>
            <p>Ontvangsten en terugbetalingen op factuur <?=$h($invoiceNumber ?? '')?>.</p>
        </div>
        <span class="admin-status is-<?=$h($paymentStatusTone ?? 'neutral')?>"><?=$h($paymentStatusLabel ?? '')?></span>
    </div>

    <div class="panel__body admin-panel__body admin-payment-panel__body">
        <div class="admin-payment-summary" aria-label="Betaalsamenvatting">
            <div><span>Factuurtotaal</span><strong><?=$h($grossTotal ?? '')?></strong></div>
            <div><span>Ontvangen</span><strong><?=$h($allocatedTotal ?? '')?></strong></div>
            <div><span><?=$h($balanceLabel ?? 'Openstaand')?></span><strong><?=$h($balance ?? '')?></strong></div>
        </div>

        <?php if ($directDebitReady ?? false) { ?>
            <div>
                <h4>Automatische incasso</h4>
                <?php if (($debit ?? null) !== null) { ?>
                    <p><strong><?=$h($debitLabel)?></strong> — vanaf <?=$h((new \DateTimeImmutable($debit['collect_on']))->format('d-m-Y'))?>.</p>
                    <?php if ($debit['mode'] === 'test') { ?><p>Testmodus: geen echte afschrijving.</p><?php } ?>
                    <?php if (!empty($debit['error'])) { ?><p><?=$h($debit['error'])?></p><?php } ?>
                    <?php if (empty($debit['payment_id']) && in_array($debit['status'], ['creating', 'attention'], true)) { ?>
                    <details><summary>Uitkomst in Mollie controleren</summary><p>Zoek in Mollie of deze factuur al een incassobetaling heeft. Koppel alleen die bestaande betaling; hiermee wordt geen nieuwe afschrijving gestart.</p>
                        <form ajax="true" action="<?=$h($recoverDebitAction)?>" method="post">
                            <input type="hidden" name="invoice_public_id" value="<?=$h($invoicePublicId)?>">
                            <label class="admin-field" style="--cw:12"><span>Betalingsnummer uit Mollie</span><input name="payment_id" required pattern="tr_[A-Za-z0-9]+" placeholder="tr_..."></label>
                            <div style="--cw:12"><button type="submit" class="button button-secondary">Bestaande betaling controleren en koppelen</button></div>
                        </form>
                    </details>
                    <?php } ?>
                    <?php if (!empty($debit['payment_id'])) { ?><button type="button" class="button button-secondary" ajax="true" action="<?=$h($syncDebitAction)?>" invoice_public_id="<?=$h($invoicePublicId)?>" use-waiting-icon>Incassostatus controleren</button><?php } ?>
                <?php } else { ?><p>Incasso vereist een geldige machtiging en een ingeschakelde incassovoorkeur op de klantpagina. De factuur moet eerst definitief zijn.</p><?php } ?>
                <?php if (($directDebitEnabled ?? false) && (($debit ?? null) === null || $debit['status'] === 'awaiting_notice') && ($canRegister ?? false)) { ?>
                    <button type="button" class="button button-secondary" ajax="true" action="<?=$h($announceDebitAction)?>" invoice_public_id="<?=$h($invoicePublicId)?>" use-waiting-icon alert="Deze factuur per e-mail versturen met bedrag en incassodatum? Een eerdere factuurmail wordt hiermee opnieuw verstuurd.">Factuur versturen en incasso plannen</button>
                <?php } ?>
            </div>
        <?php } ?>

        <?php if ($mollieEnabled ?? false) { ?>
            <div class="admin-payment-link">
                <h4>Mollie-betaallink</h4>
                <?php if (($mollieLink['status'] ?? '') === 'ready' || ($mollieLink['status'] ?? '') === 'paid') { ?>
                    <p>Deze link staat ook met QR-code op de definitieve PDF zolang de factuur openstaat. Controleer de openstaande balans voordat je hem deelt; handmatige betalingen wijzigen een bestaande link niet.</p>
                    <p><a class="button button-secondary" href="<?=$h($mollieLink['url'])?>" target="_blank" rel="noopener noreferrer">Betaallink openen</a> <span><?=$h($mollieLink['status'] === 'paid' ? 'Betaald via Mollie' : 'Nog niet via Mollie betaald')?></span></p>
                    <p><code><?=$h($mollieLink['url'])?></code></p>
                <?php } elseif ($mollieLink !== null) { ?>
                    <p>De betaallink wordt verwerkt of vereist handmatige controle. Maak niet opnieuw een link aan voordat de Mollie-status is nagekeken.</p>
                <?php } elseif ($canCreateLink ?? false) { ?>
                    <p>Bij het genereren van de definitieve PDF wordt automatisch een betaallink aangemaakt. Met deze knop kan dat ook vooraf; er wordt geen e-mail verstuurd.</p>
                    <button class="button button-secondary" type="button" ajax="true" action="<?=$h($createLinkAction ?? '')?>" invoice_public_id="<?=$h($invoicePublicId ?? '')?>" alert="Mollie-betaallink voor het volledige factuurbedrag aanmaken?" use-waiting-icon>Betaallink aanmaken</button>
                <?php } ?>
            </div>
        <?php } ?>

        <?php if ($canRegister ?? false) { ?>
            <form class="admin-form admin-payment-form" ajax="true" action="<?=$h($registerAction ?? '')?>" method="post" use-waiting-icon>
                <input type="hidden" name="invoice_public_id" value="<?=$h($invoicePublicId ?? '')?>">
                <label class="admin-field">
                    <span class="admin-field__label">Type</span>
                    <select name="payment_type" required>
                        <option value="receipt">Ontvangst</option>
                        <option value="refund">Terugbetaling</option>
                    </select>
                </label>
                <label class="admin-field">
                    <span class="admin-field__label">Bedrag (<?=$h($currency ?? '')?>)</span>
                    <input type="number" name="amount" min="0.01" step="0.01" inputmode="decimal" value="<?=$h($suggestedAmount ?? '')?>" required>
                </label>
                <label class="admin-field">
                    <span class="admin-field__label">Boekdatum</span>
                    <input type="date" name="booked_on" value="<?=$h($bookedOn ?? '')?>" required>
                </label>
                <label class="admin-field admin-payment-form__reference">
                    <span class="admin-field__label">Referentie</span>
                    <input type="text" name="reference" maxlength="255" placeholder="Bijvoorbeeld bankkenmerk">
                </label>
                <label class="admin-field admin-payment-form__description">
                    <span class="admin-field__label">Omschrijving</span>
                    <input type="text" name="description" maxlength="255" placeholder="Ontvangen betaling">
                </label>
                <div class="admin-payment-form__action">
                    <button class="button button-publish" type="submit"><i class="fas fa-plus" aria-hidden="true"></i> Registreren</button>
                </div>
            </form>
        <?php } ?>

        <div class="admin-payment-history">
            <?=new TableRenderer($table ?? [])?>
        </div>
    </div>
</section>
