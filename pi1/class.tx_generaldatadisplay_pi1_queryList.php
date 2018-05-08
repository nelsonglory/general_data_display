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
 * query-Class for the 'general_data_display' extension.
 * provides methods to query data
 *
 * @author	Roderick Braun <roderick.braun@ph-freiburg.de>
 * @package	TYPO3
 * @subpackage	tx_generaldatadisplay
 */

abstract class tx_generaldatadisplay_pi1_queryList extends tslib_pibase {
	// vars
	public $scriptRelPath = 'pi1/class.tx_generaldatadisplay_pi1_queryList.php';
	public $extKey = 'general_data_display';
	
	protected $objArr = array();
	protected $restrictQuery;
	protected $whereClause;
	protected $groupByField;
	protected $orderByField;
	protected $nrResults = 0;
	protected $sqlErrorMsg = '';
	
	public function __construct() {
		parent::__construct();
		$this->cObj = t3lib_div::makeInstance('tslib_cObj');
		$this->pi_loadLL();
		$this->restrictQuery = "pid=" . DATA_PID . " AND NOT deleted";
	}
	
	public function getProperty($property) {
		return isset($this->$property) ? $this->$property : NULL;
	}
	
	public function setProperty($property, $value) {
		$this->$property = $value;
		return $this->getProperty($property);
	}
	
	public function delProperty($property) {
		if (isset($this->$property))
			unset($this->$property);
	}
	
	public function getDS(tx_generaldatadisplay_pi1_objClause &$clause = NULL, $range = "") {
		// delete former result	
		$this->objArr = array();
		
		$whereClause = $this->restrictQuery . ($clause && $clause->notEmpty() ? " AND " . $clause->get($this->table) : "");
		
		$dataSet = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->table, $where = $whereClause, $groupBy = $this->groupByField, $orderBy = $this->orderField, $limit = $range);
		$this->sqlErrorMsg = $GLOBALS['TYPO3_DB']->sql_error();
		$this->nrResults = $GLOBALS['TYPO3_DB']->sql_num_rows($dataSet);
		
		if ($this->nrResults > 0) {
			// Content
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dataSet)) {
				$data    = t3lib_div::makeInstance($this->objType);
				$uid     = $data->setProperty("uid", $row['uid']);
				$objVars = t3lib_div::makeInstance(PREFIX_ID . '_objVar');
				$data->setProperty("objVars", $objVars->set($row));
				$this->objArr[$uid] = $data;
			}
		}
		return $this->objArr;
	}
	
	public function getHash($valueField, $keyField = 'uid', $plain = FALSE) {
		$hash = array();
		
		foreach ($this->objArr as $key => $obj)
			$hash[$obj->getObjVar($keyField)] = $obj->getObjVar($valueField, $plain);
		
		return $hash;
	}
	
	public function getOptionSelect($field, $selected = '', $checkfield = 'uid') {
		$options = "";
		
		// Get options
		foreach ($this->objArr as $key => $obj) {
			$optionEntry = '<option value="' . $obj->getObjVar($checkfield) . '"' . (($obj->getObjVar($checkfield) == $selected) ? ' selected="selected">' : '>') . $obj->getObjVar($field) . '</option>';
			
			$options .= $optionEntry;
		}
		
		return $options;
	}
	
	public function addBackTicks($var) {
		if (is_array($var))
			foreach ($var as $key => $value)
				$result["`" . $key . "`"] = $value;
		else
			$result = "`" . $var . "`";
		return $result;
	}
	
	public function sqlError() {
        // return true or false 
        return $this->sqlErrorMsg ? TRUE : FALSE;
	}
}

class tx_generaldatadisplay_pi1_dataList extends tx_generaldatadisplay_pi1_queryList {
	// vars
	protected $table = "tx_generaldatadisplay_temptable";
	protected $objType = "tx_generaldatadisplay_pi1_data";
	protected $orderField = "category_sort";
	protected static $tableColumnHash = array();
	
	
	public function __construct() {
		parent::__construct();
		$this->restrictQuery = "pid=" . DATA_PID;
	}
	
