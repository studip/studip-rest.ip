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

        $query = "SELECT DISTINCT osr_consumer_key        as consumer_key,
                        osr_consumer_secret     as consumer_secret,
                        osr_enabled             as enabled,
                        osr_status              as status,
                        osr_application_uri     as application_uri,
                        osr_application_title   as application_title,
                        osr_application_descr   as application_descr
                FROM oauth_server_registry
                    JOIN oauth_server_token
                    ON ost_osr_id_ref = osr_id
                WHERE ost_usa_id_ref = ?
                  AND ost_token_ttl  >= NOW()
                  AND ost_authorized = 1
                ORDER BY osr_application_title";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($user_id));
        return $statement->fetchAll(PDO::FETCH_ASSOC);
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