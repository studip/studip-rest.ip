<?
class OAuthController extends OAuthedController {
    function login_action() {
        if ($unconsumed_path == 'auth/login') {
            $url  = $GLOBALS['ABSOLUTE_URI_STUDIP'];
            $url .= '/plugins_packages/UOL/restipplugin/api.php/oauth/register';
            header('Location: ' . $url);
            die;
        }
    }
    
    function index_action() {
        $this->render_text('foo');
    }
    
    function request_token_action() {
        $this->set_layout(null);
        try {
            $server = new OAuthServer();
            $token = $server->requestToken();
            $this->render_text(serialize($token));
        } catch (Exception $e) {
            $this->render_text($e->getMessage());
        }
    }
    
    function authorize_action() {
        die(__METHOD__);
        global $auth;
        
        $auth->login_if($auth->auth["uid"] == "nobody"); 

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

        $this->render_text(__METHOD__);
    }
    
    function access_token_action() {
        $server = new OAuthServer();
        $server->accessToken();

        $this->render_nothing();
    }
}