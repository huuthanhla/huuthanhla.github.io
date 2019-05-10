<?php

class XenForo_ViewPublic_Forum_GlobalRss extends XenForo_ViewPublic_Base
{
	public function renderRss()
	{
		$options = XenForo_Application::get('options');
		$title = ($options->boardTitle ? $options->boardTitle : XenForo_Link::buildPublicLink('canonical:index'));
		$description = ($options->boardDescription ? $options->boardDescription : $title);

		$buggyXmlNamespace = (defined('LIBXML_DOTTED_VERSION') && LIBXML_DOTTED_VERSION == '2.6.24');

		$feed = new Zend_Feed_Writer_Feed();
		$feed->setEncoding('utf-8');
		$feed->setTitle($title);
		$feed->setDescription($description);
		$feed->setLink(XenForo_Link::buildPublicLink('canonical:index'));
		if (!$buggyXmlNamespace)
		{
			$feed->setFeedLink(XenForo_Link::buildPublicLink('canonical:forums/-/index.rss'), 'rss');
		}
		$feed->setDateModified(XenForo_Application::$time);
		$feed->setLastBuildDate(XenForo_Application::$time);
		$feed->setGenerator($title);

		$bbCodeSnippetParser = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('XenForo_BbCode_Formatter_BbCode_AutoLink', false));
		$bbCodeParser = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));

		foreach ($this->_params['threads'] AS $thread)
		{
			$entry = $feed->createEntry();
			$entry->setTitle($thread['title'] ? $thread['title'] : $thread['title'] . ' ');
			$entry->setLink(XenForo_Link::buildPublicLink('canonical:threads', $thread));
			$entry->setDateCreated(new Zend_Date($thread['post_date'], Zend_Date::TIMESTAMP));
			$entry->setDateModified(new Zend_Date($thread['last_post_date'], Zend_Date::TIMESTAMP));
			if (!empty($thread['canViewContent']) && !empty($thread['message']) && XenForo_Application::getOptions()->discussionRssContentLength)
			{
				$snippet = $bbCodeSnippetParser->render(XenForo_Helper_String::wholeWordTrim($thread['message'], XenForo_Application::getOptions()->discussionRssContentLength));
				if ($snippet != $thread['message'])
				{
					$snippet .= "\n\n[url='" . XenForo_Link::buildPublicLink('canonical:threads', $thread) . "']" . $thread['title'] . '[/url]';
				}
				$content = trim($bbCodeParser->render($snippet));
				if (strlen($content))
				{
					$entry->setContent($content);
				}
			}
			if (!$buggyXmlNamespace)
			{
				$entry->addAuthor(array(
					'name' => $thread['username'],
					'email' => 'invalid@example.com',
					'uri' => XenForo_Link::buildPublicLink('canonical:members', $thread)
				));
				if ($thread['reply_count'])
				{
					$entry->setCommentCount($thread['reply_count']);
				}
			}

			$feed->addEntry($entry);
		}

		return $feed->export('rss');
	}
}