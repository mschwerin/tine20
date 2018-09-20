<?php
/**
 * Tine 2.0
 * @package     Calendar
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2009-2013 Metaways Infosystems GmbH (http://www.metaways.de)
 */

/**
 * Cli frontend for Calendar
 *
 * This class handles cli requests for the Calendar
 *
 * @package     Calendar
 */
class Calendar_Frontend_Cli extends Tinebase_Frontend_Cli_Abstract
{
    /**
     * the internal name of the application
     * 
     * @var string
     */
    protected $_applicationName = 'Calendar';
    /**
     * import demodata default definitions
     *
     * @var array
     */
    protected $_defaultDemoDataDefinition = [
        'Calendar_Model_Event' => 'cal_import_event_csv'
    ];
    /**
     * help array with function names and param descriptions
     * 
     * @return void
     */
    protected $_help = array(
        'updateCalDavData' => array(
            'description'    => 'update calendar/events from a CalDav source using etags',
            'params'         => array(
                'url'        => 'CalDav source URL',
                'caldavuserfile' => 'CalDav user file containing utf8 username;pwd',
             )
        ),
        'importCalDavData' => array(
            'description'    => 'import calendar/events from a CalDav source',
            'params'         => array(
                'url'        => 'CalDav source URL',
                'caldavuserfile' => 'CalDav user file containing utf8 username;pwd',
             )
        ),
        'importCalDavCalendars' => array(
            'description'    => 'import calendars without events from a CalDav source',
            'params'         => array(
                'url'        => 'CalDav source URL',
                'caldavuserfile' => 'CalDav user file containing utf8 username;pwd',
             )
        ),
        'importegw14' => array(
            'description'    => 'imports calendars/events from egw 1.4',
            'params'         => array(
                'host'       => 'dbhost',
                'username'   => 'username',
                'password'   => 'password',
                'dbname'     => 'dbname'
            )
        ),
        'exportICS' => array(  
            'description'    => "export calendar as ics", 
            'params'         => array('container_id') 
        ),
    );
    
    /**
     * return anonymous methods
     * 
     * @return array
     */
    public static function getAnonymousMethods()
    {
        return array('Calendar.repairDanglingDisplaycontainerEvents');
    }
    
    /**
     * import events
     *
     * @param Zend_Console_Getopt $_opts
     */
    public function import($_opts)
    {
        //If old param is used add the new one
        foreach ( $_opts->getRemainingArgs() as $key => $opt) {
            if (strpos($opt, 'importContainerId=') !== false) {
                $_opts->addArguments(array('container_id='. substr($opt, 18, strlen($opt))));
            }
        }
        parent::_import($_opts);
    }
    
    /**
     * exports calendars as ICS
     *
     * @param Zend_Console_Getopt $_opts
     */
    public function exportICS($_opts)
    {
        Tinebase_Core::set('HOSTNAME', 'localhost');
        
        $opts = $_opts->getRemainingArgs();
        $container_id = $opts[0];
        $filter = new Calendar_Model_EventFilter(array(
            array(
                'field'     => 'container_id',
                'operator'  => 'equals',
                'value'     => $container_id
            )

        ));
        $result = Calendar_Controller_MSEventFacade::getInstance()->search($filter, null, false, false, 'get');
        if ($result->count() == 0) {
            throw new Tinebase_Exception('this calendar does not contain any records.');
        }
        $converter = Calendar_Convert_Event_VCalendar_Factory::factory("generic");
        $result = $converter->fromTine20RecordSet($result);
        print $result->serialize();
    }
    
