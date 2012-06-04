<?
# namespace RestIP;

class EventsRoute implements APIPlugin
{
    public function describeRoutes()
    {
        return array();
    }

    public static function before()
    {
        require_once $GLOBALS['RELATIVE_PATH_CALENDAR'] . '/lib/sync/CalendarExport.class.php';
        require_once $GLOBALS['RELATIVE_PATH_CALENDAR'] . '/lib/sync/CalendarWriterICalendar.class.php';
    }

    public function routes(&$router)
    {
        $router->get('/courses/:course_id/events', function ($course_id) use ($router) {
            $export = new CalendarExport(new CalendarWriterICalendar());
            $export->exportFromDatabase($course_id, 0, 2114377200, 'ALL_EVENTS');

            $router->halt(501);
        });
    }
}
