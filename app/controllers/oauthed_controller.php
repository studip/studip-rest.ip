<?
class OAuthedController extends Trails_Controller
{
    private $user_id = false;

    function before_filter(&$action, &$args) {
        parent::before_filter($action, $args);

        $options = array(
            'dsn'      => 'mysql:host=' . $GLOBALS['DB_STUDIP_HOST']
                       .       ';dbname=' . $GLOBALS['DB_STUDIP_DATABASE'],
            'username' => $GLOBALS['DB_STUDIP_USER'],
            'password' => $GLOBALS['DB_STUDIP_PASSWORD']
        );
        OAuthStore::instance('pdo', $options);
    }
    
    function isAuthorized() {
        try {
            $req = new OAuthRequestVerifier();
            $this->user_id = $req->verify();
        } catch (OAuthException $e) {
            $this->response
                ->setStatus(401)
                ->addHeader('WWW-Authenticate', 'OAuth realm=""');

            throw new Exception($e->getMessage());
        }        
        return $this;
    }
    
    function rescue($exception) {
        return new Trails_Response($exception->getMessage(), array(), 401,
                                    $exception->getMessage());
    }
}
