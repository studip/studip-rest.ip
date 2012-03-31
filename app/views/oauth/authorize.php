<section class="oauth authorize">
    <p>
        <?= sprintf(_('Die Applikation <strong>%s</strong> möchte auf Ihre Daten zugreifen.'), 
                    htmlReady($rs['application_title'])) ?>
    </p>

    <form action="<?= $controller->url_for('oauth/authorize?oauth_token=' . $rs['token']) ?>" method="post">
        <p>
            <?= Studip\Button::createAccept('erlauben', 'allow') ?>
            <?= Studip\LinkButton::createCancel('verweigern', $rs['callback_url']) ?>
        </p>
    </form>

    <p>
        <?= Avatar::getAvatar($GLOBALS['user']->id)->getImageTag(Avatar::SMALL) ?>

        <?= sprintf(_('Angemeldet als <strong>%s</strong> (%s)'),
                    $name = get_fullname(), $GLOBALS['user']->username) ?><br>
        <small>
            <?= sprintf(_('Sind sie nicht <strong>%s</strong>, so <a href="%s">melden Sie sich bitte ab</a> und versuchen es erneut.'),
                        $name, URLHelper::getLink('logout.php')) ?>
        </small>
    </p>
</section>
