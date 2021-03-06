<?php

namespace Lingo\Redirect;


/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2015 Leo Feyer
 *
 * PHP version 5
 * @copyright  Lingo4you 2015
 * @author     Mario Müller <https://www.lingolia.com/>
 * @version    1.0.5
 * @package    redirect_404
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */

class PageError404 extends \Contao\PageError404
{
	public function generate($pageId, $strDomain=null, $strHost=null)
	{
		$strUrl = FALSE;
		$intStatus = 301;
		$language = ($GLOBALS['TL_LANGUAGE'] != '' ? $GLOBALS['TL_LANGUAGE'] : FALSE);
		$strArticle = NULL;

		$blnProcessRequest = TRUE;

		// HOOK: add custom logic
		if (isset($GLOBALS['TL_HOOKS']['redirectPageError404']) && is_array($GLOBALS['TL_HOOKS']['redirectPageError404']))
		{
			foreach ($GLOBALS['TL_HOOKS']['redirectPageError404'] as $callback)
			{
				$this->import($callback[0]);
				$arrModules = $this->$callback[0]->$callback[1]($pageId, $strDomain, $strHost, $strUrl, $intStatus);
			}
		}

		// only handle if $strUrl == FALSE
		if ($strUrl === FALSE)
		{
			// Get the request string without the index.php fragment
			if (\Environment::get('request') == 'index.php')
			{
				$strRequest = '';
			}
			else
			{
				list($strRequest) = explode('?', str_replace('index.php/', '', \Environment::get('request')), 2);

				$strRequest = rawurldecode($strRequest);
			}

			if ($strRequest != '' && (!$GLOBALS['TL_CONFIG']['addLanguageToUrl'] || !preg_match('@^[a-z]{2}(\-[A-Z]{2})?/$@', $strRequest)))
			{
				$intSuffixLength = strlen($GLOBALS['TL_CONFIG']['urlSuffix']);

				if ($intSuffixLength > 0)
				{
					if (substr($strRequest, -$intSuffixLength) != $GLOBALS['TL_CONFIG']['urlSuffix'])
					{
						$blnProcessRequest = FALSE;
					}

					$strRequest = substr($strRequest, 0, -$intSuffixLength);
				}
			}

			if ($blnProcessRequest && $GLOBALS['TL_CONFIG']['addLanguageToUrl'])
			{
				$arrMatches = array();

				if (preg_match('@^([a-z]{2}(\-[A-Z]{2})?)/(.*)$@', $strRequest, $arrMatches))
				{
					$language = $arrMatches[1];
					$strRequest = $arrMatches[3];
				}
			}

			if ($blnProcessRequest && preg_match('@^(.*)(/articles/.+)$@', $strRequest, $arrMatches))
			{
				$strRequest = $arrMatches[1];
				$strArticle = $arrMatches[2];
			}

			$strAlias = $strRequest;

			if ($blnProcessRequest && !$strUrl && ($strAlias != ''))
			{
				$strUrl = $this->findInHistory(\Environment::get('host'), $strAlias, $strArticle, $language);
			}
		}


		if ($blnProcessRequest && ($strUrl !== FALSE))
		{
			// see https://github.com/contao/core/issues/6785
			// \Search::removeEntry(\Environment::get('request'));

			\System::log(sprintf('Redirect %s → %s (%d) Referer: %s', ($strAlias!=''?$strAlias:\Environment::get('request')), $strUrl, $intStatus, $this->getReferer()), __METHOD__, TL_GENERAL);

			$this->redirect($strUrl, $intStatus);
		}
		else
		{
			parent::generate($pageId, $strDomain, $strHost);
		}
	}


	/**
	 * look for the page in the history table
	 */
	protected function findInHistory($strDomain, $strAlias, $strArticle, $language = FALSE)
	{
		$objDatabase = \Database::getInstance();

		if ($language !== FALSE)
		{
			$objResult = $objDatabase->prepare("SELECT `pid` FROM `tl_page_history` WHERE `alias`=? AND `language`=? AND `dns`=?")
									 ->execute($strAlias, $language, $strDomain);
		}
		elseif ($strDomain !== FALSE)
		{
			$objResult = $objDatabase->prepare("SELECT `pid` FROM `tl_page_history` WHERE `alias`=? AND `dns`=?")
									 ->execute($strAlias, $strDomain);
		}
		else
		{
			$objResult = $objDatabase->prepare("SELECT `pid` FROM `tl_page_history` WHERE `alias`=?")
									 ->execute($strAlias);
		}

		if ($objResult->next())
		{
			$objPage = \PageModel::findWithDetails($objResult->pid);

			if ($objPage != NULL && $objPage->published != '')
			{
				\System::log(sprintf('Found «%s» in history', $strAlias.($language !== FALSE?'; language='.$language:'').($strDomain !== FALSE?'; dns='.$strDomain:'')), __METHOD__, TL_GENERAL);

				return \Environment::get('base') . $this->generateFrontendUrl($objPage->row(), $strArticle, $language);
			}
		}
		elseif ($strDomain !== FALSE)
		{
			return $this->findInHistory(FALSE, $strAlias, $strArticle, $language);
		}
		elseif ($language !== FALSE)
		{
			return $this->findInHistory(FALSE, $strAlias, $strArticle, FALSE);
		}

		return FALSE;
	}
}
