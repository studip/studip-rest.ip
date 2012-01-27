<? use Studip\Button, Studip\LinkButton; ?>
<!--
array(9) {
  ["token"]=>
  string(41) "d1f474ce093d7f94276e7c130a737d2404f22cdcb"
  ["token_secret"]=>
  string(32) "fedb6688e0281972b9e5686a9759d24c"
  ["consumer_key"]=>
  string(41) "1d918110489350d4ff682c48f247a34804f2268ef"
  ["consumer_secret"]=>
  string(32) "07d9acf83e15069f54476fb2f6e13583"
  ["token_type"]=>
  string(7) "request"
  ["callback_url"]=>
  string(128) "http://127.0.0.1/~tleilax/studip/trunk/public/plugins.php/restipplugin/client?resource=news%2Fstudip&mode=php&method=GET&submit="
  ["application_title"]=>
  string(13) "Testclient #2"
  ["application_descr"]=>
  string(0) ""
  ["application_uri"]=>
  string(0) ""
}
-->
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