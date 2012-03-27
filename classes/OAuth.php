<?php

class OAuth
{
    public static function verify()
    {
        try {
            $req = new OAuthRequestVerifier();
            $id = $req->verify('access');
            
            $query = "SELECT user_id FROM oauth_mapping WHERE oauth_id = ?";
            $statement = DBManager::get()->prepare($query);
            $statement->execute(array($id));
            $user_id = $statement->fetchColumn();
            
            if (!$user_id) {
                header('HTTP/1.1 412 Precondition failed', true, 412);
                die('HTTP/1.1 412 Precondition failed');
            }
            
            return $user_id;
        } catch (Exception $e) {
            header('HTTP/1.1 401 Unauthorized', true, 401);
            die('HTTP/1.1 401 Unauthorized (' . $e->getMessage() . ')');
        }
    }
}
