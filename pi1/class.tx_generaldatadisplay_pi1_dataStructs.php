<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2008 Roderick Braun <roderick.braun@ph-freiburg.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License,  or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful, 
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * DS-Class for the 'general_data_display' extension.
 * insert/update/delete methods for used data/tables
 *
 * @author	Roderick Braun <roderick.braun@ph-freiburg.de>
 * @package	TYPO3
 * @subpackage	tx_generaldatadisplay
 */

abstract class tx_generaldatadisplay_pi1_dataStructs {
	// vars
	protected $uid;
	protected $objVars;
	protected $sqlErrorMsg = '';
	protected $formError = array();
	protected $fields = array();
	protected $commonFields = array('uid' => 1, 'pid' => 1, 'tstamp' => 1, 'crdate' => 1, 'cruser_id' => 1);
	
	public function __construct() {
		$this->fields  = array_merge($this->commonFields, $this->fields);
		$this->objVars = t3lib_div::makeInstance(PREFIX_ID . '_objVar');
	}
	
	public function __destruct() {
		unset($this->objVars);
	}
	
	public function getProperty($property) {
		return isset($this->$property) ? $this->$property : NULL;
	}
	
	public function setProperty($property, $value) {
		$this->$property = $value;
		return $this->getProperty($property);
	}
	
	public function getObjVar($key, $plain = FALSE) {
		return $plain ? $this->objVars->get($key, TRUE) : $this->objVars->get($key);
	}
	
	public function setObjVar($key, $value) {
		return $this->objVars->setValue($key, $value);
	}
	
	public function getObjKeys() {
		return $this->objVars->getKeys();
	}
	
	protected function cleanedObjVars($checkpiVars = TRUE) {
		// unset FALSE fields
		foreach ($this->objVars->get("", TRUE) as $key => $value) {
			if ($checkpiVars && !$this->fields[$key])
				$this->objVars->delKey($key);
			else
				$this->setObjVar($key, $this->cleanContent($key, $value));
		}
		
		return $this->objVars->get("", TRUE);
	}
	
	protected function cleanContent($key, $value) {
		$datafieldType = tx_generaldatadisplay_pi1_dataFields::getFieldType($key);
		// check value is not empty
		$error = tx_generaldatadisplay_pi1_formData::checkValue($value, 'notEmpty');
		if ($error['notEmpty'])
			return '';
		
		if ($datafieldType) {
            switch ($datafieldType) {
                case 'currency':
                    if (is_array($value) && ($value['VALUE_PREFIX'] || $value['VALUE_SUFFIX']))
					$content = serialize($value);
                    break;
			
                case 'date':
                    if (is_array($value)) {
                        # add zero to single values
                        if (strlen($value['MONTH']) == 1)
                            $value['MONTH'] = '0' . $value['MONTH'];
                        if (strlen($value['DAY']) == 1)
                            $value['DAY'] = '0' . $value['DAY'];
                        $content = serialize($value);
                    }
                    break;
			
                case 'time':
                    if (is_array($value)) {
                        # add zero to single values
                        if (strlen($value['HOUR']) == 1)
                            $value['HOUR'] = '0' . $value['HOUR'];
                        if (strlen($value['MINUTE']) == 1)
                            $value['MINUTE'] = '0' . $value['MINUTE'];
                        if (strlen($value['SECOND']) == 1)
                            $value['SECOND'] = '0' . $value['SECOND'];
					
                        $content = serialize($value);
                    }
                    break;
			
                default:
                    $content = is_array($value) ? serialize($value) : trim($value);
                }
            return $content;
        // no type -> just return value as it is
        } else return $value;
	}
	
	public function newDS() {
		if ($this->havePerm()) {
			$this->setObjVar('pid', DATA_PID);
			$this->setObjVar('tstamp', time());
			$this->setObjVar('crdate', time());
			$this->setObjVar('cruser_id', $GLOBALS['BE_USER']->user['uid'] ? $GLOBALS['BE_USER']->user['uid'] : $GLOBALS['TSFE']->fe_user->user['uid']);
			
			$GLOBALS['TYPO3_DB']->exec_INSERTquery($this->table, $this->cleanedObjVars());
			$this->setSQLErrorMsg = $GLOBALS['TYPO3_DB']->sql_error();
			return $GLOBALS['TYPO3_DB']->sql_insert_id();
		}
		return FALSE;
	}
	
