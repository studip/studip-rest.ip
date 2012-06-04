<? #use Studip\Button, Studip\LinkButton; ?>
<h1>
<?= $consumer['consumer_key']
    ? sprintf(_('Registrierte Applikation "%s" bearbeiten'), $consumer['application_title'])
    : _('Neue Applikation registrieren') ?></h1>


<form class="<?= $consumer['consumer_key'] ? 'horizontal' : '' ?> settings"
      action="<?= $controller->url_for('admin/edit', $consumer['consumer_key']) ?>" method="post">
    <?= CSRFProtection::tokenTag() ?>

    <fieldset>
        <legend><?= _('Grundeinstellungen') ?></legend>

        <div class="type-checkbox">
            <label for="enabled"><?= _('Aktiviert') ?></label>
            <input type="checkbox" class="switch" id="enabled" name="enabled" value="1"
                   <?= $consumer['enabled'] ? 'checked' : '' ?>>
        </div>

        <div class="type-text">
            <label for="application_title"><?= _('Titel')?></label>
            <input required type="text" id="application_title" name="application_title"
                   placeholder="<?= _('Beispiel-Applikation') ?>"
                   value="<?= htmlReady($consumer['application_title']) ?>">
        </div>

        <div class="type-text">
            <label for="requester_name"><?= _('Kontaktperson') ?></label>
            <input required type="text" id="requester_name" name="requester_name"
                   placeholder="John Doe"
                   value="<?= htmlReady($consumer['requester_name']) ?>">
        </div>

        <div class="type-text">
            <label for="requester_email"><?= _('Kontaktadresse') ?></label>
            <input required type="text" id="requester_email" name="requester_email"
                   placeholder="support@appsite.tld"
                   value="<?= htmlReady($consumer['requester_email']) ?>">
        </div>

        <div class="type-text">
            <label for="callback_uri"><?= _('Callback URL')?></label>
            <input required type="text" id="callback_uri" name="callback_uri"
                   placeholder="http://appsite.tld/auth"
                   value="<?= htmlReady($consumer['callback_uri']) ?>">
        </div>

    <? if ($consumer['consumer_key']): ?>
        <div class="type-text">
            <label for="consumer_key"><?= _('Consumer Key')?></label>
            <input readonly type="text" id="consumer_key"
                   value="<?= htmlReady($consumer['consumer_key']) ?>">
        </div>

        <div class="type-text">
            <label for="consumer_secret"><?= _('Consumer Secret')?></label>
            <input readonly type="text" id="consumer_secret"
                   value="<?= htmlReady($consumer['consumer_secret']) ?>">
        </div>
    <? endif; ?>

        <? if ($consumer['consumer_key']): ?>
            <div class="centered">
                <?= strftime(_('Erstellt am %d.%m.%Y %H:%M:%S'), strtotime($consumer['issue_date'])) ?><br>
            <? if ($consumer['issue_date'] != $consumer['timestamp']): ?>
                <?= strftime(_('Zuletzt geändert am %d.%m.%Y %H:%M:%S'), strtotime($consumer['timestamp'])) ?>
            <? endif; ?>
            </div>
        <? endif; ?>
    </fieldset>

    <fieldset>
        <legend><?= _('Applikation-Details') ?></legend>

        <div class="type-checkbox">
            <label for="application_commercial"><?= _('Kommerziell') ?></label>
            <input type="checkbox" class="switch" id="application_commercial" name="application_commercial" value="1"
                   <?= $consumer['application_commercial'] ? 'checked' : '' ?>>
        </div>

        <div class="type-text">
            <label for="application_descr"><?= _('Beschreibung')?></label>
            <textarea id="application_descr" name="application_descr"
                ><?= htmlReady($consumer['application_descr']) ?></textarea>
        </div>

        <div class="type-text">
            <label for="application_uri"><?= _('URL')?></label>
            <input type="text" id="application_uri" name="application_uri"
                   placeholder="http://appsite.tld"
                   value="<?= htmlReady($consumer['application_uri']) ?>">
        </div>

        <div class="type-select">
            <label for="application_type"><?= _('Typ')?></label>
            <select name="application_type" id="application_type">
                <option value="">- <?= _('Keine Angabe') ?> -</option>
            <? foreach ($types as $type => $label): ?>
                <option value="<?= $type ?>" <?= $consumer['application_type'] == $type ? 'selected' : '' ?>>
                    <?= $label ?>
                </option>
            <? endforeach; ?>
            </select>
        </div>

        <div class="type-text">
            <label for="application_notes"><?= _('Notizen')?></label>
            <textarea id="application_notes" name="application_notes"
                ><?= htmlReady($consumer['application_notes']) ?></textarea>
        </div>
    </fieldset>

    <div class="type-button">
        <?= Button::createAccept(_('speichern'), 'store') ?>
        <?= LinkButton::createCancel(_('abbrechen'), $controller->url_for('admin/index')) ?>
    </div>
</form>