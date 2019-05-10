<?php

class XenForo_Helper_Ip
{
	/**
	 * Gets the binary form of the provided IP or if no IP is provided, from the request.
	 * Binary IPs are IPv4 or IPv6 IPs compressed into 4 or 16 bytes.
	 *
	 * @param Zend_Controller_Request_Http|null $request Request object used; created if needed
	 * @param string|null $ip String value of the IP to use or null to read from request
	 * @param mixed $invalidValue Value to use for the IP if no valid IP is available
	 *
	 * @return mixed Usually a string containing a binary IP, but can be the invalid value
	 */
	public static function getBinaryIp(Zend_Controller_Request_Http $request = null, $ip = null, $invalidValue = false)
	{
		if (!$ip)
		{
			if ($request)
			{
				$ip = $request->getServer('REMOTE_ADDR');
			}
			else
			{
				$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
			}
		}

		$ip = $ip ? self::convertIpStringToBinary($ip) : false;
		return $ip !== false ? $ip : $invalidValue;
	}

	/**
	 * Converts a string based IP (v4 or v6) to a 4 or 16 byte string.
	 * This tries to identify not only 192.168.1.1 and 2001::1:2:3:4 style IPs,
	 * but integer encoded IPv4 and already binary encoded IPs. IPv4
	 * embedded in IPv6 via things like ::ffff:192.168.1.1 is also detected.
	 *
	 * @param string|int $ip
	 *
	 * @return bool|string False on failure, binary data otherwise
	 */
	public static function convertIpStringToBinary($ip)
	{
		$originalIp = $ip;
		$ip = trim($ip);

		if (strpos($ip, ':') !== false)
		{
			// IPv6
			if (preg_match('#:(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})$#', $ip, $match))
			{
				// embedded IPv4
				$long = ip2long($match[1]);
				if (!$long)
				{
					return false;
				}

				$hex = str_pad(dechex($long), 8, '0', STR_PAD_LEFT);
				$v4chunks = str_split($hex, 4);
				$ip = str_replace($match[0], ":$v4chunks[0]:$v4chunks[1]", $ip);
			}

			if (strpos($ip, '::') !== false)
			{
				if (substr_count($ip, '::') > 1)
				{
					// ambiguous
					return false;
				}

				$delims = substr_count($ip, ':');
				if ($delims > 7)
				{
					return false;
				}

				$ip = str_replace('::', str_repeat(':0', 8 - $delims) . ':', $ip);
				if ($ip[0] == ':')
				{
					$ip = '0' . $ip;
				}
			}

			$ip = strtolower($ip);

			$parts = explode(':', $ip);
			if (count($parts) != 8)
			{
				return false;
			}

			foreach ($parts AS &$part)
			{
				$len = strlen($part);
				if ($len > 4 || preg_match('/[^0-9a-f]/', $part))
				{
					return false;
				}

				if ($len < 4)
				{
					$part = str_repeat('0', 4 - $len) . $part;
				}
			}

			$hex = implode('', $parts);
			if (strlen($hex) != 32)
			{
				return false;
			}

			return XenForo_Helper_Ip::convertHexToBin($hex);
		}
		else if (strpos($ip, '.'))
		{
			// IPv4
			if (!preg_match('#(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})#', $ip, $match))
			{
				return false;
			}

			$long = ip2long($match[1]);
			if (!$long)
			{
				return false;
			}

			return XenForo_Helper_Ip::convertHexToBin(
				str_pad(dechex($long), 8, '0', STR_PAD_LEFT)
			);
		}
		else if (strlen($ip) == 4 || strlen($ip) == 16)
		{
			// already binary encoded
			return $ip;
		}
		else if (is_numeric($originalIp) && $originalIp < pow(2, 32))
		{
			// IPv4 as integer
			return XenForo_Helper_Ip::convertHexToBin(
				str_pad(dechex($originalIp), 8, '0', STR_PAD_LEFT)
			);
		}
		else
		{
			return false;
		}
	}

