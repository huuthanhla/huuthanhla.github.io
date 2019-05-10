<?php

class XenForo_Model_Help extends XenForo_Model
{
	public function getHelpPageById($id)
	{
		return $this->_getDb()->fetchRow("
			SELECT *
			FROM xf_help_page
			WHERE page_id = ?
		", $id);
	}

	public function getHelpPageByName($name)
	{
		return $this->_getDb()->fetchRow("
			SELECT *
			FROM xf_help_page
			WHERE page_name = ?
		", $name);
	}

	public function getHelpPages()
	{
		return $this->fetchAllKeyed("
			SELECT *
			FROM xf_help_page
			ORDER BY display_order
		", 'page_id');
	}

	public function preparePage(array $page)
	{
		$page['title'] = new XenForo_Phrase($this->getHelpPageTitlePhraseName($page['page_id']));
		$page['description'] = new XenForo_Phrase($this->getHelpPageDescriptionPhraseName($page['page_id']));

		return $page;
	}

	public function preparePages(array $pages)
	{
		foreach ($pages AS &$page)
		{
			$page = $this->preparePage($page);
		}

		return $pages;
	}

	/**
	 * Gets the help page's title phrase name.
	 *
	 * @param integer $pageId
	 *
	 * @return string
	 */
	public function getHelpPageTitlePhraseName($pageId)
	{
		return 'help_page_' . $pageId . '_title';
	}

	/**
	 * Gets the help page's description phrase name.
	 *
	 * @param integer $pageId
	 *
	 * @return string
	 */
	public function getHelpPageDescriptionPhraseName($pageId)
	{
		return 'help_page_' . $pageId . '_desc';
	}

	/**
	 * Gets the help page's master title phrase text.
	 *
	 * @param integer $pageId
	 *
	 * @return string
	 */
	public function getHelpPageMasterTitlePhraseValue($pageId)
	{
		$phraseName = $this->getHelpPageTitlePhraseName($pageId);
		return $this->_getPhraseModel()->getMasterPhraseValue($phraseName);
	}

	/**
	 * Gets the help page's master description phrase text.
	 *
	 * @param integer $pageId
	 *
	 * @return string
	 */
	public function getHelpPageMasterDescriptionPhraseValue($pageId)
	{
		$phraseName = $this->getHelpPageDescriptionPhraseName($pageId);
		return $this->_getPhraseModel()->getMasterPhraseValue($phraseName);
	}

	/**
	 * Gets the help page's template name.
	 *
	 * @param integer $pageId
	 *
	 * @return string
	 */
	public function getHelpPageTemplateName($pageId)
	{
		return '_help_page_' . $pageId;
	}

	public function getHelpPageTemplate($pageId)
	{
		$templateName = $this->getHelpPageTemplateName($pageId);

		return $this->_getDb()->fetchRow("
			SELECT *
			FROM xf_template
			WHERE title = ?
				AND style_id = 0
		", $templateName);
	}

	/**
	 * Gets the phrase model object.
	 *
	 * @return XenForo_Model_Phrase
	 */
	protected function _getPhraseModel()
	{
		return $this->getModelFromCache('XenForo_Model_Phrase');
	}
}