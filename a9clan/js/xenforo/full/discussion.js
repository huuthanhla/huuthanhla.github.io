/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	//TODO: Enable jQuery plugin and compressor in editor template.

	/**
	 * Enables quick reply for a message form
	 * @param $form
	 */
	XenForo.QuickReply = function($form)
	{
		if ($('#messageList').length == 0)
		{
			return console.error('Quick Reply not possible for %o, no #messageList found.', $form);
		}

		var $lastDateInput = $('input[name="last_date"]', $form);
		if ($lastDateInput.data('load-value'))
		{
			// FF caches this value on refresh, but since we have the new posts, we need this to reflect the source value
			$lastDateInput.val(Math.max($lastDateInput.val(), $lastDateInput.data('load-value')));
		}

		var submitEnableCallback = XenForo.MultiSubmitFix($form);

		/**
		 * Scrolls QuickReply into view and focuses the editor
		 */
		this.scrollAndFocus = function()
		{
			$(document).scrollTop($form.offset().top);

			var ed = XenForo.getEditorInForm($form);
			if (!ed)
			{
				return false;
			}

			if (ed.$editor)
			{
				ed.focus(true);
			}
			else
			{
				ed.focus();
			}

			return this;
		};

		$form.data('QuickReply', this).bind(
		{
			/**
			 * Fires just before the form would be AJAX submitted,
			 * to detect whether or not the 'more options' button was clicked,
			 * and to abort AJAX submission if it was.
			 *
			 * @param event e
			 * @return
			 */
			AutoValidationBeforeSubmit: function(e)
			{
				if ($(e.clickedSubmitButton).is('input[name="more_options"]'))
				{
					e.preventDefault();
					e.returnValue = true;
				}
			},

			/**
			 * Fires after the AutoValidator form has successfully validated the AJAX submission
			 *
			 * @param event e
			 */
			AutoValidationComplete: function(e)
			{
				if (e.ajaxData._redirectTarget)
				{
					window.location = e.ajaxData._redirectTarget;
				}

				$('input[name="last_date"]', $form).val(e.ajaxData.lastDate);

				if (submitEnableCallback)
				{
					submitEnableCallback();
				}

				$form.find('input:submit').blur();

				new XenForo.ExtLoader(e.ajaxData, function()
				{
					$('#messageList').find('.messagesSinceReplyingNotice').remove();

					$(e.ajaxData.templateHtml).each(function()
					{
						if (this.tagName)
						{
							$(this).xfInsert('appendTo', $('#messageList'));
						}
					});
				});

				var $textarea = $('#QuickReply').find('textarea');
				$textarea.val('');
				var ed = $textarea.data('XenForo.BbCodeWysiwygEditor');
				if (ed)
				{
					ed.resetEditor(null, true);
				}

				$form.trigger('QuickReplyComplete');

				return false;
			},

			BbCodeWysiwygEditorAutoSaveComplete: function(e)
			{
				var $messageList = $('#messageList'),
					$notice = $messageList.find('.messagesSinceReplyingNotice');

				if (e.ajaxData.newPostCount && e.ajaxData.templateHtml)
				{
					if ($notice.length)
					{
						$notice.remove();
						$(e.ajaxData.templateHtml).appendTo($messageList).show().xfActivate();
					}
					else
					{
						$(e.ajaxData.templateHtml).xfInsert('appendTo', $messageList);
					}
				}
				else
				{
					$notice.remove();
				}
			}
		});
	};

	// *********************************************************************

	/**
	 * Controls to initialise Quick Reply with a quote
	 *
	 * @param jQuery a.ReplyQuote, a.MultiQuote
	 */
	XenForo.QuickReplyTrigger = function($trigger)
	{
		/**
		 * Activates quick reply and quotes the post to which the trigger belongs
		 *
		 * @param e event
		 *
		 * @return boolean false
		 */
		$trigger.click(function(e)
		{
			console.info('Quick Reply Trigger Click');

			var $form = null,
				xhr = null,
				queryData = {},
				$quoteSelected = null,
				dataPrepare = null;

			if ($trigger.is('.MultiQuote'))
			{
				$form = $($trigger.data('form'));
			}
			else
			{
				$form = $('#QuickReply');
				$form.data('QuickReply').scrollAndFocus();
			}

			// fire event to get additional data
			dataPrepare = new $.Event('QuickReplyDataPrepare');
			dataPrepare.$trigger = $trigger;
			dataPrepare.queryData = queryData;
			$(document).trigger(dataPrepare);

			if (!xhr)
			{
				xhr = XenForo.ajax
				(
					$trigger.data('posturl') || $trigger.attr('href'),
					queryData,
					function(ajaxData, textStatus)
					{
						if (XenForo.hasResponseError(ajaxData))
						{
							return false;
						}

						delete(xhr);

						var ed = XenForo.getEditorInForm($form);
						if (!ed)
						{
							return false;
						}

						if (ed.$editor)
						{
							ed.insertHtml(ajaxData.quoteHtml);

							console.info('QuoteHTML: %s', ajaxData.quoteHtml);

							if (ed.$editor.data('xenForoElastic'))
							{
								ed.$editor.data('xenForoElastic')();
							}
						}
						else
						{
							ed.val(ed.val() + ajaxData.quote);
						}

						if ($trigger.is('.MultiQuote'))
						{
							// reset cookie and checkboxes
							$form.trigger('MultiQuoteComplete');
						}
					}
				);
			}

			return false;
		});
	};

	// *********************************************************************

	XenForo.InlineMessageEditor = function($form)
	{
		new XenForo.MultiSubmitFix($form);

		$form.bind(
		{
			AutoValidationBeforeSubmit: function(e)
			{
				if ($(e.clickedSubmitButton).is('input[name="more_options"]'))
				{
					e.preventDefault();
					e.returnValue = true;
				}
			},
			AutoValidationComplete: function(e)
			{
				var overlay = $form.closest('div.xenOverlay').data('overlay'),
					target = overlay.getTrigger().data('target');

				if (XenForo.hasTemplateHtml(e.ajaxData, 'messagesTemplateHtml') || XenForo.hasTemplateHtml(e.ajaxData))
				{
					e.preventDefault();
					overlay.close().getTrigger().data('XenForo.OverlayTrigger').deCache();

					XenForo.showMessages(e.ajaxData, overlay.getTrigger(), 'instant');
				}
				else
				{
					console.warn('No template HTML!');
				}
			}
		});
	};

	// *********************************************************************

	XenForo.NewMessageLoader = function($ctrl)
	{
		$ctrl.click(function(e) {
			e.preventDefault();

			XenForo.ajax(
				$ctrl.data('href') || $ctrl.attr('href'),
				{},
				function(ajaxData) {
					if (XenForo.hasResponseError(ajaxData))
					{
						return false;
					}

					var $form = $('#QuickReply'),
						$messageList = $('#messageList');

					$('input[name="last_date"]', $form).val(ajaxData.lastDate);

					new XenForo.ExtLoader(ajaxData, function()
					{
						$messageList.find('.messagesSinceReplyingNotice').remove();

						$(ajaxData.templateHtml).each(function()
						{
							if (this.tagName)
							{
								$(this).xfInsert('appendTo', $messageList);
							}
						});
					});
				}
			)
		});
	};

	// *********************************************************************

	XenForo.MessageLoader = function($ctrl)
	{
		$ctrl.click(function(e)
		{
			e.preventDefault();

			var messageIds = [];

			$($ctrl.data('messageselector')).each(function(i, msg)
			{
				messageIds.push(msg.id);
			});

			if (messageIds.length)
			{
				XenForo.ajax
				(
					$ctrl.attr('href'),
					{
						messageIds: messageIds
					},
					function(ajaxData, textStatus)
					{
						XenForo.showMessages(ajaxData, $ctrl, 'fadeDown');
					}
				);
			}
			else
			{
				console.warn('No messages found to load.'); // debug message, no phrasing
			}
		});
	};

	// *********************************************************************

	XenForo.showMessages = function(ajaxData, $ctrl, method)
	{
		var showMessage = function(selector, templateHtml)
		{
			switch (method)
			{
				case 'instant':
				{
					method =
					{
						show: 'xfShow',
						hide: 'xfHide',
						speed: 0
					};
					break;
				}

				case 'fadeIn':
				{
					method =
					{
						show: 'xfFadeIn',
						hide: 'xfFadeOut',
						speed: XenForo.speed.fast
					};
					break;
				}

				case 'fadeDown':
				default:
				{
					method =
					{
						show: 'xfFadeDown',
						hide: 'xfFadeUp',
						speed: XenForo.speed.normal
					};
				}
			}

			$(selector)[method.hide](method.speed / 2, function()
			{
				$(templateHtml).xfInsert('replaceAll', selector, method.show, method.speed);
			});
		};

		if (XenForo.hasResponseError(ajaxData))
		{
			return false;
		}

		if (XenForo.hasTemplateHtml(ajaxData, 'messagesTemplateHtml'))
		{
			new XenForo.ExtLoader(ajaxData, function()
			{
				$.each(ajaxData.messagesTemplateHtml, showMessage);
			});
		}
		else if (XenForo.hasTemplateHtml(ajaxData))
		{
			// single message
			new XenForo.ExtLoader(ajaxData, function()
			{
				showMessage($ctrl.data('messageselector'), ajaxData.templateHtml);
			});
		}
	};

	// *********************************************************************

	XenForo.PollVoteForm = function($form)
	{
		var replacePollBlock = function(ajaxData)
		{
			new XenForo.ExtLoader(ajaxData, function()
			{
				var $container = $form.closest('.PollContainer');

				$form.xfFadeUp(XenForo.speed.normal, function()
				{
					$form.empty().remove();

					var $html = $(ajaxData.templateHtml);
					if ($html.is('.PollContainer'))
					{
						$html = $html.children();
					}
					else if ($html.find('.PollContainer').length)
					{
						$html = $html.find('.PollContainer').children();
					}

					$html.xfInsert('appendTo', $container);
					$container.xfActivate();
				}, XenForo.speed.normal, 'swing');
			});
		};

		$form.bind('AutoValidationComplete', function(e)
		{
			e.preventDefault();

			if (XenForo.hasTemplateHtml(e.ajaxData))
			{
				replacePollBlock(e.ajaxData);
			}
		});

		$form.on('click', '.PollChangeVote', function(e)
		{
			e.preventDefault();

			XenForo.ajax($(e.target).attr('href'), {}, function(ajaxData)
			{
				if (XenForo.hasTemplateHtml(ajaxData))
				{
					replacePollBlock(ajaxData);
				}
			}, {method: 'get'});
		});

		var maxVotes = $form.data('max-votes') || 0;
		if (maxVotes > 1)
		{
			$form.on('click', '.PollResponse', function(e)
			{
				var $responses = $form.find('.PollResponse'),
					$unselected = $responses.filter(':not(:checked)');

				// saves a separate query to get the selected count
				if ($responses.length - $unselected.length >= maxVotes)
				{
					$unselected.prop('disabled', true);
				}
				else
				{
					$unselected.prop('disabled', false);
				}
			});
		}
	};

	// *********************************************************************

	XenForo.MultiQuote = function($button) { this.__construct($button); };
	XenForo.MultiQuote.prototype =
	{
		__construct: function($button)
		{
			this.$button = $button.click($.context(this, 'prepareOverlay'));
			this.$form = $button.closest('form');
			this.cookieName = $button.data('mq-cookie') || 'MultiQuote';
			this.cookieValue = [];
			this.submitUrl = $button.data('submiturl');
			this.$controls = new jQuery();

			this.getCookieValue();
			this.setButtonState();

			var self = this;

			this.$form.bind('MultiQuoteComplete', $.context(this, 'reset'));
			this.$form.bind('MultiQuoteRemove MultiQuoteAdd', function(e, data)
			{
				if (data && data.messageId)
				{
					self.toggleControl(data.messageId, e.type == 'MultiQuoteAdd');
				}
			});

			$(document).bind('QuickReplyDataPrepare', $.context(this, 'quickReplyDataPrepare'));
		},

		getCookieValue: function()
		{
			var cookieString = $.getCookie(this.cookieName);

			this.cookieValue = (cookieString == null ? [] : cookieString.split(','));
		},

		setButtonState: function()
		{
			this.getCookieValue();

			if (this.cookieValue.length)
			{
				this.$button.show();
			}
			else
			{
				this.$button.hide();
			}
		},

		addControl: function($control)
		{
			$control.click($.context(this, 'clickControl'));

			this.getCookieValue();

			this.setControlState($control, ($.inArray($control.data('messageid') + '', this.cookieValue) >= 0), true);

			this.$controls = this.$controls.add($control);
		},

		setControls: function()
		{
			var MQ = this;

			MQ.getCookieValue();

			this.$controls.each(function()
			{
				MQ.setControlState($(this), ($.inArray($(this).data('messageid') + '', MQ.cookieValue) >= 0));
			});
		},

		setControlState: function($control, isActive, isInitial)
		{
			var text, $button = this.$button, classExpected;
			if (isActive)
			{
				text = $button.data('remove') || '-';
				classExpected = true;
			}
			else
			{
				text = $button.data('add') || '+';
				classExpected = false;
			}

			if (!isInitial || $control.hasClass('active') !== classExpected)
			{
				$control
					.toggleClass('active', isActive)
					.find('span.symbol').text(text);
			}
		},

		clickControl: function(e)
		{
			e.preventDefault();

			var $control,
				newActive,
				quoteHtml,
				messageId,
				alertMessage;

			$control = $(e.target).closest('a.MultiQuoteControl');

			if ($control.is('.QuoteSelected'))
			{
				newActive = true;

				quoteHtml = $('#QuoteSelected').data('quote-html');

				$control.trigger('QuoteSelectedClicked');
			}
			else
			{
				newActive = !$control.is('.active');

				quoteHtml = null;
			}

			messageId = $control.data('messageid');

			this.toggleControl(messageId, newActive, quoteHtml);

			// show an alert to explain what has been done
			if (alertMessage = this.$button.data(newActive ? 'add-message' : 'remove-message'))
			{
				XenForo.alert(alertMessage, '', 2000);
			}
		},

		toggleControl: function(itemId, active, quoteHtml)
		{
			this.getCookieValue();

			var quoteId = null;

			itemId += '';
			if (itemId.indexOf('-') > 0)
			{
				var parts = itemId.split('-');
				itemId = parts[0];
				quoteId = parts[1];
			}

			var $control,
				i = $.inArray(itemId, this.cookieValue);

			$control = this.$controls.filter(function()
			{
				return $(this).data('messageid') == itemId;
			}).first();

			if (active)
			{
				/*
				If control is already active, un-check control if there is no message selection,
				Otherwise, add the new selection to the message
				 */

				if ($control.length)
				{
					this.setControlState($control, true);
				}

				// add quote to localStorage
				if (quoteHtml !== null)
				{
					this.storeSelectedQuote(itemId, quoteHtml);
				}
				else
				{
					this.removeQuotesFromStorage(itemId); // selecting the whole thing instead
				}

				// add to cookie
				if (i < 0)
				{
					this.cookieValue.push(itemId);
				}
			}
			else
			{
				// remove from localStorage
				this.removeQuotesFromStorage(itemId, quoteId);

				// only act as if there's no quote if we removed the last partial one
				if (!this.getStorageForId(itemId))
				{
					if ($control.length)
					{
						this.setControlState($control, false);
					}

					// remove cookie
					if (i >= 0)
					{
						this.cookieValue.splice(i, 1);
					}
				}
			}

			if (this.cookieValue.length > 0)
			{
				$.setCookie(this.cookieName, this.cookieValue.join(','));
			}
			else
			{
				$.deleteCookie(this.cookieName);
			}

			this.setButtonState();
		},

		/**
		 * Store a quote selection
		 *
		 * @param integer message id
		 * @param string quoteHtml
		 */
		storeSelectedQuote: function(id, quoteHtml)
		{
			var storage = this.getStorageObject(),
				count = 0;

			if (!storage[id] || typeof storage[id] != 'object')
			{
				storage[id] = {};
			}

			$.each(storage[id], function(k)
			{
				count = k;
			});

			count = parseInt(count, 10) + 1;

			storage[id][count] = quoteHtml;

			this.saveStorageObject(storage);
		},

		/**
		 * Removes quoted sections from message [id]
		 *
		 * @param id
		 * @param count
		 */
		removeQuotesFromStorage: function(id, count)
		{
			var storage = this.getStorageObject();

			id += '';

			if (!count && id.indexOf('-') > 0)
			{
				var parts = id.split('-');
				id = parts[0];
				count = parts[1];
			}

			if (count)
			{
				delete storage[id][count];
				if ($.isEmptyObject(storage[id]))
				{
					delete storage[id];
				}
			}
			else
			{
				delete storage[id];
			}

			this.saveStorageObject(storage);
		},

		/**
		 * Get the localStorage object that contains quote selections
		 *
		 * @returns object
		 */
		getStorageObject: function()
		{
			if (!window.localStorage)
			{
				return {};
			}

			var storageObj = null;

			try
			{
				storageObj = JSON.parse(localStorage.getItem(this.cookieName));
			}
			catch(e) {}

			if (storageObj === null || typeof storageObj !== 'object')
			{
				storageObj = {};
			}

			return storageObj;
		},

		getStorageForId: function(id)
		{
			var storage = this.getStorageObject();

			if (storage[id] && typeof storage[id] == 'object' && !$.isEmptyObject(storage[id]))
			{
				return storage[id];
			}

			return null;
		},

		getStorageObjectFlat: function()
		{
			var output = {};
			$.each(this.getStorageObject(), function(id, v)
			{
				if (typeof v != 'object')
				{
					return;
				}

				$.each(v, function(count, html)
				{
					output[id + '-' + count] = html;
				});
			});

			return output;
		},

		/**
		 * Store the object that contains quote selections
		 *
		 * @param object storageObj
		 */
		saveStorageObject: function(storageObj)
		{
			if (window.localStorage)
			{
				localStorage.setItem(this.cookieName, JSON.stringify(storageObj));
			}
		},

		/**
		 * Prepares a request for the server to open the multiquote overlay
		 *
		 * @param e
		 */
		prepareOverlay: function(e)
		{
			var quotes = this.getStorageObjectFlat();
			$.each(quotes, function(i, html)
			{
				quotes[i] = XenForo.unparseBbCode(html);
			});

			XenForo.ajax(
				this.$button.data('href'),
				{ quoteSelections: quotes },
				function(ajaxData, textStatus)
				{
					if (XenForo.hasTemplateHtml(ajaxData))
					{
						new XenForo.ExtLoader(ajaxData, function(ajaxData)
						{
							 ajaxData.noCache = true; // otherwise we pick up old data before a refresh...

							 XenForo.createOverlay(null, ajaxData.templateHtml, ajaxData).load();
						});
					}
				}
			);
		},

		/**
		 * Triggered prior to the multiquote overlay being submitted for insertion into the QR editor
		 * Expects (event)e to have (jQuery)$trigger and (obj)queryData properties.
		 *
		 * @param e
		 */
		quickReplyDataPrepare: function(e)
		{
			if (e.$trigger.is('.MultiQuote'))
			{
				var quotes = this.getStorageObjectFlat();

				$.each(quotes, function(i, html)
				{
					quotes[i] = XenForo.unparseBbCode(html);
				});
				e.queryData.quoteSelections = quotes;

				e.queryData.postIds = $(e.$trigger.data('inputs')).map(function()
				{
					return this.value;
				}).get();
			}
		},

		reset: function()
		{
			$.deleteCookie(this.cookieName);
			this.cookieValue = [];
			if (window.localStorage)
			{
				localStorage.removeItem(this.cookieName);
			}

			this.setControls();
			this.setButtonState();
		}
	};

	// *********************************************************************

	/**
	 * Handles adding and removing messages from multi-quote
	 */
	XenForo.MultiQuoteControl = function($link)
	{
		var mqSelector = $link.data('mq-target') || '#MultiQuote',
			mq = $(mqSelector).data('XenForo.MultiQuote');
		if (!mq)
		{
			return;
		}

		mq.addControl($link);
	};

	/**
	 * Handles removal of quotes from the multi-quote overlay
	 */
	XenForo.MultiQuoteRemove = function($link)
	{
		$link.click(function()
		{
			var $container = $link.closest('.MultiQuoteItem'),
				messageId = $container.find('.MultiQuoteId').val(),
				$watcherForm = $($('#MultiQuoteForm').data('form')),
				$overlay = $link.closest('.xenOverlay');

			if (messageId)
			{
				$watcherForm.trigger('MultiQuoteRemove', {messageId: messageId});
			}

			$container.remove();

			if ($overlay.length && !$overlay.find('.MultiQuoteItem').length)
			{
				$overlay.overlay().close();
			}
		});
	};

	// *********************************************************************

	XenForo.Sortable = function($container)
	{
		$container.sortable(
		{
			forcePlaceholderSize: true

		}).bind(
			{
				'sortupdate': function(e) {},
				'dragstart' : function(e)
				{
					console.log('drag start, %o', e.target);
				},
				'dragend' : function(e) { console.log('drag end'); }
			}
		);
	};

	// *********************************************************************
	
	XenForo.SelectQuotable = function($container) { this.__construct($container); };
	XenForo.SelectQuotable.prototype =
	{
		__construct: function($container)
		{
			if (!window.getSelection)
			{
				return;
			}
			if (!$('#QuickReply').length)
			{
				return;
			}

            this.$messageTextContainer = null;

			var self = this,
				isMouseDown = false,
				timeout,
				trigger = function()
				{
					if (!timeout && !self.processing)
					{
						timeout = setTimeout(function()
						{
							timeout = null;
							self._handleSelection();
						}, 100);
					}
				};

			$container.on('mousedown', function()
			{
				isMouseDown = true;
			});
			$container.on('mouseup', function()
			{
				isMouseDown = false;
				trigger();
			});
			$(document).on('selectionchange', function()
			{
				if (!isMouseDown)
				{
					trigger();
				}
			});
			$(document).on('QuickReplyDataPrepare', function(e)
			{
				var $quoteSelected = e.$trigger.closest('#QuoteSelected');
				if ($quoteSelected.length)
				{
					e.queryData.quoteHtml = XenForo.unparseBbCode($quoteSelected.data('quote-html'));

					e.$trigger.trigger('QuoteSelectedClicked');
				}
			});
		},

		buttonClicked: function()
		{
			var s = window.getSelection();
			if (!s.isCollapsed)
			{
				s.collapse(s.getRangeAt(0).commonAncestorContainer, 0);
				this.hideQuoteButton();
			}
		},

		/**
		 * Handle selection events within document
		 */
		_handleSelection: function()
		{
			this.processing = true;

			var selection = window.getSelection();

			if (this._isValidSelection(selection))
			{
				this.showQuoteButton(selection);
			}
			else
			{
				this.hideQuoteButton();
			}

			var self = this;
			setTimeout(function()
			{
				self.processing = false;
			}, 0);
		},

		/**
		 * Check that the specified selection is not collapsed, and is completely contained within $container
		 *
		 * @param selection
		 * @returns {boolean}
		 */
		_isValidSelection: function(selection)
		{
			this.$messageTextContainer = null;
			if (selection.isCollapsed || !selection.rangeCount)
			{
				return false;
			}

			var range = selection.getRangeAt(0);
			this._adjustRange(range);

			if (!$.trim(range.toString()).length)
			{
				if (!range.cloneContents().querySelectorAll('img').length)
				{
					return false;
				}
			}

			var $container = $(range.commonAncestorContainer).closest('.SelectQuoteContainer');
			if (!$container.length)
			{
				return false;
			}

			var $message = $container.closest('.message');
			if (!$message.find('a.MultiQuoteControl, a.ReplyQuote').length)
			{
				return false;
			}

			if ($(range.startContainer).closest('.bbCodeQuote').length
		        || $(range.endContainer).closest('.bbCodeQuote').length)
	        {
		        return false;
	        }

			this.$messageTextContainer = $container;
			return true;
		},

		_adjustRange: function(range)
		{
			var changed = false,
				isQuote = false;

			if (range.endOffset == 0)
			{
				var $end = $(range.endContainer);
				if (range.endContainer.nodeType == 3 && !range.endContainer.previousSibling)
				{
					// text node with nothing before it, move up
					$end = $end.parent();
				}
				isQuote = $end.is('.quote, .attribution, .bbCodeQuote');
			}

			if (isQuote)
			{
				var $quote = $(range.endContainer).closest('.bbCodeQuote');
				if ($quote.length)
				{
					range.setEndBefore($quote[0]);
					changed = true;
				}
			}

			if (changed)
			{
				var sel = window.getSelection();
				sel.removeAllRanges();
				sel.addRange(range);
			}
		},

		/**
		 * Show the quote button, following a selection, if necessary
		 *
		 * @param selection
		 */
		showQuoteButton: function(selection)
		{
			var id = this.$messageTextContainer.closest('[id]').attr('id');
			if (this.$button === undefined || this.$button.data('id') !== id)
			{
				this.hideQuoteButton();
				this.createButton();
				this.$button.data('id', id);
			}
			this.$button.data('quote-html', this.getSelectionHtml(selection));

			var width = this.$button.width();

			var offset = this.getButtonPositionMarker(selection),
				left = this.$messageTextContainer ? this.$messageTextContainer.offset().left : 0;
			if (offset.left - width < left)
			{
				this.$button[XenForo.isRTL() ? 'addClass' : 'removeClass']('flipped').css('left', offset.left - 16);
			}
			else
			{
				this.$button[XenForo.isRTL() ? 'removeClass' : 'addClass']('flipped').css('left',  offset.left - width - 5);
			}

			this.$button
				.css({
					position: 'absolute',
					top: offset.top + 6
				})
				.show();
		},

		getButtonPositionMarker: function(selection)
		{
			// get absolute position of end of selection - or maybe focusNode
			// and position the quote button immediately next to the highlight
			var $el, range, offset, height, bounds;

			$el = $('<span />');
			$el.text($.browser.opera ? 'x' : '\u200B');

			range = selection.getRangeAt(0).cloneRange();
			bounds = range.getBoundingClientRect ? range.getBoundingClientRect() : null;
			range.collapse(false);
			range.insertNode($el[0]);

			var changed,
				moves = 0;

			do
			{
				changed = false;
				moves++;

				if ($el[0].parentNode && $el[0].parentNode.className == 'messageTextEndMarker')
				{
					// highlight after the marker to ensure that triple click works
					$el.insertBefore($el[0].parentNode);

					changed = true;
				}
				if ($el[0].previousSibling && $el[0].previousSibling.nodeType == 3 && $.trim($el[0].previousSibling.textContent).length == 0)
				{
					// highlight after an empty text block
					$el.insertBefore($el[0].previousSibling);

					changed = true;
				}
				if ($el[0].parentNode && $el[0].parentNode.tagName == 'LI' && !$el[0].previousSibling)
				{
					// highlight at the beginning of a list item, move to previous item if possible
					var li = $el[0].parentNode;
					if ($(li).prev('li').length)
					{
						// move to inside the last li
						$el.appendTo($(li).prev('li'));

						changed = true;
					}
					else if (li.parentNode)
					{
						// first list item, move before the list
						$el.insertBefore(li.parentNode);

						changed = true;
					}
				}
				if ($el[0].parentNode && !$el[0].previousSibling && $.inArray($el[0].parentNode.tagName, ['DIV', 'BLOCKQUOTE', 'PRE']) != -1)
				{
					$el.insertBefore($el[0].parentNode);

					changed = true;
				}
				if ($el[0].previousSibling && $.inArray($el[0].previousSibling.tagName, ['OL', 'UL']) != -1)
				{
					// immediately after a list, position at end of last LI
					$($el[0].previousSibling).find('li:last').append($el);

					changed = true;
				}
				if ($el[0].previousSibling && $.inArray($el[0].previousSibling.tagName, ['DIV', 'BLOCKQUOTE', 'PRE']) != -1)
				{
					// highlight immediately after a block causes weird positioning
					$el.appendTo($el[0].previousSibling);

					changed = true;
				}
				if ($el[0].previousSibling && $el[0].previousSibling.tagName == 'BR')
				{
					// highlight immediately after a line break causes weird positioning
					$el.insertBefore($el[0].previousSibling);

					changed = true;
				}
			}
			while (changed && moves < 5);

			offset = $el.offset();
			height = $el.height();

			// if we're in a scrollable element, find the right edge of that element and don't position beyond it
			$el.parentsUntil('body').each(function() {
				var $parent = $(this), left, right;

				switch ($parent.css('overflow-x'))
				{
					case 'hidden':
					case 'scroll':
					case 'auto':
						left = $parent.offset().left;
						right = left + $parent.outerWidth();
						if (offset.left < left)
						{
							offset.left = left;
						}
						if (right < offset.left)
						{
							offset.left = right;
						}
				}
			});

			$el.remove();

			if (bounds && !XenForo.isRTL())
			{
				if (offset.left - bounds.left > 32)
				{
					offset.left -= 16;
				}
			}

			offset.top += height;

			return offset;
		},

		/**
		 * Create the 'Quote Selected' button if necessary
		 */
		createButton: function()
		{
			// TODO: clone the ReplyQuote control and use that...
            if (!this.$messageTextContainer.length)
            {
                return false;
            }

			var $message = this.$messageTextContainer.closest('.message');

			this.$button = $('<div id="QuoteSelected" class="xenTooltip"></div>')
				.click($.context(this, 'hideQuoteButton'))
				.append('<span class="arrow"></span>');

			var $mqButton = $message.find('a.MultiQuoteControl').first().clone();
			if ($mqButton.length)
			{
				$mqButton
					.addClass('QuoteSelected')
					.attr('title', '')
					.on('QuoteSelectedClicked', $.context(this, 'buttonClicked'));
				$mqButton.find('span.symbol').text($('.MultiQuoteWatcher').data('add')); // always use Quote +
				new XenForo.MultiQuoteControl($mqButton);

				this.$button.append($mqButton);
				this.$button.append(document.createTextNode(" | "));
			}

			var $quoteButton = $message.find('a.ReplyQuote').clone();
			$quoteButton
				.addClass('QuoteSelected')
				.attr('title', '')
				.on('QuoteSelectedClicked', $.context(this, 'buttonClicked'));
			new XenForo.QuickReplyTrigger($quoteButton);

			this.$button.append($quoteButton);

			$(document.body).append(this.$button);

			var self = this, windowWidth = $(window).width();
			$(window).on('resize.SelectQuotable', function(e)
			{
				var newWidth = $(window).width();
				if (newWidth != windowWidth)
				{
					windowWidth = newWidth;
					self._handleSelection();
				}
			});
			$(document).on('XFOverlay.SelectQuotable', function(e)
			{
				self.hideQuoteButton();
				window.getSelection().collapseToEnd();
			});
		},


		/**
		 * Hide the quote button when it's no longer required
		 */
		hideQuoteButton: function()
		{
			if (this.$button !== undefined)
			{
				this.$button.remove();
				delete(this.$button);
			}

			$(window).off('resize.SelectQuotable');
			$(document).off('XFOverlay.SelectQuotable');
		},

		/**
		 * Returns the entirety of the HTML enclosed by the specified selection
		 *
		 * @param selection
		 * @returns {string}
		 */
		getSelectionHtml: function(selection)
		{
			var el = document.createElement('div'),
				i, len;
			
			for (i = 0, len = selection.rangeCount; i < len; i++)
			{
				el.appendChild(selection.getRangeAt(i).cloneContents());
			}

			return this.prepareSelectionHtml(el.innerHTML);
		},

		prepareSelectionHtml: function(html)
		{
			return html;
		}
	};

	// *********************************************************************

	/**
	 * Attempts to convert various HTML-BB codes back into BB code
	 *
	 * @param string html
	 *
	 * @returns string
	 */
	XenForo.unparseBbCode = function(html)
	{
		var $div = $(document.createElement('div'));

		$div.html(html);
		console.log($div.find('.bbCodeQuote').length);

		// handle b, i, u
		$.each(['B', 'I', 'U'], function(i, tagName)
		{
			$div.find(tagName).each(function()
			{
				$(this).replaceWith('[' + tagName + ']' + $(this).html() + '[/' + tagName + ']');
			});
		});

		// handle quote tags as best we can
		$div.find('.bbCodeQuote').each(function()
		{
			var $this = $(this),
				$quote = $this.find('.quote');
			if ($quote.length)
			{
				$this.replaceWith('<div>[QUOTE]' + $quote.html() + '[/QUOTE]</div>');
			}
			else
			{
				$quote.find('.quoteExpand').remove();
			}
		});

		// now for PHP, CODE and HTML
		$div.find('.bbCodeCode, .bbCodeHtml, .bbCodePHP').each(function()
		{
			var $this = $(this),
				type = $(this).find('div.type').first().text(),
				findTag = 'pre';

			if (type !== '')
			{
				type = type.replace(/^(.+):$/, '$1');
			}

			if ($this.is('.bbCodePHP'))
			{
				findTag = 'code';
			}

			$this.replaceWith($this.find(findTag).first().attr('data-type', type));
		});

		// now alignment tags
		$div.find('div[style*="text-align"]').each(function()
		{
			var align = $(this).css('text-align').toUpperCase();

			$(this).replaceWith('[' + align + ']' + $(this).html() + '[/' + align + ']');
		});

		// and finally, spoilers...
		$div.find('.bbCodeSpoilerContainer').each(function()
		{
			var $button, target, spoilerTitle, spoilerText;

			// find the button and the target
			$button = $(this).find('.bbCodeSpoilerButton');
			if ($button.length)
			{
				target = $button.data('target');
				if (target)
				{
					spoilerText = $(this).find(target).html();

					$spoilerTitle = $(this).find('.SpoilerTitle');
					if ($spoilerTitle.length)
					{
						spoilerTitle = '="' + $spoilerTitle.text() + '"';
					}
					else
					{
						spoilerTitle = '';
					}

					$(this).replaceWith('[SPOILER' + spoilerTitle + ']' + spoilerText + '[/SPOILER]');
				}
			}
		});

		console.info('HTML to be sent: %s', $div.html());

		return $div.html();
	};

	// *********************************************************************

	XenForo.register('#QuickReply', 'XenForo.QuickReply');

	XenForo.register('a.ReplyQuote, a.MultiQuote, a.QuoteSelected', 'XenForo.QuickReplyTrigger');

	XenForo.register('form.InlineMessageEditor', 'XenForo.InlineMessageEditor');

	XenForo.register('a.MessageLoader', 'XenForo.MessageLoader');
	XenForo.register('a.NewMessageLoader', 'XenForo.NewMessageLoader');

	XenForo.register('form.PollVoteForm', 'XenForo.PollVoteForm');

	XenForo.register('.MultiQuoteWatcher', 'XenForo.MultiQuote');
	XenForo.register('a.MultiQuoteControl', 'XenForo.MultiQuoteControl');
	XenForo.register('a.MultiQuoteRemove', 'XenForo.MultiQuoteRemove');

	XenForo.register('.Sortable', 'XenForo.Sortable');

	XenForo.register('.SelectQuotable', 'XenForo.SelectQuotable');

}
(jQuery, this, document);