    /**
     * delete duplicate events
     *  - allowed params:
     *      organizer=ORGANIZER_CONTACTID (equals)
     *      created_by=USER_ID (equals)
     *      dtstart="2014-10-28" (after)
     *      summary=EVENT_SUMMARY (contains)
     *      -d (dry run)
     * 
     * @param Zend_Console_Getopt $opts
     * 
     * @see 0008182: event with lots of exceptions breaks calendar sync
     * 
     * @todo allow to pass json encoded filter
     */
    public function deleteDuplicateEvents($opts)
    {
        $this->_addOutputLogWriter(6);
        
        $args = $this->_parseArgs($opts);
        
        $be = new Calendar_Backend_Sql();
        $filterData = array(array(
            'field'    => 'dtstart',
            'operator' => 'after',
            'value'    => isset($args['dtstart']) ? new Tinebase_DateTime($args['dtstart']) : Tinebase_DateTime::now(),
        ));
        if (isset($args['organizer'])) {
            $filterData[] = array(
                'field'    => 'organizer',
                'operator' => 'equals',
                'value'    => $args['organizer'],
            );
        }
        if (isset($args['created_by'])) {
            $filterData[] = array(
                'field'    => 'created_by',
                'operator' => 'equals',
                'value'    => $args['created_by'],
            );
        }
        if (isset($args['summary'])) {
            $filterData[] = array(
                'field'    => 'summary',
                'operator' => 'contains',
                'value'    => $args['summary'],
            );
        }
        $filter = new Calendar_Model_EventFilter($filterData);
        
        $result = $be->deleteDuplicateEvents($filter, $opts->d);
        exit($result > 0 ? 1 : 0);
    }
    
    /**
     * repair dangling attendee records (no displaycontainer_id)
     * 
     * @see https://forge.tine20.org/mantisbt/view.php?id=8172
     */
    public function repairDanglingDisplaycontainerEvents()
    {
        $writer = new Zend_Log_Writer_Stream('php://output');
        $writer->addFilter(new Zend_Log_Filter_Priority(5));
        Tinebase_Core::getLogger()->addWriter($writer);
        
        $be = new Calendar_Backend_Sql();
        $be->repairDanglingDisplaycontainerEvents();
    }
    
    /**
     * import calendars from a CalDav source
     * 
     * param Zend_Console_Getopt $_opts
     */
    public function importCalDavCalendars(Zend_Console_Getopt $_opts)
    {
        $args = $this->_parseArgs($_opts, array('url', 'caldavuserfile'));
        
        $this->_addOutputLogWriter(4);
        
        $caldavCli = new Calendar_Frontend_CalDAV_Cli($_opts, $args);
        $caldavCli->importAllCalendars();
    }
    
    /**
     * import calendar events from a CalDav source
     * 
     * param Zend_Console_Getopt $_opts
     */
    public function importCalDavData(Zend_Console_Getopt $_opts)
    {
        $args = $this->_parseArgs($_opts, array('url', 'caldavuserfile'));
        
        $this->_addOutputLogWriter(4);
        
        $caldavCli = new Calendar_Frontend_CalDAV_Cli($_opts, $args);
        $caldavCli->importAllCalendarDataForUsers();
    }
    
    /**
     * import calendars and calendar events from a CalDav source using multiple parallel processes
     * 
     * param Zend_Console_Getopt $_opts
     */
    public function importCalDavMultiProc(Zend_Console_Getopt $_opts)
    {
        $args = $this->_parseArgs($_opts, array('url', 'caldavuserfile', 'numProc'));
        
        $this->_addOutputLogWriter(4);
        
        $caldavCli = new Calendar_Frontend_CalDAV_Cli($_opts, $args);
        $caldavCli->runImportUpdateMultiproc('import');
    }
    
    /**
     * update calendar events from a CalDav source using multiple parallel processes
     * 
     * param Zend_Console_Getopt $_opts
     */
    public function updateCalDavMultiProc(Zend_Console_Getopt $_opts)
    {
        $args = $this->_parseArgs($_opts, array('url', 'caldavuserfile', 'numProc'));
        
        $this->_addOutputLogWriter(4);
        
        $caldavCli = new Calendar_Frontend_CalDAV_Cli($_opts, $args);
        $caldavCli->runImportUpdateMultiproc('update');
    }
    
    /**
     * import calendar events from a CalDav source for one user
     * 
     * param Zend_Console_Getopt $_opts
     */
    public function importCalDavDataForUser(Zend_Console_Getopt $_opts)
    {
        $args = $this->_parseArgs($_opts, array('url', 'caldavuserfile', 'line', 'run'));
        
        $this->_addOutputLogWriter(4);
        
        $caldavCli = new Calendar_Frontend_CalDAV_Cli($_opts, $args);
        $caldavCli->importAllCalendarData();
    }
    
