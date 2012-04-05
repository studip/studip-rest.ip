<form action="<?= $controller->url_for('admin/permissions', $consumer_key) ?>" method="post">
<table class="default">
    <thead>
        <tr>
            <th><?= _('Route') ?></th>
            <th><?= _('Zugriff auf') ?></th>
            <th><?= _('Methoden') ?></th>
            <th><?= _('Quelle') ?></th>
            <th><?= _('Zugriff') ?></th>
        </tr>
    </thead>
    <tbody>
<? foreach ($routes as $route => $methods): ?>

<?
        $class = TextHelper::cycle('cycle_even', 'cycle_odd');
        $i = 0;
?>

    <? foreach ($methods as $method => $source): ?>
        <tr class="<?= $class ?>" style="vertical-align: top;">
        <? if ($i++): ?>
            <td colspan="2">&nbsp;</td>
        <? else: ?>
            <td><?= htmlReady($route) ?></td>
            <td><?= htmlReady($descriptions[$route]) ?></td>
        <? endif; ?>
            <td><?= htmlReady($method) ?></td>
            <td><?= htmlReady($source ? "Plugin: ${source}" : _('System')) ?></td>
            <td>
                <input type="hidden" name="permission[<?= urlencode($route) ?>][<?= urlencode($method) ?>]" value="0">
                <input type="checkbox" name="permission[<?= urlencode($route) ?>][<?= urlencode($method) ?>]"
                <? if (!$global || $global->check($route, $method)) { ?>
                    <? if ($permissions->check($route, $method)) echo 'checked'; ?>
                <? } else echo 'disabled'; ?>
                    value="1">
            </td>
        </tr>
    <? endforeach; ?>
<? endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5">
                <?= Studip\Button::createAccept(_('Speichern'), 'store') ?>
            </td>
        </tr>
    </tfoot>
</table>
</form>