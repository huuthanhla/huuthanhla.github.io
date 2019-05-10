<?php

class XenForo_Importer_IPBoard34x extends XenForo_Importer_IPBoard32x
{
	public static function getName()
	{
		return 'IP.Board 3.4';
	}

	protected function _parseIPBoardBbCode($message, $autoLink = true)
	{
		$output = parent::_parseIPBoardBbCode($message, $autoLink);

		return strip_tags($output);
	}

	protected function _parseIPBoardText($message)
	{
		// Handle HTML line breaks
		$message = preg_replace('/<br( \/)?>(\s*)/si', "\n", $message);
		$message = str_replace('&nbsp;' , ' ', $message);

		return $this->_convertToUtf8($message, true);
	}

	protected function _getIPBoardBBCodeReplacements()
	{
		return array_merge(
			parent::_getIPBoardBBCodeReplacements(),
			array(
				'#<span [^>]*style="color:\s*([^";\\]]+?)[^"]*"[^>]*>(.*)</span>#siU' => '[COLOR=\\1]\\2[/COLOR]',
				'#<span [^>]*style="font-family:\s*([^";\\],]+?)[^"]*"[^>]*>(.*)</span>#siU' => '[FONT=\\1]\\2[/FONT]',
				'#<span [^>]*style="font-size:\s*([^";\\]]+?)[^"]*"[^>]*>(.*)</span>#siU' => '[SIZE=\\1]\\2[/SIZE]',
				'#<span[^>]*>(.*)</span>#siU' => '\\1',
				'#<(strong|b)>(.*)</\\1>#siU' => '[B]\\2[/B]',
				'#<(em|i)>(.*)</\\1>#siU' => '[I]\\2[/I]',
				'#<(u)>(.*)</\\1>#siU' => '[U]\\2[/U]',
				'#<(strike)>(.*)</\\1>#siU' => '[S]\\2[/S]',
				'#<a [^>]*href=(\'|")([^"\']+)\\1[^>]*>(.*)</a>#siU' => '[URL="\\2"]\\3[/URL]',
				'#<img [^>]*src="([^"]+)"[^>]*>#' => '[IMG]\\1[/IMG]',
				'#<img [^>]*src=\'([^\']+)\'[^>]*>#' => '[IMG]\\1[/IMG]',

				'#<(p|div) [^>]*style="text-align:\s*left;?">(.*)</\\1>(\r?\n)??#siU' => "[LEFT]\\2[/LEFT]\n",
				'#<(p|div) [^>]*style="text-align:\s*center;?">(.*)</\\1>(\r?\n)??#siU' => "[CENTER]\\2[/CENTER]\n",
				'#<(p|div) [^>]*style="text-align:\s*right;?">(.*)</\\1>(\r?\n)??#siU' => "[RIGHT]\\2[/RIGHT]\n",
				'#<(p|div) [^>]*class="bbc_left"[^>]*>(.*)</\\1>(\r?\n)??#siU' => "[LEFT]\\2[/LEFT]\n",
				'#<(p|div) [^>]*class="bbc_center"[^>]*>(.*)</\\1>(\r?\n)??#siU' => "[CENTER]\\2[/CENTER]\n",
				'#<(p|div) [^>]*class="bbc_right"[^>]*>(.*)</\\1>(\r?\n)??#siU' => "[RIGHT]\\2[/RIGHT]\n",

				'#<ul[^>]*>(.*)</ul>(\r?\n)??#siU' => "[LIST]\\1[/LIST]\n",
				'#<ol[^>]*>(.*)</ol>(\r?\n)??#siU' => "[LIST=1]\\1[/LIST]\n",
				'#<li[^>]*>(.*)</li>(\r?\n)??#siU' => "[*]\\1\n",

				'#<blockquote [^>]*class="ipsBlockquote"\s+data-author="([^"]+)"[^>]*>(.*)</blockquote>(\r?\n)??#siU' => '[QUOTE=\\1]\\2[/QUOTE]',
				'#<blockquote [^>]*class="ipsBlockquote"[^>]*>(.*)</blockquote>(\r?\n)??#siU' => '[QUOTE]\\1[/QUOTE]',

				'#<(p|pre)[^>]*>(&nbsp;|' . chr(0xC2) . chr(0xA0) .'|\s)*</\\1>(\r?\n)??#siU' => "\n",
				'#<p[^>]*>(.*)</p>(\r?\n)??#siU' => "\\1\n",
				'#<div[^>]*>(.*)</div>(\r?\n)??#siU' => "\\1\n",

				'#<pre[^>]*>(.*)</pre>(\r?\n)??#siU' => "[CODE]\\1[/CODE]\n",

				'#<!--.*-->#siU' => ''
			)
		);
	}
}