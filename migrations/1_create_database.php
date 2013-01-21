<?php

/**
 *
 **/
class CreateDatabase extends DBMigration
{
    /**
     *
     **/
    public function description()
    {
        return _('Erstellt die notwendigen Datenbanktabellen und Konfigurationseinträge für OAuth');
    }

    /**
     *
     **/
    public function up ()
    {
        $sql = file_get_contents(dirname(__FILE__) . '/../vendor/oauth-php/library/store/mysql/mysql.sql');
        $chunks = explode('#--SPLIT--', $sql);
        $chunks = array_filter($chunks);
        foreach ($chunks as $chunk) {
            $chunk = preg_replace('/^#.*/m', '', $chunk);
            $chunk = implode("\n", array_filter(explode("\n", $chunk)));
            DBManager::get()->exec($chunk);
        }

        DBManager::get()->exec("
            CREATE TABLE IF NOT EXISTS `oauth_mapping` (
                `oauth_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `user_id` char(32) NOT NULL,
                `mkdate` int(11) unsigned NOT NULL,
                `access_granted` tinyint(1) unsigned NOT NULL DEFAULT '1',
                PRIMARY KEY (`oauth_id`),
                UNIQUE KEY `oauth_id` (`user_id`)
            ) ENGINE=InnoDB
        ");

        DBManager::get()->exec("
            CREATE TABLE IF NOT EXISTS `oauth_api_permissions` (
              `route_id` char(32) NOT NULL,
              `consumer_key` varchar(64) DEFAULT NULL,
              `method` char(6) NOT NULL,
              `granted` tinyint(1) unsigned NOT NULL DEFAULT '0',
              UNIQUE KEY `route_id` (`route_id`,`consumer_key`,`method`)
            )
        ");

        // Create config entries
        $query = "INSERT INTO config (config_id, field, value, is_default, type, `range`, section, mkdate, chdate, description)
                  VALUES (MD5(?), ?, '1', 1, 'boolean', 'global', 'global', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), ?)";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array(
            'OAUTH_ENABLED',
            'OAUTH_ENABLED',
            'Schaltet die OAuth-Schnittstelle ein',
        ));

        $query = "INSERT INTO config (config_id, field, value, is_default, type, `range`, section, mkdate, chdate, description)
                  VALUES (MD5(?), ?, 'Standard', 1, 'string', 'global', 'global', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), ?)";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array(
            'OAUTH_AUTH_PLUGIN',
            'OAUTH_AUTH_PLUGIN',
            'Definiert das verwendete Authentifizierungsverfahren',
        ));
    }

    /**
     *
     **/
    public function down()
    {
        // Delete all tables that belong to oauth
        $tables = DBManager::get()
            ->query("SHOW TABLES LIKE 'oauth_%'")
            ->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            DBManager::get()->exec("DROP TABLE " . $table);
        }

        // Delete config entry
        DBManager::get()->exec("DELETE FROM config WHERE field = 'OAUTH_ENABLED'");
    }
}
