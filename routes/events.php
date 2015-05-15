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
        require_once 'lib/classes/Seminar.class.php';
        require_once 'lib/raumzeit/raumzeit_functions.inc.php';
        require_once 'lib/raumzeit/Issue.class.php';
        if (!class_exists('\CourseEvent')) { //before 3.2
            require_once $GLOBALS['RELATIVE_PATH_CALENDAR'] . '/lib/Calendar.class.php';
            require_once $GLOBALS['RELATIVE_PATH_CALENDAR'] . '/lib/DbCalendarEventList.class.php';
            require_once $GLOBALS['RELATIVE_PATH_CALENDAR'] . '/lib/SingleCalendar.class.php';
            require_once $GLOBALS['RELATIVE_PATH_CALENDAR'] . '/lib/sync/CalendarExport.class.php';
            require_once $GLOBALS['RELATIVE_PATH_CALENDAR'] . '/lib/sync/CalendarExportFile.class.php';
            require_once $GLOBALS['RELATIVE_PATH_CALENDAR'] . '/lib/sync/CalendarWriterICalendar.class.php';
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
        } else {
            require_once 'app/models/calendar/SingleCalendar.php';
            require_once $GLOBALS['RELATIVE_PATH_CALENDAR'].'/CalendarExport.class.php';
            require_once $GLOBALS['RELATIVE_PATH_CALENDAR'].'/CalendarWriterICalendar.class.php';

            $router->get('/events', function () use ($router) {
                $start = time();
                $end   = strtotime('+2 weeks');
                $calendar = new SingleCalendar($GLOBALS['user']->id);
                $calendar->getEvents(null, $start, $end);
                $events = array();
                foreach ($calendar->events as $event) {
                        $events[] = array(
                            'event_id'    => $event->event_id,
                            'course_id'   => $event instanceof \CourseEvent ? $event->getSeminarId() : '',
                            'start'       => $event->getStart(),
                            'end'         => $event->getEnd(),
                            'title'       => $event->getTitle(),
                            'description' => $event->getDescription() ?: '',
                            'categories'  => $event->toStringCategories() ?: '',
                            'room'        => $event->getLocation(),
                            'canceled'    => $event instanceof \CourseCancelledEvent
                        );
                    }
                $router->render(compact('events'));
            });
        }

        $router->get('/events/ical', function () use ($router) {
            $export = new CalendarExport(new CalendarWriterICalendar());
            $export->exportFromDatabase($GLOBALS['user']->id);

            if ($GLOBALS['_calendar_error']->getMaxStatus(ERROR_CRITICAL)) {
                $router->halt(500);
            }

            $content = join($export->getExport());
            $content = str_replace('CLASS:PRIVATE', 'CLASS:PUBLIC', $content);
            header('Content-Type: text/calendar;charset=utf-8');
            header('Content-Disposition: attachment; filename="studip.ics"');
            header('Content-Transfer-Encoding: binary');
            header('Pragma: public');
            header('Cache-Control: private');
            header('Content-Length:' . strlen($content));
            header('Expires: ' . gmdate(DATE_RFC1123, strtotime('+1 day')));
            die($content);
        });

        $router->get('/courses/:course_id/events', function ($course_id) use ($router) {
             $seminar = new Seminar($course_id);
             $themen = $seminar->getIssues();
             $termine = getAllSortedSingleDates($seminar);
             $events = array();

            if (is_array($termine) && sizeof($termine) > 0) {
                foreach ($termine as $singledate_id => $singledate) {
                    if (!$singledate->isExTermin()) {
                        $tmp_ids = $singledate->getIssueIDs();
                        $title = $description = '';
                        if (is_array($tmp_ids)) {
                            $title = trim(join("\n", array_map(function ($tid) use ($themen) {return $themen[$tid]->getTitle();}, $tmp_ids)));
                            $description = trim(join("\n\n", array_map(function ($tid) use ($themen) {return $themen[$tid]->getDescription();}, $tmp_ids)));
                        }
                        $events[] = array(
                                        'event_id'    => $singledate->getSingleDateID(),
                                        'course_id'   => $course_id,
                                        'start'       => $singledate->getStartTime(),
                                        'end'         => $singledate->getEndTime(),
                                        'title'       => $title,
                                        'description' => $description,
                                        'categories'  => $singledate->getTypeName(),
                                        'room'        => $singledate->getRoom() ?: $singledate->getFreeRoomText(),
                                        'canceled'    => false
                                    );
                    } elseif ($singledate->getComment()) {
                        $events[] = array(
                                        'event_id'    => $singledate->getSingleDateID(),
                                        'course_id'   => $course_id,
                                        'start'       => $singledate->getStartTime(),
                                        'end'         => $singledate->getEndTime(),
                                        'title'       => _('fällt aus') . ' (' . _('Kommentar:') . ' ' . $singledate->getComment() . ')',
                                        'description' => '',
                                        'categories'  => '',
                                        'room'        => '',
                                        'canceled'    => true
                                    );
                    }
                }
            }
            header('Cache-Control: private');
            $router->expires('+1 day');
            $router->render(compact('events'));
        });
    }
}
