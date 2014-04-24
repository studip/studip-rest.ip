<?php

/**
 *
 **/
class AddAuthConfig extends DBMigration
{
    /**
     *
     **/
    public function description()
    {
        return _('Fügt Optionen zum deaktivieren der Authentifizierung hinzu');
    }

    /**
     *
     **/
    public function up ()
    {
        $query = "INSERT INTO config (config_id, field, value, is_default, type, `range`, section, mkdate, chdate, description)
                  VALUES (MD5(?), ?, '0', 1, 'boolean', 'global', 'global', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), ?)";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array(
            'RESTIP_AUTH_SESSION_ENABLED',
            'RESTIP_AUTH_SESSION_ENABLED',
            'Schaltet die REST.IP Authentifizierung über Stud.IP Session ein',
        ));
        $query = "INSERT INTO config (config_id, field, value, is_default, type, `range`, section, mkdate, chdate, description)
                  VALUES (MD5(?), ?, '0', 1, 'boolean', 'global', 'global', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), ?)";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array(
            'RESTIP_AUTH_HTTP_ENABLED',
            'RESTIP_AUTH_HTTP_ENABLED',
            'Schaltet die REST.IP Authentifizierung über HTTP ein',
        ));
    }

    /**
     *
     **/
    public function down()
    {
         // Delete config entry
        DBManager::get()->exec("DELETE FROM config WHERE field = 'RESTIP_AUTH_HTTP_ENABLED'");
        DBManager::get()->exec("DELETE FROM config WHERE field = 'RESTIP_AUTH_SESSION_ENABLED'");
    }
}