    /**
     * update calendar/events from a CalDav source using etags for one user
     * 
     * @param Zend_Console_Getopt $_opts
     */
    public function updateCalDavDataForUser(Zend_Console_Getopt $_opts)
    {
        $args = $this->_parseArgs($_opts, array('url', 'caldavuserfile', 'line', 'run'));
        
        $this->_addOutputLogWriter(4);
        
        $caldavCli = new Calendar_Frontend_CalDAV_Cli($_opts, $args);
        $caldavCli->updateAllCalendarData();
    }
    
    /**
     * update calendar/events from a CalDav source using etags
     * 
     * param Zend_Console_Getopt $_opts
     */
    public function updateCalDavData(Zend_Console_Getopt $_opts)
    {
        $args = $this->_parseArgs($_opts, array('url', 'caldavuserfile'));
        
        $this->_addOutputLogWriter(4);
        
        $caldavCli = new Calendar_Frontend_CalDAV_Cli($_opts, $args);
        $caldavCli->updateAllCalendarDataForUsers();
    }

    /**
     * print alarm acknowledgement report (when, ip, client, user, ...)
     * 
     * @param Zend_Console_Getopt $_opts
     */
    public function alarmAckReport(Zend_Console_Getopt $_opts)
    {
        $until = Tinebase_DateTime::now();
        $from = Tinebase_DateTime::now()->subDay(1);
        
        // get all events for today for current user
        $filter = new Calendar_Model_EventFilter(array(
            array('field' => 'attender', 'operator' => 'in', 'value' => array(array(
                'user_type' => 'user',
                'user_id'   => Tinebase_Core::getUser()->contact_id,
            ))),
            array('field' => 'attender_status', 'operator' => 'not',    'value' => Calendar_Model_Attender::STATUS_DECLINED),
            array('field' => 'period', 'operator' => 'within', 'value' =>
                array("from" => $from, "until" => $until)
            )
        ));
        $events = Calendar_Controller_Event::getInstance()->search($filter);
        Calendar_Model_Rrule::mergeAndRemoveNonMatchingRecurrences($events, $filter);
        Calendar_Controller_Event::getInstance()->getAlarms($events);
        
        echo "Reporting alarms for events of user " . Tinebase_Core::getUser()->accountLoginName
            . " (All times in UTC) from " . $from->toString() . ' until ' . $until->toString() . "...\n\n";
        
        // loop alarms and print alarm ack info
        foreach ($events as $event) {
            if (count($event->alarms) > 0) {
                $this->_printShortEvent($event);
                foreach ($event->alarms as $alarm) {
                    echo "  Alarm " . $alarm->alarm_time . "\n";
                    $ackTime = Calendar_Controller_Alarm::getAcknowledgeTime($alarm);
                    if (empty($ackTime)) {
                        echo "    not acknowledged!";
                    } else {
                        if (is_array($ackTime)) {
                            $ackTime = array_pop($ackTime);
                        }
                        echo "    acknowledged " . $ackTime->toString()
                            . "\n    IP -> ". $alarm->getOption(Tinebase_Model_Alarm::OPTION_ACK_IP)
                            . "\n    Client ->". $alarm->getOption(Tinebase_Model_Alarm::OPTION_ACK_CLIENT) . "\n";
                    }
                    echo "\n";
                }
                echo "\n";
            }
        }
    }

