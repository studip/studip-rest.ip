<? use Studip\Button, Studip\LinkButton; ?>

<form action="<?= $controller->url_for('admin/config') ?>" method="post">
    <fieldset>
        <legend><?= _('Einstellungen') ?></legend>

        <div class="type-checkbox">
            <label for="active"><?= _('API aktiviert') ?></label>
            <input type="hidden" name="active" value="0">
            <input type="checkbox" name="active" id="active" value="1" <? if ($config['OAUTH_ENABLED']) echo 'checked'; ?>>
        </div>
        <div class="type-checkbox">
            <label for="session-active"><?= _('Authentifizierung mit Stud.IP Session aktiviert') ?></label>
            <input type="hidden" name="session-active" value="0">
            <input type="checkbox" name="session-active" id="session-active" value="1" <? if ($config['RESTIP_AUTH_SESSION_ENABLED']) echo 'checked'; ?>>
        </div>
        <div class="type-checkbox">
            <label for="http-active"><?= _('Authentifizierung mit HTTP-AUTH aktiviert') ?></label>
            <input type="hidden" name="http-active" value="0">
            <input type="checkbox" name="http-active" id="http-active" value="1" <? if ($config['RESTIP_AUTH_HTTP_ENABLED']) echo 'checked'; ?>>
        </div>
        <? if (count($auth_plugins)) : ?>
            <div class="type-select">
                <label for="auth"><?= _('Standard-Authentifizierung beim Login (nur SingleSignOn)') ?></label>
                <select name="auth" id="auth">
                <? foreach (array_merge(array('Standard'),$auth_plugins) as $plugin): ?>
                    <option <? if ($config['OAUTH_AUTH_PLUGIN'] === $plugin) echo 'selected'; ?>>
                        <?= $plugin ?>
                    </option>
                <? endforeach; ?>
                </select>
            </div>
        <? endif ?>
        <div class="type-button">
            <?= Button::createAccept(_('Speichern')) ?>
            <?= LinkButton::createCancel(_('Abbrechen'), $controller->url_for('admin')) ?>
        </div>
    </fieldset>
</form>