	public function getDS(tx_generaldatadisplay_pi1_objClause &$clause = NULL, $range = "", $formatContent = FALSE) {
		$this->objArr = array();
		
		$this->createTempTable($formatContent);
		
		$whereClause = $this->restrictQuery . ($clause && $clause->notEmpty() ? " AND " . $clause->get($this->table . DATA_PID) : "");
		
		$dataSet = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->table . DATA_PID, $where = $whereClause, $groupBy = '', $orderBy = $this->orderField, $limit = $range);
		$this->sqlErrorMsg = $GLOBALS['TYPO3_DB']->sql_error();
		$this->nrResults = $GLOBALS['TYPO3_DB']->sql_num_rows($dataSet);
		
		if ($this->nrResults > 0) {
			// Content
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dataSet)) {
                $uid = $row['uid'];
                if (isset($this->objArr[$uid])) {
                    // multiple categories result in multiple datasets
                    // add additional category to data
                    $data = $this->objArr[$uid];
                    $categories = explode(',',$data->getObjVar('data_category'));
                    $categories[] = $row['data_category'];
                    $data->setObjVar('data_category', implode(',',$categories));
                } else {
                    $data    = t3lib_div::makeInstance($this->objType);
                    $objVars = t3lib_div::makeInstance(PREFIX_ID . '_objVar');
                    $data->setProperty("uid", $$uid);
                    $data->setProperty("objVars", $objVars->set($row));
                }
                $this->objArr[] = $data;
			}
		}
		return $this->objArr;
	}
	
	
	private function createTempTable($formatContent) {
		// if temptable is already existing nothing has to be done
		if (tx_generaldatadisplay_pi1_tempdata::tempTableExist())
			return TRUE;
		
		$tempDataClass   = PREFIX_ID . '_tempdata';
		$tableColumnHash = self::getColumns();
		foreach ($tableColumnHash as $key => $value)
			$fieldArr[] = $this->addBackTicks($key) . " " . $value;
		
		$createFields = implode(", ", $fieldArr);
		
		// create temptable
		$tempData = t3lib_div::makeInstance($tempDataClass);
		$tempData->createTable($createFields);
		
		if (!$tempData->sqlError()) {
			// get all non deleted entrys from page
			$dataList   = t3lib_div::makeInstance(PREFIX_ID . '_dataOnlyList');
			$dataObjArr = $dataList->getDS();

			// get categoryList
			$categoryList = t3lib_div::makeInstance(PREFIX_ID . '_categoryList');
			$catArr = $categoryList->getDS();

			// build sortHash
			foreach ($catArr as $key => $obj) {
				$level[$obj->getObjVar('level')]++;
				// necessary for correct alphabetical sorting
                if ($level[$obj->getObjVar('level')] < 10) 
                    $level[$obj->getObjVar('level')] = '0'.$level[$obj->getObjVar('level')];
				for ($i=$obj->getObjVar('level'); $i+1 < count($level); $i++) {
					array_pop($level);
				}
				$categorySortHash[$key] = implode('.',$level);
			}
			// go and get the data
			foreach ($dataObjArr as $key => $obj) {
				// Content	
				// first unset possibly existing datacontent array
				unset($dataContent);
				// get dataContent
				$dataSet = $GLOBALS['TYPO3_DB']->exec_SELECTquery('tx_generaldatadisplay_datafields.datafield_name, tx_generaldatadisplay_datafields.datafield_type, tx_generaldatadisplay_datacontent.datacontent', 
                   'tx_generaldatadisplay_datacontent LEFT JOIN tx_generaldatadisplay_datafields ON tx_generaldatadisplay_datacontent.datafields_uid = tx_generaldatadisplay_datafields.uid',
                   'tx_generaldatadisplay_datacontent.pid=' . DATA_PID . ' AND tx_generaldatadisplay_datacontent.data_uid=' . $obj->getObjVar('uid') . ' 
                    AND NOT tx_generaldatadisplay_datacontent.deleted AND NOT tx_generaldatadisplay_datafields.deleted');
				
				$this->sqlErrorMsg = $GLOBALS['TYPO3_DB']->sql_error();
				$this->nrResults = $GLOBALS['TYPO3_DB']->sql_num_rows($dataSet);

				if ($this->nrResults > 0) {
					$baseObj       = t3lib_div::makeInstance(PREFIX_ID);
					$baseObj->cObj = t3lib_div::makeInstance('tslib_cObj');
					$baseObj->pi_loadLL();
					
					while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dataSet)) {
						if ($row['datafield_name'])
							$dataContent[$row['datafield_name']] = $formatContent ? $baseObj->formatContentType(NULL, $row['datacontent'], $row['datafield_type']) : $row['datacontent'];
                    }
                }
                // additional fields
                $dataContent['pid']           = DATA_PID;
                $dataContent['uid']           = $obj->getObjVar('uid');
                $dataContent['data_title']    = $obj->getObjVar('data_title', TRUE);
                $dataContent['data_category'] = $obj->getObjVar('category_uid');
                $dataContent['category_sort'] = isset($categorySortHash[$dataContent['data_category']]) ? $categorySortHash[$dataContent['data_category']] : 0;
                $dataContent['category_name'] = is_object($catArr[$dataContent['data_category']]) ? $catArr[$dataContent['data_category']]->getObjVar('category_name') : '';
                $dataContent                  = $this->addBackTicks($dataContent);
                // set DS in tempTable
                $objVars                      = t3lib_div::makeInstance(PREFIX_ID . '_objVar');
                $tempData->setProperty("objVars", $objVars->set($dataContent));
                $tempData->newDS();
			}
		}
		return $GLOBALS['TYPO3_DB']->sql_error() ? FALSE : TRUE;
	}
	
	public static function getColumns() {
		if (!empty(self::$tableColumnHash))
			return self::$tableColumnHash;
		
		self::$tableColumnHash = array(
			'pid' => 'int',
			'uid' => 'int',
			'data_title' => 'tinytext',
			'data_category' => 'int',
			'category_sort' => 'tinytext',
			'category_name' => 'tinytext'
		);
		// get list of datafield names
		$dataSet               = $GLOBALS['TYPO3_DB']->exec_SELECTquery('datafield_name, datafield_type', 'tx_generaldatadisplay_datafields', 'pid=' . DATA_PID . ' AND NOT deleted');
		
		if ($dataSet) {
			// Content
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dataSet)) {
				// build column hash from datafields
				self::$tableColumnHash[$row['datafield_name']] = "text";
			}
		}
		
		return self::$tableColumnHash;
	}
}

