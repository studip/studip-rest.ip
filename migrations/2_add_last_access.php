<?php

/**
 *
 **/
class AddLastAccess extends DBMigration
{
    /**
     *
     **/
    public function description()
    {
        return _('Fügt ein Feld "Letzter Zugriff" beim User-Mapping hinzu');
    }

    /**
     *
     **/
    public function up ()
    {
        $query = "ALTER TABLE `oauth_mapping` ADD COLUMN `last_access` INT(11) UNSIGNED NULL DEFAULT NULL";
        DBManager::get()->exec($query);
    }

    /**
     *
     **/
    public function down()
    {
        $query = "ALTER TABLE `oauth_mapping` DROP COLUMN `last_access`";
        DBManager::get()->exec($query);
    }
}