	public function updateDS() {
		if ($this->havePerm()) {
			$this->setObjVar('tstamp', time());
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery($this->table, 'uid=' . $this->uid, $this->cleanedObjVars());
			$this->sqlErrorMsg = $GLOBALS['TYPO3_DB']->sql_error();
			return $this->sqlError() ? FALSE : TRUE;
		}
		return FALSE;
	}
	
	public function deleteDS() {
		if ($this->havePerm()) {
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery($this->table, 'uid=' . $this->uid, array(
				'deleted' => 1
			));
			$this->sqlErrorMsg = $GLOBALS['TYPO3_DB']->sql_error();
			return $this->sqlError() ? FALSE : TRUE;
		}
		return FALSE;
	}
	
	public function getTemplateArray() {
		$templateArray = array();
		
		// make generic template out of DS
		foreach ($this->objVars->get() as $key => $value)
			$templateArray["###" . strtoupper($key) . "###"] = $value;
		
		return $templateArray;
	}
	
	public function havePerm() {
		if (ADM_PERM) {
			// update or delete existing DS
			if ($this->uid) {
				$dataSet = $GLOBALS['TYPO3_DB']->exec_SELECTquery('pid,cruser_id', $this->table, $where = 'uid=' . $this->uid);
				
				if ($dataSet) {
					$ds = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dataSet);
					if ($ds['pid'] == DATA_PID) {
						if (ADM_PERM == 'BE')
							return TRUE;
						else
							return ($this->type == 'data' || $this->type == 'datacontent') && (STRICT_PERM ? $ds['cruser_id'] == $GLOBALS['TSFE']->fe_user->user['uid'] : TRUE);
					} else
						return FALSE;
				}
			} else
				return TRUE; // new DS
		}
		return FALSE;
	}
	
	public function sqlError() {
        // return true or false 
        return $this->sqlErrorMsg ? TRUE : FALSE;
	}
	
	protected function tableExist() {
		$tableHash = $GLOBALS['TYPO3_DB']->admin_get_tables();
		return isset($tableHash[$this->table]) ? TRUE : FALSE;
	}
	
	protected function isTableColumn($column) {
		$tableColumnHash = $GLOBALS['TYPO3_DB']->admin_get_fields($this->table);
		return isset($tableColumnHash[$column]) ? TRUE : FALSE;
	}
}

class tx_generaldatadisplay_pi1_data extends tx_generaldatadisplay_pi1_dataStructs {
	// vars
	protected $type = "data";
	protected $table = "tx_generaldatadisplay_data";
	protected $fields = array('data_title' => 1);
	
	public function newDS() {
		// save objVars (TODO sql error handling)
		$savedObjVars = $this->cleanedObjVars(FALSE);
		
		if ($this->havePerm() && $uid = parent::newDS()) {
			// get all datafields
			$dataFieldList = t3lib_div::makeInstance(PREFIX_ID . '_datafieldList');
			$dataFieldList->getDS();
			
			// instantiate datacontent obj
			$dataContentObj = t3lib_div::makeInstance(PREFIX_ID . '_datacontent');
			
			// instantiate category_mm obj
			$dataCategoriesObj = t3lib_div::makeInstance(PREFIX_ID . '_categories_mm');
			
			// add uid 0 (no category) to table if none is given
			$savedObjVars['data_category'] = is_array($savedObjVars['data_category']) ? $savedObjVars['data_category'] : array(0);
			
			// build and insert datasets
			foreach ($savedObjVars as $name => $value) {
                // special: multiple categories
                if ($name == 'data_category') {
                    foreach($savedObjVars['data_category'] as $category) {
                        $dataCategoriesObj->setObjVar('data_uid', $uid);
                        $dataCategoriesObj->setObjVar('category_uid', $category);
                        $dataCategoriesObj->newDS();
                    }
                } else {
                    if ($datafieldsUid = $dataFieldList->getUidFromDatafield($name)) {
                        $dataContentObj->setObjVar('datafields_uid', $datafieldsUid);
                        $dataContentObj->setObjVar('data_uid', $uid);
                        $dataContentObj->setObjVar('datacontent', $value);
                        $dataContentObj->newDS();
                    }
				}
			}
			return TRUE;
		}
		return FALSE;
	}
	
