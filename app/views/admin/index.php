<h1><?= _('Registrierte Applikationen') ?></h1>

<? if (!empty($consumers)): ?>
<table class="default">
    <thead>
        <tr>
            <th><?= ('Aktiv') ?></th>
            <th><?= _('Name') ?></th>
            <th><?= _('Typ') ?></th>
            <th><?= _('Kontakt') ?></th>
            <th><?= _('Kommerziell') ?></th>
            <th>&nbsp;</th>
        </tr>
    </thead>
    <tbody>
<? foreach ($consumers as $consumer): ?>
        <tr class="<?= TextHelper::cycle('cycle_even', 'cycle_odd') ?>">
            <td>
                <a href="<?= $controller->url_for('admin/toggle', $consumer['consumer_key'], $consumer['enabled'] ? 'off' : 'on') ?>">
                    <?= Assets::img('icons/16/blue/checkbox-' . ($consumer['enabled'] ? '' : 'un') . 'checked') ?>
                </a>
            </td>
            <td>
            <? if ($consumer['application_uri']): ?>
                <a href="<?= $consumer['application_uri'] ?>" target="_blank">
                    <?= htmlReady($consumer['application_title']) ?>
                </a>
            <? else: ?>
                <?= htmlReady($consumer['application_title']) ?>
            <? endif; ?>
            </td>
            <td><?= $types[$consumer['application_type']] ?: '&nbsp;' ?></td>
            <td>
                <a href="mailto:<?= $consumer['requester_email'] ?>">
                    <?= htmlReady($consumer['requester_name']) ?>
                </a>
            </td>
            <td><?= Assets::img('icons/16/blue/checkbox-' . ($consumer['application_commercial'] ? '' : 'un') . 'checked') ?></td>
            <td align="right">
                <a href="<?= $controller->url_for('admin/keys', $consumer['consumer_key']) ?>"
                   data-behaviour="modal"
                   title="<?= htmlReady(sprintf(_('Schlüssel anzeigen für Applikation "%s"'), $consumer['application_title'])) ?>">
                    <?= Assets::img('icons/16/blue/info-circle.png') ?>
                </a>
                <a href="<?= $controller->url_for('admin/edit', $consumer['consumer_key']) ?>" title="<?= _('Applikation bearbeiten') ?>">
                    <?= Assets::img('icons/16/blue/edit.png') ?>
                </a>
                <a href="<?= $controller->url_for('admin/permissions', $consumer['consumer_key']) ?>" title="<?= _('Zugriffsberechtigungen verwalten') ?>">
                    <?= Assets::img('icons/16/blue/admin.png') ?>
                </a>
                <a data-behaviour="confirm" href="<?= $controller->url_for('admin/delete', $consumer['consumer_key']) ?>"
                   title="<?= htmlReady(sprintf(_('Applikation "%s" entfernen'), $consumer['application_title'])) ?>">
                    <?= Assets::img('icons/16/blue/trash.png') ?>
                </a>
            </td>
        </tr>
<? endforeach; ?>
    </tbody>
</table>
<? else: ?>
<p>
    <?= _('Es wurde noch keine Applikation registriert.') ?>
    <?= sprintf(_('Klicken Sie <a href="%s">hier</a>, um eine Applikation zu registrieren.'), $controller->url_for('admin/edit')) ?>
</p>
<? endif; ?>
