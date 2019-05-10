<?php

abstract class XenForo_Model_TemplateModificationAbstract extends XenForo_Model
{
	protected $_modTableName = '';
	protected $_logTableName = '';
	protected $_dataWriterName = '';

	abstract public function onAddonActiveSwitch(array $addon);

	public function getModificationById($id)
	{
		return $this->_getDb()->fetchRow("
			SELECT *
			FROM {$this->_modTableName}
			WHERE modification_id = ?
		", $id);
	}

	public function getModificationByKey($key)
	{
		return $this->_getDb()->fetchRow("
			SELECT *
			FROM {$this->_modTableName}
			WHERE modification_key = ?
		", $key);
	}

	public function getAllModifications()
	{
		return $this->fetchAllKeyed("
			SELECT *
			FROM {$this->_modTableName}
			ORDER BY template, execution_order
		", 'modification_id');
	}

	public function getModificationsByKeys(array $keys)
	{
		if (!$keys)
		{
			return array();
		}

		return $this->fetchAllKeyed("
			SELECT *
			FROM {$this->_modTableName}
			WHERE modification_key IN (" . $this->_getDb()->quote($keys) . ")
		", 'modification_key');
	}

	public function groupModificationsByAddon(array $modifications)
	{
		$results = array();
		foreach ($modifications AS $id => $modification)
		{
			$results[$modification['addon_id']][$id] = $modification;
		}

		return $results;
	}

	public function getModificationsForTemplate($title)
	{
		return $this->fetchAllKeyed("
			SELECT *
			FROM {$this->_modTableName}
			WHERE template = ?
			ORDER BY execution_order
		", 'modification_id', $title);
	}

	public function getActiveModificationsForTemplate($title)
	{
		return $this->fetchAllKeyed("
			SELECT modification.*
			FROM {$this->_modTableName} AS modification
			LEFT JOIN xf_addon AS addon ON (addon.addon_id = modification.addon_id)
			WHERE modification.template = ?
				AND modification.enabled = 1
				AND (addon.active IS NULL OR addon.active = 1)
			ORDER BY modification.execution_order
		", 'modification_id', $title);
	}

	public function getModificationLogSummary()
	{
		return $this->fetchAllKeyed("
			SELECT modification_id,
				SUM(apply_count) AS ok,
				COUNT(IF(status = 'ok' AND apply_count = 0, 1, NULL)) AS not_found,
				COUNT(IF(status LIKE 'error%', 1, NULL)) AS error
			FROM {$this->_logTableName}
			GROUP BY modification_id
		", 'modification_id');
	}

	public function getModificationLogsForModification($modificationId)
	{
		return $this->fetchAllKeyed("
			SELECT *
			FROM {$this->_logTableName}
			WHERE modification_id = ?
		", 'template_id', $modificationId);
	}

	public function getModificationTemplateTitlesForAddon($addOnId)
	{
		return $this->_getDb()->fetchCol("
			SELECT DISTINCT template
			FROM {$this->_modTableName}
			WHERE addon_id = ?
		", $addOnId);
	}

	public function updateTemplateModificationLog($templateId, array $modificationStatuses)
	{
		$inserts = array();
		$db = $this->_getDb();
		$quotedTemplateId = $db->quote($templateId);

		foreach ($modificationStatuses AS $id => $status)
		{
			if (is_int($status))
			{
				$inserts[] = '(' . $quotedTemplateId .
					', ' . $db->quote($id) .
					", 'ok', " . $db->quote($status) . ')';
			}
			else
			{
				$inserts[] = '(' . $quotedTemplateId .
					', ' . $db->quote($id) .
					', ' . $db->quote($status) . ', 0)';
			}
		}

		$db->delete($this->_logTableName, 'template_id = ' . $quotedTemplateId);
		if ($inserts)
		{
			$db->query("
				INSERT INTO {$this->_logTableName}
					(template_id, modification_id, status, apply_count)
				VALUES " . implode(', ', $inserts)
			);
		}
	}

	public function applyModificationsToTemplate($title, $template, &$status = array())
	{
		$modifications = $this->getActiveModificationsForTemplate($title);
		return $this->applyTemplateModifications($template, $modifications, $status);
	}

	public function applyTemplateModifications($template, array $modifications, &$status = array())
	{
		$status = array();

		foreach ($modifications AS $id => $modification)
		{
			$template = str_replace("\r\n", "\n", $template);

			switch ($modification['action'])
			{
				case 'str_replace':
					$modification['find'] = str_replace("\r\n", "\n", $modification['find']);
					$modification['replace'] = str_replace('$0', $modification['find'], $modification['replace']);

					$status[$id] = substr_count($template, $modification['find']);
					$template = str_replace($modification['find'], $modification['replace'], $template);
					break;

				case 'preg_replace':
				case 'callback':
					$modification['find'] = str_replace(
						array("\r\n", '\r\n'),
						array("\n", '\n'),
						$modification['find']
					);

					try
					{
						if (preg_match('/\W[\s\w]*e[\s\w]*$/', $modification['find']))
						{
							// can't run a /e regex
							$status[$id] = 'error_invalid_regex';
						}
						else
						{
							$status[$id] = preg_match_all($modification['find'], $template, $null);
						}
					}
					catch (ErrorException $e)
					{
						$status[$id] = 'error_invalid_regex';
						break;
					}

					if ($modification['action'] == 'callback')
					{
						if (XenForo_Application::getConfig()->enableTemplateModificationCallbacks)
						{
							if (preg_match('/^([a-z0-9_\\\\]+)::([a-z0-9_]+)$/i', $modification['replace'], $match))
							{
								if (!class_exists($match[1]) || !is_callable(array($match[1], $match[2])))
								{
									$status[$id] = 'error_invalid_callback';
								}
								else
								{
									try
									{
										$template = preg_replace_callback(
											$modification['find'],
											array($match[1], $match[2]),
											$template
										);
									}
									catch (Exception $e)
									{
										$status[$id] = 'error_callback_failed';
										XenForo_Error::logException($e, false, 'Template modification callback error: ');
									}
								}
							}
							else
							{
								$status[$id] = 'error_invalid_callback';
							}
						}
					}
					else
					{
						$template = preg_replace($modification['find'], $modification['replace'], $template);
					}
					break;

				default:
					$status[$id] = 'error_unknown_action';
			}
		}

		return $template;
	}

	/**
	 * Gets all modifications that belong to the specified add-on,
	 * ordered by their modification keys.
	 *
	 * @param string $addOnId
	 *
	 * @return array Format: [modification_key] => info
	 */
	public function getModificationsByAddOnId($addOnId)
	{
		return $this->fetchAllKeyed("
			SELECT *
			FROM {$this->_modTableName}
			WHERE addon_id = ?
			ORDER BY modification_key
		", 'modification_key', $addOnId);
	}

	/**
	 * Deletes the modifications that belong to the specified add-on.
	 *
	 * @param string $addOnId
	 */
	public function deleteModificationsForAddOn($addOnId)
	{
		$db = $this->_getDb();
		$db->query("
			DELETE log FROM {$this->_logTableName} AS log
			INNER JOIN {$this->_modTableName} AS modification ON
				(log.modification_id = modification.modification_id AND modification.addon_id = ?)
		", $addOnId);
		$db->delete($this->_modTableName, 'addon_id = ' . $db->quote($addOnId));
	}

	/**
	 * Imports the modifications for an add-on.
	 *
	 * @param SimpleXMLElement $xml XML element pointing to the root of the event data
	 * @param string $addOnId Add-on to import for
	 */
	public function importModificationAddOnXml(SimpleXMLElement $xml, $addOnId)
	{
		$db = $this->_getDb();

		$addonMods = $this->getModificationsByAddOnId($addOnId);

		XenForo_Db::beginTransaction($db);
		$this->deleteModificationsForAddOn($addOnId);

		$xmlEntries = XenForo_Helper_DevelopmentXml::fixPhpBug50670($xml->modification);

		$keys = array();
		foreach ($xmlEntries AS $entry)
		{
			$keys[] = (string)$entry['modification_key'];
		}

		$modifications = $this->getModificationsByKeys($keys);

		foreach ($xmlEntries AS $modification)
		{
			$key = (string)$modification['modification_key'];

			$dw = XenForo_DataWriter::create($this->_dataWriterName);
			if (isset($modifications[$key]))
			{
				$dw->setExistingData($modifications[$key]);
			}

			if (isset($addonMods[$key]))
			{
				$enabled = $addonMods[$key]['enabled'];
			}
			else
			{
				$enabled = (string)$modification['enabled'];
			}

			$dw->setOption(XenForo_DataWriter_TemplateModificationAbstract::OPTION_FULL_TEMPLATE_COMPILE, false);
			$dw->setOption(XenForo_DataWriter_TemplateModificationAbstract::OPTION_REPARSE_TEMPLATE, false);
			$dw->setOption(XenForo_DataWriter_TemplateModificationAbstract::OPTION_VERIFY_MODIFICATION_KEY, false);
			$dw->bulkSet(array(
				'addon_id' => $addOnId,
				'template' => (string)$modification['template'],
				'modification_key' => $key,
				'description' => (string)$modification['description'],
				'execution_order' => (int)$modification['execution_order'],
				'enabled' => $enabled,
				'action' => (string)$modification['action'],
				'find' => XenForo_Helper_DevelopmentXml::processSimpleXmlCdata($modification->find[0]),
				'replace' => XenForo_Helper_DevelopmentXml::processSimpleXmlCdata($modification->replace[0])
			));
			$this->_addExtraToAddonXmlImportDw($dw, $modification);
			$dw->save();
		}

		XenForo_Db::commit($db);
	}

	protected function _addExtraToAddonXmlImportDw(XenForo_DataWriter_TemplateModificationAbstract $dw, SimpleXMLElement $modification)
	{

	}

	/**
	 * Appends the add-on template modification XML to a given DOM element.
	 *
	 * @param DOMElement $rootNode Node to append all prefix elements to
	 * @param string $addOnId Add-on ID to be exported
	 */
	public function appendModificationAddOnXml(DOMElement $rootNode, $addOnId)
	{
		$modifications = $this->getModificationsByAddOnId($addOnId);

		$document = $rootNode->ownerDocument;

		foreach ($modifications AS $modification)
		{
			$modNode = $document->createElement('modification');
			$modNode->setAttribute('template', $modification['template']);
			$modNode->setAttribute('modification_key', $modification['modification_key']);
			$modNode->setAttribute('description', $modification['description']);
			$modNode->setAttribute('execution_order', $modification['execution_order']);
			$modNode->setAttribute('enabled', $modification['enabled']);
			$modNode->setAttribute('action', $modification['action']);

			$findNode = $document->createElement('find');
			$findNode->appendChild(XenForo_Helper_DevelopmentXml::createDomCdataSection($document, $modification['find']));
			$modNode->appendChild($findNode);

			$replaceNode = $document->createElement('replace');
			$replaceNode->appendChild(XenForo_Helper_DevelopmentXml::createDomCdataSection($document, $modification['replace']));
			$modNode->appendChild($replaceNode);

			$this->_modifyAddOnXmlNode($modNode, $modification);

			$rootNode->appendChild($modNode);
		}
	}

	protected function _modifyAddOnXmlNode(DOMElement &$modNode, array $modification)
	{
	}

	public function canEditModification(array $modification)
	{
		return XenForo_Application::debugMode()
			|| empty($modification['addon_id'])
			|| empty($modification['modification_id']);
	}
}
