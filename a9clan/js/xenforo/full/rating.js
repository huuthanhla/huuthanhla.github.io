/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	XenForo.RatingWidget = function($widget)
	{
		var xhr = null,
			overlay = null,

		$hint = $widget.find('.Hint').each(function()
		{
			var $el = $(this);
			$el.data('text', $el.text());
		}),

		$currentRating = $widget.find('.RatingValue .Number'),

		$stars = $widget.find('button').each(function()
		{
			var $el = $(this);
			$el.data('hint', $el.attr('title')).removeAttr('title');
		}),

		setStars = function(starValue)
		{
			$stars.each(function(i)
			{
				// i is 0-4, not 1-5
				$(this)
					.toggleClass('Full', (starValue >= i + 1))
					.toggleClass('Half', (starValue >= i + 0.5 && starValue < i + 1));
			});
		},

		resetStars = function()
		{
			setStars($currentRating.text());

			$hint.text($hint.data('text'));
		};


		$stars.bind(
		{
			mouseenter: function(e)
			{
				e.preventDefault();

				setStars($(this).val());

				$hint.text($(this).data('hint'));
			},

			click: function(e)
			{
				e.preventDefault();

				if (overlay)
				{
					overlay.load();
					return;
				}

				xhr = XenForo.ajax
				(
					$widget.attr('action'),
					{ rating: $(this).val() },
					function(ajaxData, textStatus)
					{
						if (!XenForo.hasResponseError(ajaxData))
						{
							if (ajaxData._redirectMessage)
							{
								XenForo.alert(ajaxData._redirectMessage, '', 1000);
							}

							if (ajaxData.newRating)
							{
								$currentRating.text(ajaxData.newRating);
							}

							if (ajaxData.hintText)
							{
								$hint.data('text', ajaxData.hintText);
							}

							if (ajaxData.templateHtml)
							{
								new XenForo.ExtLoader(ajaxData, function()
								{
									overlay = XenForo.createOverlay(null, ajaxData.templateHtml, {
										title: ajaxData.h1 || ajaxData.title
									}).load();
									overlay.getOverlay().find('.OverlayCloser').click(function() {
										overlay = null;
									});
								});
							}
						}

						resetStars();

						xhr = null;
					}
				);
			}
		});

		$widget.mouseleave(function(e)
		{
			if (xhr === null)
			{
				resetStars();
			}
		});
	};

	// *********************************************************************

	XenForo.register('form.RatingWidget', 'XenForo.RatingWidget');

}
(jQuery, this, document);