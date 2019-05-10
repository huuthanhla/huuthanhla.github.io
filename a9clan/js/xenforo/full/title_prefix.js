/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	XenForo.TitlePrefix = function($select)
	{
		var $wrap,
			$container = $($select.data('container')),
			$textbox = $($select.data('textbox')),
			$popupControl = $('<span rel="Menu"><span class="prefixText"></span></span>').addClass('prefix noPrefix').data('css', 'prefix noPrefix'),
			$prefixMenu = null,
			$nodeControl = $($select.data('nodecontrol')),
			prefixId = 0,
			prefixCache = {};

		if ($textbox.length == 0)
		{
		//	return;
		}

		function updatePrefixSelection(prefixGroups)
		{
			var $appendItem,
				selectVal = $select.val();

			$select.find('option, optgroup').not('[value=0]').empty().remove();

			$.each(prefixGroups, function(prefixGroupId, optGroup)
			{
				prefixGroupId = optGroup.prefix_group_id;
				if (prefixGroupId != 0)
				{
					$appendItem = $('<optgroup />').attr('label', optGroup.title).appendTo($select);
				}
				else
				{
					$appendItem = $select;
				}

				$.each(optGroup.prefixes, function(prefixId, prefix)
				{
					prefixId = prefix.prefix_id;
					$('<option />').attr('value', prefixId).data('css', prefix.css).text(prefix.title).appendTo($appendItem);
				});
			});

			setPrefixMenuContents($prefixMenu);
			var selectedId = prefixId;
			setTimeout(function() { setPrefixById(selectedId); }, 0);

			$select.val(selectVal).trigger('change');
		}

		function setTextboxWidth(e)
		{
			if ($textbox.length)
			{
				var w = $wrap.innerWidth() - 10;

				$textbox.siblings().not($textbox).each(function()
				{
					w -= $(this).outerWidth(true);
				});

				if (w < 130)
				{
					$textbox.css('width', '100%');
					$wrap.addClass('blockInput');
				}
				else
				{
					$textbox.css('width', w);
					$wrap.removeClass('blockInput');
				}
			}
		}

		function setPrefix($link, preventFocus)
		{
			if ($textbox.length)
			{
				var $option = $link.data('option'), $prefixGroup;

				$link.closest('ul.PrefixMenu').find('li.PrefixOption, li.PrefixGroup').removeClass('selected');

				if ($option instanceof jQuery)
				{
					if ($option.val() != 0)
					{
						$link.closest('li.PrefixOption').addClass('selected');
					}

					$prefixGroup = $link.closest('li.PrefixGroup');
					if ($prefixGroup.length)
					{
						if ($prefixGroup.find('li.PrefixOption').not('.selected').length == 0)
						{
							$prefixGroup.addClass('selected');
						}
					}

					if ($popupControl.data('css'))
					{
						$popupControl.removeClass($popupControl.data('css'));
					}

					$popupControl
						.addClass($option.data('css'))
						.data('css', $option.data('css'))
						.find('span.prefixText').text($option.text());

					prefixId = $option.val();

					console.info('set prefix %s', prefixId);
					$select.val(prefixId).trigger('change');
				}

				setTextboxWidth();

				$select.trigger(
				{
					type: 'XFSetPrefix',
					link: $link
				});

				if (!preventFocus)
				{
					$textbox.get(0).select();
				}
			}
		}

		function setPrefixById(prefixId)
		{
			var $option = $select.find('option[value=' + prefixId + ']');

			if ($option.length < 1)
			{
				$option = $select.find('option[value=0]');
			}

			setPrefix($option.data('link'), true);
		}

		function appendPrefixOption(option, $menu)
		{
			var $option = $(option),

			$link = $('<a href="javascript:" />').data('option', $option).text($option.text()).addClass($option.data('css')).click(function(e)
			{
				setPrefix($link);
			});

			$menu.append($('<li />').addClass('PrefixOption').append($link));

			$option.data('link', $link);

			if (option.selected)
			{
				setTimeout(function() { setPrefix($link, true); }, 0);
			}
		}

		function getPrefixMenu()
		{
			if ($textbox.length)
			{
				$prefixMenu = $('<ul class="Menu PrefixMenu secondaryContent" />');

				setPrefixMenuContents($prefixMenu);

				return $('<div class="Popup PrefixPopup"></div>').append($popupControl).append($prefixMenu);
			}
		}

		function setPrefixMenuContents($prefixMenu)
		{
			if ($textbox.length)
			{
				$prefixMenu.empty();

				$select.children('optgroup').each(function(i, optgroup)
				{
					var $optgroup = $(optgroup), $group, $links;

					$group = $('<li />').addClass('PrefixGroup').appendTo($prefixMenu);

					$('<h3 />').text($optgroup.attr('label')).appendTo($group);

					$links = $('<ul />').appendTo($group);

					$optgroup.children('option').each(function(i, option)
					{
						appendPrefixOption(option, $links);
					});
				});

				$select.children('option:not([value=0])').each(function(i, option)
				{
					appendPrefixOption(option, $prefixMenu);
				});
				$select.children('option[value=0]').each(function(i, option)
				{
					appendPrefixOption(option, $prefixMenu);
				});
			}
		}

		if ($nodeControl.length && $select.data('prefixurl'))
		{
			$nodeControl.change(function(e)
			{
				var nodeId = $nodeControl.val(),
					prefixUrl = $select.data('prefixurl'),
					xhr = null;

				if (prefixCache[nodeId])
				{
					updatePrefixSelection(prefixCache[nodeId]);
					return;
				}
				else if (prefixUrl)
				{
					setTimeout(function()
					{
						if (xhr)
						{
							xhr.abort();
						}

						xhr = XenForo.ajax(prefixUrl, { node_id: nodeId }, function(ajaxData, textStatus)
						{
							xhr = null;

							if (XenForo.hasResponseError(ajaxData))
							{
								return false;
							}

							if (ajaxData.prefixGroups)
							{
								prefixCache[nodeId] = ajaxData.prefixGroups;
								updatePrefixSelection(ajaxData.prefixGroups);
							}
						});
					}, 0);
				}
			});
		}

		if ($textbox.length)
		{
			$container.hide();

			$wrap = $('<div />').addClass('textCtrlWrap').addClass($textbox.attr('class')).insertBefore($textbox).append($textbox);

			$wrap.prepend(getPrefixMenu());

			$textbox.bind(
			{
				focus: function(e) { $wrap.addClass('Focus'); },
				blur: function(e) { $wrap.removeClass('Focus'); }
			});

			$(document).bind('XenForoActivationComplete OverlayOpened TitlePrefixRecalc', setTextboxWidth);
			$(window).on('resize', setTextboxWidth);
		}
	};

	// *********************************************************************

	XenForo.register('select.TitlePrefix', 'XenForo.TitlePrefix');

}
(jQuery, this, document);