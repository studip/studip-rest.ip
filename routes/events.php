<?
namespace RestIP;
use \CalendarExport, \CalendarWriterICalendar, \Calendar, \DbCalendarEventList, \SingleCalendar, \SingleDate;

class EventsRoute implements \APIPlugin
{
    public function describeRoutes()
    {
        return array();
    }
    
    public static function before()
    {
        require_once $GLOBALS['RELATIVE_PATH_CALENDAR'] . '/lib/DbCalendarEventList.class.php';
        require_once $GLOBALS['RELATIVE_PATH_CALENDAR'] . '/lib/SingleCalendar.class.php';
        require_once $GLOBALS['RELATIVE_PATH_CALENDAR'] . '/lib/sync/CalendarExportFile.class.php';
        require_once $GLOBALS['RELATIVE_PATH_CALENDAR'] . '/lib/sync/CalendarWriterICalendar.class.php';
    }
    
    public function routes(&$router)
    {
        $router->get('/events', function () use ($router) {
            $start = time();
            $end   = strtotime('+2 weeks');
            $list = @new DbCalendarEventList(new SingleCalendar($GLOBALS['user']->id, Calendar::PERMISSION_OWN), $start, $end, true, Calendar::getBindSeminare());

            $events = array();
            while ($termin = $list->nextEvent()) {
                $singledate = new SingleDate($termin->id);
                
                $events[] = array(
                    'id'          => $termin->id,
                    'seminar_id'  => (strtolower(get_class($termin)) === 'seminarevent') ? $termin->getSeminarId() : null,
                    'start'       => $termin->getStart(),
                    'end'         => $termin->getEnd(),
                    'title'       => $termin->getTitle(),
                    'description' => $termin->getDescription() ?: null,
                    'categories'  => $termin->toStringCategories(),
                    'room'        => $singledate->getRoom() ?: $singledate->getFreeRoomText() ?: null,
                );
            }

            $router->render(compact('events'));
        });
        
        $router->get('/events/ical', function () use ($router) {
            $extype = 'ALL_EVENTS';
            $export = new CalendarExport(new CalendarWriterICalendar());
            $export->exportFromDatabase($GLOBALS['user']->id, 0, 2114377200, 'ALL_EVENTS',
                    Calendar::getBindSeminare($GLOBALS['user']->id));

            if ($GLOBALS['_calendar_error']->getMaxStatus(ERROR_CRITICAL)) {
                $router->halt(500);
            }

            $content = join($export->getExport());
            header('Content-Type: text/calendar');
            header('Content-Disposition: attachment; filename="studip.ics"');
            header('Content-Transfer-Encoding: binary');
            header('Pragma: public');
            header('Cache-Control: private');
            header('Content-Length:' . strlen($content));
            die($content);
        });
    }
}