    /**
     * report of shared calendars
     *
     * @param Zend_Console_Getopt $_opts
     * @return integer
     *
     * @todo generalize for other apps?
     */
    public function sharedCalendarReport(Zend_Console_Getopt $_opts)
    {
        $this->_checkAdminRight();

        // get all personal containers of all users
        $filter = [
            ['field' => 'type', 'operator' => 'equals', 'value' => Tinebase_Model_Container::TYPE_PERSONAL],
            ['field' => 'model', 'operator' => 'equals', 'value' => Calendar_Model_Event::class],
            ['field' => 'application_id', 'operator' => 'equals',
                'value' => Tinebase_Application::getInstance()->getApplicationByName($this->_applicationName)->getId()],
        ];
        Tinebase_Container::getInstance()->doSearchAclFilter(false);
        $containers = Tinebase_Container::getInstance()->search(
            new Tinebase_Model_ContainerFilter($filter),
            new Tinebase_Model_Pagination(['sort' => 'owner_id'])
        );
        Tinebase_Container::getInstance()->doSearchAclFilter(true);

        $resultArray = [];
        $users = [];
        $groups = [];
        foreach ($containers as $container) {
            $grants = Tinebase_Container::getInstance()->getGrantsOfContainer($container, true);
            $readGrants = $grants->filter('readGrant', true);
            foreach ($readGrants as $readGrant) {
                // remove all containers with only personal grants
                if ($readGrant->account_id === $container->owner_id) {
                    continue;
                }
                try {
                    if (! isset($users[$container->owner_id])) {
                        $users[$container->owner_id] = Tinebase_User::getInstance()->getFullUserById($container->owner_id)->accountLoginName;
                    }

                    $grantArray = $readGrant->toArray();
                    unset($grantArray['id']);

                    switch ($readGrant->account_type) {
                        case Tinebase_Acl_Rights::ACCOUNT_TYPE_USER:
                            if (!isset($users[$readGrant->account_id])) {
                                $users[$readGrant->account_id] = Tinebase_User::getInstance()->getFullUserById($readGrant->account_id)->accountLoginName;
                            }
                            $accountName = $users[$readGrant->account_id];
                            break;
                        case Tinebase_Acl_Rights::ACCOUNT_TYPE_GROUP:
                            if (!isset($groups[$readGrant->account_id])) {
                                $members = Tinebase_Group::getInstance()->getGroupMembers($readGrant->account_id);
                                $membernames = [];
                                foreach ($members as $member) {
                                    if (! isset($users[$member])) {
                                        $users[$member] = Tinebase_User::getInstance()->getFullUserById($member)->accountLoginName;
                                    }
                                    $membernames[] = $users[$member];
                                }
                                $groups[$readGrant->account_id] = [
                                    'name' => Tinebase_Group::getInstance()->getGroupById($readGrant->account_id)->name,
                                    'members' => $membernames
                                ];
                            }
                            $accountName = $groups[$readGrant->account_id];
                            break;
                        case Tinebase_Acl_Rights::ACCOUNT_TYPE_ANYONE:
                            $accountName = Tinebase_Acl_Rights::ACCOUNT_TYPE_ANYONE;
                            break;
                    }
                } catch (Tinebase_Exception_NotFound $tenf) {
                    Tinebase_Exception::log($tenf);
                    continue;
                } catch (Tinebase_Exception_Record_NotDefined $ternd) {
                    Tinebase_Exception::log($ternd);
                    continue;
                }
                $grantArray['accountName'] = $accountName;
                $resultArray[$users[$container->owner_id]][$container->name][] = $grantArray;
            }
        }
        echo Zend_Json::encode($resultArray);

        return 1;
    }

    /**
     * @param $event
     */
    protected function _printShortEvent($event)
    {
        echo "Event '" . substr($event->summary, 0, 30) . "' (" . $event->dtstart->toString() . ' - ' . $event->dtend->toString() .")\n";
    }
    
    /**
     * compare two calendars and create report of missing/different events
     * 
     * @param Zend_Console_Getopt $_opts
     */
    public function compareCalendars(Zend_Console_Getopt $_opts)
    {
        $args = $this->_parseArgs($_opts, array('cal1', 'cal2'));
        
        $from = isset($args['from']) ? new Tinebase_DateTime($args['from']) : Tinebase_DateTime::now();
        $until = isset($args['until']) ? new Tinebase_DateTime($args['until']) : Tinebase_DateTime::now()->addYear(1);
        
        echo "Comparing Calendars with IDs " . $args['cal1'] . " / " . $args['cal2'] . " (" . $from . " - " . $until . ")...\n"
            . " (All times in UTC)\n";
        
        $result = Calendar_Controller_Event::getInstance()->compareCalendars($args['cal1'], $args['cal2'], $from, $until);
        
        // print results
        echo "-----------------------------\n";
        echo "Found " . count($result['matching']) . " matching Events\n";
        echo "-----------------------------\n";
        echo "Found " . count($result['changed']) . " changed Events (same summary but different time):\n";
        foreach ($result['changed'] as $event) {
            $this->_printShortEvent($event);
        }
        echo "-----------------------------\n";
        echo "Found " . count($result['missingInCal1']) . " missing Events from calendar " . $args['cal1'] . ":\n";
        foreach ($result['missingInCal1'] as $event) {
            $this->_printShortEvent($event);
        }
        echo "-----------------------------\n";
        echo "Found " . count($result['missingInCal2']) . " missing Events from calendar " . $args['cal2'] . ":\n";
        foreach ($result['missingInCal2'] as $event) {
            $this->_printShortEvent($event);
        }
        echo "-----------------------------\n";
    }
    