class tx_generaldatadisplay_pi1_dataOnlyList extends tx_generaldatadisplay_pi1_queryList {
	// vars
	protected $table = "tx_generaldatadisplay_data";
	protected $objType = "tx_generaldatadisplay_pi1_data";
	protected $orderField = "data_title";
	
	public function __construct() {
		parent::__construct();
		$this->restrictQuery = "tx_generaldatadisplay_data.pid=" . DATA_PID . " AND NOT tx_generaldatadisplay_data.deleted AND NOT tx_generaldatadisplay_categories_mm.deleted";
	}
	
	public function getDS(tx_generaldatadisplay_pi1_objClause &$clause = NULL) {
		// delete former result	
		$this->objArr = array();
		
		$table = $this->table;
		
		$whereClause = $this->restrictQuery . ($clause && $clause->notEmpty() ? " AND " . $clause->get($this->table) : "");
		
		$dataSet = $GLOBALS['TYPO3_DB']->exec_SELECTquery('tx_generaldatadisplay_data.*, tx_generaldatadisplay_categories_mm.category_uid', 
            'tx_generaldatadisplay_data LEFT JOIN tx_generaldatadisplay_categories_mm
             ON tx_generaldatadisplay_data.uid = tx_generaldatadisplay_categories_mm.data_uid', $whereClause, '', $this->orderField);
             
        $this->sqlErrorMsg = $GLOBALS['TYPO3_DB']->sql_error();
		$this->nrResults = $GLOBALS['TYPO3_DB']->sql_num_rows($dataSet);
		
		if ($this->nrResults > 0) {
			// Content
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dataSet)) {
				$data    = t3lib_div::makeInstance($this->objType);
				$objVars = t3lib_div::makeInstance(PREFIX_ID . '_objVar');
				$data->setProperty("objVars", $objVars->set($row));
				// multiple categories result in multiple ds with same ID
				$this->objArr[] = $data;
			}
		}
		return $this->objArr;
	}
}

class tx_generaldatadisplay_pi1_datacontentList extends tx_generaldatadisplay_pi1_queryList {
	// vars
	protected $table = "tx_generaldatadisplay_datacontent";
	protected $objType = "tx_generaldatadisplay_pi1_datacontent";
	protected $orderField = "tx_generaldatadisplay_datafields.display_sequence";
	
