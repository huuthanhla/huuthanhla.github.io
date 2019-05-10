<?php

class XenForo_DnsBl
{
	protected $_host;

	public function __construct($host)
	{
		$this->_host = $host;
	}

	public function checkIp($ip)
	{
		$parts = explode('.', trim($ip));
		if (count($parts) != 4)
		{
			return false;
		}

		$parts = array_map('intval', $parts);
		$parts = array_reverse($parts);

		$query = sprintf($this->_host, implode('.', $parts));

		$result = gethostbyname($query);
		if (!$result)
		{
			return false;
		}

		if ($result === $query)
		{
			// not found
			return false;
		}

		$resultParts = explode('.', $result);
		if (count($resultParts) < 4)
		{
			return false;
		}

		return $resultParts;
	}

	public static function getFinalOctetIf($resultParts, $part0 = '127', $part1 = '0', $part2 = '0')
	{
		if (!is_array($resultParts) || count($resultParts) != 4)
		{
			return false;
		}

		if ($resultParts[0] == $part0 && $resultParts[1] == $part1 && $resultParts[2] == $part2)
		{
			return intval($resultParts[3]);
		}
		else
		{
			return false;
		}
	}

	public static function checkSpamhaus($ip)
	{
		$dnsBl = new self('%s.zen.spamhaus.org');
		$result = self::getFinalOctetIf($dnsBl->checkIp($ip));

		// on the SBL or XBL
		return ($result && $result >= 2 && $result <= 7);
	}

	public static function checkTornevall($ip)
	{
		$dnsBl = new self('%s.dnsbl.tornevall.org');
		$result = self::getFinalOctetIf($dnsBl->checkIp($ip));

		// bitmask 64 is abusive
		return ($result && $result & 64);
	}

	public static function checkProjectHoneyPot($ip, $key, $minThreatLevel = 10, $dateCutOff = 31)
	{
		$dnsBl = new self($key . '.%s.dnsbl.httpbl.org');
		$result = $dnsBl->checkIp($ip);
		if (!$result)
		{
			return false;
		}

		return ($result[0] == '127'
			&& intval($result[1]) <= $dateCutOff
			&& intval($result[2]) >= $minThreatLevel
			&& intval($result[3])
		);
	}
}