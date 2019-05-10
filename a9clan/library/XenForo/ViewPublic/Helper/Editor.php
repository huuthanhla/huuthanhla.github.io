<?php

abstract class XenForo_ViewPublic_Helper_Editor
{
	/**
	 * Array of editor IDs already used, prevents duplicate IDs.
	 *
	 * @var array
	 */
	protected static $_editorIds = array();

	/**
	 * Gets the editor template. The WYSIWYG editor will be used if supported by
	 * the browser.
	 *
	 * @param XenForo_View $view
	 * @param string $formCtrlName Name of the textarea. If using the WYSIWYG editor, this will have _html appended to it.
	 * @param string $message Default message to put in editor. This should contain BB code
	 * @param array $editorOptions Array of options for the editor. Defaults are provided for any unspecified
	 * 	Currently supported:
	 * 		editorId - (string) override normal {formCtrlName}_html id
	 * 		templateName - (string) override normal 'editor' name
	 * 		disable - (boolean) true to prevent WYSIWYG from activating
	 * 		json - (array) JSON options to be passed to the JS
	 *
	 * @return XenForo_Template_Abstract
	 */
	public static function getEditorTemplate(XenForo_View $view, $formCtrlName, $message = '', array $editorOptions = array())
	{
		$messageHtml = '';

		if (!empty($editorOptions['disable']))
		{
			$showWysiwyg = false;
		}
		else if (!XenForo_Visitor::getInstance()->enable_rte)
		{
			$showWysiwyg = false;
		}
		else
		{
			$showWysiwyg = self::supportsRichTextEditor();
		}

		$draftOption = XenForo_Application::getOptions()->saveDrafts;
		if (empty($draftOption['enabled']))
		{
			unset($editorOptions['autoSaveUrl']);
		}
		else if (!empty($editorOptions['autoSaveUrl']) && !isset($editorOptions['json']['autoSaveFrequency']))
		{
			$editorOptions['json']['autoSaveFrequency'] = $draftOption['saveFrequency'];
		}

		if (empty($editorOptions['templateName']))
		{
			$editorOptions['templateName'] = 'editor';
		}
		if (!isset($editorOptions['height']))
		{
			$editorOptions['height'] = '260px';
		}

		if (!isset($editorOptions['json']['bbCodes']))
		{
			if (XenForo_Application::isRegistered('bbCode'))
			{
				$bbCodeCache = XenForo_Application::get('bbCode');
			}
			else
			{
				$bbCodeCache = XenForo_Model::create('XenForo_Model_BbCode')->getBbCodeCache();
				XenForo_Application::set('bbCode', $bbCodeCache);
			}

			$bbCodes = array();
			if (!empty($bbCodeCache['bbCodes']))
			{
				foreach ($bbCodeCache['bbCodes'] AS $bbCodeTag => $bbCode)
				{
					if (!$bbCode['editor_icon_url'])
					{
						continue;
					}

					$bbCodes[$bbCodeTag] = array(
						'title' => new XenForo_Phrase('custom_bb_code_' . $bbCodeTag . '_title'),
						'hasOption' => $bbCode['has_option']
					);
				}
			}

			$editorOptions['json']['bbCodes'] = $bbCodes;
		}

		XenForo_CodeEvent::fire('editor_setup', array($view, $formCtrlName, &$message, &$editorOptions, &$showWysiwyg));

		if ($showWysiwyg)
		{
			if (substr($formCtrlName, -1) == ']')
			{
				$formCtrlNameHtml = substr($formCtrlName, 0, -1) . '_html]';
			}
			else
			{
				$formCtrlNameHtml = $formCtrlName . '_html';
			}

			if ($message !== '')
			{
				$bbCodeParser = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Wysiwyg', array('view' => $view)));
				$messageHtml = $bbCodeParser->render($message, array('lightBox' => false));
			}
		}
		else
		{
			$formCtrlNameHtml = $formCtrlName;
		}

		// get editor id
		if (isset($editorOptions['editorId']))
		{
			$editorId = $editorOptions['editorId'];
		}
		else
		{
			$ctrlInc = 0;

			do
			{
				$editorId = 'ctrl_' . $formCtrlName . ($ctrlInc ? "_$ctrlInc" : '');
				$ctrlInc++;
			}
			while (isset(self::$_editorIds[$editorId]) && $ctrlInc < 100);

			self::$_editorIds[$editorId] = true;
		}

		return $view->createTemplateObject($editorOptions['templateName'], array(
			'showWysiwyg' => $showWysiwyg,
			'height' => $editorOptions['height'],
			'formCtrlNameHtml' => $formCtrlNameHtml,
			'formCtrlName' => $formCtrlName,
			'editorId' => $editorId,

			'message' => $message,
			'messageHtml' => $messageHtml,

			'editorOptions' => $editorOptions,
		));
	}

	public static function supportsRichTextEditor()
	{
		$showWysiwyg = true;
		if (!empty($_SERVER['HTTP_USER_AGENT']))
		{
			if (preg_match('#blackberry#i', $_SERVER['HTTP_USER_AGENT']))
			{
				// this should match BB 7 and earlier - v10 uses "BB10"
				$showWysiwyg = false;
			}
			else if (preg_match('#opera mini|opera mobi#i', $_SERVER['HTTP_USER_AGENT']))
			{
				$showWysiwyg = false;
			}
			else if (
				preg_match('#IEMobile#i', $_SERVER['HTTP_USER_AGENT'], $match)
			)
			{
				// current IE mobile has issues with cursor moving
				$showWysiwyg = false;
			}
			else if (preg_match('#iphone|ipod|ipad#i', $_SERVER['HTTP_USER_AGENT']))
			{
				if (preg_match('#OS (\d+)_(\d+)#', $_SERVER['HTTP_USER_AGENT'], $match))
				{
					// contenteditable support in 5.0
					$showWysiwyg = intval($match[1]) >= 5;
				}
			}
			else if (
				preg_match('#android (\d+)\.(\d+)#i', $_SERVER['HTTP_USER_AGENT'], $match)
				&& !preg_match('#chrome/#i', $_SERVER['HTTP_USER_AGENT'])
			)
			{
				// contenteditable support in 3.0
				$showWysiwyg = intval($match[1]) >= 3;
			}
			else if (
				preg_match('#msie (\d+)#i', $_SERVER['HTTP_USER_AGENT'], $match)
			)
			{
				// disable the wysiwyg for IE6 and 7
				$showWysiwyg = intval($match[1]) >= 8;
			}
			else if (
				preg_match('#firefox#i', $_SERVER['HTTP_USER_AGENT'], $match)
				&& preg_match('#mobile|android#i', $_SERVER['HTTP_USER_AGENT'], $match)
			)
			{
				// FF for Android doesn't fire the keyup event
				$showWysiwyg = false;
			}
		}

		return $showWysiwyg;
	}

	public static function getQuickReplyEditor(XenForo_View $view, $formCtrlName, $message = '', array $editorOptions = array())
	{
		$editorOptions['height'] = false;

		return self::getEditorTemplate($view, $formCtrlName, $message, $editorOptions);
	}
}