	/**
	 * Converts a hex string to binary
	 *
	 * @param string $hex
	 *
	 * @return string
	 */
	public static function convertHexToBin($hex)
	{
		if (function_exists('hex2bin'))
		{
			return hex2bin($hex);
		}

		$len = strlen($hex);

		if ($len % 2)
		{
			trigger_error('Hexadecimal input string must have an even length', E_USER_WARNING);
		}

		if (strspn($hex, '0123456789abcdefABCDEF') != $len)
		{
			trigger_error('Input string must be hexadecimal string', E_USER_WARNING);
		}

		return pack('H*', $hex);
	}

	/**
	 * Converts a binary string containing IPv4 or v6 data to a printable/human
	 * readable version. If shortening is enabled, IPv6 data will be collapsed
	 * as much as possible.
	 *
	 * @param string $ip Binary IP data
	 * @param bool $shorten
	 *
	 * @return bool|string
	 */
	public static function convertIpBinaryToString($ip, $shorten = true)
	{
		if (strlen($ip) == 4)
		{
			// IPv4
			$parts = array();
			foreach (str_split($ip) AS $char)
			{
				$parts[] = ord($char);
			}

			return implode('.', $parts);
		}
		else if (strlen($ip) == 16)
		{
			// IPv6
			$parts = array();
			$chunks = str_split($ip);
			for ($i = 0; $i < 16; $i += 2)
			{
				$char1 = $chunks[$i];
				$char2 = $chunks[$i + 1];

				$part = sprintf('%02x%02x', ord($char1), ord($char2));
				if ($shorten)
				{
					// reduce this to the shortest length possible, but keep 1 zero if needed
					$part = ltrim($part, '0');
					if (!strlen($part))
					{
						$part = '0';
					}
				}
				$parts[] = $part;
			}

			$output = implode(':', $parts);
			if ($shorten)
			{
				$output = preg_replace('/((^0|:0){2,})(.*)$/', ':$3', $output);
				if (substr($output, -1) === ':' && (strlen($output) == 1 || substr($output, -2, 1) !== ':'))
				{
					$output .= ':';
				}
			}

			return strtolower($output);
		}
		else if (preg_match('/^[0-9]+$/', $ip))
		{
			return long2ip($ip + 0);
		}
		else
		{
			return false;
		}
	}

	/**
	 * Gets the range of binary-encoded IPs that match the given
	 * CIDR block size. Supports IPv4 and v6. Ranges can be checked
	 * via >= lower and <= upper.
	 *
	 * @param string $ip Binary IP
	 * @param integer $cidr CIDR range
	 *
	 * @return array|string If no CIDR is specified or if the CIDR specifies only one address, a string with that address.
	 * 		Otherwise, an array with a lower and upper bound.
	 */
	public static function getIpCidrMatchRange($ip, $cidr)
	{
		if (!$cidr)
		{
			return $ip;
		}

		$bytes = strlen($ip);
		$bits = $bytes * 8;
		if ($cidr >= $bits)
		{
			return $ip; // exact match
		}

		$prefixBytes = (int)floor($cidr / 8);
		$remainingBits = ($cidr - $prefixBytes * 8);

		$prefix = substr($ip, 0, $prefixBytes);
		if ($remainingBits)
		{
			$partialByteOrd = ord($ip[$prefixBytes]); // first character after full prefix bytes
			$mask = (1 << 8 - $remainingBits) - 1;

			$upperBound = chr($partialByteOrd | $mask);
			$lowerBound = chr($partialByteOrd & ~$mask);
			$boundLength = 1;
		}
		else
		{
			$upperBound = '';
			$lowerBound = '';
			$boundLength = 0;
		}

		$suffixBytes = $bytes - $prefixBytes - $boundLength;
		if ($suffixBytes)
		{
			$lowerSuffix = str_repeat(chr(0), $suffixBytes);
			$upperSuffix = str_repeat(chr(255), $suffixBytes);
		}
		else
		{
			$lowerSuffix = '';
			$upperSuffix = '';
		}

		return array($prefix . $lowerBound . $lowerSuffix, $prefix . $upperBound . $upperSuffix);
	}

