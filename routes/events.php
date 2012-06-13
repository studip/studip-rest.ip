<?
namespace RestIP;
use \CalendarExportFile, \CalendarWriterICalendar, \Calendar;

class EventsRoute implements \APIPlugin
{
    public function describeRoutes()
    {
        return array();
    }
    
    public static function before()
    {
        require_once $GLOBALS['RELATIVE_PATH_CALENDAR'] . '/lib/sync/CalendarExportFile.class.php';
        require_once $GLOBALS['RELATIVE_PATH_CALENDAR'] . '/lib/sync/CalendarWriterICalendar.class.php';
    }
    
    public function routes(&$router)
    {
        $router->get('/courses/:course_id/events', function ($course_id) use ($router) {
            $_calendar = Calendar::getInstance(Calendar::RANGE_SEM, $couse_id);
            
            
            $export = new CalendarExportFile(new CalendarWriterICalendar());
            $export->exportFromDatabase($course_id, 0, \Calendar::CALENDAR_END, 'ALL_EVENTS', array($course_id));
            $export->sendFile();
            die;
            
            var_dump($export->getExport());
            die;
            
            $router->halt(501);
        });
    }
}
