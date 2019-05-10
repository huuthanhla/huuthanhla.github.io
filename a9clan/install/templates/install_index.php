<?php
	class_exists('XenForo_Application', false) || die('Invalid');

	if ($errors)
	{
		$__extraData['title'] = 'XenForo ' . XenForo_Application::$version . ' - Errors';
	}
	else
	{
		$__extraData['title'] = 'XenForo ' . XenForo_Application::$version . ' - Welcome';
	}
?>

<?php if ($errors) { ?>
	<p class="text">The following errors occurred while verifying that your server can run XenForo:</p>
	<div class="baseHtml">
		<ul>
		<?php foreach ($errors AS $error) { ?>
			<li><?php echo $error; ?></li>
		<?php } ?>
		</ul>
	</div>
	<p class="text">Please correct these errors and try again.</p>
<?php } else { ?>
	<?php if ($warnings) { ?>
		<div class="warningMessage">
			The following warnings were detected when verifying that your server can run XenForo:
			<div class="baseHtml">
				<ul>
				<?php foreach ($warnings AS $warning) { ?>
					<li><?php echo $warning; ?></li>
				<?php } ?>
				</ul>
			</div>
			These may affect the proper execution of XenForo at times and should be resolved if possible.
			However, you may still continue with the installation.
		</div>
	<?php } else { ?>
		<p class="text">Your server meets all of XenForo requirements and you're now ready to begin installation.</p>
	<?php } ?>
	<p class="text"><a href="index.php?install/step/1" class="button primary">Begin Installation</a></p>
<?php } ?>