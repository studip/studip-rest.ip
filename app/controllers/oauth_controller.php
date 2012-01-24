<?
class OAuthController extends Trails_Controller
{
    function before_filter(&$action, &$args) {
        parent::before_filter($action, $args);

        $options = array(
            'dsn'      => 'mysql:host=' . $GLOBALS['DB_STUDIP_HOST']
                       .       ';dbname=' . $GLOBALS['DB_STUDIP_DATABASE'],
            'username' => $GLOBALS['DB_STUDIP_USER'],
            'password' => $GLOBALS['DB_STUDIP_PASSWORD']
        );
        $this->store = OAuthStore::instance('pdo', $options);
    }
    
    function checkSigned() {
        if (!OAuthRequestVerifier::requestIsSigned()) {
            return;
        }
        
        try {
            $req = new OAuthRequestVerifier();
            $this->user_id = $req->verify();
        } catch (OAuthException $e) {
            $this->response
                ->setStatus(401)
                ->addHeader('WWW-Authenticate', 'OAuth realm=""');

            throw new Exception($e->getMessage());
        }        
    }
    
    function rescue($exception) {
        $this->render_text($exception->getMessage());
    }
}
