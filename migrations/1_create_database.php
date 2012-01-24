<? 
class CreateDatabase extends DBMigration
{
    function description() {
        return _('Erstellt die notwendigen Datenbanktabellen');
    }

    function up () {
        $sql = file_get_contents(dirname(__FILE__) . '/../oauth-php/library/store/mysql/mysql.sql');
        $chunks = explode('#--SPLIT--', $sql);
        $chunks = array_filter($chunks);
        foreach ($chunks as $chunk) {
            $chunk = preg_replace('/^#.*/m', '', $chunk);
            $chunk = implode("\n", array_filter(explode("\n", $chunk)));
            DBManager::get()->exec($chunk);
        }
    }

    function down() {
        $tables = DBManager::get()
            ->query("SHOW TABLES LIKE 'oauth_%'")
            ->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            DBManager::get()->exec("DROP TABLE " . $table);
        }
    }
}
