<?php
	class_exists('XenForo_Application', false) || die('Invalid');

	$__extraData['title'] = 'Upgrading...';
?>

Upgrading... <?php echo $versionName; ?>,  Step <?php echo htmlspecialchars($step); ?><?php if ($stepMessage) { echo " ($stepMessage)" ; } ?>
&nbsp;<img src="../styles/default/xenforo/widgets/ajaxload.info_000000_facebook.gif" alt="Loading..." id="loadingImage" style="display:none" />

<form action="index.php?upgrade/run" method="post" id="continueForm">
	<input type="submit" value="Continue" class="button" />

	<input type="hidden" name="run_version" value="<?php echo htmlspecialchars($newRunVersion); ?>" />
	<input type="hidden" name="step" value="<?php echo htmlspecialchars($newStep); ?>" />
	<input type="hidden" name="position" value="<?php echo htmlspecialchars($position); ?>" />
	<?php if ($stepData) { ?><input type="hidden" name="step_data" value="<?php echo htmlspecialchars(json_encode($stepData)); ?>" /><?php } ?>
	<input type="hidden" name="_xfToken" value="<?php echo htmlspecialchars($visitor['csrf_token_page']); ?>" />
</form>

<script>
$(function() {
	$('#continueForm').submit();
	$('#continueForm .button').hide();
	$('#loadingImage').show();
});
</script>