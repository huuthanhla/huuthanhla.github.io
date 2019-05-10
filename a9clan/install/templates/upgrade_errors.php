<?php
	class_exists('XenForo_Application', false) || die('Invalid');

	$__extraData['title'] = 'Upgrade Errors';
?>

<p class="text">Uh oh, your upgrade to <?php echo htmlspecialchars($version); ?> has failed!</p>

<p class="text">The following elements of the database are incorrect:</p>
<div class="baseHtml">
	<ul>
	<?php foreach($errors AS $error) { ?>
		<li><?php echo htmlspecialchars($error); ?></li>
	<?php } ?>
	</ul>
</div>
<p class="text">This is likely caused by an add-on conflict. You may need to restore a backup, remove the offending add-on data from the database, and retry the upgrade. Contact support if you are not sure how to proceed.</p>