<?php
/**
 * egroupware 2.0
 * 
 * @package     Tasks
 * @license     http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2007-2007 Metaways Infosystems GmbH (http://www.metaways.de)
 * @version     $Id$
 *
 */

/**
 * backend class for Egwbase_Http_Server
 * This class handles all Http requests for the calendar application
 * 
 * @package Tasks
 */
class Tasks_Http extends Egwbase_Application_Http_Abstract
{
    protected $_appname = 'Tasks';
    
    /**
     * Returns all JS files which must be included for this app
     *
     * @return array Array of filenames
     */
    public function getJsFilesToInclude()
    {
        return array(
            self::_appendFileTime("Tasks/js/Widgets.js"),
            self::_appendFileTime("Tasks/js/Status.js"),
            self::_appendFileTime("Tasks/js/containerTree.js"),
            self::_appendFileTime("Tasks/js/Tasks.js"),
        );
    }

    
    /**
     * Returns initial data which is send to the app at createon time.
     *
     * When the mainScreen is created, Egwbase_Http_Controler queries this function
     * to get the initial datas for this app. This pattern prevents that any app needs
     * to make an server-request for its initial datas.
     * 
     * Initial datas are just javascript varialbes declared in the mainScreen html code.
     * 
     * The returned data have to be an array with the variable names as keys and
     * the datas as values. The datas will be JSON encoded later. Note that the
     * varialbe names get prefixed with Egw.<applicationname>
     * 
     * @return mixed array 'variable name' => 'data'
     */
    public function getInitialMainScreenData()
    {
        return array(
            'InitialData' => array('Status' => Tasks_Controller::getInstance()->getStati()->toArray())
        );
    }

    /**
     * Supplies HTML for edit tasks dialog
     * 
     */
    public function editTask($taskId)
    {
        $view = new Zend_View();
         
        $view->setScriptPath('Egwbase/views');
        $view->formData = array();
        $view->title="edit lead";
        
        $view->jsIncludeFiles  = $this->getJsFilesToInclude();
        $view->cssIncludeFiles = $this->getCssFilesToInclude();
        $view->initialData = array('Tasks' => $this->getInitialMainScreenData());

        $view->jsExecute = 'Egw.Tasks.EditDialog.render();';

        $view->configData = array(
            'timeZone' => Zend_Registry::get('userTimeZone'),
            'currentAccount' => Zend_Registry::get('currentAccount')->toArray()
        );
        header('Content-Type: text/html; charset=utf-8');
        echo $view->render('popup.php');
    }
}