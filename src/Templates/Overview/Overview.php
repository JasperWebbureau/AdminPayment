<grid>
    <div class="panel" style="--cw:12">
        <div class="panel__header"><h2>Betalingen</h2></div>
        <div class="panel__body">
            <p>Ontvangen en terugbetaalde bedragen uit alle aangesloten onderdelen, gegroepeerd per valuta. Dit is een betaaloverzicht; factuuromzet blijft een afzonderlijke administratie-indicator.</p>
            <?= $filter ?>
            <?php foreach ($totals as $total) { ?>
                <p><strong><?= htmlspecialchars($total['currency'], ENT_QUOTES, 'UTF-8') ?></strong> · Ontvangen: <?= $total['receipts'] ?> · Terugbetaald: <?= $total['refunds'] ?> · Saldo: <?= $total['net'] ?></p>
            <?php } ?>
            <?= new \Flexgrid\Html\Table\TableRenderer($table) ?>
        </div>
    </div>
</grid>
