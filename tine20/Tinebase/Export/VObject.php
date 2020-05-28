<?php
/**
 * Tine 2.0
 * @package     Tinebase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2020 Metaways Infosystems GmbH (http://www.metaways.de)
 */

/**
 * @package     Tinebase
 */
abstract class Tinebase_Export_VObject extends Tinebase_Export_Abstract
{
    /**
     * 10 MB
     *
     * @const MAX_FILE_SIZE
     */
    const MAX_FILE_SIZE = 10 * 1024 * 1024;

    protected $_document = null;
    protected $_exportFileHandle = null;
    protected $_exportFilenames = [];
    protected $_currentExportFilename = null;

    protected function _writeToFile()
    {
        return (boolean) $this->_config->filename;
    }

    /**
     * @param Tinebase_Record_Interface $_record
     */
    public function _processRecord(Tinebase_Record_Interface $_record)
    {
        Tinebase_FileSystem_RecordAttachments::getInstance()->getRecordAttachments($_record);

        if ($this->_writeToFile()) {
            $this->_addRecordToFile($_record);
        } else {
            $this->_getDocument($_record);
            $this->_addRecordToDocument($_record);
        }
    }

    protected function _getDocument(Tinebase_Record_Interface $_record, $recreate = false)
    {
        if ($recreate) {
            return $this->_createDocument($_record);
        } else if ($this->_document !== null) {
            return $this->_document;
        } else {
            $this->_document = $this->_createDocument($_record);
            return $this->_document;
        }
    }

    abstract protected function _createDocument(Tinebase_Record_Interface $_record);

    abstract protected function _addRecordToDocument(Tinebase_Record_Interface $_record);

    abstract protected function _addRecordToFile(Tinebase_Record_Interface $_record);

    protected function _checkMaxFileSize()
    {
        if (! $this->_exportFileHandle) {
            return;
        }
        $currentSize = ftell($this->_exportFileHandle);
        $offset = 1024 * 512;
        $splitSize = isset($this->_config->maxfilesize) ? (int) $this->_config->maxfilesize : self::MAX_FILE_SIZE;

        if ($splitSize > 0 && $currentSize + $offset > $splitSize) {
            // close current file - open new file in _createExportFilehandle
            fclose($this->_exportFileHandle);
            $this->_exportFileHandle = null;
            $number = count($this->_exportFilenames) + 1;
            // TODO invent a helper function for filename generation (with numbers)
            if (preg_match('/(n[0-9]+)*(.ics)$/i', $this->_currentExportFilename, $matches)) {
                $this->_currentExportFilename = str_replace($matches[0], 'n' . $number . $matches[2],
                    $this->_currentExportFilename);
            } else {
                $this->_currentExportFilename .= '_' . $number;
            }
        }
    }

    protected function _createExportFilehandle()
    {
        if (! $this->_currentExportFilename) {
            $this->_currentExportFilename = $this->_config->filename;
        }
        $this->_exportFilenames[] = $this->_currentExportFilename;
        $this->_exportFileHandle = fopen($this->_currentExportFilename, 'w');
        if (! $this->_exportFileHandle) {
            throw new Tinebase_Exception('could not open export file: ' . $this->_currentExportFilename);
        }
    }

    protected function _addComponentToEndOfFile(Sabre\VObject\Component $component)
    {
        fwrite($this->_exportFileHandle, $component->serialize());
    }

    /**
     * @param string filename
     *
     * @throws Tinebase_Exception_AccessDenied
     * @throws Tinebase_Exception_NotFound
     *
     * @todo add function signature to Tinebase_Export_Abstract
     */
    public function write($filename = null)
    {
        if ($filename) {
            // TODO use fopen + fpassthru?
            echo file_get_contents($filename);
        } else if ($this->_document !== null) {
            echo (
                is_object($this->_document) && method_exists($this->_document, 'serialize')
                ? $this->_document->serialize()
                : $this->_document
            );
        } else {
            if (Tinebase_Core::isLogLevel(Zend_Log::NOTICE)) {
                Tinebase_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' no records exported');
            }
        }
    }

    /**
     * TODO return all generated files - should be refactored, currently \Tinebase_Frontend_Http_Abstract::_export
     *      only expects one file name
     *
     * @return mixed|null
     */
    protected function _returnExportFilename()
    {
        return $this->_writeToFile() && ! empty($this->_exportFilenames) ? $this->_exportFilenames[0] : null;
    }
}