	public function updateDS() {
		// save objVars (TODO sql error handling)
		$savedObjVars = $this->cleanedObjVars(FALSE);
		
		if ($this->havePerm() && parent::updateDS()) {
			// get all datafields
			$dataFieldList = t3lib_div::makeInstance(PREFIX_ID . '_datafieldList');
			$dataFieldList->getDS();
			
			// instantiate datacontent obj
			$dataContentObj = t3lib_div::makeInstance(PREFIX_ID . '_datacontent');
			
			// instantiate category_mm obj
			$dataCategoriesObj = t3lib_div::makeInstance(PREFIX_ID . '_categories_mm');
			
            // instantiate and set clauseObj
            $clauseObj       = t3lib_div::makeInstance(PREFIX_ID . '_objClause');
			
			// build and update datasets
			foreach ($savedObjVars as $name => $value) {
                // special: multiple categories
                if ($name == 'data_category') {
                    // first delete all previous categories
                    $categories_mmList = t3lib_div::makeInstance(PREFIX_ID . '_categories_mmList');
                    $clauseObj->reset();
                    $clauseObj->addAND('data_uid', $this->uid, '=');
                    $objArr = $categories_mmList->getDS($clauseObj);
                    foreach($objArr as $key => $obj) {
                        $dataCategoriesObj->setProperty('uid',$key);
                        $dataCategoriesObj->deleteDS();
                    }
                    // add uid 0 (no category) to table if none is given
                    $savedObjVars['data_category'] = is_array($savedObjVars['data_category']) ? $savedObjVars['data_category'] : array(0);
                    // now add new categories
                    foreach($savedObjVars['data_category'] as $category) {
                        $dataCategoriesObj->setObjVar('data_uid', $this->uid);
                        $dataCategoriesObj->setObjVar('category_uid', $category);
                        $dataCategoriesObj->newDS();
                    }
                } else {
                    if ($datafieldsUid = $dataFieldList->getUidFromDatafield($name)) {
                        // get uid from datacontent
                        $dataContentList = t3lib_div::makeInstance(PREFIX_ID . '_datacontentList');
                        $clauseObj->reset();
                        $clauseObj->addAND('data_uid', $this->uid, '=');
                        $clauseObj->addAND('datafields_uid', $datafieldsUid, '=');
                        $objArr = $dataContentList->getDS($clauseObj);
                        // there should be maximum one DS
                        if (count($objArr) <= 1) {
                            $dataContentObj->setObjVar('datafields_uid', $datafieldsUid);
                            $dataContentObj->setObjVar('data_uid', $this->uid);
                            $dataContentObj->setObjVar('datacontent', $value);
                            $dataContentObj->setProperty('uid', key($objArr));
						
                            $objArr ? $dataContentObj->updateDS() : $dataContentObj->newDS();
                        }   else return FALSE;
                    }
				}
			}
			return TRUE;
		}
		return FALSE;
	}
	
	public function deleteDS() {
		if ($this->havePerm()) {
            $result = TRUE;
            // instantiate and set clauseObj
            $clauseObj       = t3lib_div::makeInstance(PREFIX_ID . '_objClause');
		
            // instantiate datacontent obj
			$contentList = t3lib_div::makeInstance(PREFIX_ID . '_datacontentList');
			
			// instantiate category_mm obj
            $categories_mmList = t3lib_div::makeInstance(PREFIX_ID . '_categories_mmList');
		
            // delete dataContent
            $clauseObj->reset();
            $clauseObj->addAND('data_uid', $this->uid, '=');
            $objArr = $contentList->getDS($clauseObj);
            
            foreach($objArr as $obj) { 
                $result = $result && $obj->deleteDS();
            }
            
            // delete category mm entry
            $objArr = $categories_mmList->getDS($clauseObj);
            foreach($objArr as $obj) {
                $result = $result && $obj->deleteDS();
            }
            // now delete data entry
            $result = $result && parent::deleteDS();
			
			// delete entry from temptable
			if ($result && 'tx_generaldatadisplay_pi1_datacontent_tempdata::$tempTable'); {
				$tempData = t3lib_div::makeInstance(PREFIX_ID . '_tempdata');
				$tempData->setProperty("uid", $this->uid);
				$tempData->deleteDS();
			}
			return $result;
		}
		return FALSE;
	}
}

