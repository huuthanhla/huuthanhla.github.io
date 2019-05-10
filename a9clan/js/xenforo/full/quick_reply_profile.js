/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	/**
	 * Enables AJAX quick reply for profile posts.
	 *
	 * @param $jQuery form#ProfilePoster
	 */
	XenForo.ProfilePoster = function($form) { this.__construct($form); };
	XenForo.ProfilePoster.prototype =
	{
		__construct: function($form)
		{
			this.$form = $form.bind(
			{
				AutoValidationBeforeSubmit: $.context(this, 'beforeSubmit'),
				AutoValidationComplete: $.context(this, 'formValidated')
			});

			if (XenForo.isPositive($form.data('hide-submit')))
			{
				$form.find('.submitUnit').hide();
				$form.find('.StatusEditor').focus(function(e)
				{
					$form.find('.submitUnit').show();
				});
			}

			this.listTarget = $form.data('target') || '#ProfilePostList';

			this.submitEnableCallback = XenForo.MultiSubmitFix(this.$form);
		},

		beforeSubmit: function(e)
		{
			// unused at present
		},

		formValidated: function(e)
		{
			if (e.ajaxData._redirectTarget)
			{
				window.location = e.ajaxData._redirectTarget;
			}

			if (this.submitEnableCallback)
			{
				this.submitEnableCallback();
			}

			this.$form.find('input:submit').blur();

			if (e.ajaxData.statusHtml)
			{
				$('#UserStatus').html(e.ajaxData.statusHtml).xfActivate();
			}

			if (XenForo.hasTemplateHtml(e.ajaxData))
			{
				var listTarget = this.listTarget;
				new XenForo.ExtLoader(e.ajaxData, function()
				{
					$('#NoProfilePosts').remove();

					$(e.ajaxData.templateHtml).xfInsert('prependTo', listTarget);
				});
			}

			var StatusEditor,
				$textarea = this.$form.find('textarea[name="message"]')
				.val('').trigger('XFRecalculate').blur();

			if (StatusEditor = $textarea.data('XenForo.StatusEditor'))
			{
				StatusEditor.update();
			}

			return false;
		}
	};

	// *********************************************************************

	XenForo.register('#ProfilePoster', 'XenForo.ProfilePoster'); // form#ProfilePoster
}
(jQuery, this, document);