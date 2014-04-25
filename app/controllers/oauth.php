<?php
/**
 *
 **/
class OauthController extends Trails_Controller
{
    /**
     *
     **/
    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);

        $this->set_layout(null);
    }

    /**
     *
     **/
    public function index_action()
    {
        $this->render_text('TODO');
    }

    /**
     *
     **/
    public function request_token_action()
    {
        try {
            $server = new OAuthServer();
            $token = $server->requestToken();
            $this->render_nothing();
        } catch (Exception $e) {
            $this->render_text($e->getMessage());
        }
    }

    /**
     *
     **/
    public function authorize_action()
    {
        global $user, $auth;

        $auth_plugin = Config::get()->OAUTH_AUTH_PLUGIN;
        if ($GLOBALS['user']->id === 'nobody' && in_array($auth_plugin, Restip\Helper::getSSOPlugins()) && !Request::option('sso')) {
            $params = $_GET;
            $params['sso'] = strtolower($auth_plugin);
            $params['cancel_login'] = 1;
            $this->redirect($this->url_for('oauth/authorize?' . http_build_query($params)));
            return;
        } else {
            $auth->login_if($user->id === 'nobody');
        }

        $user_id = OAuthUser::getMappedId($user->id);

        // Fetch the oauth store and the oauth server.
        $store  = OAuthStore::instance();
        $server = new OAuthServer();
        try {
            // Check if there is a valid request token in the current request
            // Returns an array with the consumer key, consumer secret, token, token secret and token type.
            $rs = $server->authorizeVerify();

            if (isset($_POST['allow'])) {
                // See if the user clicked the 'allow' submit button (or whatever you choose)
                $authorized = array_key_exists('allow', $_POST);

                // Set the request token to be authorized or not authorized
                // When there was a oauth_callback then this will redirect to the consumer
                $server->authorizeFinish($authorized, $user_id);

                // No oauth_callback, show the user the result of the authorization
                // ** your code here **
                PageLayout::postMessage(MessageBox::success(_('Sie haben der Applikation Zugriff auf Ihre Daten gewährt.')));
                $this->redirect('user#' . $rs['consumer_key']);
           }
        } catch (OAuthException $e) {
            // No token to be verified in the request, show a page where the user can enter the token to be verified
            // **your code here**
            die('invalid');
        }

        PageLayout::disableHeader();
        $this->set_layout($GLOBALS['template_factory']->open('layouts/base_without_infobox'));
        $this->rs = $rs;
    }

    /**
     *
     **/
    public function access_token_action()
    {
        $server = new OAuthServer();
        $server->accessToken();

        $this->render_nothing();
    }
}
