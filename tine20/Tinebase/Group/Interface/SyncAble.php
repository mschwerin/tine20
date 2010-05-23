<?php
/**
 * Tine 2.0
 *
 * @package     Tinebase
 * @subpackage  Group
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2010 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 * @version     $Id$
 */

/**
 * syncable group backend interface
 *
 * @package     Tinebase
 * @subpackage  Group
 */
interface Tinebase_Group_Interface_SyncAble
{
    /**
     * resolve groupid(for example ldap gidnumber) to uuid(for example ldap entryuuid)
     *
     * @param   string  $_groupId
     * @return  string  the uuid for groupid
     */
    public function resolveSyncAbleGidToUUid($_groupId);
    
    /**
     * get syncable group by id directly from sync backend
     * 
     * @param  $_groupId  the groupid
     * @return Tinebase_Model_Group
     */
    public function getGroupByIdFromSyncBackend($_groupId);

    /**
     * create a new group in Tine 2.0 database only
     *
     * @param  Tinebase_Model_Group  $_group
     * @return Tinebase_Model_Group
     */
    public function addLocalGroup(Tinebase_Model_Group $_group);
     
    /**
     * get groupmemberships of user
     * 
     * @param   Tinebase_Model_User  $_user
     * @return  array
     */
    public function getGroupMembershipsFromSyncBackend(Tinebase_Model_User $_user);
    
    /**
     * get list of groups from syncbackend
     *
     * @todo make filtering working. Allways returns all groups
     *
     * @param string $_filter
     * @param string $_sort
     * @param string $_dir
     * @param int $_start
     * @param int $_limit
     * @return Tinebase_Record_RecordSet with record class Tinebase_Model_Group
     */
    public function getGroupsFromSyncBackend($_filter = NULL, $_sort = 'name', $_dir = 'ASC', $_start = NULL, $_limit = NULL);
}
