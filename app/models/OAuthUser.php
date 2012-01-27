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

    static function getStore() {
        $options = array(
            'dsn'      => 'mysql:host=' . $GLOBALS['DB_STUDIP_HOST']
                       .       ';dbname=' . $GLOBALS['DB_STUDIP_DATABASE'],
            'username' => $GLOBALS['DB_STUDIP_USER'],
            'password' => $GLOBALS['DB_STUDIP_PASSWORD']
        );
        return OAuthStore::instance('pdo', $options);
    }
    
    // Fetch all consumers registered by the given user
    static function getConsumers($user_id) {
        $user_id = self::getMappedId($user_id);

        return self::getStore()->listConsumerTokens($user_id);
    }
    
    static function revokeToken($user_id, $consumer_key) {
        $user_id = self::getMappedId($user_id);
        $query = "DELETE oauth_server_token "
               . "FROM oauth_server_token "
               .    "JOIN oauth_server_registry ON ost_osr_id_ref = osr_id "
               . "WHERE ost_usa_id_ref = ? AND osr_consumer_key = ?";
        DBManager::get()
            ->prepare($query)
            ->execute(array($user_id, $consumer_key));
    }
}