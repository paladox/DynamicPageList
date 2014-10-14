<?php
/**
 * DynamicPageList
 * DPL Variables Class
 *
 * @author		IlyaHaykinson, Unendlich, Dangerville, Algorithmix, Theaitetos, Alexia E. Smith
 * @license		GPL
 * @package		DynamicPageList
 *
 **/
namespace DPL;

class Parameters extends ParametersData {
	/**
	 * Set parameter options.
	 *
	 * @var		array
	 */
	private $parameterOptions = [];

	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		parent::__construct();
		$this->setDefaults();
	}

	/**
	 * Handle simple parameter functions.
	 *
	 * @access	public
	 * @param	string	Function(Parameter) Called
	 * @param	string	Function Arguments
	 * @return	boolean	Successful
	 */
	public function __call($parameter, $arguments) {
		//Subvert to the real function if it exists.  This keeps code elsewhere clean from needed to check if it exists first.
		if (method_exists($this, $parameter.'Parameter')) {
			return call_user_func_array($this->$parameter.'Parameter', $arguments);
		}
		$option = $arguments[0];
		$parameter = strtolower($parameter);

		//Assume by default that these simple parameter options should not failed, but if they do we will set $success to false below.
		$success = true;
		$parameterData = $this->getData($parameter);
		if ($parameterData !== false) {
			$this->setParameter($parameter, $option);

			//If a parameter specifies options then enforce them.
			if (is_array($parameterData['values']) === true && in_array($option, $parameterData['values'])) {
				$this->setParameter($parameter, $option);
			} else {
				$success = false;
			}

			//Strip <html> tag.
			if ($parameterData['strip_html'] === true) {
				$this->setParameter($parameter, self::stripHtmlTags($option));
			}

			//Simple integer intval().
			if ($parameterData['integer'] === true) {
				if (!is_numeric($option)) {
					if ($parameterData['default'] !== null) {
						$this->setParameter($parameter, intval($parameterData['default']));
					} else {
						$success = false;
					}
				} else {
					$this->setParameter($parameter, intval($option));
				}
			}

			// Booleans
			if ($parameterData['boolean'] === true) {
				$option = $this->filterBoolean($option);
				if ($option !== null) {
					$this->setParameter($parameter, $option);
				} else {
					$success = false;
				}
			}

			//Timestamps
			if ($parameterData['timestamp'] === true) {
				$option = wfTimestamp(TS_MW, $option);
				if ($option !== false) {
					$this->setParameter($parameter, $option);
				} else {
					$success = false;
				}
			}

			//List of Pages
			if ($parameterData['page_name_list'] === true) {
				$list = $this->getPageNameList($option, (bool) $parameterData['page_name_must_exist']);
				if ($list !== false) {
					$this->setParameter($parameter, $list);
					if (empty($list)) {
						//If the list array is empty simply return true because selection criteria is not found and there are no open reference conflicts.
						return true;
					}
				} else {
					$success = false;
				}
			}

			/***************************************************************************************************/
			/* The following two are last as they should only be triggered if the above options pass properly. */
			/***************************************************************************************************/
			//Set that criteria was found for a selection.
			if ($parameterData['set_criteria_found'] === true && !empty($option)) {
				$this->setSelectionCriteriaFound(true);
			}

			//Set open references conflict possibility.
			if ($parameterData['open_ref_conflict'] === true) {
				$this->setOpenReferencesConflict(true);
			}
		}
		return $success;
	}

	/**
	 * Set default parameters based on ParametersData.
	 *
	 * @access	private
	 * @return	void
	 */
	private function setDefaults() {
		$parameters = self::getParametersForRichness();
		foreach ($parameters as $parameter) {
			if ($this->getData($parameter)['default'] !== null) {
				$this->setParameter($parameter, $this->getData($parameter)['default']);
			}
		}
	}

	/**
	 * Set a parameter's option.
	 *
	 * @access	public
	 * @param	string	Parameter to set
	 * @param	mixed	Option to set
	 * @return	void
	 */
	public function setParameter($parameter, $option) {
		$this->parameterOptions[$parameter] = $option
	}

	/**
	 * Set a parameter's option.
	 *
	 * @access	public
	 * @param	string	Parameter to set
	 * @return	mixed	Option for specified parameter.
	 */
	public function getParameter($option) {
		return $this->parameterOptions[$parameter];
	}

	/**
	 * Filter a standard boolean like value into an actual boolean.
	 *
	 * @access	private
	 * @param	mixed	Integer or string to evaluated through filter_var().
	 * @return	boolean
	 */
	private function filterBoolean($boolean) {
		return filter_var($boolean, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
	}

	/**
	 * Strip <html> tags.
	 *
	 * @access	private
	 * @param	string	Dirty Text
	 * @return	string	Clean Text
	 */
	private function stripHtmlTags($text) {
		$text = preg_replace("#<.*?html.*?>#is", "", $text);

		return $text;
	}

	/**
	 * Get a list of valid page names.
	 *
	 * @access	private
	 * @param	string	Raw Text of Pages
	 * @param	boolean	[Optional] Each Title MUST Exist
	 * @return	mixed	List of page titles or false on error.
	 */
	private function getPageNameList($text, $mustExist = true) {
		$list = [];
		$pages = explode('|', trim($text));
		foreach ($pages as $page) {
			$page = trim($page);
			$page = rtrim($page, '\\'); //This was fixed from the original code, but I am not sure what its intended purpose was.
			if (empty($page)) {
				continue;
			}
			if ($mustExist === true) {
				$title = \Title::newFromText($page)
				if (!$title) {
					return false;
				}
				$list[] = $title;
			} else {
				$list[] = $page;
			}
		}

		return $list;
	}

	/**
	 * Clean and test 'category' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function categoryParameter($option) {
		$option = trim($option);
		if (empty($option)) {
			return false;
		}
		// Init array of categories to include
		$categories = [];
		$heading    = false;
		$notHeading = false;
		if (substr($option, 0, 1) == '+') { // categories are headings
			$heading = true;
			$option = ltrim($option, '+');
		}
		if (substr($option, 0, 1) == '-') { // categories are NOT headings
			$notHeading = true;
			$option = ltrim($option, '-');
		}

		$operator = 'OR';
		//We expand html entities because they contain an '& 'which would be interpreted as an AND condition
		$option = html_entity_decode($option, ENT_QUOTES);
		if (strpos($option, '&') !== false) {
			$parameters = explode('&', $option);
			$operator = 'AND';
		} else {
			$parameters = explode('|', $option);
		}
		foreach ($parameters as $parameter) {
			$parameter = trim($parameter);
			if ($parameter == '_none_') {
				$parameters[$parameter] = '';
				$bIncludeUncat    = true;
				$categories[]    = '';
			} elseif ($parameter != '') {
				if ($parameter[0] == '*' && strlen($parameter) >= 2) {
					if ($parameter[1] == '*') {
						$parameterList = explode('|', self::getSubcategories(substr($parameter, 2), $sPageTable, 2));
					} else {
						$parameterList = explode('|', self::getSubcategories(substr($parameter, 1), $sPageTable, 1));
					}
					foreach ($parameterList as $sPar) {
						$title = \Title::newFromText($sPar);
						if (!is_null($title)) {
							$categories[] = $title->getDbKey();
						}
					}
				} else {
					$title = \Title::newFromText($parameter);
					if (!is_null($title)) {
						$categories[] = $title->getDbKey();
					}
				}
			}
		}
		if (!empty($categories)) {
			if ($operator == 'OR') {
				$aIncludeCategories[] = $categories;
			} else {
				foreach ($categories as $parameters) {
					$parameter               = array();
					$parameter[]             = $parameters;
					$aIncludeCategories[] = $parameter;
				}
			}
			if ($heading) {
				$aCatHeadings = array_unique($aCatHeadings + $categories);
			}
			if ($notHeading) {
				$aCatNotHeadings = array_unique($aCatNotHeadings + $categories);
			}
			$this->setOpenReferencesConflict(true);
		}
	}

	/**
	 * Clean and test 'notcategory' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function notcategoryParameter($option) {
		$title = \Title::newFromText($option);
		if (!is_null($title)) {
			$data = $this->getParameter('excludecategories');
			$data[] = $title->getDbKey();
			$this->setParameter('excludecategories', $data);
			$this->setOpenReferencesConflict(true);
			return true;
		}
		return false;
	}

	/**
	 * Clean and test 'namespace' parameter.
	 *
	 * @access	public
	 * @param	string	Option passed to parameter.
	 * @return	boolean	Success
	 */
	public function namespaceParameter($option) {
		$extraParams = explode('|', $option);
		foreach ($extraParams as $parameter) {
			$parameter = trim($parameter);
			$data = $this->getParameter('namespace');
			if (in_array($parameter, $options->getOptions('namespace')['values'])) {
				$data[] = $wgContLang->getNsIndex($parameter)
				$this->setParameter('namespaces', $data);
				$this->setSelectionCriteriaFound(true);
			} elseif (array_key_exists($parameter, array_keys($options->getOptions('namespace')['values']))) {
				$data[] = $parameter;
				$this->setParameter('namespaces', $data);
				$this->setSelectionCriteriaFound(true);
			} else {
				return false;
			}
		}
		return true;
	}

	/**
	 * Clean and test 'notnamespace' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function notnamespaceParameter($option) {
		if (!in_array($option, $options->getOptions('notnamespace')['values'])) {
			return false;
		}
		$data = $this->getParameter('excludenamespaces');
		$data[] = $wgContLang->getNsIndex($option);
		$this->setParameter('excludenamespaces', $data);
		$this->setSelectionCriteriaFound(true);
		return true;
	}

	/**
	 * Clean and test 'ordermethod' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function ordermethodParameter($option) {
		$methods   = explode(',', $option);
		$success = true;
		foreach ($methods as $method) {
			if (!in_array($method, $options->getOptions('ordermethod')['values'])) {
				$success = false;
			}
		}
		if ($success === true) {
			$this->setParameter('ordermethods', $methods);
			if ($methods[0] != 'none') {
				$this->setOpenReferencesConflict(true);
			}
		}
		return false;
	}

	/**
	 * Clean and test 'mode' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function modeParameter($option) {
		if (in_array($option, $options->getOptions('mode')['values'])) {
			//'none' mode is implemented as a specific submode of 'inline' with <br/> as inline text
			if ($option == 'none') {
				$this->setParameter('pagelistmode', 'inline');
				$this->setParameter('inltxt', '<br/>');
			} else if ($option == 'userformat') {
				// userformat resets inline text to empty string
				$this->setParameter('inltxt', '');
				$this->setParameter('pagelistmode', $option);
			} else {
				$this->setParameter('pagelistmode', $option);
			}
		} else {
			return false;
		}
	}

	/**
	 * Clean and test 'distinct' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function distinctParameter($option) {
		if (in_array($option, $options->getOptions('distinct')['values'])) {
			if ($option == 'strict') {
				$this->setParameter('distinctresultset', 'strict');
			} elseif (self::filterBoolean($option) === true) {
				$this->setParameter('distinctresultset', true);
			} else {
				$this->setParameter('distinctresultset', false);
			}
		} else {
			return false;
		}
	}

	/**
	 * Clean and test 'ordercollation' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function ordercollationParameter($option) {
		if ($option == 'bridge') {
			$this->setParameter('ordersuitsymbols', true);
		} elseif (!empty($option)) {
			$this->setParameter('ordercollation', "COLLATE ".self::$DB->strencode($option));
		}
	}

	/**
	 * Short cut to formatParameter();
	 *
	 * @access	public
	 * @return	mixed
	 */
	public function listseparatorsParameter() {
		return call_user_func_array([$this, 'formatParameter'], func_get_args());
	}

	/**
	 * Clean and test 'format' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function formatParameter($option) {
		// parsing of wikitext will happen at the end of the output phase
		// we replace '\n' in the input by linefeed because wiki syntax depends on linefeeds
		$option            = self::stripHtmlTags($option);
		$option            = str_replace(['\n', "¶"], "\n", $option);
		$this->setParameter('listseparators', explode(',', $option, 4));
		// mode=userformat will be automatically assumed
		$this->setParameter('pagelistmode', 'userformat');
		$this->setParameter('inltxt', '');
	}

	/**
	 * Clean and test 'title' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function titleParameter($option) {
		// we replace blanks by underscores to meet the internal representation
		// of page names in the database
		$title = \Title::newFromText($option);
		if ($title) {
			$this->setParameter('titleis', str_replace(' ', '_', $title->getText()));
			$this->setParameter('namespaces', array_merge($this->getParameter['namespaces'], $title->getNamespace()));
			$this->setParameter('pagelistmode', 'userformat');
			$this->setParameter('ordermethods', explode(',', ''));
			$this->setParameter('selectioncriteriafound', true);
			$this->setParameter('allowcachedresults', true);
			$this->setOpenReferencesConflict(true);
		}
	}

	/**
	 * Clean and test 'title<' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function titleLTParameter($option) {
		// we replace blanks by underscores to meet the internal representation
		// of page names in the database
		$this->setParameter('titlege', str_replace(' ', '_', $option));
		$this->setSelectionCriteriaFound(true);
	}

	/**
	 * Clean and test 'title<' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function titleGTParameter($option) {
		// we replace blanks by underscores to meet the internal representation
		// of page names in the database
		$this->setParameter('titlele', str_replace(' ', '_', $option));
		$this->setSelectionCriteriaFound(true);
	}

	/**
	 * Clean and test 'scroll' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function scrollParameter($option) {
		if (in_array($option, $options->getOptions('scroll')['values'])) {
			$this->setParameter('scroll', self::filterBoolean($option));
			// if scrolling is active we adjust the values for certain other parameters
			// based on URL arguments
			if ($this->getParameter('scroll') === true) {
				$sTitleGE = $wgRequest->getVal('DPL_fromTitle', '');
				if (strlen($sTitleGE) > 0) {
					$sTitleGE[0] = strtoupper($sTitleGE[0]);
				}
				// findTitle has priority over fromTitle
				$findTitle = $wgRequest->getVal('DPL_findTitle', '');
				if (strlen($findTitle) > 0) {
					$findTitle[0] = strtoupper($findTitle[0]);
				}
				if ($findTitle != '') {
					$this->setParameter('titlege', '=_' . $findTitle);
				}
				$sTitleLE = $wgRequest->getVal('DPL_toTitle', '');
				if (strlen($sTitleLE) > 0) {
					$sTitleLE[0] = strtoupper($sTitleLE[0]);
				}
				$this->setParameter('titlege', str_replace(' ', '_', $sTitleGE));
				$this->setParameter('titlele', str_replace(' ', '_', $sTitleLE));
				$this->setParameter('crolldir', $wgRequest->getVal('DPL_scrollDir', ''));
				// also set count limit from URL if not otherwise set
				$this->setParameter('countscroll', $wgRequest->getVal('DPL_count', ''));
			}
		} else {
			return false;
		}
	}

	/**
	 * Clean and test 'replaceintitle' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function replaceintitleParameter($option) {
		// we offer a possibility to replace some part of the title
		$replaceInTitle = explode(',', $option, 2);
		if (isset($replaceInTitle[1])) {
			$replaceInTitle[1] = self::stripHtmlTags($replaceInTitle[1]);
		}

		$this->setParameter('replaceintitle', $replaceInTitle);
	}

	/**
	 * Clean and test 'debug' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function debugParameter($option) {
		if (in_array($option, $options->getOptions('debug')['values'])) {
			if ($key > 1) {
				$output .= $logger->escapeMsg(\DynamicPageListHooks::WARN_DEBUGPARAMNOTFIRST, $option);
			}
			$logger->iDebugLevel = intval($option);
		} else {
			return false;
		}
	}

	/**
	 * Clean and test 'titlematch' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function titlematchParameter($option) {
		// we replace blanks by underscores to meet the internal representation
		// of page names in the database
		$aTitleMatch             = explode('|', str_replace(' ', '\_', $option));
		$this->setSelectionCriteriaFound(true);
	}

	/**
	 * Clean and test 'categoriesminmax' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function categoriesminmaxParameter($option) {
		if (preg_match($options->getOptions('categoriesminmax')['pattern'], $option)) {
			$aCatMinMax = ($option == '') ? null : explode(',', $option);
		} else {
			return false;
		}
	}

	/**
	 * Clean and test 'include' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function includeParameter($option) {
	case 'includepage':
		$bIncPage = $option !== '';
		if ($bIncPage) {
			$aSecLabels = explode(',', $option);
		}
	}

	/**
	 * Clean and test 'includematch' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function includematchParameter($option) {
		$aSecLabelsMatch = explode(',', $option);
	}

	/**
	 * Clean and test 'includenotmatch' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function includenotmatchParameter($option) {
		$aSecLabelsNotMatch = explode(',', $option);
	}

	/**
	 * Clean and test 'secseparators' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function secseparatorsParameter($option) {
		// we replace '\n' by newline to support wiki syntax within the section separators
		$option = str_replace(['\n', "¶"], "\n", $option);
		$aSecSeparators = explode(',', $option);
	}

	/**
	 * Clean and test 'multisecseparators' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function multisecseparatorsParameter($option) {
		// we replace '\n' by newline to support wiki syntax within the section separators
		$option = str_replace(['\n', "¶"], "\n", $option);
		$aMultiSecSeparators = explode(',', $option);
	}

	/**
	 * Clean and test 'table' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function tableParameter($option) {
		$this->setOption['table'] = str_replace(['\n', "¶"], "\n", $option);
	}

	/**
	 * Clean and test 'tablerow' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function tablerowParameter($option) {
		$option = str_replace(['\n', "¶"], "\n", $option);
		if (trim($option) == '') {
			$this->setOption['tablerow'] = [];
		} else {
			$this->setOption['tablerow'] = explode(',', $option);
		}
	}

	/**
	 * Clean and test 'allowcachedresults' parameter.
	 * This function is necessary for the custom 'yes+warn' opton that sets 'warncachedresults'.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function allowcachedresultsParameter($option) {
		//If execAndExit was previously set (i.e. if it is not empty) we will ignore all cache settings which are placed AFTER the execandexit statement thus we make sure that the cache will only become invalid if the query is really executed.
		if (!$this->setOptions['execandexit']) {
			if ($option == 'yes+warn') {
				$this->setOptions['allowcachedresults'] = true;
				$this->setOptions['warncachedresults'] = true;
				return true;
			}
			$option = self::filterBoolean($option);
			if ($option !== null) {
				$this->setOptions['allowcachedresults'] = self::filterBoolean($option);
			} else {
				return false;
			}
		}
		return true;
	}

	/**
	 * Clean and test 'dplcache' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function dplcacheParameter($option) {
		if ($option != '') {
			$DPLCache     = $parser->mTitle->getArticleID() . '_' . str_replace("/", "_", $option) . '.dplc';
			$DPLCachePath = $parser->mTitle->getArticleID() % 10;
		} else {
			return false;
		}
	}

	/**
	 * Clean and test 'fixcategory' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function fixcategoryParameter($option) {
		\DynamicPageListHooks::fixCategory($option);
	}

	/**
	 * Clean and test 'reset' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function resetParameter($option) {
		foreach (preg_split('/[;,]/', $option) as $arg) {
			$arg = trim($arg);
			if (empty($arg)) {
				continue;
			}
			if (!in_array($arg, $options->getOptions('reset')['values'])) {
				return false;
			} elseif ($arg == 'links') {
				$this->setOption['reset'][0] = true;
			} elseif ($arg == 'templates') {
				$this->setOption['reset'][1] = true;
			} elseif ($arg == 'categories') {
				$this->setOption['reset'][2] = true;
			} elseif ($arg == 'images') {
				$this->setOption['reset'][3] = true;
			} elseif ($arg == 'all') {
				$this->setOption['reset'][0] = true;
				$this->setOption['reset'][1] = true;
				$this->setOption['reset'][2] = true;
				$this->setOption['reset'][3] = true;
			}
		}
	}

	/**
	 * Clean and test 'eliminate' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function eliminateParameter($option) {
		foreach (preg_split('/[;,]/', $option) as $arg) {
			$arg = trim($arg);
			if (empty($arg)) {
				continue;
			}
			if (!in_array($arg, $options->getOptions('eliminate')['values'])) {
				return false;
			} elseif ($arg == 'links') {
				$this->setOption['reset'][4] = true;
			} elseif ($arg == 'templates') {
				$this->setOption['reset'][5] = true;
			} elseif ($arg == 'categories') {
				$this->setOption['reset'][6] = true;
			} elseif ($arg == 'images') {
				$this->setOption['reset'][7] = true;
			} elseif ($arg == 'all') {
				$this->setOption['reset'][4] = true;
				$this->setOption['reset'][5] = true;
				$this->setOption['reset'][6] = true;
				$this->setOption['reset'][7] = true;
			} elseif ($arg == 'none') {
				$this->setOption['reset'][4] = false;
				$this->setOption['reset'][5] = false;
				$this->setOption['reset'][6] = false;
				$this->setOption['reset'][7] = false;
			}
		}
	}

	/**
	 * Clean and test 'categoryregexp' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function categoryregexpParameter($option) {
		$sCategoryComparisonMode      = ' REGEXP ';
		$aIncludeCategories[]         = array(
			$option
		);
		$this->setOpenReferencesConflict(true);
	}

	/**
	 * Clean and test 'categorymatch' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function categorymatchParameter($option) {
		$sCategoryComparisonMode      = ' LIKE ';
		$aIncludeCategories[]         = explode('|', $option);
		$this->setOpenReferencesConflict(true);
	}

	/**
	 * Clean and test 'notcategoryregexp' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function notcategoryregexpParameter($option) {
		$sNotCategoryComparisonMode   = ' REGEXP ';
		$aExcludeCategories[]         = $option;
		$this->setOpenReferencesConflict(true);
	}

	/**
	 * Clean and test 'notcategorymatch' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function notcategorymatchParameter($option) {
		$sNotCategoryComparisonMode   = ' LIKE ';
		$aExcludeCategories[]         = $option;
		$this->setOpenReferencesConflict(true);
	}

	/**
	 * Clean and test 'titleregexp' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function titleregexpParameter($option) {
		$sTitleMatchMode         = ' REGEXP ';
		$aTitleMatch             = array(
			$option
		);
		$this->setSelectionCriteriaFound(true);
	}

	/**
	 * Clean and test 'nottitleregexp' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function nottitleregexpParameter($option) {
		$sNotTitleMatchMode      = ' REGEXP ';
		$aNotTitleMatch          = array(
			$option
		);
		$this->setSelectionCriteriaFound(true);
	}

	/**
	 * Clean and test 'nottitlematch' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function nottitlematchParameter($option) {
		// we replace blanks by underscores to meet the internal representation
		// of page names in the database
		$aNotTitleMatch          = explode('|', str_replace(' ', '_', $option));
		$this->setSelectionCriteriaFound(true);
	}

	/**
	 * Clean and test 'articlecategory' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function articlecategoryParameter($option) {
		$sArticleCategory = str_replace(' ', '_', $option);
	}
}
?>