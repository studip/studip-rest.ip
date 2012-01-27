<h1><?= _('Applikationen') ?></h1>
<? if (empty($consumers)): ?>
<p><?= _('Sie haben noch keinen Apps Zugriff auf Ihren Account gewährt.') ?></p>
<? else: ?>
<p><?= _('Dies sind die Apps, die Zugriff auf Ihren Account haben.') ?></p>
<ul>
<? foreach ($consumers as $consumer): ?>
<!--
array(12) {
    ["consumer_key"]=> string(41) "1d918110489350d4ff682c48f247a34804f2268ef"
    ["consumer_secret"]=> string(32) "07d9acf83e15069f54476fb2f6e13583"
    ["enabled"]=> string(1) "1"
    ["status"]=> string(6) "active"
    ["application_uri"]=> string(0) ""
    ["application_title"]=> string(13) "Testclient #2"
    ["application_descr"]=> string(0) ""
    ["timestamp"]=> string(19) "2012-01-27 18:03:16"
    ["token"]=> string(41) "a604322090356361c79f425edcaa128f04f22d8d4"
    ["token_secret"]=> string(32) "6538d33a6675fcfe1abe263f1fbc22e2"
    ["token_referrer_host"]=> string(9) "127.0.0.1"
    ["callback_uri"]=> string(77) "http://127.0.0.1/~tleilax/studip/trunk/public/plugins.php/restipplugin/client"
} 
-->
    <li>
        <?= htmlReady($consumer['application_title']) ?>
        <a href="<?= $controller->url_for('user/revoke', $consumer['consumer_key']) ?>">
            <?= _('entfernen') ?>
        </a>
    </li>
<? endforeach; ?>
</ul>
<? endif; ?>