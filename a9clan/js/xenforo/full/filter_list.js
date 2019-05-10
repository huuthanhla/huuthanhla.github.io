/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	XenForo.FilterList = function($list) { this.__construct($list); };
	XenForo.FilterList.prototype =
	{
		__construct: function($list)
		{
			this.$list = $list;
			this.$form = this.$list.closest('form').bind('AutoValidationComplete', $.context(function(e)
			{
				this.$form.find('input.Toggler').each(function()
				{
					// TODO: un-toggle if there was an error
					$(this).closest('h4').toggleClass('disabled', !this.checked);
				})
			}, this));
			this.$listCounter = this.$form.find('.FilterListCount');

			this.lookUpUrl = XenForo.isPositive(this.$list.data('ajaxfilter')) ?
					this.$form.attr('action') : false;

			this.registerListItems();
			this.handleLast();

			if (this.activateFilterControls())
			{
				this.filter();
			}


		},

		/**
		 * Finds and activates the filter controls for the list
		 */
		activateFilterControls: function()
		{
			if (this.$form.length)
			{
				this.$filter = $('input[name="filter"]', this.$form)
					.keyup($.context(this, 'filterKeyUp'))
					.on('paste', $.context(this, 'filterPaste'))
					.bind('search', $.context(this, 'instantSearch'))
					.keypress($.context(this, 'filterKeyPress'));

				this.$prefixMatch = $('input[name="prefixmatch"]', this.$form)
					.change($.context(this, 'filter'));

				this.$clearFilter = $('input[name="clearfilter"]', this.$form)
					.click($.context(this, 'clearFilter'));

				console.info('XenForo.FilterList %o', this.$filter);

				if (this.$filter.length)
				{
					return this.getCookie();
				}
			}

			return false;
		},

		/**
		 * Create XenForo.FilterListItem objects for each list item
		 *
		 * @return array this.listItems
		 */
		registerListItems: function()
		{
			this.FilterListItems = [];

			this.$listItems = this.$list.find('.listItem');

			this.$listItems.each($.context(function(i)
			{
				this.FilterListItems.push(new XenForo.FilterListItem($(this.$listItems[i])));
			}, this));

			this.$groups = this.$list.find('> li:not(.listItem)');
		},

		/**
		 * Read the query string for a 'last' parameter, and if it's found, scroll the item into view, if possible.
		 */
		handleLast: function()
		{
			if (window.location.hash)
			{
				var $last = $(window.location.hash.replace(/[^\w_#-.]/g, '').replace('.', '\\.'));
				if ($last.hasClass('listItem'))
				{
					console.log('Last: %o', $last);
					$last.addClass('Last');
				}
			}
		},

		/**
		 * A little speed-up for live typing
		 *
		 * @param event e
		 *
		 * @return
		 */
		filterKeyUp: function(e)
		{
			if (e.keyCode == 13)
			{
				// enter key - instant search
				this.instantSearch();
				return;
			}

			clearTimeout(this.timeOut);
			this.timeOut = setTimeout($.context(this, 'filter'), 250);
		},

		filterPaste: function(e)
		{
			clearTimeout(this.timeOut);
			this.timeOut = setTimeout($.context(this, 'filter'), 250);
		},

		/**
		 * Filters key press events to make enter search instantly
		 *
		 * @param event e
		 */
		filterKeyPress: function(e)
		{
			if (e.keyCode == 13)
			{
				// enter - disable form submitting
				e.preventDefault();
			}
		},

		/**
		 * Instantly begins a search.
		 */
		instantSearch: function()
		{
			clearTimeout(this.timeOut);
			this.filter();
		},

		/**
		 * Filters the list of templates according to the filter and prefixmatch controls
		 *
		 * @param event e
		 */
		filter: function(e)
		{
			var val = this.$filter.data('XenForo.Prompt').val(),
				prefixMatch = this.$prefixMatch.is(':checked');

			if (this.$filter.hasClass('prompt') || val === '')
			{
				this.$groups.show();
				this.$listItems.show();
				this.applyFilter(this.FilterListItems);
				this.$listCounter.text(this.$listItems.length);
				if (this.lookUpUrl)
				{
					$('.PageNav').show();
				}

				this.removeAjaxResults();
				this.showHideNoResults(false);
				this.deleteCookie();
				return;
			}

			console.log('Filtering on \'%s\'', val);

			this.setCookie();

			if (this.lookUpUrl)
			{
				XenForo.ajax(this.lookUpUrl,
					{ _filter: { value: val, prefix: prefixMatch ? 1 : 0 } },
					$.context(this, 'filterAjax'),
					{ type: 'GET' }
				);
				return;
			}

			var $groups,
				visible = this.applyFilter(this.FilterListItems);

			this.$listCounter.text(visible);

			// hide empty groups
			this.$groups.each(function(i, group)
			{
				var $group = $(group);

				$group.show();

				if ($group.find('li.listItem:visible').length == 0)
				{
					$group.hide();
				}
			});

			this.removeAjaxResults();
			this.showHideNoResults(visible ? false : true);
		},

		removeAjaxResults: function()
		{
			if (this.$ajaxResults)
			{
				this.$ajaxResults.remove();
				delete(this.$ajaxResults);
			}
		},

		applyFilter: function(items)
		{
			var i,
				visible = 0,
				filterRegex = new RegExp(
					(this.$prefixMatch.is(':checked') ? '^' : '')
					+ '(' + XenForo.regexQuote(this.$filter.data('XenForo.Prompt').val()) + ')', 'i');

			for (i = items.length - 1; i >= 0; i--) // much faster than .each(...)
			{
				visible += items[i].filter(filterRegex);
			}

			return visible;
		},

		showHideNoResults: function(show)
		{
			var $noRes = $('#noResults');

			if (show)
			{
				if (!$noRes.length)
				{
					$noRes = $('<li id="noResults" class="listNote" style="display:none" />')
						.text(XenForo.phrases.no_items_matched_your_filter || 'No items matched your filter.');

					this.$list.append($noRes);
				}

				$noRes.xfFadeIn(XenForo.speed.normal);
			}
			else
			{
				$noRes.xfHide();
			}
		},

		filterAjax: function(ajaxData)
		{
			if (XenForo.hasResponseError(ajaxData))
			{
				return;
			}

			var $children = $('<ul />').html($.trim(ajaxData.templateHtml)).children();

			this.$groups.hide();
			this.$listItems.hide();
			if (this.lookUpUrl)
			{
				$('.PageNav').hide();
			}

			this.removeAjaxResults();

			if (!$children.length)
			{
				this.$listCounter.text(0);
				this.showHideNoResults(true);
			}
			else
			{
				this.$ajaxResults = $children;

				this.showHideNoResults(false);
				this.$list.append($children);
				$children.xfActivate();

				var $items = $children.filter('.listItem').add($children.find('.listItem')), items = [];
				$items.each(function(i, el) {
					items[i] = new XenForo.FilterListItem($(el));
				});
				this.applyFilter(items);
				this.$listCounter.text($items.length);
			}

			this.handleLast();
		},

		/**
		 * Gets the name of the filter controls cookie, in the form FilterList_{form.action}
		 *
		 * @return string
		 */
		getCookieName: function()
		{
			// TODO: use crc32 or something similar?
			return 'FilterList_' + encodeURIComponent(this.$form.attr('action')).replace(/[\.\+\/]|(%([0-9a-f]{2}|u\d+))/gi, '');
		},

		/**
		 * Sets the filter controls cookie in the form {prefixmatch 1/0},{filter value}
		 *
		 * @return string
		 */
		setCookie: function()
		{
			var value = (this.$prefixMatch.is(':checked') ? 1 : 0) + ',' + this.$filter.data('XenForo.Prompt').val();

			if (this.$filter.data('XenForo.Prompt').isEmpty())
			{
				this.deleteCookie();
			}
			else
			{
				$.setCookie(this.getCookieName(), value);
			}

			return value;
		},

		/**
		 * Gets the value of the filter controls cookie, and applies its values to the filter controls if possible
		 *
		 * @return string Raw cookie value
		 */
		getCookie: function()
		{
			var cookie = $.getCookie(this.getCookieName());

			if (cookie)
			{
				this.$prefixMatch.attr('checked', cookie.substring(0, 1) == '1' ? true : false);

				this.$filter.data('XenForo.Prompt').val(cookie.substring(2));
			}

			return cookie;
		},

		deleteCookie: function()
		{
			$.deleteCookie(this.getCookieName(), '');
		},

		/**
		 * Clears the filter input
		 *
		 * @param jQuery Event e
		 */
		clearFilter: function(e)
		{
			this.$filter.focus();

			this.$filter.data('XenForo.Prompt').val('', true);

			this.filter(e);

			return true;
		}
	};

	// *********************************************************************

	/**
	 * Controls a single FilterListItem, registering its textual content and handling the filtering
	 * Intended to be called by XenForo.FilterList
	 *
	 * @param jQuery .FilterList > .listItem
	 */
	XenForo.FilterListItem = function($item) { this.__construct($item); };
	XenForo.FilterListItem.prototype =
	{
		__construct: function($item)
		{
			this.$item = $item;
			this.$textContainer = this.$item.find('h4 em');
			this.text = this.$textContainer.text();

			this.$item.find('input.Toggler').click(function(e)
			{
				$(this).closest('h4').toggleClass('disabled', !this.checked);
			});
		},

		/**
		 * Show or hide the item based on whether its text matches the filterRegex
		 *
		 * @param regexp filterRegex
		 *
		 * @return integer 1 if matched, 0 if not
		 */
		filter: function(filterRegex)
		{
			if (this.text.match(filterRegex))
			{
				this.$textContainer.html(this.text.replace(filterRegex, '<strong>$1</strong>'));

				this.$item.css('display', 'block'); // much faster in Opera

				return 1;
			}
			else
			{
				this.$item.css('display', 'none'); // much faster in Opera

				return 0;
			}
		}
	};

	// *********************************************************************

	XenForo.RadioTablets = function($container)
	{
		$container.children('label').each(function()
		{
			var $control = $(this),
				$radio = $control.find('input:radio');

			$control.addClass('radioTablet');

			function setStatus(e)
			{
				if (e)
				{
					$radio.closest('form').submit();
				}

				$container.find('input:radio[name="' + $radio.attr('name') + '"]').each(function()
				{
					if ($(this).is(':checked'))
					{
						$(this).closest('.radioTablet').addClass('checked');
					}
					else
					{
						$(this).closest('.radioTablet').removeClass('checked');
					}
				});
			}

			$radio.hide().change(setStatus);

			setStatus();
		});
	};

	// *********************************************************************

	XenForo.Sortable = function($container)
	{
		var connectWith = $container.data('connect-with'),
			itemSelector = 'li.listItem',
			$filterList = $container.closest('.FilterList'),
			sortUrl = $filterList.data('sort-url');

		if (!sortUrl)
		{
			console.log('No FilterList or data-sort-url for sortable.');
			return;
		}

		$container.sortable(
		{
			items: itemSelector,
			connectWith: connectWith,
			forcePlaceholderSize: true

		}).bind('sortupdate', function(e)
		{
			var order = [];

			$filterList.find('[data-item-id]').each(function(i)
			{
				var $this = $(this),
					itemId = $this.data('item-id'),
					parentId = $this.parent().data('parent-id');

				if (parentId !== undefined)
				{
					order[i] = [itemId, parentId];
				}
				else
				{
					order[i] = itemId;
				}
			});
			
			// show / hide noResults items
			$filterList.find(connectWith).each(function(i, el)
			{				
				var $el = $(el);
				
				if ($el.find(itemSelector).length == 0)
				{
					$el.find('li.noResults').show();
				}
				else
				{
					$el.find('li.noResults').hide();
				}
			});

			// moving across groups can trigger this multiple times
			if ($filterList.data('sort-timer'))
			{
				clearTimeout($filterList.data('sort-timer'));
			}
			$filterList.data('sort-timer', setTimeout(function()
			{
				XenForo.ajax(
					sortUrl,
					{ order: order },
					function(e)
					{
						// ajax progress complete, execute display order update
						console.info('ajax request complete');
					}
				);
			}, 100));
		});
	};

	// *********************************************************************

	XenForo.register('.FilterList', 'XenForo.FilterList');

	XenForo.register('.RadioTablets', 'XenForo.RadioTablets');

	XenForo.register('.Sortable', 'XenForo.Sortable');

}
(jQuery, this, document);