	public function __construct() {
		parent::__construct();
		$this->restrictQuery = "tx_generaldatadisplay_datacontent.pid=" . DATA_PID . " AND NOT tx_generaldatadisplay_datacontent.deleted AND NOT tx_generaldatadisplay_datafields.deleted";
	}
	
	public function getDS(tx_generaldatadisplay_pi1_objClause &$clause = NULL) {
		// delete former result	
		$this->objArr = array();
		
		$table = $this->table;
		
		$whereClause = $this->restrictQuery . ($clause && $clause->notEmpty() ? " AND " . $clause->get($this->table) : "");
		
		$dataSet = $GLOBALS['TYPO3_DB']->exec_SELECTquery('tx_generaldatadisplay_datafields.datafield_name, tx_generaldatadisplay_datafields.datafield_type, tx_generaldatadisplay_datacontent.uid, tx_generaldatadisplay_datacontent.datacontent, tx_generaldatadisplay_datacontent.datafields_uid', 'tx_generaldatadisplay_datacontent LEFT JOIN tx_generaldatadisplay_datafields
		ON tx_generaldatadisplay_datacontent.datafields_uid = tx_generaldatadisplay_datafields.uid', $whereClause, '', $this->orderField);
		
		$this->sqlErrorMsg = $GLOBALS['TYPO3_DB']->sql_error();
		$this->nrResults = $GLOBALS['TYPO3_DB']->sql_num_rows($dataSet);
		
		if ($this->nrResults > 0) {
			// Content
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dataSet)) {
				$data    = t3lib_div::makeInstance($this->objType);
				$uid     = $data->setProperty("uid", $row['uid']);
				$objVars = t3lib_div::makeInstance(PREFIX_ID . '_objVar');
				$data->setProperty("objVars", $objVars->set($row));
				$this->objArr[$uid] = $data;
			}
		}
		return $this->objArr;
	}
}

class tx_generaldatadisplay_pi1_categoryList extends tx_generaldatadisplay_pi1_queryList {
	// vars
	protected $table = "tx_generaldatadisplay_categories";
	protected $objType = "tx_generaldatadisplay_pi1_category";
	protected $orderField = "category_name";
	
	public function getDS(tx_generaldatadisplay_pi1_objClause &$clause = NULL) {
		parent::getDS($clause);
		$this->createCategoryHierarchy();
		
		return $this->objArr;
	}
	
	public function getUsedCategoryValues($valueField = 'category_name') {
		// returns an array of used values of the category table
		$usedHashArr = array();
		
		// get special dataList Hash
		$dataList = t3lib_div::makeInstance(PREFIX_ID . '_categories_mmList');
		$dataList->getDS();

		// special data_category hash
		$dataCategoryHash = $dataList->getHash('data_uid', 'category_uid');
		foreach ($this->objArr as $key => $obj) {
			if ($dataCategoryHash[$key]) {
				$usedHashArr[$key] = $obj->getObjVar($valueField);
				// add progenitors too
				$progenitors       = $this->getAllProgenitors($key);
				foreach ($progenitors as $uid)
					$usedHashArr[$uid] = $this->objArr[$uid]->getObjVar($valueField);
			}
		}
		return $usedHashArr;
	}
	
	public function getAllProgenitors($dataCategory) {
		$allProgenitors = array();
		
		while ($this->objArr[$dataCategory] && !$checkLoop[$dataCategory]) {
			$checkLoop[$dataCategory] = 1;
			$dataCategory             = $this->objArr[$dataCategory]->getObjVar('category_progenitor');
			if ($dataCategory)
				$allProgenitors[$dataCategory] = $dataCategory;
		}
		return $allProgenitors;
	}
	
