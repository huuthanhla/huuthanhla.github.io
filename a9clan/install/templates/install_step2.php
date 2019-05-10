<?php
	class_exists('XenForo_Application', false) || die('Invalid');

	$__extraData['title'] = 'Install';
?>

<div class="baseHtml">
	<ol>
	<?php if ($removed) { ?>
		<li>Removed old tables...</li>
	<?php } ?>
	<li><?php echo ($endOffset) ? "Created tables ($endOffset)..." : 'Created tables...'; ?></li>
	<?php if ($endOffset === false) { ?>
		<li>Inserted default data...</li>
	<?php } ?>
	</ol>
</div>

<?php if ($endOffset === false) { ?>
	<form action="index.php?install/step/2b" method="post" class="xenForm" id="continueForm">
		<input type="submit" value="Continue..." accesskey="s" class="button primary" />
	</form>
<?php } else { ?>
	<form action="index.php?install/step/2" method="post" class="xenForm" id="continueForm">
		<input type="hidden" name="start" value="<?php echo htmlspecialchars($endOffset); ?>" />
		<input type="submit" value="Continue..." accesskey="s" class="button primary" />
	</form>
<?php } ?>
<script>
	$('#continueForm').submit();
	$('#continueForm .button').hide();
</script>