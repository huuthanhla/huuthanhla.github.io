/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	XenForo.AdminSearchForm = function($form)
	{
		var $input = $('#AdminSearchInput'),
			$target = $($form.data('target')),
			timeOut = null,
			xhr = null,
			storedValue = '';

		$input.attr('autocomplete', 'off').bind(
		{
			keyup: function(e)
			{
				var currentValue = $input.strval();

				if (currentValue != storedValue && currentValue.length >= 2)
				{
					storedValue = currentValue;

					clearTimeout(timeOut);
					timeOut = setTimeout(function()
					{
						console.log('The input now reads "%s"', $input.strval());

						if (xhr)
						{
							xhr.abort();
						}

						xhr = XenForo.ajax
						(
							$form.attr('action'),
							$form.serializeArray(),
							function(ajaxData, textStatus)
							{
								if (XenForo.hasResponseError(ajaxData))
								{
									return false;
								}

								if (XenForo.hasTemplateHtml(ajaxData))
								{
									$target.empty().append(ajaxData.templateHtml);

									$target.find('li').mouseleave(function(e)
									{
										$(this).removeClass('kbSelect');
									});

									$target.find('li:first').addClass('kbSelect');
								}
							}
						);

					}, 250);
				}
				else if (currentValue == '')
				{
					$target.empty();
				}
			},

			paste: function(e)
			{
				setTimeout(function() {
					$input.trigger('keyup');
				}, 0);
			},

			keydown: function(e)
			{
				switch (e.which)
				{
					case 38: // up
					case 40: // down
					{
						var $links = $target.find('li'),
							$selected = $links.filter('.kbSelect'),
							index = 0;

						if ($selected.length)
						{
							index = $links.index($selected.get(0));

							index += (e.which == 40 ? 1 : -1);

							if (index < 0 || index >= $links.length)
							{
								index = 0;
							}
						}

						$links.removeClass('kbSelect').eq(index).addClass('kbSelect');
						return false;
					}
				}
			}
		});

		$input.closest('form').submit(function(e)
		{
			e.preventDefault();

			var $link = $target.find('li.kbSelect a');

			if ($link.length)
			{
				window.location = $link.attr('href');
			}

			return false;
		});
	};

	// *********************************************************************

	XenForo.register('#AdminSearchForm', 'XenForo.AdminSearchForm');

}
(jQuery, this, document);