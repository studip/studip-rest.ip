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
        // Add vendor tables
        $query = "CREATE TABLE IF NOT EXISTS `oauth_consumer_registry` (
          `ocr_id` int(11) NOT NULL AUTO_INCREMENT,
          `ocr_usa_id_ref` int(11) DEFAULT NULL,
          `ocr_consumer_key` varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
          `ocr_consumer_secret` varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
          `ocr_signature_methods` varchar(128) NOT NULL DEFAULT 'HMAC-SHA1,PLAINTEXT',
          `ocr_server_uri` varchar(128) NOT NULL,
          `ocr_server_uri_host` varchar(128) NOT NULL,
          `ocr_server_uri_path` varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
          `ocr_request_token_uri` varchar(255) NOT NULL,
          `ocr_authorize_uri` varchar(255) NOT NULL,
          `ocr_access_token_uri` varchar(255) NOT NULL,
          `ocr_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`ocr_id`),
          UNIQUE KEY `ocr_consumer_key` (`ocr_consumer_key`,`ocr_usa_id_ref`,`ocr_server_uri`),
          KEY `ocr_server_uri` (`ocr_server_uri`),
          KEY `ocr_server_uri_host` (`ocr_server_uri_host`,`ocr_server_uri_path`),
          KEY `ocr_usa_id_ref` (`ocr_usa_id_ref`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8";
        DBManager::get()->exec($query);

        $query = "CREATE TABLE IF NOT EXISTS `oauth_consumer_token` (
          `oct_id` int(11) NOT NULL AUTO_INCREMENT,
          `oct_ocr_id_ref` int(11) NOT NULL,
          `oct_usa_id_ref` int(11) NOT NULL,
          `oct_name` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT '',
          `oct_token` varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
          `oct_token_secret` varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
          `oct_token_type` enum('request','authorized','access') DEFAULT NULL,
          `oct_token_ttl` datetime NOT NULL DEFAULT '9999-12-31 00:00:00',
          `oct_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`oct_id`),
          UNIQUE KEY `oct_ocr_id_ref` (`oct_ocr_id_ref`,`oct_token`),
          UNIQUE KEY `oct_usa_id_ref` (`oct_usa_id_ref`,`oct_ocr_id_ref`,`oct_token_type`,`oct_name`),
          KEY `oct_token_ttl` (`oct_token_ttl`),
          CONSTRAINT `oauth_consumer_token_ibfk_1` FOREIGN KEY (`oct_ocr_id_ref`) REFERENCES `oauth_consumer_registry` (`ocr_id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8";
        DBManager::get()->exec($query);

        $query = "CREATE TABLE IF NOT EXISTS `oauth_log` (
          `olg_id` int(11) NOT NULL AUTO_INCREMENT,
          `olg_osr_consumer_key` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
          `olg_ost_token` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
          `olg_ocr_consumer_key` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
          `olg_oct_token` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
          `olg_usa_id_ref` int(11) DEFAULT NULL,
          `olg_received` text NOT NULL,
          `olg_sent` text NOT NULL,
          `olg_base_string` text NOT NULL,
          `olg_notes` text NOT NULL,
          `olg_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `olg_remote_ip` bigint(20) NOT NULL,
          PRIMARY KEY (`olg_id`),
          KEY `olg_osr_consumer_key` (`olg_osr_consumer_key`,`olg_id`),
          KEY `olg_ost_token` (`olg_ost_token`,`olg_id`),
          KEY `olg_ocr_consumer_key` (`olg_ocr_consumer_key`,`olg_id`),
          KEY `olg_oct_token` (`olg_oct_token`,`olg_id`),
          KEY `olg_usa_id_ref` (`olg_usa_id_ref`,`olg_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8";
        DBManager::get()->exec($query);

        $query = "CREATE TABLE IF NOT EXISTS `oauth_server_nonce` (
          `osn_id` int(11) NOT NULL AUTO_INCREMENT,
          `osn_consumer_key` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
          `osn_token` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
          `osn_timestamp` bigint(20) NOT NULL,
          `osn_nonce` varchar(80) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
          PRIMARY KEY (`osn_id`),
          UNIQUE KEY `osn_consumer_key` (`osn_consumer_key`,`osn_token`,`osn_timestamp`,`osn_nonce`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8";
        DBManager::get()->exec($query);

        $query = "CREATE TABLE IF NOT EXISTS `oauth_server_registry` (
          `osr_id` int(11) NOT NULL AUTO_INCREMENT,
          `osr_usa_id_ref` int(11) DEFAULT NULL,
          `osr_consumer_key` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
          `osr_consumer_secret` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
          `osr_enabled` tinyint(1) NOT NULL DEFAULT '1',
          `osr_status` varchar(16) NOT NULL,
          `osr_requester_name` varchar(64) NOT NULL,
          `osr_requester_email` varchar(64) NOT NULL,
          `osr_callback_uri` varchar(255) NOT NULL,
          `osr_application_uri` varchar(255) NOT NULL,
          `osr_application_title` varchar(80) NOT NULL,
          `osr_application_descr` text NOT NULL,
          `osr_application_notes` text NOT NULL,
          `osr_application_type` varchar(20) NOT NULL,
          `osr_application_commercial` tinyint(1) NOT NULL DEFAULT '0',
          `osr_issue_date` datetime NOT NULL,
          `osr_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`osr_id`),
          UNIQUE KEY `osr_consumer_key` (`osr_consumer_key`),
          KEY `osr_usa_id_ref` (`osr_usa_id_ref`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8";
        DBManager::get()->exec($query);

        $query = "CREATE TABLE IF NOT EXISTS `oauth_server_token` (
          `ost_id` int(11) NOT NULL AUTO_INCREMENT,
          `ost_osr_id_ref` int(11) NOT NULL,
          `ost_usa_id_ref` int(11) NOT NULL,
          `ost_token` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
          `ost_token_secret` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
          `ost_token_type` enum('request','access') DEFAULT NULL,
          `ost_authorized` tinyint(1) NOT NULL DEFAULT '0',
          `ost_referrer_host` varchar(128) NOT NULL DEFAULT '',
          `ost_token_ttl` datetime NOT NULL DEFAULT '9999-12-31 00:00:00',
          `ost_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `ost_verifier` char(10) DEFAULT NULL,
          `ost_callback_url` varchar(512) DEFAULT NULL,
          PRIMARY KEY (`ost_id`),
          UNIQUE KEY `ost_token` (`ost_token`),
          KEY `ost_osr_id_ref` (`ost_osr_id_ref`),
          KEY `ost_token_ttl` (`ost_token_ttl`),
          CONSTRAINT `oauth_server_token_ibfk_1` FOREIGN KEY (`ost_osr_id_ref`) REFERENCES `oauth_server_registry` (`osr_id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8";
        DBManager::get()->exec($query);

        DBManager::get()->exec("
            CREATE TABLE IF NOT EXISTS `oauth_mapping` (
                `oauth_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `user_id` char(32) NOT NULL,
                `mkdate` int(11) unsigned NOT NULL,
                `access_granted` tinyint(1) unsigned NOT NULL DEFAULT '1',
                PRIMARY KEY (`oauth_id`),
                UNIQUE KEY `oauth_id` (`user_id`)
            ) ENGINE=MyISAM
        ");

        DBManager::get()->exec("
            CREATE TABLE IF NOT EXISTS `oauth_api_permissions` (
              `route_id` char(32) NOT NULL,
              `consumer_key` varchar(64) DEFAULT NULL,
              `method` char(6) NOT NULL,
              `granted` tinyint(1) unsigned NOT NULL DEFAULT '0',
              UNIQUE KEY `route_id` (`route_id`,`consumer_key`,`method`)
            ) ENGINE=MyISAM
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
