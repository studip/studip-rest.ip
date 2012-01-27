<?
class OauthController extends OAuthedController {
    function login_action() {
        if ($unconsumed_path == 'auth/login') {
            $url  = $GLOBALS['ABSOLUTE_URI_STUDIP'];
            $url .= '/plugins_packages/UOL/restipplugin/api.php/oauth/register';
            header('Location: ' . $url);
            die;
        }
    }

    function before_filter(&$action, &$args) {
        parent::before_filter($action, $args);        
        $this->set_layout(null);
    }
    
    function index_action() {
        $this->render_text('foo');
    }
    
    function request_token_action() {
        try {
            $server = new OAuthServer();
            $token = $server->requestToken();
            $this->render_nothing();
        } catch (Exception $e) {
            $this->render_text($e->getMessage());
        }
    }
    
    function authorize_action() {
        global $auth, $user;
        
        $auth->login_if($auth->auth["uid"] == "nobody"); 

        $user_id = OAuthUser::getMappedId($user->id);

        // Fetch the oauth store and the oauth server.
        $store  = OAuthStore::instance();
        $server = new OAuthServer();
        try
        {
            // Check if there is a valid request token in the current request
            // Returns an array with the consumer key, consumer secret, token, token secret and token type.
            $rs = $server->authorizeVerify();

            if ($_SERVER['REQUEST_METHOD'] == 'POST')
            {
                // See if the user clicked the 'allow' submit button (or whatever you choose)
                $authorized = array_key_exists('allow', $_POST);
                // Set the request token to be authorized or not authorized
                // When there was a oauth_callback then this will redirect to the consumer
                $server->authorizeFinish($authorized, $user_id);

                // No oauth_callback, show the user the result of the authorization
                // ** your code here **
                die('foo1');
           }
        }
        catch (OAuthException $e)
        {
            // No token to be verified in the request, show a page where the user can enter the token to be verified
            // **your code here**
            die('invalid');
        }

        $this->set_layout($GLOBALS['template_factory']->open('layouts/base'));
        $this->rs = $rs;
    }
    
    function access_token_action() {
        $server = new OAuthServer();
        $server->accessToken();

        $this->render_nothing();
    }
}