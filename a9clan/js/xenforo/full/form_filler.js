/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	XenForo._FormFiller = {};

	XenForo.FormFillerControl = function($control)
	{
		var $form = $control.closest('form'),

			FormFiller = $form.data('FormFiller');

		if (!FormFiller)
		{
			FormFiller = new XenForo.FormFiller($form);
			$form.data('FormFiller', FormFiller);
		}

		FormFiller.addControl($control);
	};

	XenForo.FormFiller = function($form)
	{
		var valueCache = {},
			clicked = null,
			xhr = null;

		function handleValuesResponse(clicked, ajaxData)
		{
			if (XenForo.hasResponseError(ajaxData))
			{
				return false;
			}

			$.each(ajaxData.formValues, function(selector, value)
			{
				var $ctrl = $form.find(selector);

				if ($ctrl.length)
				{
					if ($ctrl.is(':checkbox, :radio'))
					{
						$ctrl.prop('checked', value).triggerHandler('click');
					}
					else if ($ctrl.is('select, input, textarea'))
					{
						$ctrl.val(value);
					}
				}
			});

			clicked.focus();
		}

		function handleSelection(e)
		{
			var choice = $(e.target).data('choice') || $(e.target).val();
			if (choice === '')
			{
				return true;
			}

			if (xhr)
			{
			//	xhr.abort();
			}

			if (valueCache[choice])
			{
				handleValuesResponse(this, valueCache[choice]);
			}
			else
			{
				clicked = this;

				xhr = XenForo.ajax($form.data('form-filler-url'),
					{ choice: choice },
					function(ajaxData, textStatus)
					{
						valueCache[choice] = ajaxData;

						handleValuesResponse(clicked, ajaxData);
					}
				);
			}
		}

		this.addControl = function($control)
		{
			$control.click(handleSelection);
		};
	}

	XenForo.register('.FormFiller', 'XenForo.FormFillerControl');
}
(jQuery, this, document);