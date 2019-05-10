/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	XenForo.PrefixManager = function($select)
	{
		function update()
		{
			$checkboxes.each(function()
			{
				var $option = $select.find('option[value="' + this.value + '"]').prop('disabled', !this.checked);

				if (!this.checked && $option.is(':selected'))
				{
					$select.find('option').first().prop('selected', true);
				}
			});
		}

		var $checkboxes = $($select.data('checkboxes')).click(update);

		update();
	};

	// *********************************************************************

	XenForo.register('select.PrefixManager', 'XenForo.PrefixManager');

}
(jQuery, this, document);