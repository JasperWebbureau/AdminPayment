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

        <?php if ($mollieEnabled ?? false) { ?>
            <div class="admin-payment-link">
                <h4>Mollie-betaallink</h4>
                <?php if (($mollieLink['status'] ?? '') === 'ready' || ($mollieLink['status'] ?? '') === 'paid') { ?>
                    <p>Deze link is aangemaakt voor het volledige oorspronkelijke factuurbedrag. Controleer de openstaande balans voordat je hem deelt; handmatige betalingen wijzigen een bestaande link niet.</p>
                    <p><a class="button button-secondary" href="<?=$h($mollieLink['url'])?>" target="_blank" rel="noopener noreferrer">Betaallink openen</a> <span><?=$h($mollieLink['status'] === 'paid' ? 'Betaald via Mollie' : 'Nog niet via Mollie betaald')?></span></p>
                    <p><code><?=$h($mollieLink['url'])?></code></p>
                <?php } elseif ($mollieLink !== null) { ?>
                    <p>De betaallink wordt verwerkt of vereist handmatige controle. Maak niet opnieuw een link aan voordat de Mollie-status is nagekeken.</p>
                <?php } elseif ($canCreateLink ?? false) { ?>
                    <p>Maak alleen een betaallink als de klant online wil betalen. Deze stap verstuurt nog geen e-mail.</p>
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