class tx_generaldatadisplay_pi1_datacontent extends tx_generaldatadisplay_pi1_dataStructs {
	// vars
	protected $type = "datacontent";
	protected $table = "tx_generaldatadisplay_datacontent";
	protected $fields = array('data_uid' => 1, 'datacontent' => 1, 'datafields_uid' => 1);
}

class tx_generaldatadisplay_pi1_category extends tx_generaldatadisplay_pi1_dataStructs {
	// vars
	protected $type = "category";
	protected $table = "tx_generaldatadisplay_categories";
	protected $fields = array('category_name' => 1, 'category_progenitor' => 1);
	
	public function deleteDS() {
		if ($this->havePerm()) {
			$result        = TRUE;
			// get progenitor
			$catProgenitor = $this->getObjVar('category_progenitor');
			// get list of all categories
			$categoryList  = t3lib_div::makeInstance(PREFIX_ID . '_categoryList');
			$objArr        = $categoryList->getDS();
			
			foreach ($objArr as $key => $obj) {
				// change all affected categories to the progenitor of this DS
				if ($obj->getObjVar('category_progenitor') == $this->uid) {
					$obj->setObjVar('category_progenitor', $catProgenitor);
					$result = $result && $obj->updateDS();
				}
			}
			// now call parent method to delete DS
			return $result && parent::deleteDS();
		}
		return FALSE;
	}
}

class tx_generaldatadisplay_pi1_categories_mm extends tx_generaldatadisplay_pi1_dataStructs {
	// vars
	protected $type = "categories_mm";
	protected $table = "tx_generaldatadisplay_categories_mm";
	protected $fields = array('data_uid' => 1, 'category_uid' => 1);
}

class tx_generaldatadisplay_pi1_datafield extends tx_generaldatadisplay_pi1_dataStructs {
	// vars
	protected $type = "datafield";
	protected $table = "tx_generaldatadisplay_datafields";
	protected $fields = array('datafield_name' => 1, 'datafield_type' => 1, 'display_sequence' => 1, 'metadata' => 1);
}

class tx_generaldatadisplay_pi1_tempdata extends tx_generaldatadisplay_pi1_dataStructs {
	// vars
	protected $type = "tempdata";
	protected $table = "tx_generaldatadisplay_temptable";
	
	private static $tempTable;
	
	public function newDS() {
		$GLOBALS['TYPO3_DB']->exec_INSERTquery($this->table . DATA_PID, $this->cleanedObjVars(FALSE));
		$this->sqlErrorMsg = $GLOBALS['TYPO3_DB']->sql_error();
		return $GLOBALS['TYPO3_DB']->sql_insert_id();
	}
	
	public function deleteDS() {
		$GLOBALS['TYPO3_DB']->exec_DELETEquery($this->table . DATA_PID, 'uid=' . $this->uid);
		$this->sqlErrorMsg = $GLOBALS['TYPO3_DB']->sql_error();
        return $this->sqlError() ? FALSE : TRUE;
	}
	
	public function createTable($createFields) {
		// create temptable
		self::$tempTable = FALSE;
		$GLOBALS['TYPO3_DB']->sql_query("CREATE TEMPORARY TABLE " . $this->table . DATA_PID . " (" . $createFields . ")");
		$this->sqlErrorMsg = $GLOBALS['TYPO3_DB']->sql_error();
		if (!$this->sqlError()) {
			self::$tempTable = $this->table . DATA_PID;
		}
		return $this->sqlError() ? FALSE : TRUE;
	}
	
	public static function tempTableExist() {
		return self::$tempTable;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/general_data_display/pi1/class.tx_generaldatadisplay_pi1_dataStructs.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/general_data_display/pi1/class.tx_generaldatadisplay_pi1_dataStructs.php']);
}

?>
