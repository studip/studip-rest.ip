<?
namespace RestIP;
use \CalendarExport, \CalendarWriterICalendar, \Calendar, \DbCalendarEventList,
    \SingleCalendar, \SingleDate, \Seminar, \Issue;

class EventsRoute implements \APIPlugin
{
    public function describeRoutes()
    {
        return array();
    }

    public function routes(&$router)
    {
        require_once $GLOBALS['RELATIVE_PATH_CALENDAR'] . '/lib/Calendar.class.php';
        require_once $GLOBALS['RELATIVE_PATH_CALENDAR'] . '/lib/DbCalendarEventList.class.php';
        require_once $GLOBALS['RELATIVE_PATH_CALENDAR'] . '/lib/SingleCalendar.class.php';
        require_once $GLOBALS['RELATIVE_PATH_CALENDAR'] . '/lib/sync/CalendarExport.class.php';
        require_once $GLOBALS['RELATIVE_PATH_CALENDAR'] . '/lib/sync/CalendarExportFile.class.php';
        require_once $GLOBALS['RELATIVE_PATH_CALENDAR'] . '/lib/sync/CalendarWriterICalendar.class.php';
        require_once 'lib/classes/Seminar.class.php';
        require_once 'lib/raumzeit/raumzeit_functions.inc.php';
        require_once 'lib/raumzeit/Issue.class.php';

        $router->get('/events', function () use ($router) {
            $start = time();
            $end   = strtotime('+2 weeks');
            $list = @new DbCalendarEventList(new SingleCalendar($GLOBALS['user']->id, Calendar::PERMISSION_OWN), $start, $end, true, Calendar::getBindSeminare());

            $events = array();
            if ($list->existEvent()) {
                while ($termin = $list->nextEvent()) {
                    $singledate = new SingleDate($termin->id);

                    $events[] = array(
                        'event_id'    => $termin->id,
                        'course_id'   => (strtolower(get_class($termin)) === 'seminarevent') ? $termin->getSeminarId() : '',
                        'start'       => $termin->getStart(),
                        'end'         => $termin->getEnd(),
                        'title'       => $termin->getTitle(),
                        'description' => $termin->getDescription() ?: '',
                        'categories'  => $termin->toStringCategories() ?: '',
                        'room'        => html_entity_decode(strip_tags($singledate->getRoom() ?: $singledate->getFreeRoomText() ?: '')),
                        'canceled'    => $singledate->isHoliday() ?: false

                    );
                }
            }

            $router->render(compact('events'));
        });

        $router->get('/events/ical', function () use ($router) {
            $extype = 'ALL_EVENTS';
            $export = new CalendarExport(new CalendarWriterICalendar());
            $export->exportFromDatabase($GLOBALS['user']->id, 0, 2114377200, 'ALL_EVENTS', Calendar::getBindSeminare($GLOBALS['user']->id));

            if ($GLOBALS['_calendar_error']->getMaxStatus(ERROR_CRITICAL)) {
                $router->halt(500);
            }

            $content = join($export->getExport());
            $content = html_entity_decode(strip_tags($content));
            header('Content-Type: text/calendar');
            header('Content-Disposition: attachment; filename="studip.ics"');
            header('Content-Transfer-Encoding: binary');
            header('Pragma: public');
            header('Cache-Control: private');
            header('Content-Length:' . strlen($content));
            die($content);
        });

        $router->get('/courses/:course_id/events', function ($course_id) use ($router) {
            $seminar = new Seminar($course_id);
            $dates = getAllSortedSingleDates($seminar);

            $events = array();
            
            foreach ($dates as $date) {

                //TODO: Use more of the SingleDate-functionalities

                $issues = $date->getIssueIDs();
                $issue_titles = array();
                $description = '';
                if(is_array($issues)) {
                    foreach($issues as $is) {
                        $issue = new Issue(array('issue_id' => $is));
                        $issue_titles[] = $issue->getTitle();
                    }
                }
                
                
                $description = implode(', ', $issue_titles);
                $temp = getTemplateDataForSingleDate($date);
                $events[] = array(
                    'event_id'    => $date->getSingleDateID(),
                    'course_id'   => $course_id,
                    'start'       => $date->getStartTime(),
                    'end'         => $date->getEndTime(),
                    'title'       => $temp['date'],
                    'description' => $description,
                    'categories'  => $temp['art'] ?: '',
                    'room'        => html_entity_decode(strip_tags($temp['room'] ?: '')),
                    'canceled'    => $date->isHoliday() ?: false
                );
                
            }

            header('Cache-Control: private');
            $router->expires('+1 day');
            $router->render(compact('events'));
        });
    }
}
