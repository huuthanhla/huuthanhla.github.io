/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	/**
	 * Activates unfollow controls
	 *
	 * @param jQuery $link a.UnfollowLink
	 */
	XenForo.UnfollowLink = function($link) { this.__construct($link); };
	XenForo.UnfollowLink.prototype =
	{
		__construct: function($link)
		{
			this.$link = $link.click($.context(this, 'eClick'));

			this.userId = $link.data('userid');
			this.jsonUrl = $link.data('jsonurl') || $link.attr('href');

			if (this.userId === null || this.jsonUrl === null)
			{
				console.warn('Unfollow link found without userId or url defined. %o', $link);
				return false;
			}

			this.$container = $('#user_list_' + this.userId);
		},

		/**
		 * Intercept a link on an un-follow link and ask for confirmation
		 *
		 * @param event e
		 */
		eClick: function(e)
		{
			e.preventDefault();

			this.stopFollowing();
		},

		/**
		 * Confirmation callback from link event - stop following the user via AJAX
		 */
		stopFollowing: function()
		{
			XenForo.ajax(
				this.jsonUrl,
				{ user_id: this.userId, '_xfConfirm' : 1 },
				$.context(this, 'stopFollowingSuccess')
			);
		},

		/**
		 * AJAX callback for stop-following. Removes the link's container from the DOM.
		 *
		 * @param object ajaxData
		 * @param string textStatus
		 */
		stopFollowingSuccess: function(ajaxData, textStatus)
		{
			if (XenForo.hasResponseError(ajaxData))
			{
				return false;
			}

			this.$container.xfRemove();
			//xfFadeUp(XenForo.speed.normal, function() { $(this).remove(); });
		}
	};

	// *********************************************************************

	/**
	 * Controls to allow a new user to be followed.
	 *
	 * @param jQuery $form form.FollowForm
	 */
	XenForo.FollowForm = function($form) { this.__construct($form); };
	XenForo.FollowForm.prototype =
	{
		__construct: function($form)
		{
			this.$form = $form
				.bind('AutoValidationComplete', $.context(this, 'ajaxCallback'));

			this.$userInputField = this.$form.find(this.$form.data('userinputfield'));
		},

		/**
		 * Fires when triggered by the response of the form being submitted via AJAX in XenForo.AutoValidatorForm
		 *
		 * @param event jQuery event containing ajaxData.templateHtml
		 */
		ajaxCallback: function(e)
		{
			e.preventDefault();

			if (XenForo.hasResponseError(e.ajaxData))
			{
				return false;
			}

			var userIds = e.ajaxData.userIds.split(','),
				lastId = null,
				i = 0,
				templateHtml = null;

			this.$userInputField.val('').focus();

			for (i = 0; i < userIds.length; i++)
			{
				if (this.$form.find('#user_list_' + userIds[i]).length == 0)
				{
					// this user is not already shown, so insert the template here
					$templateHtml = $(e.ajaxData.users[userIds[i]]);

					if (lastId)
					{
						$templateHtml.xfInsert('insertAfter', lastId);
					}
					else
					{
						$templateHtml.xfInsert('prependTo', '.FollowList');
					}

				}

				lastId = '#user_list_' + userIds[i];
			}
		}
	};

	// *********************************************************************

	XenForo.register('a.UnfollowLink', 'XenForo.UnfollowLink');

	XenForo.register('form.FollowForm', 'XenForo.FollowForm');

}
(jQuery, this, document);