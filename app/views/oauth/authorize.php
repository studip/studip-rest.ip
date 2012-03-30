<? use Studip\Button, Studip\LinkButton; ?>

<p>
    <?= sprintf(_('Die Applikation <strong>%s</strong> möchte auf Ihre Daten zugreifen.'), 
                htmlReady($rs['application_title'])) ?>
</p>
<form action="<?= $controller->url_for('oauth/authorize?oauth_token=' . $rs['token']) ?>" method="post">
    <p align="center">
        <?= Button::createAccept('erlauben', 'allow') ?>
        <?= LinkButton::createCancel('verweigern', $rs['callback_url']) ?>
    </p>
</form>