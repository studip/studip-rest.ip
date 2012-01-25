<h1><?= _('OAuth-Applikationen') ?></h1>

<? if (!empty($applications)): ?>
<ul>
<? foreach ($applications as $id => $application): ?>
    <li>
        <?= $id ?>
        <pre><? var_dump($application); ?></pre>
    </li>
<? endforeach; ?>
</ul>
<? else: ?>
<p>
    <?= _('Es sind keine Applikationen vorhanden.') ?>
    <?= _(' Sie können hier <a href="%s">hier</a> eine erstellen.') ?>
<? endif; ?>