	public function getOptionSelect($field, $selected = '', $withNoCategory = FALSE, $usedOnly = FALSE, $checkfield = 'uid') {
		$options = "";
		
		if ($usedOnly) {
			// get uids
			$uids         = array_keys($this->getUsedCategoryValues());
			// make clause to retrieve used categories
			$searchClause = t3lib_div::makeInstance(PREFIX_ID . '_objClause');
			$searchClause->addAND('uid', implode(', ', $uids), 'IN');
			$this->getDS($searchClause);
		}
		// array or comma separated list ?
		$selected = is_array($selected) ? $selected : explode(',',$selected);
		// create selected item hash
		foreach($selected as $uid) {
            $sHash[$uid ? $uid : 0] = 1;
		}
		// no category option
		if ($withNoCategory) {
            $options = '<option value="0" '.($sHash[0] ? ' selected="selected">' : '>'). $this->pi_getLL('empty_category') . '</option>';
        }
		// get options
		foreach ($this->objArr as $obj) {
			$lvlspaces = '';
			$lvl       = $obj->getObjVar('level') ? $obj->getObjVar('level') : 0;
			for ($i = 1; $i <= $lvl; $i++)
				$lvlspaces .= '&nbsp;&nbsp;';
			$optionEntry = '<option value="' . $obj->getObjVar($checkfield) . '"' . (($sHash[$obj->getObjVar($checkfield)]) ? ' selected="selected">' : '>') . $lvlspaces . $obj->getObjVar($field) . '</option>';
			
			// add level class
			$optionEntry = $this->cObj->addParams($optionEntry, array(
				'class' => 'optionfield-categorylvl' . $obj->getObjVar('level')
			));
			$options .= $optionEntry;
		}
		
		return $options;
	}
	
	private function createCategoryHierarchy() {
		$sortHashArr = array();
		$newObjArr   = array();
		
		foreach ($this->objArr as $key => $node) {
			$level = 0;
			while ($node->getObjVar('category_progenitor')) {
				$level++;
				$savedNode = $node;
				$node      = $this->objArr[$node->getObjVar('category_progenitor')];
				// node is not part of this array -> maybe deleted...
				if (!$node)
					break;
				$childs = $node->getObjVar('childs') ? $node->getObjVar('childs') : array();
				if (!isset($childs[$savedNode->getObjVar('uid')])) {
					// save child uid
					$childs[$savedNode->getObjVar('uid')] = $savedNode->getObjVar('uid');
					$node->setObjVar('childs', $childs);
				}
			}
			// save nodelevel
			$this->objArr[$key]->setObjVar('level', $level);
			// build sortHashArr for hierachical sorting
			$sortHashArr[$level][] = $key;
		}
		
		// get the entry level
		$savelevel = $level;
		foreach ($sortHashArr as $level => $nodeArr)
			if ($level < $savelevel)
				$savelevel = $level;
		
		$this->objArr = $this->hierachicalSort($sortHashArr[$savelevel]);
	}
	
	private function hierachicalSort($nodeArr, $level = 0, &$newObjArr = array()) {
		while ($nodeArr) {
			$node                               = $this->objArr[array_shift($nodeArr)];
			$newObjArr[$node->getObjVar('uid')] = $node;
			if ($subchilds = $node->getObjVar('childs'))
				$this->hierachicalSort($subchilds, ++$level, $newObjArr);
		}
		return $newObjArr;
	}
}

class tx_generaldatadisplay_pi1_categories_mmList extends tx_generaldatadisplay_pi1_queryList {
	// vars
	protected $table = "tx_generaldatadisplay_categories_mm";
	protected $objType = "tx_generaldatadisplay_pi1_categories_mm";
	protected $orderField = "category_uid";
}

class tx_generaldatadisplay_pi1_datafieldList extends tx_generaldatadisplay_pi1_queryList {
	// vars
	protected $table = "tx_generaldatadisplay_datafields";
	protected $objType = "tx_generaldatadisplay_pi1_datafield";
	protected $orderField = "display_sequence";
	
	public function getOptionSelect($field, $selected = '', $checkfield = 'uid', $searchableOnly = TRUE) {
		// Get options
		foreach ($this->objArr as $key => $obj) {
			// get metadata of datafield
			$metadata = tx_generaldatadisplay_pi1_dataFields::getMetadata($key);
			if ($metadata['datafield_searchable'] == "yes" || !$searchableOnly) {
				$optionEntry = '<option value="' . $obj->getObjVar($checkfield) . '"' . (($obj->getObjVar($checkfield) == $selected) ? ' selected="selected">' : '>') . $obj->getObjVar($field) . '</option>';
				
				$options .= $optionEntry;
			}
		}
		
		return $options;
	}
	
	public function getUidFromDatafield($datafieldName) {
		foreach ($this->objArr as $key => $obj) {
			$objVars = $obj->getProperty('objVars');
			if ($objVars->get('datafield_name') == $datafieldName)
				return $objVars->get('uid');
		}
		return FALSE;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/general_data_display/pi1/class.tx_generaldatadisplay_pi1_queryList.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/general_data_display/pi1/class.tx_generaldatadisplay_pi1_queryList.php']);
}

?>