	/**
	 * Determines if a particular IP matches a IP CIDR range.
	 * Both IPs must be binary encoded
	 *
	 * @param string $testIp Binary encoded IP to test if within range
	 * @param string $rangeIp Binary encoded IP to make the range from
	 * @param integer $cidr CIDR block size
	 *
	 * @return bool
	 */
	public static function ipMatchesCidrRange($testIp, $rangeIp, $cidr)
	{
		$range = self::getIpCidrMatchRange($rangeIp, $cidr);
		if (is_string($range))
		{
			return ($testIp == $range);
		}
		else
		{
			return self::ipMatchesRange($testIp, $range[0], $range[1]);
		}
	}

	/**
	 * Simplifies checking if an IP is within a range.
	 * All IPs and ranges must be binary encoded.
	 *
	 * @param string $testIp
	 * @param string $lowerBound
	 * @param string $upperBound
	 *
	 * @return bool
	 */
	public static function ipMatchesRange($testIp, $lowerBound, $upperBound)
	{
		return ($testIp >= $lowerBound AND $testIp <= $upperBound AND strlen($testIp) == strlen($lowerBound));
	}

	/**
	 * Parses a human readable IP range string into a machine processable version.
	 * IPv4 can be specified with CIDR or 192.168.* style ranges. IPv6 supports CIDR only.
	 *
	 * @param string $ip Human readable IPv4 or v6 IP
	 *
	 * @return array|bool False on failure, Otherwise array with following keys:
	 * 		- printable: human readable version of the IP range. May be adjusted to standardize display slightly
	 * 		- binary: binary version of provided IP. IPv4 missing octets are considered to be 0.
	 * 		- cidr: the final CIDR range found/used. If a IPv4 partial is provided, this will be determined from number of missing octets. 0 for exact.
	 * 		- isRange: true if the provided IP actually spans a range
	 *		- startRange: binary version of lower bound IP
	 *		- endRange: binary version of upper bound IP
	 */
	public static function parseIpRangeString($ip)
	{
		$ip = trim($ip);
		$niceIp = $ip;

		if (preg_match('#/(\d+)$#', $ip, $match))
		{
			$ip = substr($ip, 0, -strlen($match[0]));
			$cidr = $match[1];
			if ($cidr && $cidr < 8)
			{
				$cidr = 8;
				$niceIp = $ip . "/$cidr";
			}
		}
		else
		{
			$cidr = 0;
		}

		if (strpos($ip, ':') !== false)
		{
			// IPv6 -- no partials, only CIDR
			$binary = self::convertIpStringToBinary($ip);
			if ($binary === false)
			{
				return false;
			}
		}
		else
		{
			$ip = preg_replace('/\.+$/', '', $ip);
			if (!preg_match('/^\d+(\.\d+){0,2}(\.\d+|\.\*)?$/', $ip))
			{
				return false;
			}

			if (substr($ip, -2) == '.*')
			{
				$ip = substr($ip, 0, -2);
			}

			$ipParts = explode('.', $ip);
			foreach ($ipParts AS $part)
			{
				if ($part < 0 || $part > 255)
				{
					return false;
				}
			}

			$localCidr = 32;
			while (count($ipParts) < 4)
			{
				$ipParts[] = 0;
				$localCidr -= 8;
			}

			if (!$cidr && $localCidr != 32)
			{
				$cidr = $localCidr;
				$niceIp = $ip . '.*';
			}

			$binary = self::convertIpStringToBinary(implode('.', $ipParts));
			if (!$binary)
			{
				return false;
			}
		}

		$range = self::getIpCidrMatchRange($binary, $cidr);

		return array(
			'printable' => $niceIp,
			'binary' => $binary,
			'cidr' => $cidr,
			'isRange' => is_array($range),
			'startRange' => is_string($range) ? $range : $range[0],
			'endRange' => is_string($range) ? $range : $range[1]
		);
	}
}