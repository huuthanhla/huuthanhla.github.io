<?php

class XenForo_Template_Helper_RightToLeft
{
	public static $regex = array
	(
		'ruleDirection'
			=> '/(?<=[^a-z0-9-])(?<!rtl-raw\.)([a-z-]+)?(left|right)(-(style|width|color|radius))?(\s*:)/siU',
		'valueDirection' // don't do css direction - it's added for constant meaning
			=> '/(?<=[^a-z0-9-])(?<!rtl-raw\.)((text-align|float|clear)\s*:\s*)(left|right)(\s*\!important\s*)?(\s*;|\s*\}|$)/siU',
		'compoundDirection'
			=> '/(?<=[^a-z0-9-])(?<!rtl-raw\.)(margin|padding|border-width|border-style|border-color)(\s*:\s*)([^;}]+)(\s*;|\s*\}|$)/siU',
	);

	public static function getRtlCss($css)
	{
		// switch padding-left, border-left-width etc.
		$css = preg_replace_callback(self::$regex['ruleDirection'], array(__CLASS__, 'switchRuleDirection'), $css);

		// switch float: left, text-direction: right etc.
		$css = preg_replace_callback(self::$regex['valueDirection'], array(__CLASS__, 'switchValueDirection'), $css);

		// handle margin, padding and border compound rules
		$css = preg_replace_callback(self::$regex['compoundDirection'], array(__CLASS__, 'handleCompoundDirections'), $css);

		// negate horizontal box shadow values
		$css = preg_replace_callback(
			'#(?<=[^a-z0-9])(?<!rtl-raw\.)(box-shadow\s*:\s*)(\S+?)#siU', array(__CLASS__, 'handleBoxShadow'), $css
		);

		// flip left/right background positions
		$css = preg_replace_callback(
			'#(?<=[^a-z0-9])(?<!rtl-raw\.)(background-position\s*:\s*)(left|right)([\s;}])#siU',
			array(__CLASS__, 'handleBackgroundPosition'),
			$css
		);
		$css = preg_replace_callback(
			'#(?<=[^a-z0-9])(?<!rtl-raw\.)(background\s*:\s*[^;]+\s+)(left|right)([\s;}])#siU',
			array(__CLASS__, 'handleBackgroundPosition'),
			$css
		);

		return $css;
	}

	public static function getStandardDirection($direction)
	{
		if (strtolower($direction) == 'left')
		{
			return 'right';
		}
		else
		{
			return 'left';
		}
	}

	public static function switchRuleDirection(array $regexMatch)
	{
		return $regexMatch[1] . self::getStandardDirection($regexMatch[2]) . $regexMatch[3] . $regexMatch[5];
	}

	public static function switchValueDirection(array $regexMatch)
	{
		return $regexMatch[1] . self::getStandardDirection($regexMatch[3]) . $regexMatch[4] . $regexMatch[5];
	}

	public static function handleCompoundDirections(array $regexMatch)
	{
		$value = trim($regexMatch[3]);

		preg_match_all('#([a-z0-9-]+\([^)]+\)|\S+?)#siU', $value, $matches);
		$bits = isset($matches) ? $matches[1] : array();

		if (count($bits) == 4)
		{
			$value = "$bits[0] $bits[3] $bits[2] $bits[1]";
		}

		return $regexMatch[1] . $regexMatch[2] . $value . $regexMatch[4];
	}

	public static function handleBoxShadow(array $regexMatch)
	{
		if (preg_match('/^(-)?(\.?\d+.*)$/s', $regexMatch[2], $numberMatch))
		{
			if ($numberMatch[1])
			{
				// negative -> positive
				$value = $numberMatch[2];
			}
			else
			{
				// positive -> negative
				$value = '-' . $numberMatch[2];
			}

			return $regexMatch[1] . $value;
		}

		// catches 'none' and other unexpected values
		return $regexMatch[1] . $regexMatch[2];
	}

	public static function handleBackgroundPosition(array $regexMatch)
	{
		$value = strtolower($regexMatch[2]);
		switch ($value)
		{
			case 'left': $value = 'right'; break;
			case 'right': $value = 'left'; break;
		}

		return $regexMatch[1] . $value . $regexMatch[3];
	}

	/**
	 * Replaces direction-specific HTML entities with the opposite-facing version if necessary
	 *
	 * @param string HTML
	 *
	 * @return string
	 */
	public static function replaceRtlEntities($html)
	{
		return strtr($html, array
		(
			'&larr;' => '&rarr;',
			'&rarr;' => '&larr;'
		));
	}
}