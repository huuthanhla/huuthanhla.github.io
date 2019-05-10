// TODO: this probably does too much work when no overlay is required (thread_view, select for moderation)
/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	/**
	 * Container for inline moderation items
	 *
	 * @param jQuery form.InlineModForm
	 */
	XenForo.InlineModForm = function($form) { this.__construct($form); };
	XenForo.InlineModForm.prototype =
	{
		__construct: function($form)
		{
			if ($form.is('form'))
			{
				$form[0].reset();
			}

			this.$form = $form;
			this.$form.data('InlineModForm', this);

			this.$checkAll = this.$form.find('#ModerationCheck').click($.context(this, 'checkAll'));

			var $controls = $(this.$form.data('controls')),
				$controlsContainer = $controls.closest('.InlineMod'),
				$selectionCount = $controls.find('.SelectionCount').click($.context(this, 'nonCheckboxShowOverlay')),
				$countContainer = $('.SelectionCountContainer');

			$controls.show().appendTo('body').wrap('<form id="InlineModOverlay" />');

			if ($countContainer.length)
			{
				$controls.find('.SelectionCount')
					.clone(true)
					.appendTo($countContainer)
					.attr('href', '#')
					.addClass('cloned');
			}

			this.$totals = $('.InlineModCheckedTotal');

			if ($controlsContainer.hasClass('Hide'))
			{
				$controlsContainer.remove();
			}

			this.overlay = null;
			this.cookieName = false;
			this.selectedIds = [];

			this.lastCheck = null;
			this.overlay = null;

			if ($form.data('cookiename'))
			{
				this.cookieName = 'inlinemod_' + $form.data('cookiename');
				this.updateSelectedIdsFromCookie();
			}

			// defer state setting until after registration finishes
			$(document).one('XenForoActivationComplete', $.context(this, 'setState'));
		},

		updateSelectedIdsFromCookie: function()
		{
			if (!this.cookieName)
			{
				return [];
			}

			var sel = this.selectedIds;

			this.cookieValue = $.getCookie(this.cookieName);
			if (this.cookieValue)
			{
				this.selectedIds = this.cookieValue.split(',');
			}
			else
			{
				this.selectedIds = [];
			}

			return {
				original: sel,
				selected: this.selectedIds,
				isDifferent: sel !== this.selectedIds
			};
		},

		setState: function()
		{
			var total = this.selectedIds.length;

			this.$totals.closest('.cloned')[(total ? 'addClass' : 'removeClass')]('itemsChecked');

			this.$totals.text(total);
		},

		recalculateSelected: function()
		{
			var selected = this.selectedIds;

			this.$form.find('input:checkbox.InlineModCheck').each(function() {
				var $ctrl = $(this),
					item = $ctrl.data('XenForo.InlineModItem'),
					checked = $ctrl.prop('checked');

				if (!item)
				{
					return;
				}

				if (selected && $.inArray($ctrl.val(), selected) >= 0)
				{
					if (!checked)
					{
						$ctrl.prop('checked', true);
						item.setStyle();
					}
				}
				else if (checked)
				{
					$ctrl.prop('checked', false);
					item.setStyle();
				}
			});
		},

		setSelectedIdState: function(id, state)
		{
			var update = this.updateSelectedIdsFromCookie();
			if (update.isDifferent)
			{
				setTimeout($.context(this, 'recalculateSelected'), 0);
			}

			var idx = $.inArray(id, this.selectedIds);
			if (state)
			{
				if (idx >= 0)
				{
					return; // adding and already in
				}

				this.selectedIds.push(id);
				this.selectedIds.sort(function(a, b) { return (a - b); });
			}
			else
			{
				if (idx < 0)
				{
					return; // removing and not there
				}

				this.selectedIds.splice(idx, 1);
			}

			this.setState();

			if (!this.cookieName)
			{
				return;
			}

			if (!this.selectedIds.length)
			{
				$.deleteCookie(this.cookieName);
			}
			else
			{
				$.setCookie(this.cookieName, this.selectedIds.join(','));
			}
		},

		/**
		 * Selects or deselects all InlineMod checkboxes for this form
		 *
		 * @param event
		 */
		checkAll: function(e)
		{
			this.$form.find('input:checkbox.InlineModCheck')
				.prop('checked', e.target.checked)
				.trigger('change');

			if (!e.target.checked)
			{
				this.overlay.close();
			}

			e.stopImmediatePropagation();
		},

		/**
		 * Clicks the next or previous InlineModCheck
		 *
		 * @param event
		 * @return
		 */
		clickNextPrev: function(e)
		{
			var $InlineModChecks = this.$form.find('input:checkbox.InlineModCheck'),
				lastCheck = this.lastCheck,
				index = null;

			$InlineModChecks.each(function(i, element)
			{
				if (element == lastCheck)
				{
					index = ($(e.target).hasClass('ClickPrev') ? (i - 1) : (i + 1));

					// halt the .each() loop
					return false;
				}
			});

			if (index === null || index >= $InlineModChecks.length)
			{
				index = 0;
			}

			// focus the next/prev checkbox
			$InlineModChecks.eq(index).get(0).focus();

			var $lastCheck =  $(this.lastCheck);
			if ($lastCheck.length)
			{
				$(XenForo.getPageScrollTagName()).animate(
				{
					scrollTop: $InlineModChecks.eq(index).offset().top
						- $(this.lastCheck).offset().top
						+ $(XenForo.getPageScrollTagName()).scrollTop()
				}, XenForo.speed.normal);
			}

			this.positionOverlay($InlineModChecks.eq(index).get(0));
		},

		/**
		 * Creates the controls overlay
		 *
		 * @return object Overlay API
		 */
		createOverlay: function()
		{
			var $InlineModOverlay = $('#InlineModOverlay'),
				$ModerationSelect = $InlineModOverlay.find('#ModerationSelect');

			$InlineModOverlay.children().show();
			$InlineModOverlay.children('#InlineModControls').css('display', 'block');

			$InlineModOverlay.overlay(
			{
				closeOnClick: false,
				fixed: false,
				close: '.OverlayCloser'
			});

			$ModerationSelect.change($.context(this, 'chooseAction'));

			$InlineModOverlay.find('input:submit').click($.context(this, 'chooseAction'));

			$InlineModOverlay.find('.ClickNext, .ClickPrev').click($.context(this, 'clickNextPrev'));

			return this.overlay = $InlineModOverlay.data('overlay');
		},

		/**
		 * Opens and positions the controls overlay next to the clicked InlineMod checkbox
		 *
		 * @param object Checkbox
		 */
		positionOverlay: function(checkbox)
		{
			if (checkbox.checked || this.$form.find('input:checkbox:checked.InlineModCheck').length)
			{
				console.info('Position overlay next to %o', checkbox);

				if (!this.overlay)
				{
					this.createOverlay();
				}

				var overlay = this.overlay,
					offset = $(checkbox).offset(),
					left = offset.left,
					top = offset.top - 15;

				if (XenForo.isRTL())
				{
					left -= overlay.getOverlay().outerWidth() + 5;
				}
				else
				{
					left += 17; // checkbox width
				}

				if (overlay.getOverlay().outerWidth() + left > $(window).width() || left < 0)
				{
					left = 0;
					top += 34;
				}

				if (!overlay.isOpened())
				{
					overlay.getConf().left = left - $(XenForo.getPageScrollTagName()).scrollLeft();
					overlay.getConf().top = top - $(XenForo.getPageScrollTagName()).scrollTop();
					overlay.load();
				}
				else
				{
					overlay.getOverlay().animate(
					{
						left: left,
						top: top
					}, ((this.lastCheck && !XenForo.isTouchBrowser()) ? XenForo.speed.normal : 0), 'easeOutBack');
				}
			}
			else if (this.overlay && this.overlay.isOpened())
			{
				this.overlay.close();
			}

			this.lastCheck = checkbox;
		},

		/**
		 * Open the overlay next to a non-checkbox trigger
		 *
		 * @param event
		 */
		nonCheckboxShowOverlay: function(e)
		{
			e.preventDefault();

			if ($(e.target).parents('#InlineModOverlay').length)
			{
				return;
			}

			if (!this.overlay)
			{
				this.createOverlay();
			}

			this.overlay.load();

			var ctrlOffset = $(e.target).coords(),
				overlayOffset = this.overlay.getOverlay().coords(),
				left;

			if (XenForo.isRTL())
			{
				left = ctrlOffset.left;
			}
			else
			{
				left = Math.max(0, ctrlOffset.left + ctrlOffset.width - overlayOffset.width);
			}

			if (this.overlay.getOverlay().outerWidth() + left > $(window).width() || left < 0)
			{
				left = 0;
			}

			this.overlay.getOverlay().css(
			{
				left: left,
				top: ctrlOffset.top + ctrlOffset.height + 5
			});

			delete(this.lastCheck);
		},

		/**
		 * Click one of the options in the action chooser
		 *
		 * @param event e
		 */
		chooseAction: function(e)
		{
			e.preventDefault();

			var a, preFn, $control = $(e.target);

			if ($control.is(':submit') && $control.attr('name'))
			{
				a = $control.attr('name');
			}
			else
			{
				a = $('#ModerationSelect').val();
			}

			if (a == '')
			{
				this.overlay.close();
				return false;
			}

			if (this.running)
			{
				return false;
			}

			preFn = a + 'PreAction';

			if (typeof this[preFn] == 'function')
			{
				this[preFn](e);
			}
			else
			{
				this.execAction(a);
			}
		},

		resetActionMenu: function()
		{
			this.overlay.getOverlay().get(0).reset();
		},

		/**
		 * Executes the specified action via AJAX
		 *
		 * @param string Action to be executed
		 */
		execAction: function(a)
		{
			this.running = true;

			XenForo.ajax(
				this.$form.attr('action'),
				this.$form.serialize() + '&a=' + a,
				$.context(function(ajaxData, textStatus)
				{
					this.running = false;
					this.resetActionMenu();

					if (XenForo.hasResponseError(ajaxData))
					{
						return false;
					}

					if (XenForo.hasTemplateHtml(ajaxData))
					{
						var api, options = {};

						options.title = ajaxData.title || ajaxData.h1;
						options.noCache = true;

						new XenForo.ExtLoader(ajaxData, function()
						{
							console.info('show overlay');
							XenForo.createOverlay('', ajaxData.templateHtml, options).load();
						});
					}
					else if (ajaxData._redirectTarget)
					{
						console.log('Inline moderation, %s complete.', a);

						var postFn = a + 'PostAction';

						if (typeof this[postFn] == 'function')
						{
							this[postFn](ajaxData, textStatus);
						}
						else
						{
							XenForo.redirect(ajaxData._redirectTarget);
						}
					}
				}, this)
			);

			var midFn = a + 'MidAction';
			if (typeof this[midFn] == 'function')
			{
				this[midFn](a);
			}
		},

		// ****************************************
		// handlers for specific inline mod actions

		/**
		 * Closes the overlay
		 */
		closeOverlayPreAction: function(a)
		{
			this.resetActionMenu();
			this.overlay.close();
		},

		/**
		 * Deselects selected items on the current page in response to a=deselect
		 */
		deselectPreAction: function(a)
		{
			this.$form.find('input:checkbox.InlineModCheck:checked')
				.prop('checked', false)
				.trigger('change');

			this.selectedIds = [];
			this.setState();

			if (this.cookieName)
			{
				$.deleteCookie(this.cookieName);
			}

			this.$checkAll.prop('checked', false);

			this.resetActionMenu();
			this.overlay.close();
		}
	};

	// *********************************************************************

	/**
	 * Handles selection and highlighting of Inline Moderation Items. Note that
	 * the form should be initialized first, to pick up the cookie data.
	 *
	 * @param jQuery :checkbox.InlineModCheck
	 */
	XenForo.InlineModItem = function($ctrl) { this.__construct($ctrl); };
	XenForo.InlineModItem.prototype =
	{
		__construct: function($ctrl)
		{
			this.$form = $ctrl.closest('form');

			this.$ctrl = $ctrl.attr('title', XenForo.htmlspecialchars($ctrl.attr('title')));

			if (XenForo.isTouchBrowser())
			{
				// don't use active tooltips for touch browsers
			}
			else
			{
				this.$ctrl.tooltip(XenForo.configureTooltipRtl({
					effect: 'fade',
					fadeInSpeed: XenForo.speed.xfast,
					offset: [ -10, -20 ],

					predelay: ($.browser.msie ? 0 : 100), // TODO: this seems to disable the tooltip in IE, but prevents a JS error
					position: 'top right',
					tipClass: 'xenTooltip inlineModCheckTip',
					onBeforeShow: $.context(this, 'beforeTooltip')
				}));
			}

			this.arrowAppended = false;

			this.$target = $($ctrl.data('target'));

			//console.info('New InlineModItem %o targeting %o', $ctrl, this.$target);

			if (this.$form.data('InlineModForm'))
			{
				var InlineModForm = this.$form.data('InlineModForm');
				if (InlineModForm.selectedIds.length)
				{
					if ($.inArray($ctrl.val(), InlineModForm.selectedIds) >= 0)
					{
						$ctrl.prop('checked', true);
					}
				}
			}

			this.$ctrl.bind(
			{
				change: $.context(this, 'setState'),
				click: $.context(this, 'positionOverlay')
			});

			this.setStyle();
		},

		/**
		 * Set the state of the checkbox programatically
		 */
		setState: function(e)
		{
			//console.log('Setting state for %o, %o to %s', this.$ctrl, this.$target, this.$ctrl.is(':checked'));
			this.setStyle();

			var InlineModForm = this.$form.data('InlineModForm');
			if (InlineModForm)
			{
				InlineModForm.setSelectedIdState(this.$ctrl.val(), this.$ctrl.prop('checked'));
			}
		},

		/**
		 * Alter the style of the target based on checkbox state
		 */
		setStyle: function()
		{
			if (this.$ctrl.is(':checked'))
			{
				this.$target.addClass('InlineModChecked');
			}
			else
			{
				this.$target.removeClass('InlineModChecked');
			}
		},

		/**
		 * Hides the current tooltip and opens (or moves) the controls overlay
		 *
		 * @param event
		 */
		positionOverlay: function(e)
		{
			if (this.$ctrl.data('target'))
			{
				var tooltip,
					InlineModForm = this.$form.data('InlineModForm');

				if (InlineModForm)
				{
					if (tooltip = this.$ctrl.data('tooltip'))
					{
						this.$ctrl.data('tooltip').hide();
					}
					InlineModForm.positionOverlay(e.target);
				}
			}
		},

		/**
		 * Prevent tooltip from showing if there are already checked items in the form
		 *
		 * @param event e
		 *
		 * @return boolean
		 */
		beforeTooltip: function(e)
		{
			if (e.target.checked || this.$form.find('input:checkbox:checked.InlineModCheck').length)
			{
				return false;
			}

			if (!this.arrowAppended)
			{
				this.$ctrl.data('tooltip').getTip().append('<span class="arrow" />');

				this.arrowAppended = true;
			}
		}
	};

	// *********************************************************************

	// Register inline moderation forms
	XenForo.register('form.InlineModForm', 'XenForo.InlineModForm');

	// Register inline moderation items
	XenForo.register('input:checkbox.InlineModCheck', 'XenForo.InlineModItem');

}
(jQuery, this, document);