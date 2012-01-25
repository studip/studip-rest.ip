<?
class AuthController extends OAuthController {
    function login_action() {
        $auth->login_if($auth->auth["uid"] == "nobody"); 

        if ($unconsumed_path == 'auth/login') {
            $url  = $GLOBALS['ABSOLUTE_URI_STUDIP'];
            $url .= '/plugins_packages/UOL/restipplugin/api.php/auth/register';
            header('Location: ' . $url);
            die;
        }
    }
    
    function register_action() {
        global $user;
        
        if (!$user->id or $user->id === 'nobody') {
            $url = $GLOBALS['ABSOLUTE_URI_STUDIP'];
            $url = preg_replace('/plugins_packages.*$/', '', $url);
            $url .= 'plugins.php/restipplugin/auth/login';
            $this->redirect($url);
        } else {
            $user_id = OAuthUser::getMappedId($user->id);
            
            echo $user->Email;
            die;
/*
            // This should come from a form filled in by the requesting user
            $consumer = array(
                // These two are required
                'requester_name'  => $user->Vorname . ' ' . $user->Nachname,
                'requester_email' => $user->Email,

                // These are all optional
                'callback_uri' => 'http://www.myconsumersite.com/oauth_callback',
                'application_uri' => 'http://www.myconsumersite.com/',
                'application_title' => 'John Doe\'s consumer site',
                'application_descr' => 'Make nice graphs of all your data',
                'application_notes' => 'Bladibla',
                'application_type' => 'website',
                'application_commercial' => 0
            );

            // Register the consumer
            $store = OAuthStore::instance(); 
            $key   = $store->updateConsumer($consumer, $user_id);

            // Get the complete consumer from the store
            $consumer = $store->getConsumer($key);

            // Some interesting fields, the user will need the key and secret
            $consumer_id = $consumer['id'];
            $consumer_key = $consumer['consumer_key'];
            $consumer_secret = $consumer['consumer_secret'];
                        
*/            
            $this->render_text(__METHOD__);
        }
    }
    
    function request_token_action() {
        global $user;
        
        if (!$user->id or $user->id === 'nobody') {
            $url = $GLOBALS['ABSOLUTE_URI_STUDIP'];
            $url = preg_replace('/plugins_packages.*$/', '', $url);
            $url .= 'plugins.php/restipplugin/auth/login';
            $this->redirect($url);
        } else {
            $user_id = OAuthUser::getMappedId($user->id);
            
            echo $user->Email;
            die;
        }

        $server = new OAuthServer();
        $token = $server->requestToken();

        $this->render_nothing();
    }
    
    function authorize_action() {
        $this->render_text(__METHOD__);
    }
    
    function access_token_action() {
        $server = new OAuthServer();
        $server->accessToken();

        $this->render_nothing();
    }
}