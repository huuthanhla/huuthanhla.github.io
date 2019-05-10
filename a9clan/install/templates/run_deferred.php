<?php
	class_exists('XenForo_Application', false) || die('Invalid');

	$__extraData['title'] = 'Processing...';
?>

<form action="<?php echo htmlspecialchars($submitUrl); ?>" method="post" class="AutoSubmit">

	<div><?php echo (!empty($status) ? htmlspecialchars($status) : 'Processing...'); ?></div>

	<input type="submit" class="button" value="Continue" />

	<input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>" />
	<input type="hidden" name="execute" value="1" />

	<?php if (!empty($visitor)) { ?>
		<input type="hidden" name="_xfToken" value="<?php echo htmlspecialchars($visitor['csrf_token_page']); ?>" />
	<?php } ?>

</form>