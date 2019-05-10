<?php

$cache_expire = 86400 * 365;
header("Pragma: public");
header("Cache-Control: public, max-age=" . $cache_expire);
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cache_expire) . ' GMT');

$locale = '';
if (!empty($_GET['l']))
{
	$locale = str_replace('-', '_', strval($_GET['l']));
	$locale = preg_replace('/[^a-z_]/i', '', $locale);
}

if (!$locale)
{
	$locale = 'en_US';
}

?>
<script src="//connect.facebook.net/<?php echo htmlspecialchars($locale); ?>/sdk.js"></script>
