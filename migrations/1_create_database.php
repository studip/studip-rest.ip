<?
class CreateDatabase extends DBMigration
{
    function description() {
        return _('Erstellt die notwendigen Datenbanktabellen');
    }

    function up () {
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
        
        $config = Config::GetInstance();
        $config->create('OAUTH_ENABLED', array(
            'description' => 'Schaltet die OAuth-Schnittstelle ein',
            'section'     => 'global',
            'type'        => 'boolean',
            'value'       => '1'
        ));
    }

    function down() {
        $tables = DBManager::get()
            ->query("SHOW TABLES LIKE 'oauth_%'")
            ->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            DBManager::get()->exec("DROP TABLE " . $table);
        }

        $config = Config::GetInstance();
        $config->delete('OAUTH_ENABLED');
    }
}
