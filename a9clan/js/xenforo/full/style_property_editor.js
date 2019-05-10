/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	/**
	 * Handles serialization of style property form input fields,
	 * and selection of the correct tab on load.
	 *
	 * @param jQuery form#PropertyForm
	 */
	XenForo.StylePropertyForm = function($form)
	{
		$form.bind('submit', function(e)
		{
			var tabs = $('#propertyTabs').data('XenForo.Tabs'),
				tabIndex = tabs.getCurrentTab(),
				$tab = tabs.api.getCurrentTab().closest('li.PropertyTab'),
				inputQueryString;

			$form.find('input[name=tab_index]').val(tabIndex);
			$form.find('input[name=tab_id]').val($tab.attr('id'));

			inputQueryString = $form.serialize();

			var $inputs = $form.find('input:not(input[type=hidden]), select, textarea'),
				$dataInput = $('<input type="hidden" name="_xfStylePropertiesData" />');

			$inputs.each(function()
			{
				var $this = $(this);
				$this.data('attr-name', $this.attr('name'));
				$this.removeAttr('name');
			});

			$dataInput.val(inputQueryString).appendTo($form);

			setTimeout(function()
			{
				$inputs.each(function()
				{
					var $this = $(this);
					$this.attr('name', $this.data('attr-name'));
				});

				$dataInput.remove();
			}, 100);
		});

		if (location.hash)
		{
			// indexed tab
			if (location.hash.indexOf('#tab-') == 0)
			{
				$('#propertyTabs').data('XenForo.Tabs').click(parseInt(location.hash.substr(5), 10));
			}
			else
			{
				// named tab - #_propertyName
				$('#propertyTabs').data('XenForo.Tabs').click(
					$('#propertyTabs > li').index(
						document.getElementById(location.hash.substr(1))
					)
				);
			}
		}
	};

	// *********************************************************************

	/**
	 * Activates style property editor for the specified unit
	 *
	 * @param $jQuery .StylePropertyEditor
	 */
	XenForo.StylePropertyEditor = function($unit)
	{
		$unit.find('.TextDecoration input:checkbox').click(function(e)
		{
			var $target = $(e.target);

			console.log('Text-decoration checkbox - Value=%s, Checked=%s', $target.attr('value'), $target.is(':checked'));

			if (!$target.is(':checkbox'))
			{
				$target.prop('checked', !$target.is(':checked'));
			}

			if ($target.is(':checked'))
			{
				if ($target.attr('value') == 'none')
				{
					// uncheck all the other checkboxes
					$(this).not('[value="none"]').prop('checked', false);
				}
				else
				{
					// uncheck the 'none' checkbox
					$(this).filter('[value="none"]').prop('checked', false);
				}
			}
		});
	};

	// *********************************************************************

	XenForo.StylePropertyTooltip = function($item)
	{
		var $descriptionTip = $item.find('div.DescriptionTip')
			.addClass('xenTooltip propertyDescriptionTip')
			.appendTo('body')
			.append('<span class="arrow" />');

		if ($descriptionTip.length)
		{
			$item.tooltip(XenForo.configureTooltipRtl({
				/*effect: 'fade',
				fadeInSpeed: XenForo.speed.normal,
				fadeOutSpeed: 0,*/

				position: 'bottom left',
				offset: [ -24, -6 ],
				tip: $descriptionTip,
				delay: 0
			}));
		}
	};

	// *********************************************************************

	XenForo.register('#PropertyForm', 'XenForo.StylePropertyForm');

	XenForo.register('.StylePropertyEditor', 'XenForo.StylePropertyEditor');

	XenForo.register('#propertyTabs > li', 'XenForo.StylePropertyTooltip');
}
(jQuery, this, document);