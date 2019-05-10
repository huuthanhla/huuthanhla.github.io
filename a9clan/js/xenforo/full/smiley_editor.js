/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	/**
	 * Activates the XenForo smiley editor form
	 *
	 * @param jQuery form.SmileyEditor[data-smiley-output]
	 */
	XenForo.SmileyEditor = function($form)
	{
		var $output = $($form.data('smiley-output')),

			$url     = $form.find('input[name="image_url"]'),
			$sprite  = $form.find('input[name="sprite_mode"]'),
			$w       = $form.find('input[name="sprite_params[w]"]'),
			$h       = $form.find('input[name="sprite_params[h]"]'),
			$x       = $form.find('input[name="sprite_params[x]"]'),
			$y       = $form.find('input[name="sprite_params[y]"]');

		if (!$output.length)
		{
			console.warn('Unable to locate the smiley output element as specified by data-smiley-output on the form %o', $form);
			return;
		}

		$form.find('input').not('input[type=button]').not('input[type=submit]').bind('change', function(e)
		{
			var $url = $form.find('#ctrl_image_url')

			if ($sprite.is(':checked'))
			{
				$output.attr('src', 'styles/default/xenforo/clear.png').css(
				{
					width: $w.val(),
					height: $h.val(),
					background: 'url(' + $url.val() + ') no-repeat ' + $x.val() + 'px ' + $y.val() + 'px'
				});
			}
			else
			{
				$output.attr('src', $url.val()).css(
				{
					width: 'auto',
					height: 'auto',
					background: 'none'
				});
			}
		});
	};

	// *********************************************************************
	
	/**
	 * Handles hidden options and serialization of smilie data form input fields
	 *
	 * @param jQuery form#SmilieImportForm
	 */
	XenForo.SmilieImportForm = function($form)
	{
		$form.find('input.Hider').change(function(e)
		{
			var $target = $($(this).data('target'));
			
			if (this.checked)
			{
				$target.xfFadeDown(XenForo.speed.fast);
			}
			else
			{
				$target.xfHide();
			}
		});
		
		$form.bind(
		{
			AutoValidationBeforeSubmit: function(e)
			{				
				var inputQueryString = $form.serialize(),
					$inputs = $form.find('input:not(input[type=hidden]), select, textarea'),
					$dataInput = $('<input type="hidden" name="_xfSmilieImportData" />');

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
			},
			
			AutoValidationError: function(e)
			{
				var $target = $(e.target);
				
				if ($target.is(':hidden'))
				{
					$target.closest('.advanced').show();
				}
			}
		});
	}

	// *********************************************************************

	XenForo.register('form.SmileyEditor', 'XenForo.SmileyEditor');
	
	XenForo.register('#SmilieImportForm', 'XenForo.SmilieImportForm');
}
(jQuery, this, document);
