<?php

/**
 *
 **/
class UserLocation extends DBMigration
{
    /**
     *
     **/
    public function description()
    {
        return _('Fügt eine neue Tabele für die Nutzerlokalisierung hinzu');
    }

    /**
     *
     **/
    public function up ()
    {
        $query = "CREATE TABLE  IF NOT EXISTS `restip_user_location` (
                    `user_id` varchar(32) NOT NULL,
                    `resource_id` varchar(32) NOT NULL,
                    `geoLocation` text NOT NULL,
                    `mkdate` int(20) NOT NULL,
                    `chdate` int(20) NOT NULL,
                    PRIMARY KEY (`user_id`))";
         DBManager::get()->exec($query);

    }

    /**
     *
     **/
    public function down()
    {
         // Delete user_location table
        DBManager::get()->exec("DROP TABLE `restip_user_location`");
    }
}