    public function repairAttendee(Zend_Console_Getopt $_opts)
    {
        $args = $this->_parseArgs($_opts, array('cal'));
        $from = isset($args['from']) ? new Tinebase_DateTime($args['from']) : Tinebase_DateTime::now();
        $until = isset($args['until']) ? new Tinebase_DateTime($args['until']) : Tinebase_DateTime::now()->addYear(1);
        $dry = $_opts->d;
        
        echo "Repairing Calendar with IDs " . $args['cal'] .  "(" . $from . " - " . $until . ")...\n";
        if ($dry) {
            echo "-- DRY RUN --\n";
        }
        
        $result = Calendar_Controller_Event::getInstance()->repairAttendee($args['cal'], $from, $until, $dry);
        echo "Repaired " . $result . " events\n";
    }

    /**
     * remove future fallout exdates for events in given calendars
     * 
     * @param $_opts
     */
    public function restoreFallouts($_opts)
    {
        $now = Tinebase_DateTime::now();
        $args = $this->_parseArgs($_opts, array('cal'));
        $events = Calendar_Controller_MSEventFacade::getInstance()->search(new Calendar_Model_EventFilter(array(
            array('field' => 'container_id', 'operator' => 'equals', 'value' => $args['cal']),
            array('field' => 'is_deleted', 'operator' => 'equals', 'value' => 0),
        )));

        $events->sort('dtstart');
        foreach($events as $event) {
            if ($event->exdate instanceof Tinebase_Record_RecordSet) {
                $fallouts = $event->exdate->filter('id', null);
                if ($fallouts->count()) {
                    $dates = [];
                    foreach($fallouts as $fallout) {
                        if ($fallout->dtstart > $now) {
                            $dates[] = $fallout->dtstart->format('d.m.Y');
                            $event->exdate->removeRecord($fallout);
                        }
                    }
                    if (! empty($dates)) {
                        echo "restoring " . implode($dates,',') . ' for event ' . "'{$event->summary}' ({$event->id})\n";
                        Calendar_Controller_MSEventFacade::getInstance()->update($event);
                    }
                }
            }
        }
    }

    /**
     * update event locations (before 2018.11 there was no autoupdate on resource rename)
     * allowed params:
     *      --updatePastEvents (otherwise from now on)
     *      -d (dry run)
     */
    public function updateEventLocations(Zend_Console_Getopt $opts)
    {
        $args = $this->_parseArgs($opts);
        $updatePastEvents = isset($args['updatePastEvents']) ? !!$args['updatePastEvents'] : false;
        $dry = $opts->d;
        $this->_addOutputLogWriter(6);

        $resources = Calendar_Controller_Resource::getInstance()->getAll();

        foreach($resources as $resource) {
            // collect old names from history
            $names = [];
            $modifications = Tinebase_Timemachine_ModificationLog::getInstance()->getModifications(
                'Calendar',
                $resource->getId(),
                Calendar_Model_Resource::class,
                'Sql',
                $resource->creation_time
            )->filter('change_type', Tinebase_Timemachine_ModificationLog::UPDATED);

            /** @var Tinebase_Model_ModificationLog $modification */
            foreach($modifications as $modification) {
                $modified_attribute = $modification->modified_attribute;

                // legacy code
                if (!empty($modified_attribute) && $modified_attribute == 'name') {
                    $names[] = $modification->old_value;

                // new code modificationLog implementation
                } else {
                    /** @var Tinebase_Record_Diff $diff */
                    $diff = new Tinebase_Record_Diff(json_decode($modification->new_value, true));
                    if (isset($diff->oldData['name'])) {
                        $names[] = $diff->oldData['name'];
                    }
                }
            }

            if (!empty($names)) {
                Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . " Ressource [{$resource->id}] '{$resource->name}' had this names before " . print_r($names, true));
                if (! $dry) {
                    Calendar_Controller_Resource::getInstance()->updateEventLocations($names, $resource->name, $updatePastEvents);
                }
            }

        }

    }
}
