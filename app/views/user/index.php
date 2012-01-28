<? use Studip\Button, Studip\LinkButton; ?>

<h1><?= _('Applikationen') ?></h1>
<? if (empty($consumers)): ?>
<p><?= _('Sie haben noch keinen Apps Zugriff auf Ihren Account gewährt.') ?></p>
<? else: ?>
<table class="oauth-apps default">
    <thead>
        <tr>
            <th><?= _('Name') ?></th>
            <th>&nbsp;</th>
    </thead>
    <tbody>
    <? foreach ($consumers as $consumer): ?>
        <tr class="<?= $class = TextHelper::cycle('cycle_even', 'cycle_odd') ?>">
            <td>
                <h2>
                <? if ($consumer['application_uri']): ?>
                    <a href="<?= $consumer['application_uri'] ?>" target="_blank">
                        <?= htmlReady($consumer['application_title']) ?>
                    </a>
                <? else: ?>
                    <?= htmlReady($consumer['application_title']) ?>
                <? endif; ?>
                <? if ($type = $types[$consumer['type']]): ?>
                    <small>(<?= htmlReady($type) ?>)</small>
                <? endif; ?>
                </h2>
                <p><?= strftime('Zugriff erteilt am %e.%m.%y %H:%M', strtotime($consumer['timestamp'])) ?></p>
            <? if ($consumer['application_descr']): ?>
                <p><?= htmlReady($consumer['application_descr']) ?></p>
            <? endif; ?>
            </td>
            <td>
                <?= LinkButton::createCancel(_('App entfernen'),
                                             $controller->url_for('user/revoke', $consumer['consumer_key']),
                                             array('data-behaviour' => 'confirm')) ?>
            </td>
        </tr>
<? endforeach; ?>
    </tbody>
</table>
<? endif; ?>