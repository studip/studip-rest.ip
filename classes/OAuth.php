<?php

/**
 *
 **/
class OAuth
{
    public static $consumer_key = null;

    public static function isSigned()
    {
        return OAuthRequestVerifier::requestIsSigned();
    }

    /**
     *
     **/
    public static function verify($uri = null, $method = null, $params = null)
    {
        $req = new OAuthRequestVerifier($uri, $method, $params);
        $result = $req->verifyExtended('access');
        self::$consumer_key = $result['consumer_key'];

        $query = "SELECT user_id FROM oauth_mapping WHERE oauth_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($result['user_id']));
        $user_id = $statement->fetchColumn();

        if (!$user_id) {
            throw new Exception('Precondition failed', 412);
        }

        return $user_id;
    }
}
