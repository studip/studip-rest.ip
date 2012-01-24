<?
class AuthController extends OAuthController {
    function register_action() {
        $this->render_text(__METHOD__);
    }
    
    function request_token_action() {
        $this->render_text(__METHOD__);
    }
    
    function authorize_action() {
        $this->render_text(__METHOD__);
    }
    
    function access_token_action() {
        $this->render_text(__METHOD__);
    }
}