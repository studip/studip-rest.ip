<?
class OAuthConsumer
{
    private $store;

    function __construct() {
        $options = array(
            'dsn'      => 'mysql:host=' . $GLOBALS['DB_STUDIP_HOST']
                       .       ';dbname=' . $GLOBALS['DB_STUDIP_DATABASE'],
            'username' => $GLOBALS['DB_STUDIP_USER'],
            'password' => $GLOBALS['DB_STUDIP_PASSWORD']
        );
        $this->store = OAuthStore::instance('PDO', $options);
    }

    static function spawn() {
        return array(
            'enabled'                => 1,
            'consumer_key'           => '',
            'consumer_secret'        => '',
            'requester_name'         => '',
            'requester_email'        => '',
            'callback_uri'           => '',
            'application_uri'        => '',
            'application_title'      => '',
            'application_descr'      => '',
            'application_notes'      => '',
            'application_type'       => '',
            'application_commercial' => 0,
        );
    }

    function getList() {
        return $this->store->listConsumers(null);
    }

    function load($key) {
        try {
            $consumer = $this->store->getConsumer($key, null, true);
        } catch (OAuthException2 $e) {
            $consumer = self::spawn();
        }

        return $consumer;
    }

    function extractConsumerFromRequest($key) {
        $key = Request::option('consumer_key', $key);
        $consumer = self::load($key);

        if (Request::submitted('store')) {
            $consumer['requester_email']        = trim(Request::get('requester_email'));
            $consumer['requester_name']         = trim(Request::get('requester_name'));
            $consumer['callback_uri']           = Request::get('callback_uri');
            $consumer['application_uri']        = Request::get('application_uri');
            $consumer['application_title']      = Request::get('application_title');
            $consumer['application_descr']      = Request::get('application_descr');
            $consumer['application_notes']      = Request::get('application_notes');
            $consumer['application_type']       = Request::option('application_type');
            $consumer['application_commercial'] = Request::int('application_commercial');
        }

        return $consumer;
    }

    function validate($consumer) {
        $errors = array();

        if (empty($consumer['requester_name'])) {
            $errors['application_title'] = _('Kein Titel angegeben');
        }

        if (empty($consumer['requester_name'])) {
            $errors['requester_name'] = _('Keine Kontaktperson angegeben');
        }

        if (!preg_match('/^[a-z0-9.!#$%&\'*+\/=?\\^_`{|}~-]+@[a-z0-9-]+(?:\.[a-z0-9-]+)*$/i', $consumer['requester_email'])) {
            $errors['requester_email'] = _('Keine gültige Kontaktadresse angegeben');
        }

        if (empty($consumer['callback_uri'])) {
            $errors['callback_uri'] = _('Keine Callback URL angegeben');
        } else {
            // TODO validate url, see email
        }

        return $errors;
    }

    function store($consumer, $enabled) {
        $key = $this->store->updateConsumer($consumer, null, true);
        DBManager::get()
            ->prepare("UPDATE oauth_server_registry SET osr_enabled = ? WHERE osr_consumer_key = ?")
            ->execute(array((int)!empty($enabled), $consumer['consumer_key']));

        return $this->load($key);
    }
    
    function delete($key) {
        $this->store->deleteConsumer($key, null, true);
    }
}