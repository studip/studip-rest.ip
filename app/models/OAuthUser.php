<?
class OAuthUser
{
    static function getMappedId($user_id) {
        $query = "SELECT oauth_id FROM oauth_mapping WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($user_id));
        $mapped_id = $statement->fetchColumn();
        
        if (!$mapped_id) {
            DBManager::get()
                ->prepare("INSERT INTO oauth_mapping (user_id, mkdate) VALUES (?, UNIX_TIMESTAMP())")
                ->execute(array($user_id));
            $mapped_id = DBManager::get()->lastInsertId();
        }
        
        return $mapped_id;
    }
}