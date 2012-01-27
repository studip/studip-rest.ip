<h1><?= _('Applikationen') ?></h1>
<? if (empty($applications)): ?>
<p><?= _('Sie haben noch keinen Apps Zugriff auf Ihren Account gewährt.') ?></p>
<? else: ?>
<p><?= _('Dies sind die Apps, die Zugriff auf Ihren Account haben.') ?></p>
<ul>
<? foreach ($applications as $application): ?>
    <li style="white-space: pre-wrap"><? var_dump($application); ?></li>
<? endforeach; ?>
</ul>
<? endif; ?>