/**
 * Create the XenForo namespace
 * @package XenForo
 */
var XenForo = {};

// _XF_JS_UNCOMPRESSED_TEST_ - do not edit/remove

if (jQuery === undefined) jQuery = $ = {};
if ($.tools === undefined) console.error('jQuery Tools is not loaded.');

/**
 * Deal with Firebug not being present
 */
!function(w) { var fn, i = 0;
	if (!w.console) w.console = {};
	if (w.console.log && !w.console.debug) w.console.debug = w.console.log;
	fn = ['assert', 'clear', 'count', 'debug', 'dir', 'dirxml', 'error', 'getFirebugElement', 'group', 'groupCollapsed', 'groupEnd', 'info', 'log', 'notifyFirebug', 'profile', 'profileEnd', 'time', 'timeEnd', 'trace', 'warn'];
	for (i = 0; i < fn.length; ++i) if (!w.console[fn[i]]) w.console[fn[i]] = function() {};
}(window);

/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	var isTouchBrowser = (function() {
		var _isTouchBrowserVal;

		try
		{
			_isTouchBrowserVal = !!('ontouchstart' in window);
		}
		catch(e)
		{
			_isTouchBrowserVal = !!(navigator.userAgent.indexOf('webOS') != -1);
		}

		return function()
		{
			return _isTouchBrowserVal;
		};
	})();

	var classes = ['hasJs'];
	classes.push(isTouchBrowser() ? 'Touch' : 'NoTouch');

	var div = document.createElement('div');
	classes.push(('draggable' in div) || ('ondragstart' in div && 'ondrop' in div) ? 'HasDragDrop' : 'NoDragDrop');

	// not especially nice but...
	if (navigator.userAgent.search(/\((iPhone|iPad|iPod);/) != -1)
	{
		classes.push('iOS');
	}

	var $html = $('html');
	$html.addClass(classes.join(' ')).removeClass('NoJs');

	/**
	 * Fix IE abbr handling
	 */
	document.createElement('abbr');

	/**
	 * Detect mobile webkit
	 */
	if (/webkit.*mobile/i.test(navigator.userAgent))
	{
		XenForo._isWebkitMobile = true;
	}

	// preserve original jQuery Tools .overlay()
	jQuery.fn._jQueryToolsOverlay = jQuery.fn.overlay;

	/**
	 * Extends jQuery core
	 */
	jQuery.extend(true,
	{
		/**
		 * Sets the context of 'this' within a called function.
		 * Takes identical parameters to $.proxy, but does not
		 * enforce the one-elment-one-method merging that $.proxy
		 * does, allowing multiple objects of the same type to
		 * bind to a single element's events (for example).
		 *
		 * @param function|object Function to be called | Context for 'this', method is a property of fn
		 * @param function|string Context for 'this' | Name of method within fn to be called
		 *
		 * @return function
		 */
		context: function(fn, context)
		{
			if (typeof context == 'string')
			{
				var _context = fn;
				fn = fn[context];
				context = _context;
			}

			return function() { return fn.apply(context, arguments); };
		},

		/**
		 * Sets a cookie.
		 *
		 * @param string cookie name (escaped)
		 * @param mixed cookie value
		 * @param string cookie expiry date
		 *
		 * @return mixed cookie value
		 */
		setCookie: function(name, value, expires)
		{
			console.log('Set cookie %s = %s', name, value);

			document.cookie = XenForo._cookieConfig.prefix + name + '=' + encodeURIComponent(value)
				+ (expires === undefined ? '' : ';expires=' + expires.toGMTString())
				+ (XenForo._cookieConfig.path  ? ';path=' + XenForo._cookieConfig.path : '')
				+ (XenForo._cookieConfig.domain ? ';domain=' + XenForo._cookieConfig.domain : '');

			return value;
		},

		/**
		 * Fetches the value of a named cookie.
		 *
		 * @param string Cookie name (escaped)
		 *
		 * @return string Cookie value
		 */
		getCookie: function(name)
		{
			var expr, cookie;

			expr = new RegExp('(^| )' + XenForo._cookieConfig.prefix + name + '=([^;]+)(;|$)');
			cookie = expr.exec(document.cookie);

			if (cookie)
			{
				return decodeURIComponent(cookie[2]);
			}
			else
			{
				return null;
			}
		},

		/**
		 * Deletes a cookie.
		 *
		 * @param string Cookie name (escaped)
		 *
		 * @return null
		 */
		deleteCookie: function(name)
		{
			console.info('Delete cookie %s', name);

			document.cookie = XenForo._cookieConfig.prefix + name + '='
				+ (XenForo._cookieConfig.path  ? '; path=' + XenForo._cookieConfig.path : '')
				+ (XenForo._cookieConfig.domain ? '; domain=' + XenForo._cookieConfig.domain : '')
				+ '; expires=Thu, 01-Jan-70 00:00:01 GMT';

			return null;
		}
	});

	/**
	 * Extends jQuery functions
	 */
	jQuery.fn.extend(
	{
		/**
		 * Wrapper for XenForo.activate, for 'this' element
		 *
		 * @return jQuery
		 */
		xfActivate: function()
		{
			return XenForo.activate(this);
		},

		/**
		 * Retuns .data(key) for this element, or the default value if there is no data
		 *
		 * @param string key
		 * @param mixed defaultValue
		 *
		 * @return mixed
		 */
		dataOrDefault: function(key, defaultValue)
		{
			var value = this.data(key);

			if (value === undefined)
			{
				return defaultValue;
			}

			return value;
		},

		/**
		 * Like .val() but also trims trailing whitespace
		 */
		strval: function()
		{
			return String(this.val()).replace(/\s+$/g, '');
		},

		/**
		 * Get the 'name' attribute of an element, or if it exists, the value of 'data-fieldName'
		 *
		 * @return string
		 */
		fieldName: function()
		{
			return this.data('fieldname') || this.attr('name');
		},

		/**
		 * Get the value that would be submitted with 'this' element's name on form submit
		 *
		 * @return string
		 */
		fieldValue: function()
		{
			switch (this.attr('type'))
			{
				case 'checkbox':
				{
					return $('input:checkbox[name="' + this.fieldName() + '"]:checked', this.context.form).val();
				}

				case 'radio':
				{
					return $('input:radio[name="' + this.fieldName() + '"]:checked', this.context.form).val();
				}

				default:
				{
					return this.val();
				}
			}
		},

		_jqSerialize : $.fn.serialize,

		/**
		 * Overridden jQuery serialize method to ensure that RTE areas are serialized properly.
		 */
		serialize: function()
		{
			$('textarea.BbCodeWysiwygEditor').each(function() {
				var data = $(this).data('XenForo.BbCodeWysiwygEditor');
				if (data)
				{
					data.syncEditor();
				}
			});

			return this._jqSerialize();
		},

		_jqSerializeArray : $.fn.serializeArray,

		/**
		 * Overridden jQuery serializeArray method to ensure that RTE areas are serialized properly.
		 */
		serializeArray: function()
		{
			$('textarea.BbCodeWysiwygEditor').each(function() {
				var data = $(this).data('XenForo.BbCodeWysiwygEditor');
				if (data)
				{
					data.syncEditor();
				}
			});

			return this._jqSerializeArray();
		},

		/**
		 * Returns the position and size of an element, including hidden elements.
		 *
		 * If the element is hidden, it will very quickly un-hides a display:none item,
		 * gets its offset and size, restore the element to its hidden state and returns values.
		 *
		 * @param string inner/outer/{none} Defines the jQuery size function to use
		 * @param string offset/position/{none} Defines the jQuery position function to use (default: offset)
		 *
		 * @return object Offset { left: float, top: float }
		 */
		coords: function(sizeFn, offsetFn)
		{
			var coords,
				visibility,
				display,
				widthFn,
				heightFn,
				hidden = this.is(':hidden');

			if (hidden)
			{
				visibility = this.css('visibility'),
				display = this.css('display');

				this.css(
				{
					visibility: 'hidden',
					display: 'block'
				});
			}

			switch (sizeFn)
			{
				case 'inner':
				{
					widthFn = 'innerWidth';
					heightFn = 'innerHeight';
					break;
				}
				case 'outer':
				{
					widthFn = 'outerWidth';
					heightFn = 'outerHeight';
					break;
				}
				default:
				{
					widthFn = 'width';
					heightFn = 'height';
				}
			}

			switch (offsetFn)
			{
				case 'position':
				{
					offsetFn = 'position';
					break;
				}

				default:
				{
					offsetFn = 'offset';
					break;
				}
			}

			coords = this[offsetFn]();
				coords.width = this[widthFn]();
				coords.height = this[heightFn]();

			if (hidden)
			{
				this.css(
				{
					display: display,
					visibility: visibility
				});
			}

			return coords;
		},

		/**
		 * Sets a unique id for an element, if one is not already present
		 */
		uniqueId: function()
		{
			if (!this.attr('id'))
			{
				this.attr('id', 'XenForoUniq' + XenForo._uniqueIdCounter++);
			}

			return this;
		},

		/**
		 * Wrapper functions for commonly-used animation effects, so we can customize their behaviour as required
		 */
		xfFadeIn: function(speed, callback)
		{
			return this.fadeIn(speed, function() { $(this).ieOpacityFix(callback); });
		},
		xfFadeOut: function(speed, callback)
		{
			return this.fadeOut(speed, callback);
		},
		xfShow: function(speed, callback)
		{
			return this.show(speed, function() { $(this).ieOpacityFix(callback); });
		},
		xfHide: function(speed, callback)
		{
			return this.hide(speed, callback);
		},
		xfSlideDown: function(speed, callback)
		{
			return this.slideDown(speed, function() { $(this).ieOpacityFix(callback); });
		},
		xfSlideUp: function(speed, callback)
		{
			return this.slideUp(speed, callback);
		},

		/**
		 * Animates an element opening a space for itself, then fading into that space
		 *
		 * @param integer|string Speed of fade-in
		 * @param function Callback function on completion
		 *
		 * @return jQuery
		 */
		xfFadeDown: function(fadeSpeed, callback)
		{
			this.filter(':hidden').xfHide().css('opacity', 0);

			fadeSpeed = fadeSpeed || XenForo.speed.normal;

			return this
				.xfSlideDown(XenForo.speed.fast)
				.animate({ opacity: 1 }, fadeSpeed, function()
				{
					$(this).ieOpacityFix(callback);
				});
		},

		/**
		 * Animates an element fading out then closing the gap left behind
		 *
		 * @param integer|string Speed of fade-out - if this is zero, there will be no animation at all
		 * @param function Callback function on completion
		 * @param integer|string Slide speed - ignored if fadeSpeed is zero
		 * @param string Easing method
		 *
		 * @return jQuery
		 */
		xfFadeUp: function(fadeSpeed, callback, slideSpeed, easingMethod)
		{
			fadeSpeed = ((typeof fadeSpeed == 'undefined' || fadeSpeed === null) ? XenForo.speed.normal : fadeSpeed);
			slideSpeed = ((typeof slideSpeed == 'undefined' || slideSpeed === null) ? fadeSpeed : slideSpeed);

			return this
				.slideUp({
					duration: Math.max(fadeSpeed, slideSpeed),
					easing: easingMethod || 'swing',
					complete: callback,
					queue: false
				})
				.animate({ opacity: 0, queue: false }, fadeSpeed);
		},

		/**
		 * Inserts and activates content into the DOM, using xfFadeDown to animate the insertion
		 *
		 * @param string jQuery method with which to insert the content
		 * @param string Selector for the previous parameter
		 * @param string jQuery method with which to animate the showing of the content
		 * @param string|integer Speed at which to run the animation
		 * @param function Callback for when the animation is complete
		 *
		 * @return jQuery
		 */
		xfInsert: function(insertMethod, insertReference, animateMethod, animateSpeed, callback)
		{
			if (insertMethod == 'replaceAll')
			{
				$(insertReference).xfFadeUp(animateSpeed);
			}

			this
				.addClass('__XenForoActivator')
				.css('display', 'none')
				[insertMethod || 'appendTo'](insertReference)
				.xfActivate()
				[animateMethod || 'xfFadeDown'](animateSpeed, callback);

			return this;
		},

		/**
		 * Removes an element from the DOM, animating its removal with xfFadeUp
		 * All parameters are optional.
		 *
		 *  @param string animation method
		 *  @param function callback function
		 *  @param integer Sliding speed
		 *  @param string Easing method
		 *
		 * @return jQuery
		 */
		xfRemove: function(animateMethod, callback, slideSpeed, easingMethod)
		{
			return this[animateMethod || 'xfFadeUp'](XenForo.speed.normal, function()
			{
				$(this).empty().remove();

				if ($.isFunction(callback))
				{
					callback();
				}
			}, slideSpeed, easingMethod);
		},

		/**
		 * Prepares an element for xfSlideIn() / xfSlideOut()
		 *
		 * @param boolean If true, return the height of the wrapper
		 *
		 * @return jQuery|integer
		 */
		_xfSlideWrapper: function(getHeight)
		{
			if (!this.data('slidewrapper'))
			{
				this.data('slidewrapper', this.wrap('<div class="_swOuter"><div class="_swInner" /></div>')
					.closest('div._swOuter').css('overflow', 'hidden'));
			}

			if (getHeight)
			{
				try
				{
					return this.data('slidewrapper').height();
				}
				catch (e)
				{
					// so IE11 seems to be randomly throwing an exception in jQuery here, so catch it
					return 0;
				}
			}

			return this.data('slidewrapper');
		},

		/**
		 * Slides content in (down), with content glued to lower edge, drawer-like
		 *
		 * @param duration
		 * @param easing
		 * @param callback
		 *
		 * @return jQuery
		 */
		xfSlideIn: function(duration, easing, callback)
		{
			var $wrap = this._xfSlideWrapper().css('height', 'auto'),
				height = 0;

			$wrap.find('div._swInner').css('margin', 'auto');
			height = this.show(0).outerHeight();

			$wrap
				.css('height', 0)
				.animate({ height: height }, duration, easing, function() {
					$wrap.css('height', '');
				})
			.find('div._swInner')
				.css('marginTop', height * -1)
				.animate({ marginTop: 0 }, duration, easing, callback);

			return this;
		},

		/**
		 * Slides content out (up), reversing xfSlideIn()
		 *
		 * @param duration
		 * @param easing
		 * @param callback
		 *
		 * @return jQuery
		 */
		xfSlideOut: function(duration, easing, callback)
		{
			var height = this.outerHeight();

			this._xfSlideWrapper()
				.animate({ height: 0 }, duration, easing)
			.find('div._swInner')
				.animate({ marginTop: height * -1 }, duration, easing, callback);

			return this;
		},

		/**
		 * Workaround for IE's font-antialiasing bug when dealing with opacity
		 *
		 * @param function Callback
		 */
		ieOpacityFix: function(callback)
		{
			//ClearType Fix
			if (!$.support.opacity)
			{
				this.css('filter', '');
				this.attr('style', this.attr('style').replace(/filter:\s*;/i, ''));
			}

			if ($.isFunction(callback))
			{
				callback.apply(this);
			}

			return this;
		},

		/**
		 * Wraps around jQuery Tools .overlay().
		 *
		 * Prepares overlay options before firing overlay() for best possible experience.
		 * For example, removes fancy (slow) stuff from options for touch browsers.
		 *
		 * @param options
		 *
		 * @returns jQuery
		 */
		overlay: function(options)
		{
			if (XenForo.isTouchBrowser())
			{
				return this._jQueryToolsOverlay($.extend(true, options,
				{
					//mask: false,
					speed: 0,
					loadSpeed: 0
				}));
			}
			else
			{
				return this._jQueryToolsOverlay(options);
			}
		}
	});

	/* jQuery Tools Extensions */

	/**
	 * Effect method for jQuery.tools overlay.
	 * Slides down a container, then fades up the content.
	 * Closes by reversing the animation.
	 */
	$.tools.overlay.addEffect('slideDownContentFade',
		function(position, callback)
		{
			var $overlay = this.getOverlay(),
				conf = this.getConf();

			$overlay.find('.content').css('opacity', 0);

			if (this.getConf().fixed)
			{
				position.position = 'fixed';
			}
			else
			{
				position.position = 'absolute';
				position.top += $(window).scrollTop();
				position.left += $(window).scrollLeft();
			}

			$overlay.css(position).xfSlideDown(XenForo.speed.fast, function()
			{
				$overlay.find('.content').animate({ opacity: 1 }, conf.speed, function() { $(this).ieOpacityFix(callback); });
			});
		},
		function(callback)
		{
			var $overlay = this.getOverlay();

			$overlay.find('.content').animate({ opacity: 0 }, this.getConf().speed, function()
			{
				$overlay.xfSlideUp(XenForo.speed.fast, callback);
			});
		}
	);

	$.tools.overlay.addEffect('slideDown',
		function(position, callback)
		{
			if (this.getConf().fixed)
			{
				position.position = 'fixed';
			}
			else
			{
				position.position = 'absolute';
				position.top += $(window).scrollTop();
				position.left += $(window).scrollLeft();
			}

			this.getOverlay()
				.css(position)
				.xfSlideDown(this.getConf().speed, callback);
		},
		function(callback)
		{
			this.getOverlay().hide(0, callback);
		}
	);

	// *********************************************************************

	$.extend(XenForo,
	{
		/**
		 * Cache for overlays
		 *
		 * @var object
		 */
		_OverlayCache: {},

		/**
		 * Defines whether or not an AJAX request is known to be in progress
		 *
		 *  @var boolean
		 */
		_AjaxProgress: false,

		/**
		 * Defines a variable that can be overridden to force/control the base HREF
		 * used to canonicalize AJAX requests
		 *
		 * @var string
		 */
		ajaxBaseHref: '',

		/**
		 * Counter for unique ID generation
		 *
		 * @var integer
		 */
		_uniqueIdCounter: 0,

		/**
		 * Configuration for overlays, should be redefined in the PAGE_CONTAINER template HTML
		 *
		 * @var object
		 */
		_overlayConfig: {},

		/**
		 * Contains the URLs of all externally loaded resources from scriptLoader
		 *
		 * @var object
		 */
		_loadedScripts: {},

		/**
		 * Configuration for cookies
		 *
		 * @var object
		 */
		_cookieConfig: { path: '/', domain: '', 'prefix': 'xf_'},

		/**
		 * Flag showing whether or not the browser window has focus. On load, assume true.
		 *
		 * @var boolean
		 */
		_hasFocus: true,

		/**
		 * @var object List of server-related time info (now, today, todayDow)
		 */
		serverTimeInfo: {},

		/**
		 * @var object Information about the XenForo visitor. Usually contains user_id.
		 */
		visitor: {},

		/**
		 * @var integer Time the page was loaded.
		 */
		_pageLoadTime: (new Date()).getTime() / 1000,

		/**
		 * JS version key, to force refreshes when needed
		 *
		 * @var string
		 */
		_jsVersion: '',

		/**
		 * CSRF Token
		 *
		 * @var string
		 */
		_csrfToken: '',

		/**
		 * URL to CSRF token refresh.
		 *
		 * @var string
		 */
		_csrfRefreshUrl: '',

		/**
		 * Speeds for animation
		 *
		 * @var object
		 */
		speed:
		{
			xxfast: 50,
			xfast: 100,
			fast: 200,
			normal: 400,
			slow: 600
		},

		/**
		 * Multiplier for animation speeds
		 *
		 * @var float
		 */
		_animationSpeedMultiplier: 1,

		/**
		 * Enable overlays or use regular pages
		 *
		 * @var boolean
		 */
		_enableOverlays: true,

		/**
		 * Enables AJAX submission via AutoValidator. Doesn't change things other than
		 * that. Useful to disable for debugging.
		 *
		 * @var boolean
		 */
		_enableAjaxSubmit: true,

		/**
		 * Determines whether the lightbox shows all images from the current page,
		 * or just from an individual message
		 *
		 * @var boolean
		 */
		_lightBoxUniversal: false,

		/**
		 * @var object Phrases
		 */
		phrases: {},

		/**
		 * Binds all registered functions to elements within the DOM
		 */
		init: function()
		{
			var dStart = new Date(),
				xfFocus = function()
				{
					XenForo._hasFocus = true;
					$(document).triggerHandler('XenForoWindowFocus');
				},
				xfBlur = function()
				{
					XenForo._hasFocus = false;
					$(document).triggerHandler('XenForoWindowBlur');
				},
				$html = $('html');

			if ($.browser.msie)
			{
				$(document).bind(
				{
					focusin:  xfFocus,
					focusout: xfBlur
				});
			}
			else
			{
				$(window).bind(
				{
					focus: xfFocus,
					blur:  xfBlur
				});
			}

			$(window).on('resize', function() {
				XenForo.checkQuoteSizing($(document));
			});

			// Set the animation speed based around the style property speed multiplier
			XenForo.setAnimationSpeed(XenForo._animationSpeedMultiplier);

			// Periodical timestamp refresh
			XenForo._TimestampRefresh = new XenForo.TimestampRefresh();

			// Find any ignored content that has not been picked up by PHP
			XenForo.prepareIgnoredContent();

			// init ajax progress indicators
			XenForo.AjaxProgress();

			// Activate all registered controls
			XenForo.activate(document);

			$(document).on('click', '.bbCodeQuote .quoteContainer .quoteExpand', function(e) {
				$(this).closest('.quoteContainer').toggleClass('expanded');
			});

			XenForo.watchProxyLinks();

			// make the breadcrumb and navigation responsive
			if (!$html.hasClass('NoResponsive'))
			{
				XenForo.updateVisibleBreadcrumbs();
				XenForo.updateVisibleNavigationTabs();
				XenForo.updateVisibleNavigationLinks();

				var resizeTimer, htmlWidth = $html.width();
				$(window).on('resize orientationchange load', function(e) {
					if (resizeTimer)
					{
						return;
					}
					if (e.type != 'load' && $html.width() == htmlWidth)
					{
						return;
					}
					htmlWidth = $html.width();
					resizeTimer = setTimeout(function() {
						resizeTimer = 0;
						XenForo.updateVisibleBreadcrumbs();
						XenForo.updateVisibleNavigationTabs();
						XenForo.updateVisibleNavigationLinks();
					}, 20);
				});
				$(document).on('click', '.breadcrumb .placeholder', function() {
					$(this).closest('.breadcrumb').addClass('showAll');
					XenForo.updateVisibleBreadcrumbs();
				});
			}

			// Periodical CSRF token refresh
			XenForo._CsrfRefresh = new XenForo.CsrfRefresh();

			// Autofocus for non-supporting browsers
			if (!('autofocus' in document.createElement('input')))
			{
				//TODO: work out a way to prevent focusing if something else already has focus http://www.w3.org/TR/html5/forms.html#attr-fe-autofocus
				$('input[autofocus], textarea[autofocus], select[autofocus]').first().focus();
			}


			// init Tweet buttons
			XenForo.tweetButtonInit();

			console.info('XenForo.init() %dms. jQuery %s/%s', new Date() - dStart, $().jquery, $.tools.version);

			if ($('#ManualDeferredTrigger').length)
			{
				setTimeout(XenForo.manualDeferredHandler, 100);
			}

			if ($('html.RunDeferred').length)
			{
				setTimeout(XenForo.runAutoDeferred, 100);
			}
		},

		runAutoDeferred: function() {
			XenForo.ajax('deferred.php', {}, function(ajaxData) {
				if (ajaxData && ajaxData.moreDeferred)
				{
					setTimeout(XenForo.runAutoDeferred, 100);
				}
			}, { error: false, global: false });
		},

		prepareIgnoredContent: function()
		{
			var $displayLink = $('a.DisplayIgnoredContent'),
				namesObj = {},
				namesArr = [];

			if ($displayLink.length)
			{
				$('.ignored').each(function()
				{
					var name = $(this).data('author');
					if (name)
					{
						namesObj[name] = true;
					}
				});

				$.each(namesObj, function(name)
				{
					namesArr.push(name);
				});

				if (namesArr.length)
				{
					$displayLink.attr('title', XenForo.phrases['show_hidden_content_by_x'].replace(/\{names\}/, namesArr.join(', ')));
					$displayLink.parent().show();
				}
			}
		},

		watchProxyLinks: function()
		{
			var unproxyLink = function($link)
			{
				if ($link.data('proxied') && $link.data('proxy-orig'))
				{
					$link.attr('href', $link.data('proxy-orig'));
					$link.data('proxied', false);
				}
			};

			$(document).on('mousedown click', 'a.ProxyLink', function(e)
			{
				var $this = $(this);
				if (!$this.data('proxied') && $this.data('proxy-href'))
				{
					$this.data('proxy-orig', $this.attr('href'));
					$this.attr('href', $this.data('proxy-href'));
					$this.data('proxied', true);

					if (e.type == 'click')
					{
						setTimeout(function() {
							unproxyLink($this);
						}, 100);
					}
				}
			}).on('mouseup contextmenu', 'a.ProxyLink', function(e)
			{
				var $this = $(this);

				if (e.type == 'mouseup')
				{
					setTimeout(function() {
						unproxyLink($this);
					}, 100);
				}
				else
				{
					unproxyLink($this);
				}
			});
		},

		/**
		 * Asynchronously load the specified JavaScript, with an optional callback on completion.
		 *
		 * @param string Script source
		 * @param object Callback function
		 * @param string innerHtml for the script tags
		 */
		loadJs: function(src, callback, innerHTML)
		{
			try
			{
				var script = document.createElement('script');
				script.async = true;
				if (innerHTML)
				{
					try
					{
						script.innerHTML = innerHTML;
					}
					catch(e2) {}
				}
				var f = function()
				{
					if (callback)
					{
						callback();
						callback = null;
					}
				};
				script.onload = f;
				script.onreadystatechange = function()
				{
					if (script.readyState === 'loaded')
					{
						f();
					}
				};
				script.src = src;
				document.getElementsByTagName('head')[0].appendChild(script);
			}
			catch(e) {}
		},

		/**
		 * Asynchronously load the Twitter button JavaScript.
		 */
		tweetButtonInit: function()
		{
			if ($('a.twitter-share-button').length)
			{
				XenForo.loadJs('https://platform.twitter.com/widgets.js');
			}
		},

		/**
		 * Asynchronously load the +1 button JavaScript.
		 */
		plusoneButtonInit: function(el)
		{
			if ($(el).find('div.g-plusone, .GoogleLogin').length)
			{
				var locale = $('html').attr('lang');

				var callback = function()
				{
					$(el).find('.GoogleLogin').each(function() {
						var $button = $(this),
							clientId = $button.data('client-id');

						gapi.signin.render(this, {
							callback: function(result) {
								if (result.status.method == 'AUTO')
								{
									// this is an auto triggered login which is doesn't really fit
									// and can cause some bad behavior, so disable it
									return;
								}
								if (result.code)
								{
									window.location = XenForo.canonicalizeUrl(
										$button.data('redirect-url').replace('__CODE__', result.code)
									);
								}
							},
							clientid: clientId,
							cookiepolicy: 'single_host_origin',
							accesstype: 'offline',
							immediate: false,
							requestvisibleactions: 'http://schemas.google.com/AddActivity',
							scope: 'https://www.googleapis.com/auth/plus.login email'
						});
					});
				};

				if (window.___gcfg)
				{
					callback();
				}
				else
				{
					window.___gcfg = {
						lang: locale,
						isSignedOut: true // this is to stop the "welcome back" prompt as it doesn't fit with our flow
					};

					XenForo.loadJs('https://plus.google.com/js/client:plusone.js', callback);
				}
			}
		},

		/**
		 * Prevents Google Chrome's AutoFill from turning inputs yellow.
		 * Adapted from http://www.benjaminmiles.com/2010/11/22/fixing-google-chromes-yellow-autocomplete-styles-with-jquery/
		 */
		chromeAutoFillFix: function($root)
		{
			if ($.browser.webkit && navigator.userAgent.toLowerCase().indexOf('chrome') >= 0)
			{
				if (!$root)
				{
					$root = $(document);
				}

				// trap an error here - CloudFlare RocketLoader causes an error with this.
				var $inputs;
				try
				{
					$inputs = $root.find('input:-webkit-autofill');
				}
				catch (e)
				{
					$inputs = $([]);
				}

				if ($inputs.length)
				{
					$inputs.each(function(i)
					{
						var $this = $(this),
							val = $this.val();

						if (!val || !val.length)
						{
							return;
						}

						$this.after($this.clone(true).val(val)).remove();
					});
				}
			}
		},

		updateVisibleBreadcrumbs: function()
		{
			$('.breadcrumb').each(function() {
				var container = this,
					$container = $(container);

				$container.find('.placeholder').remove();

				var $crusts = $container.find('.crust');
				$crusts.removeClass('firstVisibleCrumb').show();

				var $homeCrumb = $crusts.filter('.homeCrumb');

				$container.css('height', '');
				var beforeHeight = container.offsetHeight;
				$container.css('height', 'auto');

				if (container.offsetHeight <= beforeHeight)
				{
					$container.css('height', '');
					return;
				}

				var $lastHidden = null,
					hideSkipSelector = '.selectedTabCrumb, :last-child';

				$crusts.each(function() {
					var $crust = $(this);
					if ($crust.is(hideSkipSelector))
					{
						return true;
					}

					$crust.hide();
					$lastHidden = $crust;
					return (container.offsetHeight > beforeHeight);
				});

				if (!$lastHidden)
				{
					$container.css('height', '');
					return;
				}

				var $placeholder = $('<span class="crust placeholder"><a class="crumb" href="javascript:"><span>...</span></a><span class="arrow"><span>&gt;</span></span></span>');
				$lastHidden.after($placeholder);

				if (container.offsetHeight > beforeHeight)
				{
					var $prev = $lastHidden.prevAll('.crust:not(' + hideSkipSelector + ')').last();
					if ($prev.length)
					{
						$prev.hide();
					}
				}

				if (container.offsetHeight > beforeHeight)
				{
					var $next = $lastHidden.nextAll('.crust:not(.placeholder, ' + hideSkipSelector + ')').first();
					if ($next.length)
					{
						$next.hide();
						$next.after($placeholder);
					}
				}

				if ($homeCrumb.length && !$homeCrumb.is(':visible'))
				{
					$container.find('.crust:visible:first').addClass('firstVisibleCrumb');
				}

				if (container.offsetHeight <= beforeHeight)
				{
					// firefox doesn't seem to contain the breadcrumbs despite the overflow hidden
					$container.css('height', '');
				}
			});
		},

		updateVisibleNavigationTabs: function()
		{
			var $tabs = $('#navigation').find('.navTabs');
			if (!$tabs.length)
			{
				return;
			}

			var	tabsCoords = $tabs.coords(),
				$publicTabs = $tabs.find('.publicTabs'),
				$publicInnerTabs = $publicTabs.find('> .navTab'),
				$visitorTabs = $tabs.find('.visitorTabs'),
				$visitorInnerTabs = $visitorTabs.find('> .navTab'),
				$visitorCounter = $('#VisitorExtraMenu_Counter'),
				maxPublicWidth,
				$hiddenTab = $publicInnerTabs.filter('.navigationHiddenTabs');

			$publicInnerTabs.show();
			$hiddenTab.hide();

			$visitorInnerTabs.show();
			$visitorCounter.addClass('ResponsiveOnly');

			if ($tabs.is('.showAll'))
			{
				return;
			}

			maxPublicWidth = $tabs.width() - $visitorTabs.width() - 1;

			var hidePublicTabs = function()
				{
					var shownSel = '.selected, .navigationHiddenTabs';

					var $hideable = $publicInnerTabs.filter(':not(' + shownSel + ')'),
						$hiddenList = $('<ul />'),
						hiddenCount = 0,
						overflowMenuShown = false;

					$.each($hideable.get().reverse(), function()
					{
						var $this = $(this);
						if (isOverflowing($publicTabs.coords(), true))
						{
							$hiddenList.prepend(
								$('<li />').html($this.find('.navLink').clone())
							);
							$this.hide();
							hiddenCount++;
						}
						else
						{
							if (hiddenCount)
							{
								$hiddenTab.show();

								if (isOverflowing($publicTabs.coords(), true))
								{
									$hiddenList.prepend(
										$('<li />').html($this.find('.navLink').clone())
									);
									$this.hide();
									hiddenCount++;
								}
								$('#NavigationHiddenMenu').html($hiddenList).xfActivate();
								overflowMenuShown = true;
							}
							else
							{
								$hiddenTab.hide();
							}

							return false;
						}
					});

					if (hiddenCount && !overflowMenuShown)
					{
						$hiddenTab.show();
						$('#NavigationHiddenMenu').html($hiddenList).xfActivate();
					}
				},
				hideVisitorTabs = function() {
					$visitorInnerTabs.hide();
					$visitorInnerTabs.filter('.account, .selected').show();
					$visitorCounter.removeClass('ResponsiveOnly');
				},
				isOverflowing = function(coords, checkMax) {
					if (
						coords.top >= tabsCoords.top + tabsCoords.height
						|| coords.height >= tabsCoords.height * 2
					)
					{
						return true;
					}

					if (checkMax && coords.width > maxPublicWidth)
					{
						return true;
					}

					return false;
				};

			if ($visitorTabs.length)
			{
				if (isOverflowing($visitorTabs.coords()))
				{
					hidePublicTabs();

					if (isOverflowing($visitorTabs.coords()))
					{
						hideVisitorTabs();
					}
				}
			}
			else if (isOverflowing($publicTabs.coords()))
			{
				hidePublicTabs();
			}
		},

		updateVisibleNavigationLinks: function()
		{
			var $linksList = $('#navigation').find('.navTab.selected .blockLinksList');
			if (!$linksList.length)
			{
				return;
			}

			var	$links = $linksList.find('> li'),
				listOffset = $linksList.offset(),
				$hidden = $links.filter('.navigationHidden'),
				$firstHidden = false;

			$links.show();
			$hidden.hide();

			if ($linksList.is('.showAll'))
			{
				return;
			}

			var hiddenForMenu = [],
				$lastLink = $links.filter(':not(.navigationHidden)').last(),
				hideOffset = 0,
				hasHidden = false,
				lastCoords,
				$link;

			if (!$lastLink.length)
			{
				return;
			}

			do
			{
				lastCoords = $lastLink.coords();
				if (lastCoords.top > listOffset.top + lastCoords.height)
				{
					$link = $links.eq(hideOffset);
					$link.hide();
					hiddenForMenu.push($link);
					hideOffset++;

					if (!hasHidden)
					{
						hasHidden = true;

						if (!$hidden.length)
						{
							$hidden = $('<li class="navigationHidden Popup PopupControl PopupClosed"><a rel="Menu" class="NoPopupGadget">...</a><div class="Menu blockLinksList primaryContent" id="NavigationLinksHiddenMenu"></div></li>');
							$linksList.append($hidden);
							new XenForo.PopupMenu($hidden);
						}
						else
						{
							$hidden.show();
						}
					}
				}
				else
				{
					break;
				}
			}
			while (hideOffset < $links.length);

			if (hasHidden)
			{
				if (hideOffset < $links.length)
				{
					var coords = $hidden.coords();
					if (coords.top > listOffset.top + coords.height)
					{
						$link = $links.eq(hideOffset);
						$link.hide();
						hiddenForMenu.push($link);
					}
				}

				var $hiddenList = $('<ul />');
				$(hiddenForMenu).each(function() {
					$hiddenList.append(
						$('<li />').html($(this).find('a').clone())
					);
				});
				$('#NavigationLinksHiddenMenu').html($hiddenList).xfActivate();
			}
		},

		/**
		 * Binds a function to elements to fire on a custom event
		 *
		 * @param string jQuery selector - to get the elements to be bound
		 * @param function Function to fire
		 * @param string Custom event name (if empty, assume 'XenForoActivateHtml')
		 */
		register: function(selector, fn, event)
		{
			if (typeof fn == 'string')
			{
				var className = fn;
				fn = function(i)
				{
					XenForo.create(className, this);
				};
			}

			$(document).bind(event || 'XenForoActivateHtml', function(e)
			{
				$(e.element).find(selector).each(fn);
			});
		},

		/**
		 * Creates a new object of class XenForo.{functionName} using
		 * the specified element, unless one has already been created.
		 *
		 * @param string Function name (property of XenForo)
		 * @param object HTML element
		 *
		 * @return object XenForo[functionName]($(element))
		 */
		create: function(className, element)
		{
			var $element = $(element),
				xfObj = window,
				parts = className.split('.'), i;

			for (i = 0; i < parts.length; i++) { xfObj = xfObj[parts[i]]; }

			if (typeof xfObj != 'function')
			{
				return console.error('%s is not a function.', className);
			}

			if (!$element.data(className))
			{
				$element.data(className, new xfObj($element));
			}

			return $element.data(className);
		},

		/**
		 * Fire the initialization events and activate functions for the specified element
		 *
		 * @param object Usually jQuery
		 *
		 * @return object
		 */
		activate: function(element)
		{
			var $element = $(element);

			console.group('XenForo.activate(%o)', element);

			$element.trigger('XenForoActivate').removeClass('__XenForoActivator');
			$element.find('noscript').empty().remove();

			XenForo._TimestampRefresh.refresh(element, true);

			$(document)
				.trigger({ element: element, type: 'XenForoActivateHtml' })
				.trigger({ element: element, type: 'XenForoActivatePopups' })
				.trigger({ element: element, type: 'XenForoActivationComplete' });

			var $form = $element.find('form.AutoSubmit:first');
			if ($form.length)
			{
				$(document).trigger('PseudoAjaxStart');
				$form.submit();
				$form.find('input[type="submit"], input[type="reset"]').hide();
			}

			XenForo.checkQuoteSizing($element);
			XenForo.plusoneButtonInit(element);
			XenForo.Facebook.start();

			console.groupEnd();

			return element;
		},

		checkQuoteSizing: function($element)
		{
			$element.find('.bbCodeQuote .quoteContainer').each(function() {
				var self = this,
					delay = 0,
					checkHeight = function() {
						var $self = $(self),
							quote = $self.find('.quote')[0];

						if (!quote)
						{
							return;
						}

						if (quote.scrollHeight == 0 || quote.offsetHeight == 0)
						{
							if (delay < 2000)
							{
								setTimeout(checkHeight, delay);
								delay += 100;
							}
							return;
						}

						// +1 resolves a chrome rounding issue
						if (quote.scrollHeight > quote.offsetHeight + 1)
						{
							$self.find('.quoteExpand').addClass('quoteCut');
						}
						else
						{
							$self.find('.quoteExpand').removeClass('quoteCut');
						}
					};

				checkHeight();
				$(this).find('img').one('load', checkHeight);
				$(this).on('elementResized', checkHeight);
			});
		},

		/**
		 * Pushes an additional parameter onto the data to be submitted via AJAX
		 *
		 * @param array|string Data parameters - either from .serializeArray() or .serialize()
		 * @param string Name of parameter
		 * @param mixed Value of parameter
		 *
		 * @return array|string Data including new parameter
		 */
		ajaxDataPush: function(data, name, value)
		{
			if (!data || typeof data == 'string')
			{
				// data is empty, or a url string - &name=value
				data = String(data);
				data += '&' + encodeURIComponent(name) + '=' + encodeURIComponent(value);
			}
			else if (data[0] !== undefined)
			{
				// data is a numerically-keyed array of name/value pairs
				data.push({ name: name, value: value });
			}
			else
			{
				// data is an object with a single set of name & value properties
				data[name] = value;
			}

			return data;
		},

		/**
		 * Wraps around jQuery's own $.ajax function, with our own defaults provided.
		 * Will submit via POST and expect JSON back by default.
		 * Server errors will be handled using XenForo.handleServerError
		 *
		 * @param string URL to load
		 * @param object Data to pass
		 * @param function Success callback function
		 * @param object Additional options to override or extend defaults
		 *
		 * @return XMLHttpRequest
		 */
		ajax: function(url, data, success, options)
		{
			if (!url)
			{
				return console.error('No URL specified for XenForo.ajax()');
			}

			url = XenForo.canonicalizeUrl(url, XenForo.ajaxBaseHref);

			data = XenForo.ajaxDataPush(data, '_xfRequestUri', window.location.pathname + window.location.search);
			data = XenForo.ajaxDataPush(data, '_xfNoRedirect', 1);
			if (XenForo._csrfToken)
			{
				data = XenForo.ajaxDataPush(data, '_xfToken', XenForo._csrfToken);
			}

			var successCallback = function(ajaxData, textStatus)
			{
				if (typeof ajaxData == 'object')
				{
					if (typeof ajaxData._visitor_conversationsUnread != 'undefined')
					{
						XenForo.balloonCounterUpdate($('#ConversationsMenu_Counter'), ajaxData._visitor_conversationsUnread);
						XenForo.balloonCounterUpdate($('#AlertsMenu_Counter'), ajaxData._visitor_alertsUnread);
						XenForo.balloonCounterUpdate($('#VisitorExtraMenu_ConversationsCounter'), ajaxData._visitor_conversationsUnread);
						XenForo.balloonCounterUpdate($('#VisitorExtraMenu_AlertsCounter'), ajaxData._visitor_alertsUnread);
						XenForo.balloonCounterUpdate($('#VisitorExtraMenu_Counter'),
							(
								parseInt(ajaxData._visitor_conversationsUnread, 10) + parseInt(ajaxData._visitor_alertsUnread, 10)
								|| 0
							).toString()
						);
					}

					if (ajaxData._manualDeferred)
					{
						XenForo.manualDeferredHandler();
					}
					else if (ajaxData._autoDeferred)
					{
						XenForo.runAutoDeferred();
					}
				}

				$(document).trigger(
				{
					type: 'XFAjaxSuccess',
					ajaxData: ajaxData,
					textStatus: textStatus
				});

				success.call(null, ajaxData, textStatus);
			};

			var referrer = window.location.href;
			if (referrer.match(/[^\x20-\x7f]/))
			{
				var a = document.createElement('a');
				a.href = '';
				referrer = referrer.replace(a.href, XenForo.baseUrl());
			}

			options = $.extend(true,
			{
				data: data,
				url: url,
				success: successCallback,
				type: 'POST',
				dataType: 'json',
				error: function(xhr, textStatus, errorThrown)
				{
					if (xhr.readyState == 0)
					{
						return;
					}

					try
					{
						// attempt to pass off to success, if we can decode JSON from the response
						successCallback.call(null, $.parseJSON(xhr.responseText), textStatus);
					}
					catch (e)
					{
						// not valid JSON, trigger server error handler
						XenForo.handleServerError(xhr, textStatus, errorThrown);
					}
				},
				headers: {'X-Ajax-Referer': referrer},
				timeout: 30000 // 30s
			}, options);

			// override standard extension, depending on dataType
			if (!options.data._xfResponseType)
			{
				switch (options.dataType)
				{
					case 'html':
					case 'json':
					case 'xml':
					{
						// pass _xfResponseType parameter to override default extension
						options.data = XenForo.ajaxDataPush(options.data, '_xfResponseType', options.dataType);
						break;
					}
				}
			}

			return $.ajax(options);
		},

		/**
		 * Updates the total in one of the navigation balloons, showing or hiding if necessary
		 *
		 * @param jQuery $balloon
		 * @param string counter
		 */
		balloonCounterUpdate: function($balloon, newTotal)
		{
			if ($balloon.length)
			{
				var $counter = $balloon.find('span.Total'),
					oldTotal = $counter.text();

				$counter.text(newTotal);

				if (!newTotal || newTotal == '0')
				{
					$balloon.fadeOut('fast', function() {
						$balloon.addClass('Zero').css('display', '');
					});
				}
				else
				{
					$balloon.fadeIn('fast', function()
					{
						$balloon.removeClass('Zero').css('display', '');

						var oldTotalInt = parseInt(oldTotal.replace(/[^\d]/, ''), 10),
							newTotalInt = parseInt(newTotal.replace(/[^\d]/, ''), 10),
							newDifference = newTotalInt - oldTotalInt;

						if (newDifference > 0 && $balloon.data('text'))
						{
							var $container = $balloon.closest('.Popup'),
								PopupMenu = $container.data('XenForo.PopupMenu'),
								$message;

							$message = $('<a />').css('cursor', 'pointer').html($balloon.data('text').replace(/%d/, newDifference)).click(function(e)
							{
								if ($container.is(':visible') && PopupMenu)
								{
									PopupMenu.$clicker.trigger('click');
								}
								else if ($container.find('a[href]').length)
								{
									window.location = XenForo.canonicalizeUrl($container.find('a[href]').attr('href'));
								}
								return false;
							});

							if (PopupMenu && !PopupMenu.menuVisible)
							{
								PopupMenu.resetLoader();
							}

							XenForo.stackAlert($message, 10000, $balloon);
						}
					});
				}
			}
		},

		_manualDeferUrl: '',
		_manualDeferOverlay: false,
		_manualDeferXhr: false,

		manualDeferredHandler: function()
		{
			if (!XenForo._manualDeferUrl || XenForo._manualDeferOverlay)
			{
				return;
			}

			var processing = XenForo.phrases['processing'] || 'Processing',
				cancel = XenForo.phrases['cancel'] || 'Cancel',
				cancelling = XenForo.phrases['cancelling'] || 'Cancelling';

			var $html = $('<div id="ManualDeferOverlay" class="xenOverlay"><h2 class="titleBar">'
					+ processing + '... '
					+ '<a class="CancelDeferred button" data-cancelling="' + cancelling + '..." style="display:none">' + cancel + '</a></h2>'
					+ '<span class="processingText">' + processing + '...</span><span class="close"></span></div>');

			$html.find('.CancelDeferred').click(function(e) {
				e.preventDefault();
				$.setCookie('cancel_defer', '1');
				$(this).text($(this).data('cancelling'));
			});

			$html.appendTo('body').overlay($.extend(true, {
				mask: {
					color: 'white',
					opacity: 0.6,
					loadSpeed: XenForo.speed.normal,
					closeSpeed: XenForo.speed.fast
				},
				closeOnClick: false,
				closeOnEsc: false,
				oneInstance: false
			}, XenForo._overlayConfig, {top: '20%'}));
			$html.overlay().load();

			XenForo._manualDeferOverlay = $html;

			$(document).trigger('PseudoAjaxStart');

			var closeOverlay = function()
			{
				XenForo._manualDeferOverlay.overlay().close();
				$('#ManualDeferOverlay').remove();
				XenForo._manualDeferOverlay = false;
				XenForo._manualDeferXhr = false;

				$(document).trigger('PseudoAjaxStop');
				$(document).trigger('ManualDeferComplete');
			};

			var fn = function() {
				XenForo._manualDeferXhr = XenForo.ajax(XenForo._manualDeferUrl, {execute: 1}, function(ajaxData) {
					if (ajaxData && ajaxData.continueProcessing)
					{
						setTimeout(fn, 0);
						XenForo._manualDeferOverlay.find('span').text(ajaxData.status);

						var $cancel = XenForo._manualDeferOverlay.find('.CancelDeferred');
						if (ajaxData.canCancel)
						{
							$cancel.show();
						}
						else
						{
							$cancel.hide();
						}
					}
					else
					{
						closeOverlay();
					}
				}).fail(closeOverlay);
			};
			fn();
		},

		/**
		 * Generic handler for server-level errors received from XenForo.ajax
		 * Attempts to provide a useful error message.
		 *
		 * @param object XMLHttpRequest
		 * @param string Response text
		 * @param string Error thrown
		 *
		 * @return boolean False
		 */
		handleServerError: function(xhr, responseText, errorThrown)
		{
			// handle timeout and parse error before attempting to decode an error
			switch (responseText)
			{
				case 'abort':
				{
					return false;
				}
				case 'timeout':
				{
					XenForo.alert(
						XenForo.phrases.server_did_not_respond_in_time_try_again,
						XenForo.phrases.following_error_occurred + ':'
					);
					return false;
				}
				case 'parsererror':
				{
					console.error('PHP ' + xhr.responseText);
					XenForo.alert('The server responded with an error. The error message is in the JavaScript console.');
					return false;
				}
				case 'notmodified':
				case 'error':
				{
					if (!xhr || !xhr.responseText)
					{
						// this is likely a user cancellation, so just return
						return false;
					}
					break;
				}
			}

			var contentTypeHeader = xhr.getResponseHeader('Content-Type'),
				contentType = false,
				data;

			if (contentTypeHeader)
			{
				switch (contentTypeHeader.split(';')[0])
				{
					case 'application/json':
					{
						contentType = 'json';
						break;
					}
					case 'text/html':
					{
						contentType = 'html';
						break;
					}
					default:
					{
						if (xhr.responseText.substr(0, 1) == '{')
						{
							contentType = 'json';
						}
						else if (xhr.responseText.substr(0, 9) == '<!DOCTYPE')
						{
							contentType = 'html';
						}
					}
				}
			}

			if (contentType == 'json' && xhr.responseText.substr(0, 1) == '{')
			{
				// XMLHttpRequest response is probably JSON
				try
				{
					data = $.parseJSON(xhr.responseText);
				}
				catch (e) {}

				if (data)
				{
					XenForo.hasResponseError(data, xhr.status);
				}
				else
				{
					XenForo.alert(xhr.responseText, XenForo.phrases.following_error_occurred + ':');
				}
			}
			else
			{
				// XMLHttpRequest is some other type...
				XenForo.alert(xhr.responseText, XenForo.phrases.following_error_occurred + ':');
			}

			return false;
		},

		/**
		 * Checks for the presence of an 'error' key in the provided data
		 * and displays its contents if found, using an alert.
		 *
		 * @param object ajaxData
		 * @param integer HTTP error code (optional)
		 *
		 * @return boolean|string Returns the error string if found, or false if not found.
		 */
		hasResponseError: function(ajaxData, httpErrorCode)
		{
			if (typeof ajaxData != 'object')
			{
				XenForo.alert('Response not JSON!'); // debug info, no phrasing
				return true;
			}

			if (ajaxData.errorTemplateHtml)
			{
				new XenForo.ExtLoader(ajaxData, function(data) {
					var $overlayHtml = XenForo.alert(
						ajaxData.errorTemplateHtml,
						XenForo.phrases.following_error_occurred + ':'
					);
					if ($overlayHtml)
					{
						$overlayHtml.find('div.errorDetails').removeClass('baseHtml');
						if (ajaxData.errorOverlayType)
						{
							$overlayHtml.closest('.errorOverlay').removeClass('errorOverlay').addClass(ajaxData.errorOverlayType);
						}
					}
				});

				return ajaxData.error || true;
			}
			else if (ajaxData.error !== undefined)
			{
				// TODO: ideally, handle an array of errors
				if (typeof ajaxData.error === 'object')
				{
					var key;
					for (key in ajaxData.error)
					{
						break;
					}
					ajaxData.error = ajaxData.error[key];
				}

				XenForo.alert(
					ajaxData.error + '\n'
						+ (ajaxData.traceHtml !== undefined ? '<ol class="traceHtml">\n' + ajaxData.traceHtml + '</ol>' : ''),
					XenForo.phrases.following_error_occurred + ':'
				);

				return ajaxData.error;
			}
			else if (ajaxData.status == 'ok' && ajaxData.message)
			{
				XenForo.alert(ajaxData.message, '', 4000);
				return true;
			}
			else
			{
				return false;
			}
		},

		/**
		 * Checks that the supplied ajaxData has a key that can be used to create a jQuery object
		 *
		 *  @param object ajaxData
		 *  @param string key to look for (defaults to 'templateHtml')
		 *
		 *  @return boolean
		 */
		hasTemplateHtml: function(ajaxData, templateKey)
		{
			templateKey = templateKey || 'templateHtml';

			if (!ajaxData[templateKey])
			{
				return false;
			}
			if (typeof(ajaxData[templateKey].search) == 'function')
			{
				return (ajaxData[templateKey].search(/\S+/) !== -1);
			}
			else
			{
				return true;
			}
		},

		/**
		 * Creates an overlay using the given HTML
		 *
		 * @param jQuery Trigger element
		 * @param string|jQuery HTML
		 * @param object Extra options for overlay, will override defaults if specified
		 *
		 * @return jQuery Overlay API
		 */
		createOverlay: function($trigger, templateHtml, extraOptions)
		{
			var $overlay = null,
				$templateHtml = null,
				api = null,
				overlayOptions = null,
				regex = /<script[^>]*>([\s\S]*?)<\/script>/ig,
				regexMatch,
				scripts = [],
				i;

			if (templateHtml instanceof jQuery && templateHtml.is('.xenOverlay'))
			{
				// this is an object that has already been initialised
				$overlay = templateHtml.appendTo('body');
				$templateHtml = templateHtml;
			}
			else
			{
				if (typeof(templateHtml) == 'string')
				{
					while (regexMatch = regex.exec(templateHtml))
					{
						scripts.push(regexMatch[1]);
					}
					templateHtml = templateHtml.replace(regex, '');
				}

				$templateHtml = $(templateHtml);

				// add a header to the overlay, unless instructed otherwise
				if (!$templateHtml.is('.NoAutoHeader'))
				{
					if (extraOptions && extraOptions.title)
					{
						$('<h2 class="heading h1" />')
							.html(extraOptions.title)
							.prependTo($templateHtml);
					}
				}

				// add a cancel button to the overlay, if the overlay is a .formOverlay, has a .submitUnit but has no :reset button
				if ($templateHtml.is('.formOverlay'))
				{
					if ($templateHtml.find('.submitUnit').length)
					{
						if (!$templateHtml.find('.submitUnit :reset').length)
						{
							$templateHtml.find('.submitUnit .button:last')
								.after($('<input type="reset" class="button OverlayCloser" />').val(XenForo.phrases.cancel))
								.after(' ');
						}
					}
				}

				// create an overlay container, add the activated template to it and append it to the body.
				$overlay = $('<div class="xenOverlay __XenForoActivator" />')
					.appendTo('body')
					.addClass($(templateHtml).data('overlayclass')) // if content defines data-overlayClass, apply the value to the overlay as a class.
					.append($templateHtml);

				if (scripts.length)
				{
					for (i = 0; i < scripts.length; i++)
					{
						$.globalEval(scripts[i]);
					}
				}

				$overlay.xfActivate();
			}

			if (extraOptions)
			{
				// add {effect}Effect class to overlay container if necessary
				if (extraOptions.effect)
				{
					$overlay.addClass(extraOptions.effect + 'Effect');
				}

				// add any extra class name defined in extraOptions
				if (extraOptions.className)
				{
					$overlay.addClass(extraOptions.className);
					delete(extraOptions.className);
				}

				if (extraOptions.noCache)
				{
					extraOptions.onClose = function()
					{
						this.getOverlay().empty().remove();
					};
				}
			}

			// add an overlay closer if one does not already exist
			if ($overlay.find('.OverlayCloser').length == 0)
			{
				$overlay.prepend('<a class="close OverlayCloser"></a>');
			}

			$overlay.find('.OverlayCloser').click(function(e) { e.stopPropagation(); });

			// if no trigger was specified (automatic popup), then activate the overlay instead of the trigger
			$trigger = $trigger || $overlay;

			var fixed = !(
				($.browser.msie && $.browser.version <= 6) // IE6 doesn't support position: fixed;
				|| XenForo.isTouchBrowser()
				|| $(window).width() <= 600 // overlay might end up especially tall
				|| $(window).height() <= 550
			);
			if ($templateHtml.is('.NoFixedOverlay'))
			{
				fixed = false;
			}

			// activate the overlay
			$trigger.overlay($.extend(true,
			{
				target: $overlay,
				oneInstance: true,
				close: '.OverlayCloser',
				speed: XenForo.speed.normal,
				closeSpeed: XenForo.speed.fast,
				mask:
				{
					color: 'white',
					opacity: 0.6,
					loadSpeed: XenForo.speed.normal,
					closeSpeed: XenForo.speed.fast
				},
				fixed: fixed

			}, XenForo._overlayConfig, extraOptions));

			$trigger.bind(
			{
				onBeforeLoad: function(e)
				{
					$(document).triggerHandler('OverlayOpening');
				},
				onLoad: function(e)
				{
					var api = $(this).data('overlay'),
						$overlay = api.getOverlay(),
						scroller = $overlay.find('.OverlayScroller').get(0),
						resizeClose = null;

					if ($overlay.css('position') == 'absolute')
					{
						$overlay.find('.overlayScroll').removeClass('overlayScroll');
					}

					// timeout prevents flicker in FF
					if (scroller)
					{
						setTimeout(function()
						{
							scroller.scrollIntoView(true);
						}, 0);
					}

					// autofocus the first form element in a .formOverlay
					var $focus = $overlay.find('form').find('input[autofocus], textarea[autofocus], select[autofocus], .AutoFocus').first();
					if ($focus.length)
					{
						$focus.focus();
					}
					else
					{
						$overlay.find('form').find('input:not([type=hidden], [type=file]), textarea, select, button, .submitUnit a.button').first().focus();
					}

					// hide on window resize
					if (api.getConf().closeOnResize)
					{
						resizeClose = function()
						{
							console.info('Window resize, close overlay!');
							api.close();
						};

						$(window).one('resize', resizeClose);

						// remove event when closing the overlay
						$trigger.one('onClose', function()
						{
							$(window).unbind('resize', resizeClose);
						});
					}

					$(document).triggerHandler('OverlayOpened');
				},
				onBeforeClose: function(e)
				{
					$overlay.find('.Popup').each(function()
					{
						var PopupMenu = $(this).data('XenForo.PopupMenu');
						if (PopupMenu.hideMenu)
						{
							PopupMenu.hideMenu(e, true);
						}
					});
				}
			});

			api = $trigger.data('overlay');
				  $overlay.data('overlay', api);

			return api;
		},

		/**
		 * Present the user with a pop-up, modal message that they must confirm
		 *
		 * @param string Message
		 * @param string Message type (error, info, redirect)
		 * @param integer Timeout (auto-close after this period)
		 * @param function Callback onClose
		 */
		alert: function(message, messageType, timeOut, onClose)
		{
			message = String(message || 'Unspecified error');

			var key = message.replace(/[^a-z0-9_]/gi, '_') + parseInt(timeOut),
				$overlayHtml;

			if (XenForo._OverlayCache[key] === undefined)
			{
				if (timeOut)
				{
					$overlayHtml = $(''
						+ '<div class="xenOverlay timedMessage">'
						+	'<div class="content baseHtml">'
						+		message
						+		'<span class="close"></span>'
						+	'</div>'
						+ '</div>');

					XenForo._OverlayCache[key] = $overlayHtml.appendTo('body').overlay({
						top: 0,
						effect: 'slideDownContentFade',
						speed: XenForo.speed.normal,
						oneInstance: false,
						onBeforeClose: (onClose ? onClose : null)
					}).data('overlay');
				}
				else
				{
					$overlayHtml = $(''
						+ '<div class="errorOverlay">'
						+ 	'<a class="close OverlayCloser"></a>'
						+ 	'<h2 class="heading">' + (messageType || XenForo.phrases.following_error_occurred) + '</h2>'
						+ 	'<div class="baseHtml errorDetails"></div>'
						+ '</div>'
					);
					$overlayHtml.find('div.errorDetails').html(message);
					XenForo._OverlayCache[key] = XenForo.createOverlay(null, $overlayHtml, {
						onLoad: function() { var el = $('input:button.close, button.close', document.getElementById(key)).get(0); if (el) { el.focus(); } },
						onClose: (onClose ? onClose : null)
					});
				}
			}

			XenForo._OverlayCache[key].load();

			if (timeOut)
			{
				setTimeout('XenForo._OverlayCache["' + key + '"].close()', timeOut);
			}

			return $overlayHtml;
		},

		/**
		 * Shows a mini timed alert message, much like the OS X notifier 'Growl'
		 *
		 * @param string message
		 * @param integer timeOut Leave empty for a sticky message
		 * @param jQuery Counter balloon
		 */
		stackAlert: function(message, timeOut, $balloon)
		{
			var $message = $('<li class="stackAlert DismissParent"><div class="stackAlertContent">'
				+ '<span class="helper"></span>'
				+ '<a class="DismissCtrl"></a>'
				+ '</div></li>'),

			$container = $('#StackAlerts');

			if (!$container.length)
			{
				$container = $('<ul id="StackAlerts"></ul>').appendTo('body');
			}

			if ((message instanceof jQuery) == false)
			{
				message = $('<span>' + message + '</span>');
			}

			message.appendTo($message.find('div.stackAlertContent'));

			function removeMessage()
			{
				$message.xfFadeUp(XenForo.speed.slow, function()
				{
					$(this).empty().remove();

					if (!$container.children().length)
					{
						$container.hide();
					}
				});
			}

			function removeMessageAndScroll(e)
			{
				if ($balloon && $balloon.length)
				{
					$balloon.get(0).scrollIntoView(true);
				}

				removeMessage();
			}

			$message
				.hide()
				.prependTo($container.show())
				.fadeIn(XenForo.speed.normal, function()
				{
					if (timeOut > 0)
					{
						setTimeout(removeMessage, timeOut);
					}
				});

			$message.find('a').click(removeMessageAndScroll);

			return $message;
		},

		/**
		 * Adjusts all animation speeds used by XenForo
		 *
		 * @param integer multiplier - set to 0 to disable all animation
		 */
		setAnimationSpeed: function(multiplier)
		{
			var ieSpeedAdjust, s, index;

			for (index in XenForo.speed)
			{
				s = XenForo.speed[index];

				if ($.browser.msie)
				{
					// if we are using IE, change the animation lengths for a smoother appearance
					if (s <= 100)
					{
						ieSpeedAdjust = 2;
					}
					else if (s > 800)
					{
						ieSpeedAdjust = 1;
					}
					else
					{
						ieSpeedAdjust = 1 + 100/s;
					}
					XenForo.speed[index] = s * multiplier * ieSpeedAdjust;
				}
				else
				{
					XenForo.speed[index] = s * multiplier;
				}
			}
		},

		/**
		 * Generates a unique ID for an element, if required
		 *
		 * @param object HTML element (optional)
		 *
		 * @return string Unique ID
		 */
		uniqueId: function(element)
		{
			if (!element)
			{
				return 'XenForoUniq' + XenForo._uniqueIdCounter++;
			}
			else
			{
				return $(element).uniqueId().attr('id');
			}
		},

		redirect: function(url)
		{
			url = XenForo.canonicalizeUrl(url);

			if (url == window.location.href)
			{
				window.location.reload();
			}
			else
			{
				window.location = url;

				var destParts = url.split('#'),
					srcParts = window.location.href.split('#');

				if (destParts[1]) // has a hash
				{
					if (destParts[0] == srcParts[0])
					{
						// destination has a hash, but going to the same page
						window.location.reload();
					}
				}
			}
		},

		canonicalizeUrl: function(url, baseHref)
		{
			if (url.indexOf('/') == 0)
			{
				return url;
			}
			else if (url.match(/^https?:|ftp:|mailto:/i))
			{
				return url;
			}
			else
			{
				if (!baseHref)
				{
					baseHref = XenForo.baseUrl();
				}
				if (typeof baseHref != 'string')
				{
					baseHref = '';
				}
				return baseHref + url;
			}
		},

		_baseUrl: false,

		baseUrl: function()
		{
			if (XenForo._baseUrl === false)
			{
				var b = document.createElement('a'), $base = $('base');
				b.href = '';

				XenForo._baseUrl = (b.href.match(/[^\x20-\x7f]/) && $base.length) ? $base.attr('href') : b.href;

				if (!$base.length)
				{
					XenForo._baseUrl = XenForo._baseUrl.replace(/\?.*$/, '').replace(/\/[^\/]*$/, '/');
				}
			}

			return XenForo._baseUrl;
		},

		/**
		 * Adds a trailing slash to a string if one is not already present
		 *
		 * @param string
		 */
		trailingSlash: function(string)
		{
			if (string.substr(-1) != '/')
			{
				string += '/';
			}

			return string;
		},

		/**
		 * Escapes a string so it can be inserted into a RegExp without altering special characters
		 *
		 * @param string
		 *
		 * @return string
		 */
		regexQuote: function(string)
		{
			return (string + '').replace(/([\\\.\+\*\?\[\^\]\$\(\)\{\}\=\!<>\|\:])/g, "\\$1");
		},

		/**
		 * Escapes HTML into plain text
		 *
		 * @param string
		 *
		 * @return string
		 */
		htmlspecialchars: function(string)
		{
			return (string || '')
				.replace(/&/g, '&amp;')
				.replace(/"/g, '&quot;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;');
		},

		/**
		 * Determines whether the current page is being viewed in RTL mode
		 *
		 * @return boolean
		 */
		isRTL: function()
		{
			if (XenForo.RTL === undefined)
			{
				var dir = $('html').attr('dir');
				XenForo.RTL = (dir && dir.toUpperCase() == 'RTL') ? true : false;
			}

			return XenForo.RTL;
		},

		/**
		 * Switches instances of 'left' with 'right' and vice-versa in the input string.
		 *
		 * @param string directionString
		 *
		 * @return string
		 */
		switchStringRTL: function(directionString)
		{
			if (XenForo.isRTL())
			{
				directionString = directionString.replace(/left/i, 'l_e_f_t');
				directionString = directionString.replace(/right/i, 'left');
				directionString = directionString.replace('l_e_f_t', 'right');
			}
			return directionString;
		},

		/**
		 * Switches the x-coordinate of the input offset array
		 * @param offsetArray
		 * @return string
		 */
		switchOffsetRTL: function(offsetArray)
		{
			if (XenForo.isRTL() && !isNaN(offsetArray[1]))
			{
				offsetArray[1] = offsetArray[1] * -1;
			}

			return offsetArray;
		},

		/**
		 * Checks whether or not a tag is a list container
		 *
		 * @param jQuery Tag
		 *
		 * @return boolean
		 */
		isListTag: function($tag)
		{
			return ($tag.tagName == 'ul' || $tag.tagName == 'ol');
		},

		/**
		 * Checks that the value passed is a numeric value, even if its actual type is a string
		 *
		 * @param mixed Value to be checked
		 *
		 * @return boolean
		 */
		isNumeric: function(value)
		{
			return (!isNaN(value) && (value - 0) == value && value.length > 0);
		},

		/**
		 * Helper to check that an attribute value is 'positive'
		 *
		 * @param scalar Value to check
		 *
		 * @return boolean
		 */
		isPositive: function(value)
		{
			switch (String(value).toLowerCase())
			{
				case 'on':
				case 'yes':
				case 'true':
				case '1':
					return true;

				default:
					return false;
			}
		},

		/**
		 * Converts the first character of a string to uppercase.
		 *
		 * @param string
		 *
		 * @return string
		 */
		ucfirst: function(string)
		{
			return string.charAt(0).toUpperCase() + string.substr(1);
		},

		/**
		 * Replaces any existing avatars for the given user on the page
		 *
		 * @param integer user ID
		 * @param array List of avatar urls for the user, keyed with size code
		 * @param boolean Include crop editor image
		 */
		updateUserAvatars: function(userId, avatarUrls, andEditor)
		{
			console.log('Replacing visitor avatars on page: %o', avatarUrls);

			$.each(avatarUrls, function(sizeCode, avatarUrl)
			{
				var sizeClass = '.Av' + userId + sizeCode + (andEditor ? '' : ':not(.AvatarCropControl)');

				// .avatar > img
				$(sizeClass).find('img').attr('src', avatarUrl);

				// .avatar > span.img
				$(sizeClass).find('span.img').css('background-image', 'url(' + avatarUrl + ')');
			});
		},

		getEditorInForm: function(form, extraConstraints)
		{
			var $form = $(form),
				$textarea = $form.find('textarea.MessageEditor' + (extraConstraints || '')).first();

			if ($textarea.length)
			{
				if ($textarea.prop('disabled'))
				{
					return $form.find('.bbCodeEditorContainer textarea' + (extraConstraints || ''));
				}
				else if ($textarea.data('redactor'))
				{
					return $textarea.data('redactor');
				}
				else
				{
					return $textarea;
				}
			}

			return false;
		},

		/**
		 * Returns the name of the tag that should be animated for page scrolling
		 *
		 * @return string
		 */
		getPageScrollTagName: function()
		{
			//TODO: watch for webkit support for scrolling 'html'
			return ($.browser.webkit ? 'body' : 'html');
		},

		/**
		 * Determines whether or not we are working with a touch-based browser
		 *
		 * @return boolean
		 */
		isTouchBrowser: isTouchBrowser,

		/**
		 * Lazy-loads Javascript files
		 */
		scriptLoader:
		{
			loadScript: function(url, success, failure)
			{
				if (XenForo._loadedScripts[url] === undefined)
				{
					if (/tiny_mce[a-zA-Z0-9_-]*\.js/.test(url))
					{
						var preInit = {suffix: '', base: '', query: ''},
							baseHref = XenForo.baseUrl();

						if (/_(src|dev)\.js/g.test(url))
						{
							preInit.suffix = '_src';
						}

						if ((p = url.indexOf('?')) != -1)
						{
							preInit.query = url.substring(p + 1);
						}

						preInit.base = url.substring(0, url.lastIndexOf('/'));

						if (baseHref && preInit.base.indexOf('://') == -1 && preInit.base.indexOf('/') !== 0)
							preInit.base = baseHref + preInit.base;
					}

					$.ajax(
					{
						type: 'GET',
						url: url,
						cache: true,
						dataType: 'script',
						error: failure,
						success: function(javascript, textStatus)
						{
							XenForo._loadedScripts[url] = true;
							//$.globalEval(javascript);
							success.call();
						}
					});
				}
				else
				{
					success.call();
				}
			},

			loadCss: function(css, urlTemplate, success, failure)
			{
				var stylesheets = [],
					url;

				// build a list of stylesheets we have not already loaded
				$.each(css, function(i, stylesheet)
				{
					if (!XenForo._loadedScripts[stylesheet])
					{
						stylesheets.push(stylesheet);
					}
				});

				// if there are any left, construct the URL and load them
				if (stylesheets.length)
				{
					url = urlTemplate.replace('__sentinel__', stylesheets.join(','));
					url = XenForo.canonicalizeUrl(url, XenForo.ajaxBaseHref);

					$.ajax(
					{
						type: 'GET',
						url: url,
						cache: true,
						dataType: 'text',
						error: failure,
						success: function(cssText, textStatus)
						{
							$.each(stylesheets, function(i, stylesheet)
							{
								console.log('Loaded css %d, %s', i, stylesheet);
								XenForo._loadedScripts[stylesheet] = true;
							});

							var baseHref = XenForo.baseUrl();
							if (baseHref)
							{
								cssText = cssText.replace(
									/(url\(("|')?)([^"')]+)(("|')?\))/gi,
									function(all, front, null1, url, back, null2)
									{
										if (!url.match(/^(https?:|\/)/i))
										{
											url = baseHref + url;
										}
										return front + url + back;
									}
								);
							}

							$('<style>' + cssText + '</style>').appendTo('head');

							success.call();
						}
					});
				}
				else
				{
					success.call();
				}
			}
		}
	});

	// *********************************************************************

	/**
	 * Loads the requested list of javascript and css files
	 * Before firing the specified callback.
	 *
	 * @param array Javascript URLs
	 * @param array CSS URLs
	 * @param function Success callback
	 * @param function Error callback
	 */
	XenForo.ExtLoader = function(data, success, failure) { this.__construct(data, success, failure); };
	XenForo.ExtLoader.prototype =
	{
		__construct: function(data, success, failure)
		{
			this.success = success;
			this.failure = failure;
			this.totalFetched = 0;
			this.data = data;

			var numJs = 0,
				hasCss = 0,
				i = 0;

			// check if css is required, and make sure the format is good
			if (data.css && !$.isEmptyObject(data.css.stylesheets))
			{
				if (!data.css.urlTemplate)
				{
					return console.warn('Unable to load CSS without a urlTemplate being provided.');
				}

				hasCss = 1;
			}

			// check if javascript is required, and make sure the format is good
			if (data.js)
			{
				numJs = data.js.length;
			}

			this.totalExt = hasCss + numJs;

			// nothing to do
			if (!this.totalExt)
			{
				return this.callSuccess();
			}

			// fetch required javascript
			if (numJs)
			{
				for (i = 0; i < numJs; i++)
				{
					XenForo.scriptLoader.loadScript(data.js[i], $.context(this, 'successCount'), $.context(this, 'callFailure'));
				}
			}

			// fetch required css
			if (hasCss)
			{
				XenForo.scriptLoader.loadCss(data.css.stylesheets, data.css.urlTemplate, $.context(this, 'successCount'), $.context(this, 'callFailure'));
			}
		},

		/**
		 * Fires the success callback
		 */
		callSuccess: function()
		{
			if (typeof this.success == 'function')
			{
				this.success(this.data);
			}
		},

		/**
		 * Fires the error callback
		 *
		 * @param object ajaxData
		 * @param string textStatus
		 * @param boolean errorThrown
		 */
		callFailure: function(ajaxData, textStatus, errorThrown)
		{
			if (!this.failed)
			{
				if (typeof this.failure == 'function')
				{
					this.failure(this.data);
				}
				else
				{
					console.warn('ExtLoader Failure %s %s', textStatus, ajaxData.status);
				}

				this.failed = true;
			}
		},

		/**
		 * Increment the totalFetched variable, and
		 * fire callSuccess() when this.totalFetched
		 * equals this.totalExt
		 *
		 * @param event e
		 */
		successCount: function(e)
		{
			this.totalFetched++;

			if (this.totalFetched >= this.totalExt)
			{
				this.callSuccess();
			}
		}
	};

	// *********************************************************************

	/**
	 * Instance of XenForo.TimestampRefresh
	 *
	 * @var XenForo.TimestampRefresh
	 */
	XenForo._TimestampRefresh = null;

	/**
	 * Allows date/time stamps on the page to be displayed as relative to now, and auto-refreshes periodically
	 */
	XenForo.TimestampRefresh = function() { this.__construct(); };
	XenForo.TimestampRefresh.prototype =
	{
		__construct: function()
		{
			this.active = this.activate();

			$(document).bind('XenForoWindowFocus', $.context(this, 'focus'));
		},

		/**
		 * Runs on window.focus, activates the system if deactivated
		 *
		 * @param event e
		 */
		focus: function(e)
		{
			if (!this.active)
			{
				this.activate(true);
			}
		},

		/**
		 * Runs a refresh, then refreshes again every 60 seconds
		 *
		 * @param boolean Refresh instantly
		 *
		 * @return integer Refresh interval or something...
		 */
		activate: function(instant)
		{
			if (instant)
			{
				this.refresh();
			}

			return this.active = window.setInterval($.context(this, 'refresh'), 60 * 1000); // one minute
		},

		/**
		 * Halts timestamp refreshes
		 *
		 * @return boolean false
		 */
		deactivate: function()
		{
			window.clearInterval(this.active);
			return this.active = false;
		},

		/**
		 * Date/Time output updates
		 */
		refresh: function(element, force)
		{
			if (!XenForo._hasFocus && !force)
			{
				return this.deactivate();
			}

			if ($.browser.msie && $.browser.version <= 6)
			{
				return;
			}

			var $elements = $('abbr.DateTime[data-time]', element),
				pageOpenTime = (new Date().getTime() / 1000),
				pageOpenLength = pageOpenTime - XenForo._pageLoadTime,
				serverTime = XenForo.serverTimeInfo.now,
				today = XenForo.serverTimeInfo.today,
				todayDow = XenForo.serverTimeInfo.todayDow,
				yesterday, week, dayOffset,
				i, $element, thisTime, thisDiff, thisServerTime, interval, calcDow;

			if (serverTime + pageOpenLength > today + 86400)
			{
				// day has changed, need to adjust
				dayOffset = Math.floor((serverTime + pageOpenLength - today) / 86400);

				today += dayOffset * 86400;
				todayDow = (todayDow + dayOffset) % 7;
			}

			yesterday = today - 86400;
			week = today - 6 * 86400;

			var rtlMarker = XenForo.isRTL() ? '\u200F' : '';

			for (i = 0; i < $elements.length; i++)
			{
				$element = $($elements[i]);

				// set the original value of the tag as its title
				if (!$element.attr('title'))
				{
					$element.attr('title', $element.text());
				}

				thisDiff = parseInt($element.data('diff'), 10);
				thisTime = parseInt($element.data('time'), 10);

				thisServerTime = thisTime + thisDiff;
				if (thisServerTime > serverTime + pageOpenLength)
				{
					thisServerTime = Math.floor(serverTime + pageOpenLength);
				}
				interval = serverTime - thisServerTime + thisDiff + pageOpenLength;

				if (interval < 0)
				{
					// date in the future
				}
				else if (interval <= 60)
				{
					$element.text(XenForo.phrases.a_moment_ago);
				}
				else if (interval <= 120)
				{
					$element.text(XenForo.phrases.one_minute_ago);
				}
				else if (interval < 3600)
				{
					$element.text(XenForo.phrases.x_minutes_ago
						.replace(/%minutes%/, Math.floor(interval / 60)));
				}
				else if (thisTime > today)
				{
					$element.text(XenForo.phrases.today_at_x
						.replace(/%time%/, $element.attr('data-timestring'))); // must use attr for string value
				}
				else if (thisTime > yesterday)
				{
					$element.text(XenForo.phrases.yesterday_at_x
							.replace(/%time%/, $element.attr('data-timestring'))); // must use attr for string value
				}
				else if (thisTime > week)
				{
					calcDow = todayDow - Math.ceil((today - thisTime) / 86400);
					if (calcDow < 0)
					{
						calcDow += 7;
					}

					$element.text(rtlMarker + XenForo.phrases.day_x_at_time_y
						.replace('%day%', XenForo.phrases['day' + calcDow])
						.replace(/%time%/, $element.attr('data-timestring')) // must use attr for string value
					);
				}
				else
				{
					$element.text(rtlMarker + $element.attr('data-datestring')); // must use attr for string value
				}
			}
		}
	};

	// *********************************************************************

	/**
	 * Periodically refreshes all CSRF tokens on the page
	 */
	XenForo.CsrfRefresh = function() { this.__construct(); };
	XenForo.CsrfRefresh.prototype =
	{
		__construct: function()
		{
			this.activate();

			$(document).bind('XenForoWindowFocus', $.context(this, 'focus'));
		},

		/**
		 * Runs on window focus, activates the system if deactivated
		 *
		 * @param event e
		 */
		focus: function(e)
		{
			if (!this.active)
			{
				this.activate(true);
			}
		},

		/**
		 * Runs a refresh, then refreshes again every hour
		 *
		 * @param boolean Refresh instantly
		 *
		 * @return integer Refresh interval or something...
		 */
		activate: function(instant)
		{
			if (instant)
			{
				this.refresh();
			}

			this.active = window.setInterval($.context(this, 'refresh'), 50 * 60 * 1000); // 50 minutes
			return this.active;
		},

		/**
		 * Halts csrf refreshes
		 */
		deactivate: function()
		{
			window.clearInterval(this.active);
			this.active = false;
		},

		/**
		 * Updates all CSRF tokens
		 */
		refresh: function()
		{
			if (!XenForo._csrfRefreshUrl)
			{
				return;
			}

			if (!XenForo._hasFocus)
			{
				this.deactivate();
				return;
			}

			XenForo.ajax(
				XenForo._csrfRefreshUrl,
				'',
				function(ajaxData, textStatus)
				{
					if (!ajaxData || ajaxData.csrfToken === undefined)
					{
						return false;
					}

					var tokenInputs = $('input[name=_xfToken]').val(ajaxData.csrfToken);

					XenForo._csrfToken = ajaxData.csrfToken;

					if (tokenInputs.length)
					{
						console.log('XenForo CSRF token updated in %d places (%s)', tokenInputs.length, ajaxData.csrfToken);
					}

					$(document).trigger(
					{
						type: 'CSRFRefresh',
						ajaxData: ajaxData
					});
				},
				{ error: false, global: false }
			);
		}
	};

	// *********************************************************************

	/**
	 * Stores the id of the currently active popup menu group
	 *
	 * @var string
	 */
	XenForo._PopupMenuActiveGroup = null;

	/**
	 * Popup menu system.
	 *
	 * Requires:
	 * <el class="Popup">
	 * 		<a rel="Menu">control</a>
	 * 		<el class="Menu {Left} {Hider}">menu content</el>
	 * </el>
	 *
	 * * .Menu.Left causes orientation of menu to reverse, away from scrollbar
	 * * .Menu.Hider causes menu to appear over control instead of below
	 *
	 * @param jQuery *.Popup container element
	 */
	XenForo.PopupMenu = function($container) { this.__construct($container); };
	XenForo.PopupMenu.prototype =
	{
		__construct: function($container)
		{
			// the container holds the control and the menu
			this.$container = $container;

			// take the menu, which will be a sibling of the control, and append/move it to the end of the body
			this.$menu = this.$container.find('.Menu').appendTo('body');
			this.$menu.data('XenForo.PopupMenu', this);
			this.menuVisible = false;

			// check that we have the necessary elements
			if (!this.$menu.length)
			{
				console.warn('Unable to find menu for Popup %o', this.$container);

				return false;
			}

			// add a unique id to the menu
			this.$menu.id = XenForo.uniqueId(this.$menu);

			// variables related to dynamic content loading
			this.contentSrc = this.$menu.data('contentsrc');
			this.contentDest = this.$menu.data('contentdest');
			this.loading = null;
			this.unreadDisplayTimeout = null;
			this.newlyOpened = false;

			// bind events to the menu control
			this.$clicker = $container.find('[rel="Menu"]').first().click($.context(this, 'controlClick'));

			if (!XenForo.isTouchBrowser())
			{
				this.$clicker.mouseover($.context(this, 'controlHover')).hoverIntent(
				{
					sensitivity: 1,
					interval: 100,
					timeout: 0,
					over: $.context(this, 'controlHoverIntent'),
					out: function(){}
				});
			}

			this.$control = this.addPopupGadget(this.$clicker);

			// the popup group for this menu, if specified
			this.popupGroup = this.$control.closest('[data-popupgroup]').data('popupgroup');

			//console.log('Finished popup menu for %o', this.$control);
		},

		addPopupGadget: function($control)
		{
			if (!$control.hasClass('NoPopupGadget') && !$control.hasClass('SplitCtrl'))
			{
				$control.append('<span class="arrowWidget" />');
			}

			var $popupControl = $control.closest('.PopupControl');
			if ($popupControl.length)
			{
				$control = $popupControl.addClass('PopupContainerControl');
			}

			$control.addClass('PopupControl');

			return $control;
		},

		/**
		 * Opens or closes a menu, or navigates to another page, depending on menu status and control attributes.
		 *
		 * Clicking a control while the menu is hidden will open and show the menu.
		 * If the control has an href attribute, clicking on it when the menu is open will navigate to the specified URL.
		 * If the control does not have an href, a click will close the menu.
		 *
		 * @param event
		 *
		 * @return mixed
		 */
		controlClick: function(e)
		{
			console.debug('%o control clicked. NewlyOpened: %s, Animated: %s', this.$control, this.newlyOpened, this.$menu.is(':animated'));

			if (!this.newlyOpened && !this.$menu.is(':animated'))
			{
				console.info('control: %o', this.$control);

				if (this.$menu.is(':hidden'))
				{
					this.showMenu(e, false);
				}
				else if (this.$clicker.attr('href') && !XenForo.isPositive(this.$clicker.data('closemenu')))
				{
					console.warn('Following hyperlink from %o', this.$clicker);
					return true;
				}
				else
				{
					this.hideMenu(e, false);
				}
			}
			else
			{
				console.debug('Click on control of newly-opened or animating menu, ignored');
			}

			e.preventDefault();
			e.target.blur();
			return false;
		},

		/**
		 * Handles hover events on menu controls. Will normally do nothing,
		 * unless there is a menu open and the control being hovered belongs
		 * to the same popupGroup, in which case this menu will open instantly.
		 *
		 * @param event
		 *
		 * @return mixed
		 */
		controlHover: function(e)
		{
			if (this.popupGroup != null && this.popupGroup == this.getActiveGroup())
			{
				this.showMenu(e, true);

				return false;
			}
		},

		/**
		 * Handles hover-intent events on menu controls. Menu will show
		 * if the cursor is hovered over a control at low speed and for a duration
		 *
		 * @param event
		 */
		controlHoverIntent: function(e)
		{
			var instant = false;//(this.popupGroup != null && this.popupGroup == this.getActiveGroup());

			if (this.$clicker.hasClass('SplitCtrl'))
			{
				instant = true;
			}

			this.showMenu(e, instant);
		},

		/**
		 * Opens and shows a popup menu.
		 *
		 * If the menu requires dynamic content to be loaded, this will load the content.
		 * To define dynamic content, the .Menu element should have:
		 * * data-contentSrc = URL to JSON that contains templateHtml to be inserted
		 * * data-contentDest = jQuery selector specifying the element to which the templateHtml will be appended. Defaults to this.$menu.
		 *
		 * @param event
		 * @param boolean Show instantly (true) or fade in (false)
		 */
		showMenu: function(e, instant)
		{
			if (this.$menu.is(':visible'))
			{
				return false;
			}

			//console.log('Show menu event type = %s', e.type);

			var $eShow = new $.Event('PopupMenuShow');
			$eShow.$menu = this.$menu;
			$eShow.instant = instant;
			$(document).trigger($eShow);

			if ($eShow.isDefaultPrevented())
			{
				return false;
			}

			this.menuVisible = true;

			this.setMenuPosition('showMenu');

			if (this.$menu.hasClass('BottomControl'))
			{
				instant = true;
			}

			if (this.contentSrc && !this.loading)
			{
				this.loading = XenForo.ajax(
					this.contentSrc, '',
					$.context(this, 'loadSuccess'),
					{ type: 'GET' }
				);

				this.$menu.find('.Progress').addClass('InProgress');

				instant = true;
			}

			this.setActiveGroup();

			this.$control.addClass('PopupOpen').removeClass('PopupClosed');

			this.$menu.stop().xfSlideDown((instant ? 0 : XenForo.speed.xfast), $.context(this, 'menuShown'));

			if (!this.menuEventsInitialized)
			{
				// TODO: make this global?
				// TODO: touch interfaces don't like this
				$(document).bind({
					PopupMenuShow: $.context(this, 'hideIfOther')
				});

				// Webkit mobile kinda does not support document.click, bind to other elements
				if (XenForo._isWebkitMobile)
				{
					$(document.body.children).click($.context(this, 'hideMenu'));
				}
				else
				{
					$(document).click($.context(this, 'hideMenu'));
				}

				var $html = $('html'), t = this, htmlSize = [$html.width(), $html.height()];
				$(window).bind(
				{
					resize: function(e) {
						// only trigger close if the window size actually changed - some mobile browsers trigger without size change
						var w = $html.width(), h = $html.height();
						if (w != htmlSize[0] || h != htmlSize[1])
						{
							htmlSize[0] = w; htmlSize[1] = h;
							t._hideMenu(e);
						}
					}
				});

				this.$menu.delegate('a', 'click', $.context(this, 'menuLinkClick'));
				this.$menu.delegate('.MenuCloser', 'click', $.context(this, 'hideMenu'));

				this.menuEventsInitialized = true;
			}
		},

		/**
		 * Hides an open popup menu (conditionally)
		 *
		 * @param event
		 * @param boolean Hide instantly (true) or fade out (false)
		 */
		hideMenu: function(e, instant)
		{
			if (this.$menu.is(':visible') && this.triggersMenuHide(e))
			{
				this._hideMenu(e, !instant);
			}
		},

		/**
		 * Hides an open popup menu, without checking context or environment
		 *
		 * @param event
		 * @param boolean Fade out the menu (true) or hide instantly out (false)
		 */
		_hideMenu: function(e, fade)
		{
			//console.log('Hide menu \'%s\' %o TYPE = %s', this.$control.text(), this.$control, e.type);
			this.menuVisible = false;

			this.setActiveGroup(null);

			if (this.$menu.hasClass('BottomControl'))
			{
				fade = false;
			}

			// stop any unread content fading into its read state
			clearTimeout(this.unreadDisplayTimeout);
			this.$menu.find('.Unread').stop();

			this.$menu.xfSlideUp((fade ? XenForo.speed.xfast : 0), $.context(this, 'menuHidden'));
		},

		/**
		 * Fires when the menu showing animation is completed and the menu is displayed
		 */
		menuShown: function()
		{
			// if the menu has a data-contentSrc attribute, we can assume that it requires dynamic content, which has not yet loaded
			var contentLoaded = (this.$menu.data('contentsrc') ? false : true),
				$input = null;

			this.$control.addClass('PopupOpen').removeClass('PopupClosed');

			this.newlyOpened = true;
			setTimeout($.context(function()
			{
				this.newlyOpened = false;
			}, this), 50);

			this.$menu.trigger('ShowComplete', [contentLoaded]);

			this.setMenuPosition('menuShown');

			this.highlightUnreadContent();

			$input = this.$menu.find('input[type=text], input[type=search], textarea, select').first();
			if ($input.length)
			{
				if ($input.data('nofocus'))
				{
					return;
				}

				$input.select();
			}
		},

		/**
		 * Fires when the menu hiding animations is completed and the menu is hidden
		 */
		menuHidden: function()
		{
			this.$control.removeClass('PopupOpen').addClass('PopupClosed');

			this.$menu.trigger('MenuHidden');
		},

		/**
		 * Fires in response to the document triggering 'PopupMenuShow' and hides the current menu
		 * if the menu that fired the event is not itself.
		 *
		 * @param event
		 */
		hideIfOther: function(e)
		{
			if (e.$menu.prop($.expando) != this.$menu.prop($.expando))
			{
				this.hideMenu(e, e.instant);
			}
		},

		/**
		 * Checks to see if an event should hide the menu.
		 *
		 * Returns false if:
		 * * Event target is a child of the menu, or is the menu itself
		 *
		 * @param event
		 *
		 * @return boolean
		 */
		triggersMenuHide: function(e)
		{
			var $target = $(e.target);

			if (e.ctrlKey || e.shiftKey || e.altKey)
			{
				return false;
			}

			if (e.which > 1)
			{
				// right or middle click, don't close
				return false;
			}

			if ($target.is('.MenuCloser'))
			{
				return true;
			}

			// is the control a hyperlink that has not had its default action prevented?
			if ($target.is('a[href]') && !e.isDefaultPrevented())
			{
				return true;
			}

			if (e.target === document || !$target.closest('#' + this.$menu.id).length)
			{
				return true;
			}

			return false;
		},

		/**
		 * Sets the position of the popup menu, based on the position of the control
		 */
		setMenuPosition: function(caller)
		{
			//console.info('setMenuPosition(%s)', caller);

			var $controlParent,
				controlLayout, // control coordinates
				menuLayout, // menu coordinates
				contentLayout, // #content coordinates
				$content,
				$window,
				proposedLeft,
				proposedTop;

			controlLayout = this.$control.coords('outer');

			this.$menu.css('position', '').removeData('position');

			$controlParent = this.$control;
			while ($controlParent && $controlParent.length && $controlParent.get(0) != document)
			{
				if ($controlParent.css('position') == 'fixed')
				{
					controlLayout.top -= $(window).scrollTop();
					controlLayout.left -= $(window).scrollLeft();

					this.$menu.css('position', 'fixed').data('position', 'fixed');
					break;
				}

				$controlParent = $controlParent.parent();
			}

			this.$control.removeClass('BottomControl');

			// set the menu to sit flush with the left of the control, immediately below it
			this.$menu.removeClass('BottomControl').css(
			{
				left: controlLayout.left,
				top: controlLayout.top + controlLayout.height - 1 // fixes a weird thing where the menu doesn't join the control
			});

			menuLayout = this.$menu.coords('outer');

			$content = $('#content .pageContent');
			if ($content.length)
			{
				contentLayout = $content.coords('outer');
			}
			else
			{
				contentLayout = $('body').coords('outer');
			}

			$window = $(window);
			var sT = $window.scrollTop(),
				sL = $window.scrollLeft(),
				windowWidth = $window.width();

			/*
			 * if the menu's right edge is off the screen, check to see if
			 * it would be better to position it flush with the right edge of the control.
			 * RTL displays will try to do this if possible.
			 */
			if (XenForo.isRTL() || menuLayout.left + menuLayout.width > contentLayout.left + contentLayout.width)
			{
				proposedLeft = Math.max(0, controlLayout.left + controlLayout.width - menuLayout.width);
				if (proposedLeft > sL)
				{
					this.$menu.css('left', proposedLeft);
				}
			}

			if (parseInt(this.$menu.css('left'), 10) + menuLayout.width > windowWidth + sL)
			{
				this.$menu.css('left', 0);
			}

			/*
			 * if the menu's bottom edge is off the screen, check to see if
			 * it would be better to position it above the control
			 */
			if (menuLayout.top + menuLayout.height > $window.height() + sT)
			{
				proposedTop = controlLayout.top - menuLayout.height;
				if (proposedTop > sT)
				{
					this.$control.addClass('BottomControl');
					this.$menu.addClass('BottomControl');
					this.$menu.css('top', controlLayout.top - this.$menu.outerHeight());
				}
			}
		},

		/**
		 * Fires when dynamic content for a popup menu has been loaded.
		 *
		 * Checks for errors and if there are none, appends the new HTML to the element selected by this.contentDest.
		 *
		 * @param object ajaxData
		 * @param string textStatus
		 */
		loadSuccess: function(ajaxData, textStatus)
		{
			if (XenForo.hasResponseError(ajaxData) || !XenForo.hasTemplateHtml(ajaxData))
			{
				return false;
			}

			this.$menu.trigger('LoadComplete');

			var $templateHtml = $(ajaxData.templateHtml);

			// check for content destination
			if (!this.contentDest)
			{
				console.warn('Menu content destination not specified, using this.$menu.');

				this.contentDest = this.$menu;
			}

			console.info('Content destination: %o', this.contentDest);

			var self = this;

			// append the loaded content to the destination
			$templateHtml.xfInsert(
				this.$menu.data('insertfn') || 'appendTo',
				this.contentDest,
				'slideDown', 0,
				function()
				{
					self.$menu.css('min-width', '199px');
					setTimeout(function() {
						self.$menu.css('min-width', '');
					}, 0);
					if (self.$control.hasClass('PopupOpen'))
					{
						self.menuShown();
					}
				}
			);

			this.$menu.find('.Progress').removeClass('InProgress');
		},

		resetLoader: function()
		{
			if (this.contentDest && this.loading)
			{
				delete(this.loading);
				$(this.contentDest).empty();
				this.$menu.find('.Progress').addClass('InProgress');
			}
		},

		menuLinkClick: function(e)
		{
			this.hideMenu(e, true);
		},

		/**
		 * Sets the name of the globally active popup group
		 *
		 * @param mixed If specified, active group will be set to this value.
		 *
		 * @return string Active group name
		 */
		setActiveGroup: function(value)
		{
			var activeGroup = (value === undefined ? this.popupGroup : value);

			return XenForo._PopupMenuActiveGroup = activeGroup;
		},

		/**
		 * Returns the name of the globally active popup group
		 *
		 * @return string Active group name
		 */
		getActiveGroup: function()
		{
			return XenForo._PopupMenuActiveGroup;
		},

		/**
		 * Fade return the background color of unread items to the normal background
		 */
		highlightUnreadContent: function()
		{
			var $unreadContent = this.$menu.find('.Unread'),
				defaultBackground = null,
				counterSelector = null;

			if ($unreadContent.length)
			{
				defaultBackground = $unreadContent.data('defaultbackground');

				if (defaultBackground)
				{
					$unreadContent.css('backgroundColor', null);

					this.unreadDisplayTimeout = setTimeout($.context(function()
					{
						// removes an item specified by data-removeCounter on the menu element
						if (counterSelector = this.$menu.data('removecounter'))
						{
							XenForo.balloonCounterUpdate($(counterSelector), 0);
						}

						$unreadContent.animate({ backgroundColor: defaultBackground }, 2000, $.context(function()
						{
							$unreadContent.removeClass('Unread');
							this.$menu.trigger('UnreadDisplayComplete');
						}, this));
					}, this), 1000);
				}
			}
		}
	};

	// *********************************************************************

	/**
	 * Shows and hides global request pending progress indicators for AJAX calls.
	 *
	 * Binds to the global ajaxStart and ajaxStop jQuery events.
	 * Also binds to the PseudoAjaxStart and PseudoAjaxStop events,
	 * see XenForo.AutoInlineUploader
	 *
	 * Initialized by XenForo.init()
	 */
	XenForo.AjaxProgress = function()
	{
		var overlay = null,

		showOverlay = function()
		{
			// mini indicators
			$('.Progress, .xenForm .ctrlUnit.submitUnit dt').addClass('InProgress');

			// the overlay
			if (!overlay)
			{
				overlay = $('<div id="AjaxProgress" class="xenOverlay"><div class="content"><span class="close" /></div></div>')
					.appendTo('body')
					.overlay(
					{
						top: 0,
						speed: XenForo.speed.fast,
						oneInstance: false,
						closeOnClick: false,
						closeOnEsc: false
					}).data('overlay');
			}

			overlay.load();
		},

		hideOverlay = function()
		{
			// mini indicators
			$('.Progress, .xenForm .ctrlUnit.submitUnit dt')
				.removeClass('InProgress');

			// the overlay
			if (overlay && overlay.isOpened())
			{
				overlay.close();
			}
		};

		$(document).bind(
		{
			ajaxStart: function(e)
			{
				XenForo._AjaxProgress = true;
				showOverlay();
			},

			ajaxStop: function(e)
			{
				XenForo._AjaxProgress = false;
				hideOverlay();
			},

			PseudoAjaxStart: function(e)
			{
				showOverlay();
			},

			PseudoAjaxStop: function(e)
			{
				hideOverlay();
			}
		});

		if ($.browser.msie && $.browser.version < 7)
		{
			$(document).bind('scroll', function(e)
			{
				if (overlay && overlay.isOpened() && !overlay.getConf().fixed)
				{
					overlay.getOverlay().css('top', overlay.getConf().top + $(window).scrollTop());
				}
			});
		}
	};

	// *********************************************************************

	/**
	 * Handles the scrollable pagenav gadget, allowing selection of any page between 1 and (end)
	 * while showing only {range*2+1} pages plus first and last at once.
	 *
	 * @param jQuery .pageNav
	 */
	XenForo.PageNav = function($pageNav) { this.__construct($pageNav); };
	XenForo.PageNav.prototype =
	{
		__construct: function($pageNav)
		{
			if (XenForo.isRTL())
			{
				// scrollable doesn't support RTL yet
				return false;
			}

			var $scroller = $pageNav.find('.scrollable');
			if (!$scroller.length)
			{
				return false;
			}

			console.info('PageNav %o', $pageNav);

			this.start = parseInt($pageNav.data('start'));
			this.page  = parseInt($pageNav.data('page'));
			this.end   = parseInt($pageNav.data('end'));
			this.last  = parseInt($pageNav.data('last'));
			this.range = parseInt($pageNav.data('range'));
			this.size  = (this.range * 2 + 1);

			this.baseurl = $pageNav.data('baseurl');
			this.sentinel = $pageNav.data('sentinel');

			$scroller.scrollable(
			{
				speed: XenForo.speed.slow,
				easing: 'easeOutBounce',
				keyboard: false,
				prev: '#nullPrev',
				next: '#nullNext',
				touch: false
			});

			this.api = $scroller.data('scrollable').onBeforeSeek($.context(this, 'beforeSeek'));

			this.$prevButton = $pageNav.find('.PageNavPrev').click($.context(this, 'prevPage'));
			this.$nextButton = $pageNav.find('.PageNavNext').click($.context(this, 'nextPage'));

			this.setControlVisibility(this.api.getIndex(), 0);
		},

		/**
		 * Scrolls to the previous 'page' of page links, creating them if necessary
		 *
		 * @param Event e
		 */
		prevPage: function(e)
		{
			if (this.api.getIndex() == 0 && this.start > 2)
			{
				var i = 0,
					minPage = Math.max(2, (this.start - this.size));

				for (i = this.start - 1; i >= minPage; i--)
				{
					this.prepend(i);
				}

				this.start = minPage;
			}

			this.api.seekTo(Math.max(this.api.getIndex() - this.size, 0));
		},

		/**
		 * Scrolls to the next 'page' of page links, creating them if necessary
		 *
		 * @param Event e
		 */
		nextPage: function(e)
		{
			if ((this.api.getIndex() + 1 + 2 * this.size) > this.api.getSize() && this.end < this.last - 1)
			{
				var i = 0,
					maxPage = Math.min(this.last - 1, this.end + this.size);

				for (i = this.end + 1; i <= maxPage; i++)
				{
					this.append(i);
				}

				this.end = maxPage;
			}

			this.api.seekTo(Math.min(this.api.getSize() - this.size, this.api.getIndex() + this.size));
		},

		/**
		 * Adds an additional page link to the beginning of the scrollable section, out of sight
		 *
		 * @param integer page
		 */
		prepend: function(page)
		{
			this.buildPageLink(page).prependTo(this.api.getItemWrap());

			this.api.next(0);
		},

		/**
		 * Adds an additional page link to the end of the scrollable section, out of sight
		 *
		 * @param integer page
		 */
		append: function(page)
		{
			this.buildPageLink(page).appendTo(this.api.getItemWrap());
		},

		/**
		 * Buids a single page link
		 *
		 * @param integer page
		 *
		 * @return jQuery page link html
		 */
		buildPageLink: function(page)
		{
			return $('<a />',
			{
				href:  this.buildPageUrl(page),
				text:  page,
				'class': (page > 999 ? 'gt999' : '')
			});
		},

		/**
		 * Converts the baseUrl into a page url by replacing the sentinel value
		 *
		 * @param integer page
		 *
		 * @return string page URL
		 */
		buildPageUrl: function(page)
		{
			return this.baseurl
				.replace(this.sentinel, page)
				.replace(escape(this.sentinel), page);
		},

		/**
		 * Runs immediately before the pagenav seeks to a new index,
		 * Toggles visibility of the next/prev controls based on whether they are needed or not
		 *
		 * @param jQuery Event e
		 * @param integer index
		 */
		beforeSeek: function(e, index)
		{
			this.setControlVisibility(index, XenForo.speed.fast);
		},

		/**
		 * Sets the visibility of the scroll controls, based on whether using them would do anything
		 * (hide the prev-page control if on the first page, etc.)
		 *
		 * @param integer Target index of the current scroll
		 *
		 * @param mixed Speed of animation
		 */
		setControlVisibility: function(index, speed)
		{
			if (index == 0 && this.start <= 2)
			{
				this.$prevButton.hide(speed);
			}
			else
			{
				this.$prevButton.show(speed);
			}

			if (this.api.getSize() - this.size <= index && this.end >= this.last - 1)
			{
				this.$nextButton.hide(speed);
			}
			else
			{
				this.$nextButton.show(speed);
			}
		}
	};

	// *********************************************************************

	XenForo.ToggleTrigger = function($trigger) { this.__construct($trigger); };
	XenForo.ToggleTrigger.prototype =
	{
		__construct: function($trigger)
		{
			this.$trigger = $trigger;
			this.loaded = false;
			this.targetVisible = false;
			this.$target = null;

			if ($trigger.data('target'))
			{
				var anchor = $trigger.closest('.ToggleTriggerAnchor');
				if (!anchor.length)
				{
					anchor = $('body');
				}
				var target = anchor.find($trigger.data('target'));
				if (target.length)
				{
					this.$target = target;
					var toggleClass = target.data('toggle-class');
					this.targetVisible = toggleClass ? target.hasClass(toggleClass) : target.is(':visible');
				}
			}

			if ($trigger.data('only-if-hidden')
				&& XenForo.isPositive($trigger.data('only-if-hidden'))
				&& this.targetVisible
			)
			{
				return;
			}

			$trigger.click($.context(this, 'toggle'));
		},

		toggle: function(e)
		{
			e.preventDefault();

			var $trigger = this.$trigger,
				$target = this.$target;

			if ($trigger.data('toggle-if-pointer') && XenForo.isPositive($trigger.data('toggle-if-pointer')))
			{
				if ($trigger.css('cursor') !== 'pointer')
				{
					return;
				}
			}

			if ($trigger.data('toggle-text'))
			{
				var toggleText = $trigger.text();
				$trigger.text($trigger.data('toggle-text'));
				$trigger.data('toggle-text', toggleText);
			}

			if (e.pageX || e.pageY)
			{
				$trigger.blur();
			}

			if ($target)
			{
				$(document).trigger('ToggleTriggerEvent',
				{
					closing: this.targetVisible,
					$target: $target
				});
				
				this.hideSelfIfNeeded();

				var triggerTargetEvent = function() {
					$target.trigger('elementResized');
				};

				var toggleClass = $target.data('toggle-class');
				if (this.targetVisible)
				{					
					if (toggleClass)
					{
						$target.removeClass(toggleClass);
						triggerTargetEvent();
					}
					else
					{
						$target.xfFadeUp(null, triggerTargetEvent);
					}
				}
				else
				{
					if (toggleClass)
					{
						$target.addClass(toggleClass);
						triggerTargetEvent();
					}
					else
					{
						$target.xfFadeDown(null, triggerTargetEvent);
					}
				}
				this.targetVisible = !this.targetVisible;
			}
			else
			{
				this.load();
			}
		},

		hideSelfIfNeeded: function()
		{
			var hideSel = this.$trigger.data('hide');

			if (!hideSel)
			{
				return false;
			}

			var $el;

			if (hideSel == 'self')
			{
				$el = this.$trigger;
			}
			else
			{
				var anchor = this.$trigger.closest('.ToggleTriggerAnchor');
				if (!anchor.length)
				{
					anchor = $('body');
				}
				$el = anchor.find(hideSel);
			}

			$el.hide(); return;
			//$el.xfFadeUp();
		},

		load: function()
		{
			if (this.loading || !this.$trigger.attr('href'))
			{
				return;
			}

			var self = this;

			var $position = $(this.$trigger.data('position'));
			if (!$position.length)
			{
				$position = this.$trigger.closest('.ToggleTriggerAnchor');
				if (!$position.length)
				{
					console.warn("Could not match toggle target position selector %s", this.$trigger.data('position'));
					return false;
				}
			}

			var method = this.$trigger.data('position-method') || 'insertAfter';

			this.loading = true;

			XenForo.ajax(this.$trigger.attr('href'), {}, function(ajaxData) {
				self.loading = false;

				if (XenForo.hasResponseError(ajaxData))
				{
					return false;
				}

				// received a redirect rather than a view - follow it.
				if (ajaxData._redirectStatus && ajaxData._redirectTarget)
				{
					var fn = function()
					{
						XenForo.redirect(ajaxData._redirectTarget);
					};

					if (XenForo._manualDeferOverlay)
					{
						$(document).one('ManualDeferComplete', fn);
					}
					else
					{
						fn();
					}
					return false;
				}

				if (!ajaxData.templateHtml)
				{
					return false;
				}

				new XenForo.ExtLoader(ajaxData, function(data) {
					self.$target = $(data.templateHtml);

					self.$target.xfInsert(method, $position);
					self.targetVisible = true;
					self.hideSelfIfNeeded();
				});
			});
		}
	};

	// *********************************************************************

	/**
	 * Triggers an overlay from a regular link or button
	 * Triggers can provide an optional data-cacheOverlay attribute
	 * to allow multiple trigers to access the same overlay.
	 *
	 * @param jQuery .OverlayTrigger
	 */
	XenForo.OverlayTrigger = function($trigger, options) { this.__construct($trigger, options); };
	XenForo.OverlayTrigger.prototype =
	{
		__construct: function($trigger, options)
		{
			this.$trigger = $trigger.click($.context(this, 'show'));
			this.options = options;
		},

		/**
		 * Begins the process of loading and showing an overlay
		 *
		 * @param event e
		 */
		show: function(e)
		{
			var parentOverlay = this.$trigger.closest('.xenOverlay').data('overlay'),
				cache,
				options,
				isUserLink = (this.$trigger.is('.username, .avatar')),
				cardHref;

			if (!parseInt(XenForo._enableOverlays))
			{
				// if no overlays, use <a href /> by preference
				if (this.$trigger.attr('href'))
				{
					return true;
				}
				else if (this.$trigger.data('href'))
				{
					if (this.$trigger.closest('.AttachmentUploader, #AttachmentUploader').length == 0)
					{
						// open the overlay target as a regular link, unless it's the attachment uploader
						XenForo.redirect(this.$trigger.data('href'));
						return false;
					}
				}
				else
				{
					// can't do anything - should not happen
					console.warn('No alternative action found for OverlayTrigger %o', this.$trigger);
					return true;
				}
			}

			// abort if this is a username / avatar overlay with NoOverlay specified
			if (isUserLink && this.$trigger.hasClass('NoOverlay'))
			{
				return true;
			}

			// abort if the event has a modifier key
			if (e.ctrlKey || e.shiftKey || e.altKey)
			{
				return true;
			}

			// abort if the event is a middle or right-button click
			if (e.which > 1)
			{
				return true;
			}

			if (this.options && this.options.onBeforeTrigger)
			{
				var newE = $.Event();
				newE.clickEvent = e;
				this.options.onBeforeTrigger(newE);
				if (newE.isDefaultPrevented())
				{
					return;
				}
			}

			e.preventDefault();

			if (parentOverlay && parentOverlay.isOpened())
			{
				var self = this;
				parentOverlay.getTrigger().one('onClose', function(innerE) {
					setTimeout(function() {
						self.show(innerE);
					}, 0);
				});
				parentOverlay.getConf().mask.closeSpeed = 0;
				parentOverlay.close();
				return;
			}

			if (!this.OverlayLoader)
			{
				options = (typeof this.options == 'object' ? this.options : {});
				options = $.extend(options, this.$trigger.data('overlayoptions'));

				cache = this.$trigger.data('cacheoverlay');
				if (cache !== undefined)
				{
					if (XenForo.isPositive(cache))
					{
						cache = true;
					}
					else
					{
						cache = false;
						options.onClose = $.context(this, 'deCache');
					}
				}
				else if (this.$trigger.is('input:submit'))
				{
					cache = false;
					options.onClose = $.context(this, 'deCache');
				}

				if (isUserLink && !this.$trigger.hasClass('OverlayTrigger'))
				{
					if (!this.$trigger.data('cardurl') && this.$trigger.attr('href'))
					{
						cardHref = this.$trigger.attr('href').replace(/#.*$/, '');
						if (cardHref.indexOf('?') >= 0)
						{
							cardHref += '&card=1';
						}
						else
						{
							cardHref += '?card=1';
						}

						this.$trigger.data('cardurl', cardHref);
					}

					cache = true;
					options.speed = XenForo.speed.fast;
				}

				this.OverlayLoader = new XenForo.OverlayLoader(this.$trigger, cache, options);
				this.OverlayLoader.load();

				e.preventDefault();
				return true;
			}

			this.OverlayLoader.show();
		},

		deCache: function()
		{
			if (this.OverlayLoader && this.OverlayLoader.overlay)
			{
				console.info('DeCache %o', this.OverlayLoader.overlay.getOverlay());
				this.OverlayLoader.overlay.getTrigger().removeData('overlay');
				this.OverlayLoader.overlay.getOverlay().empty().remove();
			}
			delete(this.OverlayLoader);
		}
	};

	// *********************************************************************

	XenForo.LightBoxTrigger = function($link)
	{
		var containerSelector = '*[data-author]';

		new XenForo.OverlayTrigger($link.data('cacheoverlay', 1),
		{
			top: 15,
			speed: 1, // prevents the onLoad event being fired prematurely
			closeSpeed: 0,
			closeOnResize: true,
			mask:
			{
				color: 'rgb(0,0,0)',
				opacity: 0.6,
				loadSpeed: 0,
				closeSpeed: 0
			},
			onBeforeTrigger: function(e)
			{
				if ($(window).height() < 500)
				{
					e.preventDefault();
				}
			},
			onBeforeLoad: function(e)
			{
				if (typeof XenForo.LightBox == 'function')
				{
					if (XenForo._LightBoxObj === undefined)
					{
						XenForo._LightBoxObj = new XenForo.LightBox(this, containerSelector);
					}

					var $imageContainer = (parseInt(XenForo._lightBoxUniversal)
						? $('body')
						: $link.closest(containerSelector));

					console.info('Opening LightBox for %o using %s', $imageContainer, containerSelector);

					XenForo._LightBoxObj.setThumbStrip($imageContainer);
					XenForo._LightBoxObj.setImage(this.getTrigger().find('img:first'));

					$(document).triggerHandler('LightBoxOpening');
				}

				return true;
			},
			onLoad: function(e)
			{
				XenForo._LightBoxObj.setDimensions(true);
				XenForo._LightBoxObj.bindNav();

				return true;
			},
			onClose: function(e)
			{
				XenForo._LightBoxObj.setImage();
				XenForo._LightBoxObj.unbindNav();

				return true;
			}
		});
	};

	// *********************************************************************

	XenForo.OverlayLoaderCache = {};

	/**
	 * Loads HTML and related external resources for an overlay
	 *
	 * @param jQuery Overlay trigger object
	 * @param boolean If true, cache the overlay HTML for this URL
	 * @param object Object of options for the overlay
	 */
	XenForo.OverlayLoader = function($trigger, cache, options)
	{
		this.__construct($trigger, options, cache);
	};
	XenForo.OverlayLoader.prototype =
	{
		__construct: function($trigger, options, cache)
		{
			this.$trigger = $trigger;
			this.cache = cache;
			this.options = options;
		},

		/**
		 * Initiates the loading of the overlay, or returns it from cache
		 *
		 * @param function Callback to run on successful load
		 */
		load: function(callback)
		{
			// special case for submit buttons
			if (this.$trigger.is('input:submit'))
			{
				this.cache = false;

				if (!this.xhr)
				{
					var $form = this.$trigger.closest('form'),

					serialized = $form.serializeArray();

					serialized.push(
					{
						name: this.$trigger.attr('name'),
						value: this.$trigger.attr('value')
					});

					this.xhr = XenForo.ajax(
						$form.attr('action'),
						serialized,
						$.context(this, 'loadSuccess')
					);
				}

				return;
			}

			//TODO: ability to point to extant overlay HTML, rather than loading via AJAX
			this.href = this.$trigger.data('cardurl') || this.$trigger.data('href') || this.$trigger.attr('href');

			if (!this.href)
			{
				console.warn('No overlay href found for control %o', this.$trigger);
				return false;
			}

			console.info('OverlayLoader for %s', this.href);

			this.callback = callback;

			if (this.cache && XenForo.OverlayLoaderCache[this.href])
			{
				this.createOverlay(XenForo.OverlayLoaderCache[this.href]);
			}
			else if (!this.xhr)
			{
				this.xhr = XenForo.ajax(
					this.href, '',
					$.context(this, 'loadSuccess'), { type: 'GET' }
				);
			}
		},

		/**
		 * Handles the returned ajaxdata from an overlay xhr load,
		 * Stores the template HTML then inits externals (js, css) loading
		 *
		 * @param object ajaxData
		 * @param string textStatus
		 */
		loadSuccess: function(ajaxData, textStatus)
		{
			delete(this.xhr);

			if (XenForo.hasResponseError(ajaxData))
			{
				return false;
			}

			// received a redirect rather than a view - follow it.
			if (ajaxData._redirectStatus && ajaxData._redirectTarget)
			{
				var fn = function()
				{
					XenForo.redirect(ajaxData._redirectTarget);
				};

				if (XenForo._manualDeferOverlay)
				{
					$(document).one('ManualDeferComplete', fn);
				}
				else
				{
					fn();
				}
				return false;
			}

			this.options.title = ajaxData.h1 || ajaxData.title;

			new XenForo.ExtLoader(ajaxData, $.context(this, 'createOverlay'));
		},

		/**
		 * Creates an overlay containing the appropriate template HTML,
		 * runs the callback specified in .load() and then shows the overlay.
		 *
		 * @param jQuery Cached $overlay object
		 */
		createOverlay: function($overlay)
		{
			var contents = ($overlay && $overlay.templateHtml) ? $overlay.templateHtml : $overlay;
			this.overlay = XenForo.createOverlay(this.$trigger, contents, this.options);

			if (this.cache)
			{
				XenForo.OverlayLoaderCache[this.href] = this.overlay.getOverlay();
			}

			if (typeof this.callback == 'function')
			{
				this.callback();
			}

			this.show();
		},

		/**
		 * Shows a finished overlay
		 */
		show: function()
		{
			if (!this.overlay)
			{
				console.warn('Attempted to call XenForo.OverlayLoader.show() for %s before overlay is created', this.href);
				this.load(this.callback);
				return;
			}

			this.overlay.load();
			$(document).trigger({
				type: 'XFOverlay',
				overlay: this.overlay,
				trigger: this.$trigger
			});
		}
	};

	// *********************************************************************

	XenForo.LoginBar = function($loginBar)
	{
		var $form = $('#login').appendTo($loginBar.find('.pageContent')),

		/**
		 * Opens the login form
		 *
		 * @param event
		 */
		openForm = function(e)
		{
			e.preventDefault();

			XenForo.chromeAutoFillFix($form);

			$form.xfSlideIn(XenForo.speed.slow, 'easeOutBack', function()
			{
				$('#LoginControl').select();

				$loginBar.expose($.extend(XenForo._overlayConfig.mask,
				{
					loadSpeed: XenForo.speed.slow,
					onBeforeLoad: function(e)
					{
						$form.css('outline', '0px solid black');
					},
					onLoad: function(e)
					{
						$form.css('outline', '');
					},
					onBeforeClose: function(e)
					{
						closeForm(false, true);
						return true;
					}
				}));
			});
		},

		/**
		 * Closes the login form
		 *
		 * @param event
		 * @param boolean
		 */
		closeForm = function(e, isMaskClosing)
		{
			if (e) e.target.blur();

			$form.xfSlideOut(XenForo.speed.fast);

			if (!isMaskClosing && $.mask)
			{
				$.mask.close();
			}
		};

		/**
		 * Toggles the login form
		 */
		$('label[for="LoginControl"]').click(function(e)
		{
			if ($(this).closest('#login').length == 0)
			{
				e.preventDefault();

				if ($form._xfSlideWrapper(true))
				{
					closeForm(e);
				}
				else
				{
					$(XenForo.getPageScrollTagName()).scrollTop(0);

					openForm(e);
				}
			}
		});

		/**
		 * Changes the text of the Log in / Sign up submit button depending on state
		 */
		$loginBar.delegate('input[name="register"]', 'click', function(e)
		{
			var $button = $form.find('input.button.primary'),
				register = $form.find('input[name="register"]:checked').val();

			$form.find('input.button.primary').val(register == '1'
				? $button.data('signupphrase')
				: $button.data('loginphrase'));
			
			$form.find('label.rememberPassword').css('visibility', (register == '1' ? 'hidden' : 'visible'));
		});

		// close form if any .click elements within it are clicked
		$loginBar.delegate('.close', 'click', closeForm);
	};

	// *********************************************************************

	XenForo.QuickSearch = function($form)
	{
		var runCount = 0;

		$('#QuickSearchPlaceholder').click(function(e) {
			e.preventDefault();
			setTimeout(function() {
				$('#QuickSearch').addClass('show');
				$('#QuickSearchPlaceholder').addClass('hide');
				$('#QuickSearchQuery').focus();
			}, 0);
		});

		$('#QuickSearchQuery').focus(function(focusEvent)
		{
			runCount++;
			console.log('Show quick search menu (%s)', runCount);

			if (runCount == 1 && $.browser.msie && $.browser.version < 9)
			{
				// IE 8 doesn't auto submit here...
				$form.find('input').keydown(function(e){
			        if (e.keyCode == 13) {
			            $(this).parents('form').submit();
			            return false;
			        }
			    });
			}

			if (runCount == 1)
			{
				$(XenForo._isWebkitMobile ? document.body.children : document).on('click', function(clickEvent)
				{
					if (!$(clickEvent.target).closest('#QuickSearch').length)
					{
						console.log('Hide quick search menu');

						$('#QuickSearch').removeClass('show');
						$('#QuickSearchPlaceholder').removeClass('hide');

						$form.find('.secondaryControls').slideUp(XenForo.speed.xfast, function()
						{
							$form.removeClass('active');
							if ($.browser.msie)
							{
								$('body').css('zoom', 1);
								setTimeout(function() { $('body').css('zoom', ''); }, 100);
							}
						});
					}
				});
			}

			$form.addClass('active');
			$form.find('.secondaryControls').slideDown(0);
		});
	};

	// *********************************************************************

	XenForo.configureTooltipRtl = function(config)
	{
		if (config.offset !== undefined)
		{
			config.offset = XenForo.switchOffsetRTL(config.offset);
		}

		if (config.position !== undefined)
		{
			config.position = XenForo.switchStringRTL(config.position);
		}

		return config;
	};

	/**
	 * Wrapper for jQuery Tools Tooltip
	 *
	 * @param jQuery .Tooltip
	 */
	XenForo.Tooltip = function($element)
	{
		var tipClass = String($element.data('tipclass') || ''),
			isFlipped = /(\s|^)flipped(\s|$)/.test(tipClass),
			offsetY = parseInt($element.data('offsety'), 10) || -6,
			innerWidth = $element.is(':visible') ? $element.innerWidth() : 0,
			dataOffsetX = parseInt($element.data('offsetx'), 10) || 0,
			offsetX = dataOffsetX + innerWidth * (isFlipped ? 1 : -1),
			title = XenForo.htmlspecialchars($element.attr('title'));

		var onBeforeShow = null;

		if (innerWidth <= 0)
		{
			var positionUpdated = false;
			onBeforeShow = function()
			{
				if (positionUpdated)
				{
					return;
				}

				var width = $element.innerWidth();
				if (width <= 0)
				{
					return;
				}
				positionUpdated = true;

				offsetX = dataOffsetX + width * (isFlipped ? 1 : -1);
				$element.data('tooltip').getConf().offset = XenForo.switchOffsetRTL([ offsetY, offsetX ]);
			};
		}

		$element.attr('title', title).tooltip(XenForo.configureTooltipRtl(
		{
			delay: 0,
			position: $element.data('position') || 'top ' + (isFlipped ? 'left' : 'right'),
			offset: [ offsetY, offsetX ],
			tipClass: 'xenTooltip ' + tipClass,
			layout: '<div><span class="arrow" /></div>',
			onBeforeShow: onBeforeShow
		}));
	};

	// *********************************************************************

	XenForo.StatusTooltip = function($element)
	{
		if ($element.attr('title'))
		{
			var title = XenForo.htmlspecialchars($element.attr('title'));

			$element.attr('title', title).tooltip(XenForo.configureTooltipRtl(
			{
				effect: 'slide',
				slideOffset: 30,
				position: 'bottom right',
				offset: [ 10, 10 ],
				tipClass: 'xenTooltip statusTip',
				layout: '<div><span class="arrow" /></div>'
			}));
		}
	};

	// *********************************************************************

	XenForo.NodeDescriptionTooltip = function($title)
	{
		var description = $title.data('description');

		if (description && $(description).length)
		{
			var $description = $(description)
				.addClass('xenTooltip nodeDescriptionTip')
				.appendTo('body')
				.append('<span class="arrow" />');

			$title.tooltip(XenForo.configureTooltipRtl(
			{
				effect: 'slide',
				slideOffset: 30,
				offset: [ 30, 10 ],
				slideInSpeed: XenForo.speed.xfast,
				slideOutSpeed: 50 * XenForo._animationSpeedMultiplier,

				/*effect: 'fade',
				fadeInSpeed: XenForo.speed.xfast,
				fadeOutSpeed: XenForo.speed.xfast,*/

				predelay: 250,
				position: 'bottom right',
				tip: description,

				onBeforeShow: function()
				{
					if (!$title.data('tooltip-shown'))
					{
						if ($(window).width() < 600)
						{
							var conf = $title.data('tooltip').getConf();
							conf.slideOffset = 0;
							conf.effect = 'toggle';
							conf.offset = [20, -$title.width()];
							conf.position = ['top', 'right'];

							if (XenForo.isRTL())
							{
								conf.offset[1] *= -1;
								conf.position[1] = 'left';
							}

							$description.addClass('arrowBottom');
						}

						$title.data('tooltip-shown', true);
					}
				}
			}));
			$title.click(function() { $(this).data('tooltip').hide(); });
		}
	};

	// *********************************************************************

	XenForo.AccountMenu = function($menu)
	{
		$menu.find('.submitUnit').hide();

		$menu.find('.StatusEditor').focus(function(e)
		{
			if ($menu.is(':visible'))
			{
				$menu.find('.submitUnit').show();
			}
		});
	};

	// *********************************************************************

	XenForo.FollowLink = function($link)
	{
		$link.click(function(e)
		{
			e.preventDefault();

			$link.get(0).blur();

			XenForo.ajax(
				$link.attr('href'),
				{ _xfConfirm: 1 },
				function (ajaxData, textStatus)
				{
					if (XenForo.hasResponseError(ajaxData))
					{
						return false;
					}

					$link.xfFadeOut(XenForo.speed.fast, function()
					{
						$link
							.attr('href', ajaxData.linkUrl)
							.html(ajaxData.linkPhrase)
							.xfFadeIn(XenForo.speed.fast);
					});
				}
			);
		});
	};

	// *********************************************************************

	/**
	 * Allows relative hash links to smoothly scroll into place,
	 * Primarily used for 'x posted...' messages on bb code quote.
	 *
	 * @param jQuery a.AttributionLink
	 */
	XenForo.AttributionLink = function($link)
	{
		$link.click(function(e)
		{
			if ($(this.hash).length)
			{
				try
				{
					var hash = this.hash,
						top = $(this.hash).offset().top,
						scroller = XenForo.getPageScrollTagName();

					if ("pushState" in window.history)
					{
						window.history.pushState({}, '', window.location.toString().replace(/#.*$/, '') + hash);
					}

					$(scroller).animate({ scrollTop: top }, XenForo.speed.normal, 'easeOutBack', function()
					{
						if (!window.history.pushState)
						{
							window.location.hash = hash;
						}
					});
				}
				catch(e)
				{
					window.location.hash = this.hash;
				}

				e.preventDefault();
			}
		});
	};

	// *********************************************************************

	/**
	 * Allows clicks on one element to trigger the click event of another
	 *
	 * @param jQuery .ClickProxy[rel="{selectorForTarget}"]
	 *
	 * @return boolean false - prevents any direct action for the proxy element on click
	 */
	XenForo.ClickProxy = function($element)
	{
		$element.click(function(e)
		{
			$($element.attr('rel')).click();

			if (!$element.data('allowdefault'))
			{
				return false;
			}
		});
	};

	// *********************************************************************

	/**
	 * ReCaptcha wrapper
	 */
	XenForo.ReCaptcha = function($captcha) { this.__construct($captcha); };
	XenForo.ReCaptcha.prototype =
	{
		__construct: function($captcha)
		{
			if (XenForo.ReCaptcha.instance)
			{
				XenForo.ReCaptcha.instance.remove();
			}
			XenForo.ReCaptcha.instance = this;

			this.publicKey = $captcha.data('publickey');
			if (!this.publicKey)
			{
				return;
			}

			$captcha.siblings('noscript').remove();

			$captcha.uniqueId();
			this.$captcha = $captcha;
			this.type = 'image';

			$captcha.find('.ReCaptchaReload').click($.context(this, 'reload'));
			$captcha.find('.ReCaptchaSwitch').click($.context(this, 'switchType'));

			this.load();
			$(window).unload($.context(this, 'remove'));

			$captcha.closest('form.AutoValidator').bind(
			{
				AutoValidationDataReceived: $.context(this, 'reload')
			});
		},

		load: function()
		{
			if (window.Recaptcha)
			{
				this.create();
			}
			else
			{
				var f = $.context(this, 'create'),
					delay = ($.browser.msie && $.browser.version <= 6 ? 250 : 0); // helps IE6 loading

				$.getScript('//www.google.com/recaptcha/api/js/recaptcha_ajax.js',
					function() { setTimeout(f, delay); }
				);
			}
		},

		create: function()
		{
			var $c = this.$captcha;

			window.Recaptcha.create(this.publicKey, $c.attr('id'),
			{
				theme: 'custom',
				callback: function() {
					$c.show();
					$('#ReCaptchaLoading').remove();
					// webkit seems to overwrite this value using the back button
					$('#recaptcha_challenge_field').val(window.Recaptcha.get_challenge());
				}
			});
		},

		reload: function(e)
		{
			if (!window.Recaptcha)
			{
				return;
			}

			if (!$(e.target).is('form'))
			{
				e.preventDefault();
			}
			window.Recaptcha.reload();
		},

		switchType: function(e)
		{
			e.preventDefault();
			this.type = (this.type == 'image' ? 'audio' : 'image');
			window.Recaptcha.switch_type(this.type);
		},

		remove: function()
		{
			this.$captcha.empty().remove();
			if (window.Recaptcha)
			{
				window.Recaptcha.destroy();
			}
		}
	};
	XenForo.ReCaptcha.instance = null;

	// *********************************************************************

	XenForo.SolveMediaCaptcha = function($captcha) { this.__construct($captcha); };
	XenForo.SolveMediaCaptcha.prototype =
	{
		__construct: function($captcha)
		{
			if (XenForo.SolveMediaCaptcha.instance)
			{
				XenForo.SolveMediaCaptcha.instance.remove();
			}
			XenForo.SolveMediaCaptcha.instance = this;

			this.cKey = $captcha.data('c-key');
			if (!this.cKey)
			{
				return;
			}

			$captcha.siblings('noscript').remove();

			$captcha.uniqueId();
			this.$captcha = $captcha;
			this.type = 'image';

			this.load();
			$(window).unload($.context(this, 'remove'));

			$captcha.closest('form.AutoValidator').bind(
			{
				AutoValidationDataReceived: $.context(this, 'reload')
			});
		},

		load: function()
		{
			if (window.ACPuzzle)
			{
				this.create();
			}
			else
			{
				var prefix = window.location.protocol == 'https:' ? 'https://api-secure' : 'http://api';

				window.ACPuzzleOptions = {
					onload: $.context(this, 'create')
				};
				XenForo.loadJs(prefix + '.solvemedia.com/papi/challenge.ajax');
			}
		},

		create: function()
		{
			var $c = this.$captcha;

			window.ACPuzzle.create(this.cKey, $c.attr('id'), {
				theme: $c.data('theme') || 'white',
				lang: $('html').attr('lang').substr(0, 2) || 'en'
			});
		},

		reload: function(e)
		{
			if (!window.ACPuzzle)
			{
				return;
			}

			if (!$(e.target).is('form'))
			{
				e.preventDefault();
			}
			window.ACPuzzle.reload();
		},

		remove: function()
		{
			this.$captcha.empty().remove();
			if (window.ACPuzzle)
			{
				window.ACPuzzle.destroy();
			}
		}
	};
	XenForo.SolveMediaCaptcha.instance = null;

	// *********************************************************************

	XenForo.KeyCaptcha = function($captcha) { this.__construct($captcha); };
	XenForo.KeyCaptcha.prototype =
	{
		__construct: function($captcha)
		{
			this.$captcha = $captcha;

			this.$form = $captcha.closest('form');
			this.$form.uniqueId();

			this.$codeEl = this.$form.find('input[name=keycaptcha_code]');
			this.$codeEl.uniqueId();

			this.load();
			$captcha.closest('form.AutoValidator').bind({
				AutoValidationDataReceived: $.context(this, 'reload')
			});
		},

		load: function()
		{
			if (window.s_s_c_onload)
			{
				this.create();
			}
			else
			{
				var $captcha = this.$captcha;

				window.s_s_c_user_id = $captcha.data('user-id');
				window.s_s_c_session_id =  $captcha.data('session-id');
				window.s_s_c_captcha_field_id = this.$codeEl.attr('id');
				window.s_s_c_submit_button_id = 'sbutton-#-r';
				window.s_s_c_web_server_sign =  $captcha.data('sign');
				window.s_s_c_web_server_sign2 =  $captcha.data('sign2');
				document.s_s_c_element = this.$form[0];
				document.s_s_c_debugmode = 1;

				var $div = $('#div_for_keycaptcha');
				if (!$div.length)
				{
					$('body').append('<div id="div_for_keycaptcha" />');
				}

				XenForo.loadJs('https://backs.keycaptcha.com/swfs/cap.js');
			}
		},

		create: function()
		{
			window.s_s_c_onload(this.$form.attr('id'), this.$codeEl.attr('id'), 'sbutton-#-r');
		},

		reload: function(e)
		{
			if (!window.s_s_c_onload)
			{
				return;
			}

			if (!$(e.target).is('form'))
			{
				e.preventDefault();
			}
			this.load();
		}
	};

	// *********************************************************************

	/**
	 * Loads a new (non-ReCaptcha) CAPTCHA upon verification failure
	 *
	 * @param jQuery #Captcha
	 */
	XenForo.Captcha = function($container)
	{
		$container.closest('form').one('AutoValidationError', function(e)
		{
			$container.fadeTo(XenForo.speed.fast, 0.5);

			XenForo.ajax($container.data('source'), {}, function(ajaxData, textStatus)
			{
				if (XenForo.hasResponseError(ajaxData))
				{
					return false;
				}

				if (XenForo.hasTemplateHtml(ajaxData))
				{
					$container.xfFadeOut(XenForo.speed.xfast, function()
					{
						$(ajaxData.templateHtml).xfInsert('replaceAll', $container, 'xfFadeIn', XenForo.speed.xfast);
					});
				}
			});
		});
	};

	// *********************************************************************

	/**
	 * Handles resizing of BB code [img] tags that would overflow the page
	 *
	 * @param jQuery img.bbCodeImage
	 */
	XenForo.BbCodeImage = function($image) { this.__construct($image); };
	XenForo.BbCodeImage.prototype =
	{
		__construct: function($image)
		{
			this.$image = $image;
			this.actualWidth = 0;

			if ($image.closest('a').length)
			{
				return;
			}

			$image
				.attr('title', XenForo.phrases.click_image_show_full_size_version || 'Show full size')
				.click($.context(this, 'toggleFullSize'));

			if (!XenForo.isTouchBrowser())
			{
				this.$image.tooltip(XenForo.configureTooltipRtl({
					effect: 'slide',
					slideOffset: 30,
					position: 'top center',
					offset: [ 45, 0 ],
					tipClass: 'xenTooltip bbCodeImageTip',
					onBeforeShow: $.context(this, 'isResized'),
					onShow: $.context(this, 'addTipClick')
				}));
			}

			if (!this.getImageWidth())
			{
				var src = $image.attr('src');

				$image.bind({
					load: $.context(this, 'getImageWidth')
				});
				//$image.attr('src', 'about:blank');
				$image.attr('src', src);
			}
		},

		/**
		 * Attempts to store the un-resized width of the image
		 *
		 * @return integer
		 */
		getImageWidth: function()
		{
			this.$image.css({'max-width': 'none', 'max-height': 'none'});
			this.actualWidth = this.$image.width();
			this.$image.css({'max-width': '', 'max-height': ''});

			//console.log('BB Code Image %o has width %s', this.$image, this.actualWidth);

			return this.actualWidth;
		},

		/**
		 * Shows and hides a full-size version of the image
		 *
		 * @param event
		 */
		toggleFullSize: function(e)
		{
			if (this.actualWidth == 0)
			{
				this.getImageWidth();
			}
			
			var currentWidth = this.$image.width(),
				offset, cssOffset, scale,
				scrollLeft, scrollTop,
				layerX, layerY,
				$fullSizeImage,
				speed = window.navigator.userAgent.match(/Android|iOS|iPhone|iPad|Mobile Safari/i) ? 0 : XenForo.speed.normal,
				easing = 'easeInOutQuart';

			if (this.actualWidth > currentWidth)
			{
				offset = this.$image.offset();
				cssOffset = offset;
				scale = this.actualWidth / currentWidth;
				layerX = e.pageX - offset.left;
				layerY = e.pageY - offset.top;

				if (XenForo.isRTL())
				{
					cssOffset.right = $('html').width() - cssOffset.left - currentWidth;
					cssOffset.left = 'auto';
				}

				$fullSizeImage = $('<img />', { src: this.$image.attr('src') })
					.addClass('bbCodeImageFullSize')
					.css('width', currentWidth)
					.css(cssOffset)
					.click(function()
					{
						$(this).remove();
						$(XenForo.getPageScrollTagName()).scrollLeft(0).scrollTop(offset.top);
					})
					.appendTo('body')
					.animate({ width: this.actualWidth }, speed, easing);

				// remove full size image if an overlay is about to open
				$(document).one('OverlayOpening', function()
				{
					$fullSizeImage.remove();
				});
				
				// remove full-size image if the source image is contained by a ToggleTrigger target that is closing 
				$(document).bind('ToggleTriggerEvent', $.context(function(e, args)
				{				
					if (args.closing && args.$target.find(this.$image).length)
					{
						console.info('Target is parent of this image %o', this.$image);
						$fullSizeImage.remove();
					}
				}, this));

				if (e.target == this.$image.get(0))
				{
					scrollLeft = offset.left + (e.pageX - offset.left) * scale - $(window).width() / 2;
					scrollTop = offset.top + (e.pageY - offset.top) * scale - $(window).height() / 2;
				}
				else
				{
					scrollLeft = offset.left + (this.actualWidth / 2) - $(window).width() / 2;
					scrollTop = offset.top + (this.$image.height() * scale / 2) - $(window).height() / 2;
				}

				$(XenForo.getPageScrollTagName()).animate(
				{
					scrollLeft: scrollLeft,
					scrollTop: scrollTop
				}, speed, easing, $.context(function()
				{
					var tooltip = this.$image.data('tooltip');
					if (tooltip)
					{
						tooltip.hide();
					}
				}, this));
			}
			else
			{
				console.log('BBCodeImage: this.actualWidth = %d, currentWidth = %d', this.actualWidth, currentWidth);
			}
		},

		isResized: function(e)
		{
			var width = this.$image.width();

			if (!width)
			{
				return false;
			}

			if (this.getImageWidth() <= width)
			{
				//console.log('Image is not resized %o', this.$image);
				return false;
			}
		},

		addTipClick: function(e)
		{
			if (!this.tipClickAdded)
			{
				$(this.$image.data('tooltip').getTip()).click($.context(this, 'toggleFullSize'));
				this.tipClickAdded = true;
			}
		}
	};

	// *********************************************************************

	/**
	 * Wrapper for the jQuery Tools Tabs system
	 *
	 * @param jQuery .Tabs
	 */
	XenForo.Tabs = function($tabContainer) { this.__construct($tabContainer); };
	XenForo.Tabs.prototype =
	{
		__construct: function($tabContainer)
		{
			// var useHistory = XenForo.isPositive($tabContainer.data('history'));
			// TODO: disabled until base tag issues are resolved
			var useHistory = false;

			this.$tabContainer = $tabContainer;
			this.$panes = $($tabContainer.data('panes'));

			/*if (useHistory)
			{
				$tabContainer.find('a[href]').each(function()
				{
					var $this = $(this), hrefParts = $this.attr('href').split('#');
					if (hrefParts[1] && location.pathname == hrefParts[0])
					{
						$this.attr('href', '#' + hrefParts[1]);
					}
				});
			}*/

			var $tabs = $tabContainer.find('a');
			if (!$tabs.length)
			{
				$tabs = $tabContainer.children();
			}

			var $active = $tabs.filter('.active'),
				initialIndex = 0;

			if ($active.length)
			{
				$tabs.each(function() {
					if (this == $active.get(0))
					{
						return false;
					}

					initialIndex++;
				});
			}

			if (window.location.hash.length > 1)
			{
				var id = window.location.hash.substr(1),
					matchIndex = -1,
					matched = false;

				this.$panes.each(function() {
					matchIndex++;
					if ($(this).attr('id') === id)
					{
						matched = true;
						return false;
					}
					return true;
				});
				if (matched)
				{
					initialIndex = matchIndex;
				}
			}

			$tabContainer.tabs(this.$panes, {
				current: 'active',
				history: useHistory,
				initialIndex: initialIndex,
				onBeforeClick: $.context(this, 'onBeforeClick')
			});
			this.api = $tabContainer.data('tabs');
		},

		getCurrentTab: function()
		{
			return this.api.getIndex();
		},

		click: function(index)
		{
			this.api.click(index);
		},

		onBeforeClick: function(e, index)
		{
			this.$tabContainer.children().each(function(i)
			{
				if (index == i)
				{
					$(this).addClass('active');
				}
				else
				{
					$(this).removeClass('active');
				}
			});

			var $pane = $(this.$panes.get(index)),
				loadUrl = $pane.data('loadurl');

			if (loadUrl)
			{
				$pane.data('loadurl', '');

				XenForo.ajax(loadUrl, {}, function(ajaxData)
				{
					if (XenForo.hasTemplateHtml(ajaxData) || XenForo.hasTemplateHtml(ajaxData, 'message'))
					{
						new XenForo.ExtLoader(ajaxData, function(ajaxData)
						{
							var $html;

							if (ajaxData.templateHtml)
							{
								$html = $(ajaxData.templateHtml);
							}
							else if (ajaxData.message)
							{
								$html = $('<div class="section" />').html(ajaxData.message);
							}

							$pane.html('');
							if ($html)
							{
								$html.xfInsert('appendTo', $pane, 'xfFadeIn', 0);
							}
						});
					}
					else if (XenForo.hasResponseError(ajaxData))
					{
						return false;
					}
				}, {type: 'GET'});
			}
		}
	};

	// *********************************************************************

	/**
	 * Handles a like / unlike link being clicked
	 *
	 * @param jQuery a.LikeLink
	 */
	XenForo.LikeLink = function($link)
	{
		$link.click(function(e)
		{
			e.preventDefault();

			var $link = $(this);

			XenForo.ajax(this.href, {}, function(ajaxData, textStatus)
			{
				if (XenForo.hasResponseError(ajaxData))
				{
					return false;
				}

				$link.stop(true, true);

				if (ajaxData.term) // term = Like / Unlike
				{
					$link.find('.LikeLabel').html(ajaxData.term);

					if (ajaxData.cssClasses)
					{
						$.each(ajaxData.cssClasses, function(className, action)
						{
							$link[action == '+' ? 'addClass' : 'removeClass'](className);
						});
					}
				}

				if (ajaxData.templateHtml === '')
				{
					$($link.data('container')).xfFadeUp(XenForo.speed.fast, function()
					{
						$(this).empty().xfFadeDown(0);
					});
				}
				else
				{
					var $container    = $($link.data('container')),
						$likeText     = $container.find('.LikeText'),
						$templateHtml = $(ajaxData.templateHtml);

					if ($likeText.length)
					{
						// we already have the likes_summary template in place, so just replace the text
						$likeText.xfFadeOut(50, function()
						{
							var textContainer = this.parentNode;

							$(this).remove();

							$templateHtml.find('.LikeText').xfInsert('appendTo', textContainer, 'xfFadeIn', 50);
						});
					}
					else
					{
						new XenForo.ExtLoader(ajaxData, function()
						{
							$templateHtml.xfInsert('appendTo', $container);
						});
					}
				}
			});
		});
	};

	// *********************************************************************

	XenForo.Facebook =
	{
		initialized: false,
		appId: '',
		fbUid: 0,
		authResponse: {},
		locale: 'en-US',

		init: function()
		{
			if (XenForo.Facebook.initialized)
			{
				return;
			}
			XenForo.Facebook.initialized = true;

			$(document.body).append($('<div id="fb-root" />'));

			var fbInfo = {
				version: 'v2.0',
				xfbml: true,
				oauth: true,
				channelUrl: XenForo.canonicalizeUrl('fb_channel.php?l=' + XenForo.Facebook.locale)
			};
			if (XenForo.Facebook.appId)
			{
				fbInfo.appId = XenForo.Facebook.appId;
			}

			FB.init(fbInfo);
			if (XenForo.Facebook.appId && XenForo.Facebook.fbUid)
			{
				FB.Event.subscribe('auth.sessionChange', XenForo.Facebook.sessionChange);
				FB.getLoginStatus(XenForo.Facebook.sessionChange);

				if (XenForo.visitor.user_id )
				{
					$(document).delegate('a.LogOut:not(.OverlayTrigger)', 'click', XenForo.Facebook.eLogOutClick);
				}
			}
		},

		start: function()
		{
			var cookieUid = $.getCookie('fbUid');
			if (cookieUid && cookieUid.length)
			{
				XenForo.Facebook.fbUid = parseInt(cookieUid, 10);
			}

			if ($('.fb-post').length)
			{
				XenForo.Facebook.forceInit = true;
			}

			if (!XenForo.Facebook.forceInit && (!XenForo.Facebook.appId || !XenForo.Facebook.fbUid))
			{
				return;
			}

			XenForo.Facebook.load();
		},

		load: function()
		{
			if (XenForo.Facebook.initialized)
			{
				FB.XFBML.parse();
			}

			XenForo.Facebook.locale = $('html').attr('lang').replace('-', '_');
			if (!XenForo.Facebook.locale)
			{
				XenForo.Facebook.locale = 'en_US';
			}

			var e = document.createElement('script'),
				locale = XenForo.Facebook.locale.replace('-', '_');
			e.src = '//connect.facebook.net/' + XenForo.Facebook.locale + '/sdk.js';
			e.async = true;

			window.fbAsyncInit = XenForo.Facebook.init;
			document.getElementsByTagName('head')[0].appendChild(e);
		},

		sessionChange: function(response)
		{
			if (!XenForo.Facebook.fbUid)
			{
				return;
			}

			var authResponse = response.authResponse, visitor = XenForo.visitor;
			XenForo.Facebook.authResponse = authResponse;

			if (authResponse && !visitor.user_id)
			{
				// facebook user, connect!
				XenForo.alert(XenForo.phrases.logging_in + '...', '', 8000);
				setTimeout(function() {
					XenForo.redirect(
						'index.php?register/facebook&t=' + escape(authResponse.accessToken)
						+ '&redirect=' + escape(window.location)
					);
				}, 250);
			}
			else if (!authResponse && visitor.user_id)
			{
				// facebook user that is no longer logged in - log out
				XenForo.Facebook.logout(null, true);
			}
		},

		logout: function(fbData, returnPage)
		{
			var location = $('a.LogOut:not(.OverlayTrigger)').attr('href');
			if (!location)
			{
				location = 'index.php?logout/&_xfToken=' + XenForo._csrfToken;
			}
			if (returnPage)
			{
				location += (location.indexOf('?') >= 0 ? '&' : '?') + 'redirect=' + escape(window.location);
			}
			XenForo.redirect(location);
		},

		eLogOutClick: function(e)
		{
			if (XenForo.Facebook.authResponse && XenForo.Facebook.authResponse.userID)
			{
				FB.logout(XenForo.Facebook.logout);
				return false;
			}
		}
	};

	// *********************************************************************
	/**
	 * Turns an :input into a Prompt
	 *
	 * @param {Object} :input[placeholder]
	 */
	XenForo.Prompt = function($input)
	{
		this.__construct($input);
	};
	if ('placeholder' in document.createElement('input'))
	{
		// native placeholder support
		XenForo.Prompt.prototype =
		{
			__construct: function($input)
			{
				this.$input = $input;
			},

			isEmpty: function()
			{
				return (this.$input.strval() === '');
			},

			val: function(value, focus)
			{
				if (value === undefined)
				{
					return this.$input.val();
				}
				else
				{
					if (focus)
					{
						this.$input.focus();
					}

					return this.$input.val(value);
				}
			}
		};
	}
	else
	{
		// emulate placeholder support
		XenForo.Prompt.prototype =
		{
			__construct: function($input)
			{
				console.log('Emulating placeholder behaviour for %o', $input);

				this.placeholder = $input.attr('placeholder');

				this.$input = $input.bind(
				{
					focus: $.context(this, 'setValueMode'),
					blur:  $.context(this, 'setPromptMode')
				});

				this.$input.closest('form').bind(
				{
					submit: $.context(this, 'eFormSubmit'),
					AutoValidationBeforeSubmit: $.context(this, 'eFormSubmit'),
					AutoValidationComplete: $.context(this, 'eFormSubmitted')
				});

				this.setPromptMode();
			},

			/**
			 * If the prompt box contains no text, or contains the prompt text (only) it is 'empty'
			 *
			 * @return boolean
			 */
			isEmpty: function()
			{
				var val = this.$input.val();

				return (val === '' || val == this.placeholder);
			},

			/**
			 * When exiting the prompt box, update its contents if necessary
			 */
			setPromptMode: function()
			{
				if (this.isEmpty())
				{
					this.$input.val(this.placeholder).addClass('prompt');
				}
			},

			/**
			 * When entering the prompt box, clear its contents if it is 'empty'
			 */
			setValueMode: function()
			{
				if (this.isEmpty())
				{
					this.$input.val('').removeClass('prompt').select();
				}
			},

			/**
			 * Gets or sets the value of the prompt and puts it into the correct mode for its contents
			 *
			 * @param string value
			 */
			val: function(value, focus)
			{
				// get value
				if (value === undefined)
				{
					if (this.isEmpty())
					{
						return '';
					}
					else
					{
						return this.$input.val();
					}
				}

				// clear value
				else if (value === '')
				{
					this.$input.val('');

					if (focus === undefined)
					{
						this.setPromptMode();
					}
				}

				// set value
				else
				{
					this.setValueMode();
					this.$input.val(value);
				}
			},

			/**
			 * When the form is submitted, empty the prompt box if it is 'empty'
			 *
			 * @return boolean true;
			 */
			eFormSubmit: function()
			{
				if (this.isEmpty())
				{
					this.$input.val('');
				}

				return true;
			},

			/**
			 * Fires immediately after the form has sent its AJAX submission
			 */
			eFormSubmitted: function()
			{
				this.setPromptMode();
			}
		};
	};

	// *********************************************************************

	/**
	 * Turn in input:text.SpinBox into a Spin Box
	 * Requires a parameter class of 'SpinBox' and an attribute of 'data-step' with a numeric step value.
	 * data-max and data-min parameters are optional.
	 *
	 * @param {Object} $input
	 */
	XenForo.SpinBox = function($input) { this.__construct($input); };
	XenForo.SpinBox.prototype =
	{
		__construct: function($input)
		{
			var param,
				inputWidth,
				$plusButton,
				$minusButton;

			if ($input.attr('step') === undefined)
			{
				console.warn('ERROR: No data-step attribute specified for spinbox.');
				return;
			}

			this.parameters = { step: null, min:  null, max:  null };

			for (param in this.parameters)
			{
				if ($input.attr(param) === undefined)
				{
					delete this.parameters[param];
				}
				else
				{
					this.parameters[param] = parseFloat($input.attr(param));
				}
			}

			inputWidth = $input.width();

			$plusButton  = $('<input type="button" class="button spinBoxButton up" value="+" data-plusminus="+" tabindex="-1" />')
				.insertAfter($input)
				.focus($.context(this, 'eFocusButton'))
				.click($.context(this, 'eClickButton'))
				.mousedown($.context(this, 'eMousedownButton'))
				.mouseup($.context(this, 'eMouseupButton'));
			$minusButton = $('<input type="button" class="button spinBoxButton down" value="-" data-plusminus="-" tabindex="-1" />')
				.insertAfter($plusButton)
				.focus($.context(this, 'eFocusButton'))
				.click($.context(this, 'eClickButton'))
				.mousedown($.context(this, 'eMousedownButton'))
				.mouseup($.context(this, 'eMouseupButton'));

			// set up the input
			this.$input = $input
				.attr('autocomplete', 'off')
				.blur($.context(this, 'eBlurInput'))
				.keyup($.context(this, 'eKeyupInput'));

			// force validation to occur on form submit
			this.$input.closest('form').bind('submit', $.context(this, 'eBlurInput'));

			// initial constraint
			this.$input.val(this.constrain(this.getValue()));
		},

		/**
		 * Returns the (numeric) value of the spinbox
		 *
		 * @return float
		 */
		getValue: function()
		{
			var value = parseFloat(this.$input.val());

			value = (isNaN(value)) ? parseFloat(this.$input.val().replace(/[^0-9.]/g, '')) : value;

			return (isNaN(value) ? 0 : value);
		},

		/**
		 * Asserts that the value of the spinbox is within defined min and max parameters.
		 *
		 * @param float Spinbox value
		 *
		 * @return float
		 */
		constrain: function(value)
		{
			if (this.parameters.min !== undefined && value < this.parameters.min)
			{
				console.warn('Minimum value for SpinBox = %s\n %o', this.parameters.min, this.$input);
				return this.parameters.min;
			}
			else if (this.parameters.max !== undefined && value > this.parameters.max)
			{
				console.warn('Maximum value for SpinBox = %s\n %o', this.parameters.max, this.$input);
				return this.parameters.max;
			}
			else
			{
				return value;
			}
		},

		/**
		 * Takes the value of the SpinBox input to the nearest step.
		 *
		 * @param string +/- Take the value up or down
		 */
		stepValue: function(plusMinus)
		{
			if (this.$input.prop('readonly'))
			{
				return false;
			}

			var val = this.getValue(),
				mod = val % this.parameters.step,
				posStep = (plusMinus == '+'),
				newVal = val - mod;

			if (!mod || (posStep && mod > 0) || (!posStep && mod < 0))
			{
				newVal = newVal + this.parameters.step * (posStep ? 1 : -1);
			}

			this.$input.val(this.constrain(newVal));
			this.$input.triggerHandler('change');
		},

		/**
		 * Handles the input being blurred. Removes the 'pseudofocus' class and constrains the spinbox value.
		 *
		 * @param Event e
		 */
		eBlurInput: function(e)
		{
			this.$input.val(this.constrain(this.getValue()));
		},

		/**
		 * Handles key events on the spinbox input. Up and down arrows perform a value step.
		 *
		 * @param Event e
		 *
		 * @return false|undefined
		 */
		eKeyupInput: function(e)
		{
			switch (e.which)
			{
				case 38: // up
				{
					this.stepValue('+');
					this.$input.select();
					return false;
				}

				case 40: // down
				{
					this.stepValue('-');
					this.$input.select();
					return false;
				}
			}
		},

		/**
		 * Handles focus events on spinbox buttons.
		 *
		 * Does not allow buttons to keep focus, returns focus to the input.
		 *
		 * @param Event e
		 *
		 * @return boolean false
		 */
		eFocusButton: function(e)
		{
			return false;
		},

		/**
		 * Handles click events on spinbox buttons.
		 *
		 * The buttons are assumed to have data-plusMinus attributes of + or -
		 *
		 * @param Event e
		 */
		eClickButton: function(e)
		{
			this.stepValue($(e.target).data('plusminus'));
			this.$input.focus();
			this.$input.select();
		},

		/**
		 * Handles a mouse-down event on a spinbox button in order to allow rapid repeats.
		 *
		 * @param Event e
		 */
		eMousedownButton: function(e)
		{
			this.eMouseupButton(e); // don't orphan

			this.holdTimeout = setTimeout(
				$.context(function()
				{
					this.holdInterval = setInterval($.context(function() { this.stepValue(e.target.value); }, this), 75);
				}, this
			), 500);
		},

		/**
		 * Handles a mouse-up event on a spinbox button in order to halt rapid repeats.
		 *
		 * @param Event e
		 */
		eMouseupButton: function(e)
		{
			clearTimeout(this.holdTimeout);
			clearInterval(this.holdInterval);
		}
	};

	// *********************************************************************

	/**
	 * Allows an input:checkbox or input:radio to disable subsidiary controls
	 * based on its own state
	 *
	 * @param {Object} $input
	 */
	XenForo.Disabler = function($input)
	{
		/**
		 * Sets the disabled state of form elements being controlled by this disabler.
		 *
		 * @param Event e
		 * @param boolean If true, this is the initialization call
		 */
		var setStatus = function(e, init)
		{
			//console.info('Disabler %o for child container: %o', $input, $childContainer);

			var $childControls = $childContainer.find('input, select, textarea, button, .inputWrapper'),
				speed = init ? 0 : XenForo.speed.fast,
				select = function(e)
				{
					$childContainer.find('input:not([type=hidden], [type=file]), textarea, select, button').first().focus().select();
				};

			if ($input.is(':checked:enabled'))
			{
				$childContainer
					.removeAttr('disabled')
					.removeClass('disabled')
					.trigger('DisablerDisabled');

				$childControls
					.removeAttr('disabled')
					.removeClass('disabled');

				if ($input.hasClass('Hider'))
				{
					if (init)
					{
						$childContainer.show();
					}
					else
					{
						$childContainer.xfFadeDown(speed, init ? null : select);
					}
				}
				else if (!init)
				{
					select.call();
				}
			}
			else
			{
				if ($input.hasClass('Hider'))
				{
					if (init)
					{
						$childContainer.hide();
					}
					else
					{
						$childContainer.xfFadeUp(speed, null, speed, 'easeInBack');
					}
				}

				$childContainer
					.prop('disabled', true)
					.addClass('disabled')
					.trigger('DisablerEnabled');

				$childControls
					.prop('disabled', true)
					.addClass('disabled')
					.each(function(i, ctrl)
					{
						var $ctrl = $(ctrl),
							disabledVal = $ctrl.data('disabled');

						if (disabledVal !== null && typeof(disabledVal) != 'undefined')
						{
							$ctrl.val(disabledVal);
						}
					});
			}
		},

		$childContainer = $('#' + $input.attr('id') + '_Disabler'),

		$form = $input.closest('form');

		var setStatusDelayed = function()
		{
			setTimeout(setStatus, 0);
		};

		if ($input.is(':radio'))
		{
			$form.find('input:radio[name="' + $input.fieldName() + '"]').click(setStatusDelayed);
		}
		else
		{
			$input.click(setStatusDelayed);
		}

		$form.bind('reset', setStatusDelayed);
		$form.bind('XFRecalculate', function() { setStatus(null, true); });

		setStatus(null, true);

		$childContainer.find('label, input, select, textarea').click(function(e)
		{
			if (!$input.is(':checked'))
			{
				$input.prop('checked', true);
				setStatus();
			}
		});

		this.setStatus = setStatus;
	};

	// *********************************************************************

	/**
	 * Quick way to check or toggle all specified items. Works in one of two ways:
	 * 1) If the control is a checkbox, a data-target attribute specified a jQuery
	 * 	selector for a container within which all checkboxes will be toggled
	 * 2) If the control is something else, the data-target attribute specifies a
	 * 	jQuery selector for the elements themselves that will be selected.
	 *
	 *  @param jQuery .CheckAll
	 */
	XenForo.CheckAll = function($control)
	{
		if ($control.is(':checkbox'))
		{
			var $target = $control.data('target') ? $($control.data('target')) : false;
			if (!$target || !$target.length)
			{
				$target = $control.closest('form');
			}

			var getCheckBoxes = function()
			{
				var $checkboxes,
					filter = $control.data('filter');

				$checkboxes = filter
					? $target.find(filter).filter('input:checkbox')
					: $target.find('input:checkbox');

				return $checkboxes;
			};

			var setSelectAllState = function()
			{
				var $checkboxes = getCheckBoxes(),
					allSelected = $checkboxes.length > 0;

				$checkboxes.each(function() {
					if ($(this).is($control))
					{
						return true;
					}

					if (!$(this).prop('checked'))
					{
						allSelected = false;
						return false;
					}
				});

				$control.prop('checked', allSelected);
			};
			setSelectAllState();

			var toggleAllRunning = false;

			$target.on('click', 'input:checkbox', function(e)
			{
				if (toggleAllRunning)
				{
					return;
				}

				var $target = $(e.target);
				if ($target.is($control))
				{
					return;
				}

				if ($control.data('filter'))
				{
					if (!$target.closest($control.data('filter')).length)
					{
						return;
					}
				}

				setSelectAllState();
			});

			$control.click(function(e)
			{
				if (toggleAllRunning)
				{
					return;
				}

				toggleAllRunning = true;
				getCheckBoxes().prop('checked', e.target.checked).triggerHandler('click');
				toggleAllRunning = false;
			});
		}
		else
		{
			$control.click(function(e)
			{
				var target = $control.data('target');

				if (target)
				{
					$(target).prop('checked', true);
				}
			});
		}
	};

	// *********************************************************************

	/**
	 * Method to allow an input (usually a checkbox) to alter the selection of others.
	 * When checking the target checkbox, it will also check any controls matching data-check
	 * and un-check any controls matching data-uncheck
	 *
	 * @param jQuery input.AutoChecker[data-check, data-uncheck]
	 */
	XenForo.AutoChecker = function($control)
	{
		$control.click(function(e)
		{
			if (this.checked)
			{
				var selector = null;

				$.each({ check: true, uncheck: false }, function(dataField, checkState)
				{
					if (selector = $control.data(dataField))
					{
						$(selector).each(function()
						{
							this.checked = checkState;

							var Disabler = $(this).data('XenForo.Disabler');

							if (typeof Disabler == 'object')
							{
								Disabler.setStatus();
							}
						});
					}
				});
			}
		});
	};

	// *********************************************************************

	/**
	 * Converts a checkbox/radio plus label into a toggle button.
	 *
	 * @param jQuery label.ToggleButton
	 */
	XenForo.ToggleButton = function($label)
	{
		var $button,

		setCheckedClasses = function()
		{
			$button[($input.is(':checked') ? 'addClass' : 'removeClass')]('checked');
		},

		$input = $label.hide().find('input:checkbox, input:radio').first(),

		$list = $label.closest('ul, ol').bind('toggleButtonClick', setCheckedClasses);

		if (!$input.length && $label.attr('for'))
		{
			$input = $('#' + $label.attr('for'));
		}

		$button = $('<a />')
			.text($label.attr('title') || $label.text())
			.insertBefore($label)
			.attr(
			{
				'class': 'button ' + $label.attr('class'),
				'title': $label.text()
			})
			.click(function(e)
			{
				$input.click();

				if ($list.length)
				{
					$list.triggerHandler('toggleButtonClick');
				}
				else
				{
					setCheckedClasses();
				}

				return false;
			});

		$label.closest('form').bind('reset', function(e)
		{
			setTimeout(setCheckedClasses, 100);
		});

		setCheckedClasses();
	};

	// *********************************************************************

	/**
	 * Allows files to be uploaded in-place without a page refresh
	 *
	 * @param jQuery form.AutoInlineUploader
	 */
	XenForo.AutoInlineUploader = function($form)
	{
		/**
		 * Fires when the contents of an input:file change.
		 * Submits the form into a temporary iframe.
		 *
		 * @param event e
		 */
		var $uploader = $form.find('input:file').each(function()
		{
			var $target = $(this).change(function(e)
			{
				if ($(e.target).val() != '')
				{
					var $iframe,
						$hiddenInput;

					$iframe = $('<iframe src="about:blank" style="display:none; background-color: white" name="AutoInlineUploader"></iframe>')
						.insertAfter($(e.target))
						.load(function(e)
						{
							var $iframe = $(e.target),
								ajaxData = $iframe.contents().text(),
								eComplete = null;

							// Opera fires this function when it's not done with no data
							if (!ajaxData)
							{
								return false;
							}

							// alert the global progress indicator that the transfer is complete
							$(document).trigger('PseudoAjaxStop');

							$uploader = $uploaderOrig.clone(true).replaceAll($target);

							// removing the iframe after a delay to prevent Firefox' progress indicator staying active
							setTimeout(function() { $iframe.remove(); }, 500);

							try
							{
								ajaxData = $.parseJSON(ajaxData);
								console.info('Inline file upload completed successfully. Data: %o', ajaxData);
							}
							catch(e)
							{
								console.error(ajaxData);
								return false;
							}

							if (XenForo.hasResponseError(ajaxData))
							{
								return false;
							}

							$('input:submit', this.$form).removeAttr('disabled');

							eComplete = new $.Event('AutoInlineUploadComplete');
							eComplete.$form = $form;
							eComplete.ajaxData = ajaxData;

							$form.trigger(eComplete);

							console.log(ajaxData);

							if (!eComplete.isDefaultPrevented() && ajaxData.message)
							{
								XenForo.alert(ajaxData.message, '', 2500);
							}
						});

					$hiddenInput = $('<span>'
						+ '<input type="hidden" name="_xfNoRedirect" value="1" />'
						+ '<input type="hidden" name="_xfResponseType" value="json-text" />'
						+ '<input type="hidden" name="_xfUploader" value="1" />'
						+ '</span>')
						.appendTo($form);

					$form.attr('target', 'AutoInlineUploader')
						.submit()
						.trigger('AutoInlineUploadStart');

					$hiddenInput.remove();

					// fire the event that will be caught by the global progress indicator
					$(document).trigger('PseudoAjaxStart');

					$form.find('input:submit').prop('disabled', true);
				}
			}),

			$uploaderOrig = $target.clone(true);
		});
	};

	// *********************************************************************

	XenForo.MultiSubmitFix = function($form)
	{
		var selector = 'input:submit, input:reset, input.PreviewButton, input.DisableOnSubmit',
			enable = function()
			{
				$(window).unbind('unload', enable);

				$form.trigger('EnableSubmitButtons').find(selector)
					.removeClass('disabled')
					.removeAttr('disabled');
			};

		var disable = function(e)
		{
			setTimeout(function()
			{
				/**
				 * Workaround for a Firefox issue that prevents resubmission after back button,
				 * however the workaround triggers a webkit rendering bug.
				 */
				if (!$.browser.webkit)
				{
					$(window).bind('unload', enable);
				}

				$form.trigger('DisableSubmitButtons').find(selector)
					.prop('disabled', true)
					.addClass('disabled');
			}, 0);

			setTimeout(enable, 5000);
		};

		$form.data('MultiSubmitEnable', enable)
			.data('MultiSubmitDisable', disable)
			.submit(disable);

		return enable;
	};

	// *********************************************************************

	/**
	 * Handler for radio/checkbox controls that cause the form to submit when they are altered
	 *
	 * @param jQuery input:radio.SubmitOnChange, input:checkbox.SubmitOnChange, label.SubmitOnChange
	 */
	XenForo.SubmitOnChange = function($input)
	{
		if ($input.is('label'))
		{
			$input = $input.find('input:radio, input:checkbox');
			if (!$input.length)
			{
				return;
			}
		}

		$input.click(function(e)
		{
			clearTimeout(e.target.form.submitTimeout);

			e.target.form.submitTimeout = setTimeout(function()
			{
				$(e.target).closest('form').submit();
			}, 500);
		});
	};

	// *********************************************************************

	/**
	 * Handler for automatic AJAX form validation and error management
	 *
	 * Forms to be auto-validated require the following attributes:
	 *
	 * * data-fieldValidatorUrl: URL of a JSON-returning validator for a single field, using _POST keys of 'name' and 'value'
	 * * data-optInOut: (Optional - default = OptOut) Either OptIn or OptOut, depending on the validation mode. Fields with a class of OptIn are included in opt-in mode, while those with OptOut are excluded in opt-out mode.
	 * * data-exitUrl: (Optional - no default) If defined, any form reset event will redirect to this URL.
	 * * data-existingDataKey: (Optional) Specifies the primary key of the data being manipulated. If this is not present, a hidden input with class="ExistingDataKey" is searched for.
	 * * data-redirect: (Optional) If set, the browser will redirect to the returned _redirectTarget from the ajaxData response after validation
	 *
	 * @param jQuery form.AutoValidator
	 */
	XenForo.AutoValidator = function($form) { this.__construct($form); };
	XenForo.AutoValidator.prototype =
	{
		__construct: function($form)
		{
			this.$form = $form.bind(
			{
				submit: $.context(this, 'ajaxSave'),
				reset:  $.context(this, 'formReset'),
				BbCodeWysiwygEditorAutoSave: $.context(this, 'editorAutoSave')
			});

			this.$form.find('input[type="submit"]').click($.context(this, 'setClickedSubmit'));

			this.fieldValidatorUrl = this.$form.data('fieldvalidatorurl');
			this.optInMode = this.$form.data('optinout') || 'optOut';
			this.ajaxSubmit = (XenForo.isPositive(this.$form.data('normalsubmit')) ? false : true);
			this.submitPending = false;

			this.fieldValidationTimeouts = {};
			this.fieldValidationRequests = {};
		},

		/**
		 * Fetches the value of the form's existing data key.
		 *
		 * This could either be a data-existingDataKey attribute on the form itself,
		 * or a hidden input with class 'ExistingDataKey'
		 *
		 * @return string
		 */
		getExistingDataKey: function()
		{
			var val = this.$form.find('input.ExistingDataKey, select.ExistingDataKey, textarea.ExistingDataKey, button.ExistingDataKey').val();
			if (val === undefined)
			{
				val = this.$form.data('existingdatakey');
				if (val === undefined)
				{
					val = '';
				}
			}

			return val;
		},

		/**
		 * Intercepts form reset events.
		 * If the form specifies a data-exitUrl, the browser will navigate there before resetting the form.
		 *
		 * @param event e
		 */
		formReset: function(e)
		{
			var exitUrl = this.$form.data('exiturl');

			if (exitUrl)
			{
				XenForo.redirect(exitUrl);
			}
		},

		/**
		 * Fires whenever a submit button is clicked, in order to store the clicked control
		 *
		 * @param event e
		 */
		setClickedSubmit: function(e)
		{
			this.$form.data('clickedsubmitbutton', e.target);
		},

		editorAutoSave: function(e)
		{
			if (this.submitPending)
			{
				e.preventDefault();
			}
		},

		/**
		 * Intercepts form submit events.
		 * Attempts to save the form with AJAX, after cancelling any pending validation tasks.
		 *
		 * @param event e
		 *
		 * @return boolean false
		 */
		ajaxSave: function(e)
		{
			if (!this.ajaxSubmit || !XenForo._enableAjaxSubmit)
			{
				// do normal validation
				return true;
			}

			this.abortPendingFieldValidation();

			var clickedSubmitButton = this.$form.data('clickedsubmitbutton'),
				serialized,
				$clickedSubmitButton,

			/**
			 * Event listeners for this event can:
			 * 	e.preventSubmit = true; to prevent any submission
			 * 	e.preventDefault(); to disable ajax sending
			 */
			eDataSend = $.Event('AutoValidationBeforeSubmit');
				eDataSend.formAction = this.$form.attr('action');
				eDataSend.clickedSubmitButton = clickedSubmitButton;
				eDataSend.preventSubmit = false;
				eDataSend.ajaxOptions = {};

			this.$form.trigger(eDataSend);

			this.$form.removeData('clickedSubmitButton');

			if (eDataSend.preventSubmit)
			{
				return false;
			}
			else if (!eDataSend.isDefaultPrevented())
			{
				serialized = this.$form.serializeArray();
				if (clickedSubmitButton)
				{
					$clickedSubmitButton = $(clickedSubmitButton);
					if ($clickedSubmitButton.attr('name'))
					{
						serialized.push({
							name: $clickedSubmitButton.attr('name'),
							value: $clickedSubmitButton.attr('value')
						});
					}
				}

				this.submitPending = true;

				XenForo.ajax(
					eDataSend.formAction,
					serialized,
					$.context(this, 'ajaxSaveResponse'),
					eDataSend.ajaxOptions
				);

				e.preventDefault();
			}
		},

		/**
		 * Handles the AJAX response from ajaxSave().
		 *
		 * @param ajaxData
		 * @param textStatus
		 * @return
		 */
		ajaxSaveResponse: function(ajaxData, textStatus)
		{
			this.submitPending = false;

			if (!ajaxData)
			{
				console.warn('No ajax data returned.');
				return false;
			}

			var eDataRecv,
				eError,
				eComplete,
				$trigger;

			eDataRecv = $.Event('AutoValidationDataReceived');
			eDataRecv.ajaxData = ajaxData;
			eDataRecv.textStatus = textStatus;
			eDataRecv.validationError = [];
			console.group('Event: %s', eDataRecv.type);
			this.$form.trigger(eDataRecv);
			console.groupEnd();
			if (eDataRecv.isDefaultPrevented())
			{
				return false;
			}

			// if the submission has failed validation, show the error overlay
			if (!this.validates(eDataRecv))
			{
				eError = $.Event('AutoValidationError');
				eError.ajaxData = ajaxData;
				eError.textStatus = textStatus;
				eError.validationError = eDataRecv.validationError;
				console.group('Event: %s', eError.type);
				this.$form.trigger(eError);
				console.groupEnd();
				if (eError.isDefaultPrevented())
				{
					return false;
				}

				if (this.$form.closest('.xenOverlay').length)
				{
					this.$form.closest('.xenOverlay').data('overlay').close();
				}

				if (ajaxData.errorTemplateHtml)
				{
					new XenForo.ExtLoader(ajaxData, function(data) {
						var $overlayHtml = XenForo.alert(
							ajaxData.errorTemplateHtml,
							XenForo.phrases.following_error_occurred + ':'
						);
						if ($overlayHtml)
						{
							$overlayHtml.find('div.errorDetails').removeClass('baseHtml');
							if (ajaxData.errorOverlayType)
							{
								$overlayHtml.closest('.errorOverlay').removeClass('errorOverlay').addClass(ajaxData.errorOverlayType);
							}
						}
					});
				}
				else if (ajaxData.templateHtml)
				{
					setTimeout($.context(function()
					{
						this.$error = XenForo.createOverlay(null, this.prepareError(ajaxData.templateHtml)).load();
					}, this), 250);
				}
				else if (ajaxData.error !== undefined)
				{
					if (typeof ajaxData.error === 'object')
					{
						var key;
						for (key in ajaxData.error)
						{
							break;
						}
						ajaxData.error = ajaxData.error[key];
					}

					XenForo.alert(
						ajaxData.error + '\n'
							+ (ajaxData.traceHtml !== undefined ? '<ol class="traceHtml">\n' + ajaxData.traceHtml + '</ol>' : ''),
						XenForo.phrases.following_error_occurred + ':'
					);
				}

				return false;
			}

			eComplete = $.Event('AutoValidationComplete'),
			eComplete.ajaxData = ajaxData;
			eComplete.textStatus = textStatus;
			eComplete.$form = this.$form;
			console.group('Event: %s', eComplete.type);
			this.$form.trigger(eComplete);
			console.groupEnd();
			if (eComplete.isDefaultPrevented())
			{
				return false;
			}

			// if the form is in an overlay, close it
			if (this.$form.parents('.xenOverlay').length)
			{
				this.$form.parents('.xenOverlay').data('overlay').close();

				if (ajaxData.linkPhrase)
				{
					$trigger = this.$form.parents('.xenOverlay').data('overlay').getTrigger();
					$trigger.xfFadeOut(XenForo.speed.fast, function()
					{
						if (ajaxData.linkUrl && $trigger.is('a'))
						{
							$trigger.attr('href', ajaxData.linkUrl);
						}

						$trigger
							.text(ajaxData.linkPhrase)
							.xfFadeIn(XenForo.speed.fast);
					});
				}
			}

			if (ajaxData.message)
			{
				XenForo.alert(ajaxData.message, '', 4000);
				return;
			}

			// if a redirect message was not specified, redirect immediately
			if (ajaxData._redirectMessage == '')
			{
				this.submitPending = true;
				return this.redirect(ajaxData._redirectTarget);
			}

			// show the redirect message, then redirect if a redirect target was specified
			this.submitPending = true;
			XenForo.alert(ajaxData._redirectMessage, '', 1000, $.context(function()
			{
				this.redirect(ajaxData._redirectTarget);
			}, this));
		},

		/**
		 * Checks for the presence of validation errors in the given event
		 *
		 * @param event e
		 *
		 * @return boolean
		 */
		validates: function(e)
		{
			return ($.isEmptyObject(e.validationErrors) && !e.ajaxData.error);
		},

		/**
		 * Attempts to match labels to errors for the error overlay
		 *
		 * @param string html
		 *
		 * @return jQuery
		 */
		prepareError: function(html)
		{
			$html = $(html);

			// extract labels that correspond to the error fields and insert their text next to the error message
			$html.find('label').each(function(i, label)
			{
				var $ctrlLabel = $('#' + $(label).attr('for'))
					.closest('.ctrlUnit')
					.find('dt > label');

				if ($ctrlLabel.length)
				{
					$(label).prepend($ctrlLabel.text() + '<br />');
				}
			});

			return $html;
		},

		/**
		 * Redirect the browser to redirectTarget if it is specified
		 *
		 * @param string redirectTarget
		 *
		 * @return boolean
		 */
		redirect: function(redirectTarget)
		{
			if (XenForo.isPositive(this.$form.data('redirect')) || !parseInt(XenForo._enableOverlays))
			{
				var $AutoValidationRedirect = new $.Event('AutoValidationRedirect');
					$AutoValidationRedirect.redirectTarget = redirectTarget;

				this.$form.trigger($AutoValidationRedirect);

				if (!$AutoValidationRedirect.isDefaultPrevented() && $AutoValidationRedirect.redirectTarget)
				{
					var fn = function()
					{
						XenForo.redirect(redirectTarget);
					};

					if (XenForo._manualDeferOverlay)
					{
						$(document).one('ManualDeferComplete', fn);
					}
					else
					{
						fn();
					}

					return true;
				}
			}

			return false;
		},

		// ---------------------------------------------------
		// Field validation methods...

		/**
		 * Sets a timeout before an AJAX field validation request will be fired
		 * (Prevents AJAX floods)
		 *
		 * @param string Name of field to be validated
		 * @param function Callback to fire when the timeout elapses
		 */
		setFieldValidationTimeout: function(name, callback)
		{
			if (!this.hasFieldValidator(name)) { return false; }

			console.log('setTimeout %s', name);

			this.clearFieldValidationTimeout(name);

			this.fieldValidationTimeouts[name] = setTimeout(callback, 250);
		},

		/**
		 * Cancels a timeout set with setFieldValidationTimeout()
		 *
		 * @param string name
		 */
		clearFieldValidationTimeout: function(name)
		{
			if (this.fieldValidationTimeouts[name])
			{
				console.log('Clear field validation timeout: %s', name);

				clearTimeout(this.fieldValidationTimeouts[name]);
				delete(this.fieldValidationTimeouts[name]);
			}
		},

		/**
		 * Fires an AJAX field validation request
		 *
		 * @param string Name of variable to be verified
		 * @param jQuery Input field to be validated
		 * @param function Callback function to fire on success
		 */
		startFieldValidationRequest: function(name, $input, callback)
		{
			if (!this.hasFieldValidator(name)) { return false; }

			// abort any existing AJAX validation requests from this $input
			this.abortFieldValidationRequest(name);

			// fire the AJAX request and register it in the fieldValidationRequests
			// object so it can be cancelled by subsequent requests
			this.fieldValidationRequests[name] = XenForo.ajax(this.fieldValidatorUrl,
			{
				name: name,
				value: $input.fieldValue(),
				existingDataKey: this.getExistingDataKey()
			}, callback,
			{
				global: false // don't show AJAX progress indicators for inline validation
			});
		},

		/**
		 * Aborts an AJAX field validation request set up by startFieldValidationRequest()
		 *
		 * @param string name
		 */
		abortFieldValidationRequest: function(name)
		{
			if (this.fieldValidationRequests[name])
			{
				console.log('Abort field validation request: %s', name);

				this.fieldValidationRequests[name].abort();
				delete(this.fieldValidationRequests[name]);
			}
		},

		/**
		 * Cancels any pending timeouts or ajax field validation requests
		 */
		abortPendingFieldValidation: function()
		{
			$.each(this.fieldValidationTimeouts, $.context(this, 'clearFieldValidationTimeout'));
			$.each(this.fieldValidationRequests, $.context(this, 'abortFieldValidationRequest'));
		},

		/**
		 * Throws a warning if this.fieldValidatorUrl is not valid
		 *
		 * @param string Name of field to be validated
		 *
		 * @return boolean
		 */
		hasFieldValidator: function(name)
		{
			if (this.fieldValidatorUrl)
			{
				return true;
			}

			//console.warn('Unable to request validation for field "%s" due to lack of fieldValidatorUrl in form tag.', name);
			return false;
		}
	};

	// *********************************************************************

	/**
	 * Handler for individual fields in an AutoValidator form.
	 * Manages individual field validation and inline error display.
	 *
	 * @param jQuery input [text-type]
	 */
	XenForo.AutoValidatorControl = function($input) { this.__construct($input); };
	XenForo.AutoValidatorControl.prototype =
	{
		__construct: function($input)
		{
			this.$form = $input.closest('form.AutoValidator').bind(
			{
				AutoValidationDataReceived: $.context(this, 'handleFormValidation')
			});

			this.$input = $input.bind(
			{
				change:              $.context(this, 'change'),
				AutoValidationError: $.context(this, 'showError'),
				AutoValidationPass:  $.context(this, 'hideError')
			});

			this.name = $input.data('validatorname') || $input.attr('name');
			this.autoValidate = $input.hasClass('NoAutoValidate') ? false : true;
		},

		/**
		 * When the value of a field changes, initiate validation
		 *
		 * @param event e
		 */
		change: function(e)
		{
			if (this.autoValidate)
			{
				this.$form.data('XenForo.AutoValidator')
					.setFieldValidationTimeout(this.name, $.context(this, 'validate'));
			}
		},

		/**
		 * Fire a validation AJAX request
		 */
		validate: function()
		{
			if (this.autoValidate)
			{
				this.$form.data('XenForo.AutoValidator')
					.startFieldValidationRequest(this.name, this.$input, $.context(this, 'handleValidation'));
			}
		},

		/**
		 * Handle the data returned from an AJAX validation request fired in validate().
		 * Fires 'AutoValidationPass' or 'AutoValidationError' for the $input according to the validation state.
		 *
		 * @param object ajaxData
		 * @param string textStatus
		 *
		 * @return boolean
		 */
		handleValidation: function(ajaxData, textStatus)
		{
			if (ajaxData && ajaxData.error && ajaxData.error.hasOwnProperty(this.name))
			{
				this.$input.trigger({
					type: 'AutoValidationError',
					errorMessage: ajaxData.error[this.name]
				});
				return false;
			}
			else
			{
				this.$input.trigger('AutoValidationPass');
				return true;
			}
		},

		/**
		 * Shows an inline error message, text contained within a .errorMessage property of the event passed
		 *
		 * @param event e
		 */
		showError: function(e)
		{
			console.warn('%s: %s', this.name, e.errorMessage);

			var error = this.fetchError(e.errorMessage).css('display', 'inline-block');
			this.positionError(error);
		},

		/**
		 * Hides any inline error message shown with this input
		 */
		hideError: function()
		{
			console.info('%s: Okay', this.name);

			if (this.$error)
			{
				this.fetchError()
					.hide();
			}
		},

		/**
		 * Fetches or creates (as necessary) the error HTML object for this field
		 *
		 * @param string Error message
		 *
		 * @return jQuery this.$error
		 */
		fetchError: function(message)
		{
			if (!this.$error)
			{
				this.$error = $('<label for="' + this.$input.attr('id') + '" class="formValidationInlineError">WHoops</label>').insertAfter(this.$input);
			}

			if (message)
			{
				this.$error.html(message).xfActivate();
			}

			return this.$error;
		},

		/**
		 * Returns an object containing top and left properties, used to position the inline error message
		 */
		positionError: function($error)
		{
			$error.removeClass('inlineError');

			var coords = this.$input.coords('outer', 'position'),
				screenCoords = this.$input.coords('outer'),
				$window = $(window),
				outerWidth = $error.outerWidth(),
				absolute,
				position = { top: coords.top };

			if (XenForo.isRTL())
			{
				position.left = coords.left - outerWidth - 10;
				absolute = (screenCoords.left - outerWidth - 10 > 0);
			}
			else
			{
				var screenLeft = screenCoords.left + screenCoords.width + 10;

				absolute = screenLeft + outerWidth < ($window.width() + $window.scrollLeft());
				position.left = coords.left + coords.width + 10;
			}

			if (absolute)
			{
				$error.css(position);
			}
			else
			{
				$error.addClass('inlineError');
			}
		},

		/**
		 * Handles validation for this field passed down from a submission of the whole AutoValidator
		 * form, and passes the relevant data into the handler for this field specifically.
		 *
		 * @param event e
		 */
		handleFormValidation: function(e)
		{
			if (!this.handleValidation(e.ajaxData, e.textStatus))
			{
				e.validationError.push(this.name);
			}
		}
	};

	// *********************************************************************

	/**
	 * Checks a form field to see if it is part of an AutoValidator form,
	 * and if so, whether or not it is subject to autovalidation.
	 *
	 * @param object Form control to be tested
	 *
	 * @return boolean
	 */
	XenForo.isAutoValidatorField = function(ctrl)
	{
		var AutoValidator, $ctrl, $form = $(ctrl.form);

		if (!$form.hasClass('AutoValidator'))
		{
			return false;
		}

		AutoValidator = $form.data('XenForo.AutoValidator');

		if (AutoValidator)
		{
			$ctrl = $(ctrl);

			switch (AutoValidator.optInMode)
			{
				case 'OptIn':
				{
					return ($ctrl.hasClass('OptIn') || $ctrl.closest('.ctrlUnit').hasClass('OptIn'));
				}
				default:
				{
					return (!$ctrl.hasClass('OptOut') && !$ctrl.closest('.ctrlUnit').hasClass('OptOut'));
				}
			}
		}

		return false;
	};

	// *********************************************************************

	XenForo.PreviewForm = function($form)
	{
		var previewUrl = $form.data('previewurl');
		if (!previewUrl)
		{
			console.warn('PreviewForm has no data-previewUrl: %o', $form);
			return;
		}

		$form.find('.PreviewButton').click(function(e)
		{
			XenForo.ajax(previewUrl, $form.serialize(), function(ajaxData)
			{
				if (XenForo.hasResponseError(ajaxData) || !XenForo.hasTemplateHtml(ajaxData))
				{
					return false;
				}

				new XenForo.ExtLoader(ajaxData, function(ajaxData)
				{
					var $preview = $form.find('.PreviewContainer').first();
					if ($preview.length)
					{
						$preview.xfFadeOut(XenForo.speed.fast, function() {
							$preview.html(ajaxData.templateHtml).xfActivate();
						});
					}
					else
					{
						$preview = $('<div />', { 'class': 'PreviewContainer'})
							.hide()
							.html(ajaxData.templateHtml)
							.prependTo($form)
							.xfActivate();
					}

					$preview.xfFadeIn(XenForo.speed.fast);
					$preview.get(0).scrollIntoView(true);
				});
			});
		});
	};

	// *********************************************************************

	/**
	 * Allows a text input field to rewrite the H1 (or equivalent) tag's contents
	 *
	 * @param jQuery input[data-liveTitleTemplate]
	 */
	XenForo.LiveTitle = function($input)
	{
		var $title = $input.closest('.formOverlay').find('h2.h1'), setTitle;

		if (!$title.length)
		{
			$title = $('.titleBar h1').first();
		}
		console.info('Title Element: %o', $title);
		$title.data('originalhtml', $title.html());

		setTitle = function(value)
		{
			$input.trigger('LiveTitleSet', [value]);

			$title.html(value === ''
				? $title.data('originalhtml')
				: $input.data('livetitletemplate').replace(/%s/, $('<div />').text(value).html())
			);
		};

		if (!$input.hasClass('prompt'))
		{
			setTitle($input.strval());
		}

		$input.bind('keyup focus', function(e)
		{
			setTitle($input.strval());
		})
		.on('paste', function(e)
		{
			setTimeout(function()
			{
				setTitle($input.strval());
			}, 0);
		})
		.closest('form').bind('reset', function(e)
		{
			setTitle('');
		});
	};

	// *********************************************************************

	XenForo.TextareaElastic = function($input) { this.__construct($input); };
	XenForo.TextareaElastic.prototype =
	{
		__construct: function($input)
		{
			this.$input = $input;
			this.curHeight = 0;

			$input.bind('keyup focus XFRecalculate', $.context(this, 'recalculate'));
			$input.bind('paste', $.context(this, 'paste'));

			if ($input.val() !== '')
			{
				this.recalculate();
			}
		},

		recalculate: function()
		{
			var $input = this.$input,
				input = $input.get(0),
				clone,
				height,
				pos;

			if ($input.val() === '')
			{
				$input.css({
					'overflow-y': 'hidden',
					'height': ''
				});
				this.curHeight = 0;
				return;
			}

			if (!input.clientWidth)
			{
				return;
			}

			if (!this.minHeight)
			{
				this.borderBox = ($input.css('-moz-box-sizing') == 'border-box' || $input.css('box-sizing') == 'border-box');
				this.minHeight = (this.borderBox ? $input.outerHeight() : input.clientHeight);

				if (!this.minHeight)
				{
					return;
				}

				this.maxHeight = parseInt($input.css('max-height'), 10);
				this.spacing = (this.borderBox ? $input.outerHeight() - $input.innerHeight() : 0);
			}

			if (!this.$clone)
			{
				this.$clone = $('<textarea />').css({
					position: 'absolute',
					left: (XenForo.isRTL() ? '9999em' : '-9999em'),
					top: 0,
					visibility: 'hidden',
					width: input.clientWidth,
					height: '1px',
					'font-size': $input.css('font-size'),
					'font-family': $input.css('font-family'),
					'font-weight': $input.css('font-weight'),
					'line-height': $input.css('line-height'),
					'word-wrap': $input.css('word-wrap')
				}).attr('tabindex', -1).val(' ');

				this.$clone.appendTo(document.body);

				this.lineHeight = this.$clone.get(0).scrollHeight;
			}

			this.$clone.val($input.val());
			clone = this.$clone.get(0);

			height = Math.max(this.minHeight, clone.scrollHeight + this.lineHeight + this.spacing);

			if (height < this.maxHeight)
			{
				if (this.curHeight != height)
				{
					input = $input.get(0);
					if (this.curHeight == this.maxHeight && input.setSelectionRange)
					{
						pos = input.selectionStart;
					}

					$input.css({
						'overflow-y': 'hidden',
						'height': height + 'px'
					});

					if (this.curHeight == this.maxHeight && input.setSelectionRange)
					{
						try
						{
							input.setSelectionRange(pos, pos);
						} catch(e) {}
					}

					this.curHeight = height;
				}
			}
			else
			{
				if (this.curHeight != this.maxHeight)
				{
					input = $input.get(0);
					if (input.setSelectionRange)
					{
						pos = input.selectionStart;
					}

					$input.css({
						'overflow-y': 'auto',
						'height': this.maxHeight + 'px'
					});

					if (input.setSelectionRange)
					{
						try
						{
							input.setSelectionRange(pos, pos);
						} catch (e) {}
					}

					this.curHeight = this.maxHeight;
				}
			}
		},

		paste: function()
		{
			setTimeout($.context(this, 'recalculate'), 100);
		}
	};

	// *********************************************************************

	XenForo.AutoTimeZone = function($element)
	{
		var now = new Date(),
			jan1 = new Date(now.getFullYear(), 0, 1), // 0 = jan
			jun1 = new Date(now.getFullYear(), 5, 1), // 5 = june
			jan1offset = Math.round(jan1.getTimezoneOffset()),
			jun1offset = Math.round(jun1.getTimezoneOffset());

		// opera doesn't report TZ offset differences in jan/jun correctly
		if ($.browser.opera)
		{
			return false;
		}

		if (XenForo.AutoTimeZone.map[jan1offset + ',' + jun1offset])
		{
			$element.val(XenForo.AutoTimeZone.map[jan1offset + ',' + jun1offset]);
			return true;
		}
		else
		{
			return false;
		}
	};

	XenForo.AutoTimeZone.map =
	{
		'660,660' : 'Pacific/Midway',
		'600,600' : 'Pacific/Honolulu',
		'570,570' : 'Pacific/Marquesas',
		'540,480' : 'America/Anchorage',
		'480,420' : 'America/Los_Angeles',
		'420,360' : 'America/Denver',
		'420,420' : 'America/Phoenix',
		'360,300' : 'America/Chicago',
		'360,360' : 'America/Belize',
		'300,240' : 'America/New_York',
		'300,300' : 'America/Bogota',
		'270,270' : 'America/Caracas',
		'240,180' : 'America/Halifax',
		'180,240' : 'America/Cuiaba',
		'240,240' : 'America/La_Paz',
		'210,150' : 'America/St_Johns',
		'180,180' : 'America/Argentina/Buenos_Aires',
		'120,180' : 'America/Sao_Paulo',
		'180,120' : 'America/Miquelon',
		'120,120' : 'America/Noronha',
		'60,60' : 'Atlantic/Cape_Verde',
		'60,0' : 'Atlantic/Azores',
		'0,-60' : 'Europe/London',
		'0,0' : 'Atlantic/Reykjavik',
		'-60,-120' : 'Europe/Amsterdam',
		'-60,-60' : 'Africa/Algiers',
		'-120,-60' : 'Africa/Windhoek',
		'-120,-180' : 'Europe/Athens',
		'-120,-120' : 'Africa/Johannesburg',
		'-180,-240' : 'Africa/Nairobi',
		'-180,-180' : 'Europe/Moscow',
		'-210,-270' : 'Asia/Tehran',
		'-240,-300' : 'Asia/Yerevan',
		'-270,-270' : 'Asia/Kabul',
		'-300,-300' : 'Asia/Tashkent',
		'-330,-330' : 'Asia/Kolkata',
		'-345,-345' : 'Asia/Kathmandu',
		'-360,-360' : 'Asia/Dhaka',
		'-390,-390' : 'Asia/Rangoon',
		'-420,-420' : 'Asia/Bangkok',
		'-420,-480' : 'Asia/Krasnoyarsk',
		'-480,-480' : 'Asia/Hong_Kong',
		'-540,-540' : 'Asia/Tokyo',
		'-630,-570' : 'Australia/Adelaide',
		'-570,-570' : 'Australia/Darwin',
		'-660,-600' : 'Australia/Sydney',
		'-600,-600' : 'Asia/Vladivostok',
		'-690,-690' : 'Pacific/Norfolk',
		'-780,-720' : 'Pacific/Auckland',
		'-825,-765' : 'Pacific/Chatham',
		'-780,-780' : 'Pacific/Tongatapu',
		'-840,-840' : 'Pacific/Kiritimati'
	};

	// *********************************************************************

	XenForo.DatePicker = function($input)
	{
		if (!XenForo.DatePicker.$root)
		{
			$.tools.dateinput.localize('_f',
			{
				months: XenForo.phrases._months,
				shortMonths: '1,2,3,4,5,6,7,8,9,10,11,12',
				days: 's,m,t,w,t,f,s',
				shortDays: XenForo.phrases._daysShort
			});
		}

		var $date = $input.dateinput(
		{
			lang: '_f',
			format: 'yyyy-mm-dd', // rfc 3339 format, required by html5 date element
			speed: 0,
			yearRange: [-100, 100],
			onShow: function(e)
			{
				var $root = XenForo.DatePicker.$root,
					offset = $date.offset(),
					maxZIndex = 0,
					position = { top: offset.top + $date.outerHeight() };

				if (XenForo.isRTL())
				{
					position.right = $('html').width() - offset.left - $date.outerWidth();
				}
				else
				{
					position.left = offset.left;
				}

				$root.css(position);

				$date.parents().each(function(i, el)
				{
					var zIndex = parseInt($(el).css('z-index'), 10);
					if (zIndex > maxZIndex)
					{
						maxZIndex = zIndex;
					}
				});

				$root.css('z-index', maxZIndex + 1000);
			}
		});

		$date.addClass($input.attr('class'));
		if ($input.attr('id'))
		{
			$date.attr('id', $input.attr('id'));
		}

		// this is needed to handle input[type=reset] buttons that end up focusing the field
		$date.closest('form').on('reset', function() {
			setTimeout(function() {
				$date.data('dateinput').hide();
			}, 10);
			setTimeout(function() {
				$date.data('dateinput').hide();
			}, 100);
		});

		if (!XenForo.DatePicker.$root)
		{
			XenForo.DatePicker.$root = $('#calroot').appendTo(document.body);

			$('#calprev').html(XenForo.isRTL() ? '&rarr;' : '&larr;').prop('unselectable', true);
			$('#calnext').html(XenForo.isRTL() ? '&larr;' : '&rarr;').prop('unselectable', true);
		}
	};

	XenForo.DatePicker.$root = null;

	// *********************************************************************

	XenForo.AutoComplete = function($element) { this.__construct($element); };
	XenForo.AutoComplete.prototype =
	{
		__construct: function($input)
		{
			this.$input = $input;

			this.url = $input.data('acurl') || XenForo.AutoComplete.getDefaultUrl();
			this.extraFields = $input.data('acextrafields');

			var options = {
				multiple: $input.hasClass('AcSingle') ? false : ',', // mutiple value joiner
				minLength: 2, // min word length before lookup
				queryKey: 'q',
				extraParams: {},
				jsonContainer: 'results',
				autoSubmit: XenForo.isPositive($input.data('autosubmit'))
			};
			if ($input.data('acoptions'))
			{
				options = $.extend(options, $input.data('acoptions'));
			}

			if (options.autoSubmit)
			{
				options.multiple = false;
			}

			this.multiple = options.multiple;
			this.minLength = options.minLength;
			this.queryKey = options.queryKey;
			this.extraParams = options.extraParams;
			this.jsonContainer = options.jsonContainer;
			this.autoSubmit = options.autoSubmit;

			this.loadVal = '';
			this.results = new XenForo.AutoCompleteResults({
				onInsert: $.context(this, 'addValue')
			});

			$input.attr('autocomplete', 'off')
				.keydown($.context(this, 'keystroke'))
				.keypress($.context(this, 'operaKeyPress'))
				.blur($.context(this, 'blur'));

			$input.on('paste', function()
			{
				setTimeout(function()
				{
					$input.trigger('keydown');
				}, 0);
			});

			$input.closest('form').submit($.context(this, 'hideResults'));
		},

		keystroke: function(e)
		{
			var code = e.keyCode || e.charCode, prevent = true;

			switch(code)
			{
				case 40: this.results.selectResult(1); break; // down
				case 38: this.results.selectResult(-1); break; // up
				case 27: this.results.hideResults(); break; // esc
				case 13: // enter
					if (this.results.isVisible())
					{
						this.results.insertSelectedResult();
					}
					else
					{
						prevent = false;
					}
					break;

				default:
					prevent = false;
					if (this.loadTimer)
					{
						clearTimeout(this.loadTimer);
					}
					this.loadTimer = setTimeout($.context(this, 'load'), 200);

					if (code != 229)
					{
						this.results.hideResults();
					}
			}

			if (prevent)
			{
				e.preventDefault();
			}
			this.preventKey = prevent;
		},

		operaKeyPress: function(e)
		{
			if ($.browser.opera && this.preventKey)
			{
				e.preventDefault();
			}
		},

		blur: function(e)
		{
			clearTimeout(this.loadTimer);

			// timeout ensures that clicks still register
			setTimeout($.context(this, 'hideResults'), 250);

			if (this.xhr)
			{
				this.xhr.abort();
				this.xhr = false;
			}
		},

		load: function()
		{
			var lastLoad = this.loadVal,
				params = this.extraParams;

			if (this.loadTimer)
			{
				clearTimeout(this.loadTimer);
			}

			this.loadVal = this.getPartialValue();

			if (this.loadVal == '')
			{
				this.hideResults();
				return;
			}

			if (this.loadVal == lastLoad)
			{
				return;
			}

			if (this.loadVal.length < this.minLength)
			{
				return;
			}

			params[this.queryKey] = this.loadVal;

			if (this.extraFields != '')
			{
				$(this.extraFields).each(function()
				{
					params[this.name] = $(this).val();
				});
			}

			if (this.xhr)
			{
				this.xhr.abort();
			}

			this.xhr = XenForo.ajax(
				this.url,
				params,
				$.context(this, 'showResults'),
				{ global: false, error: false }
			);
		},

		hideResults: function()
		{
			this.results.hideResults();
		},

		showResults: function(results)
		{
			if (this.xhr)
			{
				this.xhr = false;
			}

			if (this.jsonContainer && results)
			{
				results = results[this.jsonContainer];
			}

			this.results.showResults(this.getPartialValue(), results, this.$input);
		},

		addValue: function(value)
		{
			if (!this.multiple)
			{
				this.$input.val(value);
			}
			else
			{
				var values = this.getFullValues();
				if (value != '')
				{
					if (values.length)
					{
						value = ' ' + value;
					}
					values.push(value + this.multiple + ' ');
				}
				this.$input.val(values.join(this.multiple));
			}
			this.$input.trigger("AutoComplete", {inserted: value, current: this.$input.val()});

			if (this.autoSubmit)
			{
				this.$input.closest('form').submit();
			}
			else
			{
				this.$input.focus();
			}
		},

		getFullValues: function()
		{
			var val = this.$input.val();

			if (val == '')
			{
				return [];
			}

			if (!this.multiple)
			{
				return [val];
			}
			else
			{
				splitPos = val.lastIndexOf(this.multiple);
				if (splitPos == -1)
				{
					return [];
				}
				else
				{
					val = val.substr(0, splitPos);
					return val.split(this.multiple);
				}
			}
		},

		getPartialValue: function()
		{
			var val = this.$input.val(),
				splitPos;

			if (!this.multiple)
			{
				return $.trim(val);
			}
			else
			{
				splitPos = val.lastIndexOf(this.multiple);
				if (splitPos == -1)
				{
					return $.trim(val);
				}
				else
				{
					return $.trim(val.substr(splitPos + this.multiple.length));
				}
			}
		}
	};
	XenForo.AutoComplete.getDefaultUrl = function()
	{
		if (XenForo.AutoComplete.defaultUrl === null)
		{
			if ($('html').hasClass('Admin'))
			{
				XenForo.AutoComplete.defaultUrl = 'admin.php?users/search-name&_xfResponseType=json';
			}
			else
			{
				XenForo.AutoComplete.defaultUrl = 'index.php?members/find&_xfResponseType=json';
			}
		};

		return XenForo.AutoComplete.defaultUrl;
	};
	XenForo.AutoComplete.defaultUrl = null;

	// *********************************************************************

	XenForo.UserTagger = function($element) { this.__construct($element); };
	XenForo.UserTagger.prototype =
	{
		__construct: function($textarea)
		{
			this.$textarea = $textarea;
			this.url = $textarea.data('acurl') || XenForo.AutoComplete.getDefaultUrl();
			this.acResults = new XenForo.AutoCompleteResults({
				onInsert: $.context(this, 'insertAutoComplete')
			});

			var self = this,
				hideCallback = function() {
				setTimeout(function() {
					self.acResults.hideResults();
				}, 200);
			};

			$(document).on('scroll', hideCallback);

			$textarea.on('click blur', hideCallback);
			$textarea.on('keydown', function(e) {
				var prevent = true,
					acResults = self.acResults;

				if (!acResults.isVisible())
				{
					return;
				}

				switch (e.keyCode)
				{
					case 40: acResults.selectResult(1); break; // down
					case 38: acResults.selectResult(-1); break; // up
					case 27: acResults.hideResults(); break; // esc
					case 13: acResults.insertSelectedResult(); break; // enter

					default:
						prevent = false;
				}

				if (prevent)
				{
					e.stopPropagation();
					e.stopImmediatePropagation();
					e.preventDefault();
				}
			});
			$textarea.on('keyup', function(e) {
				var autoCompleteText = self.findCurrentAutoCompleteOption();
				if (autoCompleteText)
				{
					self.triggerAutoComplete(autoCompleteText);
				}
				else
				{
					self.hideAutoComplete();
				}
			});
		},

		findCurrentAutoCompleteOption: function()
		{
			var $textarea = this.$textarea;

			$textarea.focus();
			var sel = $textarea.getSelection(),
				testText,
				lastAt;

			if (!sel || sel.end <= 1)
			{
				return false;
			}

			testText = $textarea.val().substring(0, sel.end);
			lastAt = testText.lastIndexOf('@');

			if (lastAt != -1 && (lastAt == 0 || testText.substr(lastAt - 1, 1).match(/(\s|[\](,]|--)/)))
			{
				var afterAt = testText.substr(lastAt + 1);
				if (!afterAt.match(/\s/) || afterAt.length <= 10)
				{
					return afterAt;
				}
			}

			return false;
		},

		insertAutoComplete: function(name)
		{
			var $textarea = this.$textarea;

			$textarea.focus();
			var sel = $textarea.getSelection(),
				testText;

			if (!sel || sel.end <= 1)
			{
				return false;
			}

			testText = $textarea.val().substring(0, sel.end);

			var lastAt = testText.lastIndexOf('@');
			if (lastAt != -1)
			{
				$textarea.setSelection(lastAt, sel.end);
				$textarea.replaceSelectedText('@' + name + ' ', 'collapseToEnd');
				this.lastAcLookup = name + ' ';
			}
		},

		triggerAutoComplete: function(name)
		{
			if (this.lastAcLookup && this.lastAcLookup == name)
			{
				return;
			}

			this.hideAutoComplete();
			this.lastAcLookup = name;
			if (name.length > 2 && name.substr(0, 1) != '[')
			{
				this.acLoadTimer = setTimeout($.context(this, 'autoCompleteLookup'), 200);
			}
		},

		autoCompleteLookup: function()
		{
			if (this.acXhr)
			{
				this.acXhr.abort();
			}

			if (this.lastAcLookup != this.findCurrentAutoCompleteOption())
			{
				return;
			}

			this.acXhr = XenForo.ajax(
				this.url,
				{ q: this.lastAcLookup },
				$.context(this, 'showAutoCompleteResults'),
				{ global: false, error: false }
			);
		},

		showAutoCompleteResults: function(ajaxData)
		{
			this.acXhr = false;
			this.acResults.showResults(
				this.lastAcLookup,
				ajaxData.results,
				this.$textarea
			);
		},

		hideAutoComplete: function()
		{
			this.acResults.hideResults();

			if (this.acLoadTimer)
			{
				clearTimeout(this.acLoadTimer);
				this.acLoadTimer = false;
			}
		}
	};

	// *********************************************************************

	XenForo.AutoCompleteResults = function(options) { this.__construct(options); };
	XenForo.AutoCompleteResults.prototype =
	{
		__construct: function(options)
		{
			this.options = $.extend({
				onInsert: false
			}, options);

			this.selectedResult = 0;
			this.$results = false;
			this.resultsVisible = false;
			this.resizeBound = false;
		},

		isVisible: function()
		{
			return this.resultsVisible;
		},

		hideResults: function()
		{
			this.resultsVisible = false;

			if (this.$results)
			{
				this.$results.hide();
			}
		},

		showResults: function(val, results, $targetOver, cssPosition)
		{
			var maxZIndex = 0,
				i,
				filterRegex,
				result,
				$li;

			if (!results)
			{
				this.hideResults();
				return;
			}

			this.resultsVisible = false;

			if (!this.$results)
			{
				this.$results = $('<ul />')
					.css({position: 'absolute', display: 'none'})
					.addClass('autoCompleteList')
					.appendTo(document.body);

				$targetOver.parents().each(function(i, el)
				{
					var $el = $(el),
						zIndex = parseInt($el.css('z-index'), 10);

					if (zIndex > maxZIndex)
					{
						maxZIndex = zIndex;
					}
				});

				this.$results.css('z-index', maxZIndex + 1000);
			}
			else
			{
				this.$results.hide().empty();
			}

			filterRegex = new RegExp('(' + XenForo.regexQuote(val) + ')', 'i');

			for (i in results)
			{
				if (!results.hasOwnProperty(i))
				{
					continue;
				}

				result = results[i];

				$li = $('<li />')
					.css('cursor', 'pointer')
					.attr('unselectable', 'on')
					.data('autocomplete', i)
					.click($.context(this, 'resultClick'))
					.mouseenter($.context(this, 'resultMouseEnter'));

				if (typeof result == 'string')
				{
					$li.html(XenForo.htmlspecialchars(result).replace(filterRegex, '<strong>$1</strong>'));
				}
				else
				{
					$li.html(result['username'].replace(filterRegex, '<strong>$1</strong>'))
						.prepend($('<img class="autoCompleteAvatar" />').attr('src', result['avatar']));
				}

				$li.appendTo(this.$results);
			}

			if (!this.$results.children().length)
			{
				return;
			}

			this.selectResult(0, true);

			if (!this.resizeBound)
			{
				$(window).bind('resize', $.context(this, 'hideResults'));
			}

			if (!cssPosition)
			{
				var offset = $targetOver.offset();

				cssPosition = {
					top: offset.top + $targetOver.outerHeight(),
					left: offset.left
				};

				if (XenForo.isRTL())
				{
					cssPosition.right = $('html').width() - offset.left - $targetOver.outerWidth();
					cssPosition.left = 'auto';
				}
			}

			this.$results.css(cssPosition).show();
			this.resultsVisible = true;
		},

		resultClick: function(e)
		{
			e.stopPropagation();

			this.insertResult($(e.currentTarget).data('autocomplete'));
			this.hideResults();
		},

		resultMouseEnter: function (e)
		{
			this.selectResult($(e.currentTarget).index(), true);
		},

		selectResult: function(shift, absolute)
		{
			var sel, children;

			if (!this.$results)
			{
				return;
			}

			if (absolute)
			{
				this.selectedResult = shift;
			}
			else
			{
				this.selectedResult += shift;
			}

			sel = this.selectedResult;
			children = this.$results.children();
			children.each(function(i)
			{
				if (i == sel)
				{
					$(this).addClass('selected');
				}
				else
				{
					$(this).removeClass('selected');
				}
			});

			if (sel < 0 || sel >= children.length)
			{
				this.selectedResult = -1;
			}
		},

		insertSelectedResult: function()
		{
			var res, ret = false;

			if (!this.resultsVisible)
			{
				return false;
			}

			if (this.selectedResult >= 0)
			{
				res = this.$results.children().get(this.selectedResult);
				if (res)
				{
					this.insertResult($(res).data('autocomplete'));
					ret = true;
				}
			}

			this.hideResults();

			return ret;
		},

		insertResult: function(value)
		{
			if (this.options.onInsert)
			{
				this.options.onInsert(value);
			}
		}
	};

	// *********************************************************************

	XenForo.AutoSelect = function($input)
	{
		$input.bind('focus', function(e)
		{
			setTimeout(function() { $input.select(); }, 50);
		});
	};

	// *********************************************************************

	/**
	 * Status Editor
	 *
	 * @param jQuery $textarea.StatusEditor
	 */
	XenForo.StatusEditor = function($input) { this.__construct($input); };
	XenForo.StatusEditor.prototype =
	{
		__construct: function($input)
		{
			this.$input = $input
				.keyup($.context(this, 'update'))
				.keydown($.context(this, 'preventNewline'));

			this.$counter = $(this.$input.data('statuseditorcounter'));
			if (!this.$counter.length)
			{
				this.$counter = $('<span />').insertAfter(this.$input);
			}
			this.$counter
				.addClass('statusEditorCounter')
				.text('0');

			this.$form = this.$input.closest('form').bind(
			{
				AutoValidationComplete: $.context(this, 'saveStatus')
			});

			this.charLimit = 140; // Twitter max characters
			this.charCount = 0; // number of chars currently in use

			this.update();
		},

		/**
		 * Handles key events on the status editor, updates the 'characters remaining' output.
		 *
		 * @param Event e
		 */
		update: function(e)
		{
			var statusText = this.$input.val();

			if (this.$input.attr('placeholder') && this.$input.attr('placeholder') == statusText)
			{
				this.setCounterValue(this.charLimit, statusText.length);
			}
			else
			{
				this.setCounterValue(this.charLimit - statusText.length, statusText.length);
			}
		},

		/**
		 * Sets the value of the character countdown, and appropriate classes for that value.
		 *
		 * @param integer Characters remaining
		 * @param integer Current length of status text
		 */
		setCounterValue: function(remaining, length)
		{
			if (remaining < 0)
			{
				this.$counter.addClass('error');
				this.$counter.removeClass('warning');
			}
			else if (remaining <= this.charLimit - 130)
			{
				this.$counter.removeClass('error');
				this.$counter.addClass('warning');
			}
			else
			{
				this.$counter.removeClass('error');
				this.$counter.removeClass('warning');
			}

			this.$counter.text(remaining);
			this.charCount = length || this.$input.val().length;
		},

		/**
		 * Don't allow newline characters in the status message.
		 *
		 * Submit the form if [Enter] or [Return] is hit.
		 *
		 * @param Event e
		 */
		preventNewline: function(e)
		{
			if (e.which == 13) // return / enter
			{
				e.preventDefault();

				$(this.$input.get(0).form).submit();

				return false;
			}
		},

		/**
		 * Updates the status field after saving
		 *
		 * @param event e
		 */
		saveStatus: function(e)
		{
			this.$input.val('');
			this.update(e);

			if (e.ajaxData && e.ajaxData.status !== undefined)
			{
				$('.CurrentStatus').text(e.ajaxData.status);
			}
		}
	};

	// *********************************************************************

	/**
	 * Special effect that allows positioning based on bottom / left rather than top / left
	 */
	$.tools.tooltip.addEffect('PreviewTooltip',
	function(callback)
	{
		var triggerOffset = this.getTrigger().offset(),
			config = this.getConf(),
			css = {
				top: 'auto',
				bottom: $(window).height() - triggerOffset.top + config.offset[0]
			},
			narrowScreen = ($(window).width() < 480);

		if (XenForo.isRTL())
		{
			css.right = $('html').width() - this.getTrigger().outerWidth() - triggerOffset.left - config.offset[1];
			css.left = 'auto';
		}
		else
		{
			css.left = triggerOffset.left + config.offset[1];
			if (narrowScreen)
			{
				css.left = Math.min(50, css.left);
			}
		}

		this.getTip().css(css).xfFadeIn(XenForo.speed.normal);

	},
	function(callback)
	{
		this.getTip().xfFadeOut(XenForo.speed.fast);
	});

	/**
	 * Cache to store fetched previews
	 *
	 * @var object
	 */
	XenForo._PreviewTooltipCache = {};

	XenForo.PreviewTooltip = function($el)
	{
		var hasTooltip, previewUrl, setupTimer;

		if (!parseInt(XenForo._enableOverlays))
		{
			return;
		}

		if (!(previewUrl = $el.data('previewurl')))
		{
			console.warn('Preview tooltip has no preview: %o', $el);
			return;
		}

		$el.find('[title]').andSelf().attr('title', '');

		$el.bind(
		{
			mouseenter: function(e)
			{
				if (hasTooltip)
				{
					return;
				}

				setupTimer = setTimeout(function()
				{
					if (hasTooltip)
					{
						return;
					}

					hasTooltip = true;

					var $tipSource = $('#PreviewTooltip'),
						$tipHtml,
						xhr;

					if (!$tipSource.length)
					{
						console.error('Unable to find #PreviewTooltip');
						return;
					}

					console.log('Setup preview tooltip for %s', previewUrl);

					$tipHtml = $tipSource.clone()
						.removeAttr('id')
						.addClass('xenPreviewTooltip')
						.appendTo(document.body);

					if (!XenForo._PreviewTooltipCache[previewUrl])
					{
						xhr = XenForo.ajax(
							previewUrl,
							{},
							function(ajaxData)
							{
								if (XenForo.hasTemplateHtml(ajaxData))
								{
									XenForo._PreviewTooltipCache[previewUrl] = ajaxData.templateHtml;

									$(ajaxData.templateHtml).xfInsert('replaceAll', $tipHtml.find('.PreviewContents'));
								}
								else
								{
									$tipHtml.remove();
								}
							},
							{
								type: 'GET',
								error: false,
								global: false
							}
						);
					}

					$el.tooltip(XenForo.configureTooltipRtl({
						predelay: 500,
						delay: 0,
						effect: 'PreviewTooltip',
						fadeInSpeed: 'normal',
						fadeOutSpeed: 'fast',
						tip: $tipHtml,
						position: 'bottom left',
						offset: [ 10, -15 ] // was 10, 25
					}));

					$el.data('tooltip').show(0);

					if (XenForo._PreviewTooltipCache[previewUrl])
					{
						$(XenForo._PreviewTooltipCache[previewUrl])
							.xfInsert('replaceAll', $tipHtml.find('.PreviewContents'), 'show', 0);
					}
				}, 800);
			},

			mouseleave: function(e)
			{
				if (hasTooltip)
				{
					if ($el.data('tooltip'))
					{
						$el.data('tooltip').hide();
					}

					return;
				}

				if (setupTimer)
				{
					clearTimeout(setupTimer);
				}
			},

			mousedown: function(e)
			{
				// the click will cancel a timer or hide the tooltip
				if (setupTimer)
				{
					clearTimeout(setupTimer);
				}

				if ($el.data('tooltip'))
				{
					$el.data('tooltip').hide();
				}
			}
		});
	};

	// *********************************************************************

	/**
	 * Allows an entire block to act as a link in the navigation popups
	 *
	 * @param jQuery li.PopupItemLink
	 */
	XenForo.PopupItemLink = function($listItem)
	{
		var href = $listItem.find('.PopupItemLink').first().attr('href');

		if (href)
		{
			$listItem
				.addClass('PopupItemLinkActive')
				.click(function(e)
				{
					if ($(e.target).is('a'))
					{
						return;
					}
					XenForo.redirect(href);
				});
		}
	};

	// *********************************************************************

	/**
	 * Allows a link or input to load content via AJAX and insert it into the DOM.
	 * The control element to which this is applied must have href or data-href attributes
	 * and a data-target attribute describing a jQuery selector for the element relative to which
	 * the content will be inserted.
	 *
	 * You may optionally provide a data-method attribute to override the default insertion method
	 * of 'appendTo'.
	 *
	 * By default, the control will be unlinked and have its click event unbound after a single use.
	 * Specify data-unlink="false" to prevent this default behaviour.
	 *
	 * Upon successful return of AJAX data, the control element will fire a 'ContentLoaded' event,
	 * including ajaxData and textStatus data properties.
	 */
	XenForo.Loader = function($link)
	{
		var clickHandler = function(e)
		{
			var href = $link.attr('href') || $link.data('href'),
				target = $link.data('target');

			if (href && $(target).length)
			{
				e.preventDefault();

				if ($link.data('tooltip'))
				{
					$link.data('tooltip').hide();
				}

				XenForo.ajax(href, {}, function(ajaxData, textStatus)
				{
					if (XenForo.hasResponseError(ajaxData))
					{
						return false;
					}

					var insertEvent = new $.Event('ContentLoaded');
						insertEvent.ajaxData = ajaxData;
						insertEvent.textStatus = textStatus;

					$link.trigger(insertEvent);

					if (!insertEvent.isDefaultPrevented())
					{
						if (ajaxData.templateHtml)
						{
							new XenForo.ExtLoader(ajaxData, function()
							{
								var method = $link.data('method');

								if (typeof $.fn[method] != 'function')
								{
									method = 'appendTo';
								}

								if (method == 'replaceAll')
								{
									$(ajaxData.templateHtml).xfInsert(method, target, 'show', 0);
								}
								else
								{
									$(ajaxData.templateHtml).xfInsert(method, target);
								}

								if ($link.data('unlink') !== false)
								{
									$link.removeAttr('href').removeData('href').unbind('click', clickHandler);
								}
							});
						}
					}
				});
			}
		};

		$link.bind('click', clickHandler);
	};

	// *********************************************************************

	/**
	 * Allows a control to create a clone of an existing field, like 'add new response' for polls
	 *
	 * @param jQuery $button.FieldAdder[data-source=#selectorOfCloneSource]
	 */
	XenForo.FieldAdder = function($button)
	{
		$($button.data('source')).filter('.PollNonJsInput').remove();

		$button.click(function(e)
		{
			var $source = $($button.data('source')),
				maxFields = $button.data('maxfields'),
				$clone = null;

			console.log('source.length %s, maxfields %s', $source.length, maxFields);

			if ($source.length && (!maxFields || ($source.length < maxFields)))
			{
				$clone = $source.last().clone();
				$clone.find('input:not([type="button"], [type="submit"])').val('').prop('disabled', true);
				$clone.find('.spinBoxButton').remove();
				$button.trigger({
					type: 'FieldAdderClone',
					clone: $clone
				});
				$clone.xfInsert('insertAfter', $source.last(), false, false, function()
				{
					var $inputs = $clone.find('input');
					$inputs.prop('disabled', false);
					$inputs.first().focus().select();

					if (maxFields)
					{
						if ($($button.data('source')).length >= maxFields)
						{
							$button.xfRemove();
						}
					}
				});
			}
		});
	};

	// *********************************************************************

	/**
	 * Quick way to toggle the read status of an item
	 *
	 * @param jQuery a.ReadToggle
	 */
	XenForo.ReadToggle = function($link)
	{
		$link.click(function(e)
		{
			e.preventDefault();

			var xhr = null,
				$items = null,
				counterId = $link.data('counter');

			if (xhr == null)
			{
				$items = $link.closest('.discussionListItem').andSelf().toggleClass('unread');

				xhr = XenForo.ajax($link.attr('href'), { _xfConfirm: 1 }, function(ajaxData, textStatus)
				{
					xhr = null;

					if (XenForo.hasResponseError(ajaxData))
					{
						return false;
					}

					if (typeof ajaxData.unread != 'undefined')
					{
						$items[(ajaxData.unread ? 'addClass' : 'removeClass')]('unread');
					}

					if (counterId && typeof ajaxData.counterFormatted != 'undefined')
					{
						var $counter = $(counterId),
							$total = $counter.find('span.Total');

						if ($total.length)
						{
							$total.text(ajaxData.counterFormatted);
						}
						else
						{
							$counter.text(ajaxData.counterFormatted);
						}
					}

					if (typeof ajaxData.actionPhrase != 'undefined')
					{
						if ($link.text() != '')
						{
							$link.html(ajaxData.actionPhrase);
						}
						if ($link.attr('title'))
						{
							$link.attr('title', ajaxData.actionPhrase);
						}
					}

					XenForo.alert(ajaxData._redirectMessage, '', 1000);
				});
			}
		});
	};

	// *********************************************************************

	XenForo.Notices = function($notices)
	{
		$notices.show();

		var PanelScroller;
		if ($notices.hasClass('PanelScroller'))
		{
			PanelScroller = XenForo.PanelScroller($notices.find('.PanelContainer'),
			{
				scrollable:
				{
					speed: $notices.dataOrDefault('speed', 400) * XenForo._animationSpeedMultiplier,
					vertical: XenForo.isPositive($notices.data('vertical')),
					keyboard: false,
					touch: false,
					prev: '.NoticePrev',
					next: '.NoticeNext'
				},
				autoscroll: { interval: $notices.dataOrDefault('interval', 2000) }
			});

			if (PanelScroller && PanelScroller.getItems().length > 1)
			{
				$(document).bind(
				{
					XenForoWindowBlur: function(e) { PanelScroller.stop(); },
					XenForoWindowFocus: function(e) { PanelScroller.play(); }
				});
			}
		}

		$notices.delegate('a.DismissCtrl', 'click', function(e)
		{
			e.preventDefault();

			var $ctrl = $(this),
				$notice = $ctrl.closest('.Notice'),
				$noticeParent = $notice.parent();

			if ($ctrl.data('tooltip'))
			{
				$ctrl.data('tooltip').hide();
			}

			if (PanelScroller)
			{
				PanelScroller.removeItem($notice);

				if (!PanelScroller.getItems().length)
				{
					$notices.xfFadeUp();
				}
			}
			else
			{
				$notice.xfFadeUp(function() {
					$notice.remove();
					if (!$noticeParent.find('.Notice').length)
					{
						$notices.xfFadeUp();
					}
				});
			}

			if (!$ctrl.data('xhr'))
			{
				$ctrl.data('xhr', XenForo.ajax($ctrl.attr('href'), { _xfConfirm: 1 }, function(ajaxData, textStatus)
				{
					$ctrl.removeData('xhr');

					if (XenForo.hasResponseError(ajaxData))
					{
						return false;
					}

					//XenForo.alert(ajaxData._redirectMessage, '', 2000);
				}));
			}
		});
	};

	// *********************************************************************

	XenForo.PanelScroller = function($container, options)
	{
		var $items = $container.find('.Panels > *');

		// don't initialize if we have just a single panel
		if ($items.length < 2)
		{
			$container.find('.Panels').css('position', 'static');
			return false;
		}

		$items.find('script').remove(); // script should already have been run and document.writes break stuff

		function resizeItems()
		{
			var maxHeight = 0;

			$container.find('.Panels > *').css({ width: $container.innerWidth(), height: 'auto' }).each(function()
			{
				maxHeight = Math.max($(this).outerHeight(), maxHeight);

			}).andSelf().css('height', maxHeight);

			var api = $container.data('scrollable');
			if (api)
			{
				api.seekTo(api.getIndex(), 0);
			}
		};

		options = $.extend(true,
		{
			scrollable:
			{
				circular: true,
				items: '.Panels'
			},
			navigator:
			{
				navi: '.Nav',
				naviItem: 'a',
				activeClass: 'current'
			},
			autoscroll:
			{
				interval: 3000
			}

		}, options);

		$container.css('overflow', 'hidden');

		if (!options.scrollable.vertical)
		{
			$container.css('height', 'auto')
				.find('.Panels').css('width', '20000em')
				.find('.panel').css('float', (XenForo.isRTL() ? 'right' : 'left'));
		}

		$(window).bind('load resize', resizeItems);
		$('.mainContent').bind('XenForoResize', resizeItems);

		resizeItems();

		$container.scrollable(options.scrollable).navigator(options.navigator);

		if ($items.length > 1)
		{
			$container.autoscroll(options.autoscroll);
		}

		return $container.data('scrollable');
	};

	// *********************************************************************

	XenForo.DisplayIgnoredContent = function(e)
	{
		var i, j, styleSheet, rules, rule;

		e.preventDefault();

		$('a.DisplayIgnoredContent').hide();

		// remove the styling that hides quotes etc.
		$('#ignoredUserCss').empty().remove();

		if (document.styleSheets)
		{
			for (i = 0; i < document.styleSheets.length; i++)
			{
				styleSheet = document.styleSheets[i];
				try
				{
					rules = (styleSheet.cssRules ? styleSheet.cssRules : styleSheet.rules);
				}
				catch (e)
				{
					rules = false;
				}
				if (!rules)
				{
					continue;
				}
				for (j = 0; j < rules.length; j++)
				{
					rule = rules[j];

					if (rule.selectorText && rule.selectorText.toLowerCase() == '.ignored')
					{
						if (styleSheet.deleteRule)
						{
							styleSheet.deleteRule(j);
						}
						else
						{
							styleSheet.removeRule(j);
						}
					}
				}
			}
		}

		$('.ignored').removeClass('ignored');
	};

	if ($('html').hasClass('Public'))
	{
		$(function()
		{
			$('body').delegate('a.DisplayIgnoredContent', 'click', XenForo.DisplayIgnoredContent);

			if (window.location.hash)
			{
				var $jump = $(window.location.hash.replace(/[^\w_#-]/g, ''));
				if ($jump.hasClass('ignored'))
				{
					$jump.removeClass('ignored');
					$jump.get(0).scrollIntoView(true);
				}
			}
		});
	}

	// *********************************************************************

	XenForo.SpoilerBBCode = function($spoiler)
	{
		$spoiler.click(function(e)
		{
			$spoiler.siblings(':first').css('fontSize', '25pt');
			return false;
		});
		
		/*$spoiler.click(function(e)
		{
			$spoiler.html($spoiler.data('spoiler')).removeClass('Spoiler').addClass('bbCodeSpoiler');
		});*/
	};

	// *********************************************************************

	/**
	 * Produces centered, square crops of thumbnails identified by data-thumb-selector within the $container.
	 * Requires that CSS of this kind is in place:
	 * .SquareThumb
	 * {
	 * 		position: relative; display: block; overflow: hidden;
	 * 		width: {$thumbHeight}px; height: {$thumbHeight}px;
	 * }
	 * .SquareThumb img
	 * {
	 * 		position: relative; display: block;
	 * }
	 *
	 * @param jQuery $container
	 */
	XenForo.SquareThumbs = function($container)
	{
		var thumbHeight = $container.data('thumb-height') || 44,
			thumbSelector = $container.data('thumb-selector') || 'a.SquareThumb';

		console.info('XenForo.SquareThumbs: %o', $container);

		var $imgs = $container.find(thumbSelector).addClass('SquareThumb').children('img');

		var thumbProcessor = function()
		{
			var $thumb = $(this),
				w = $thumb.width(),
				h = $thumb.height();

			if (!w || !h)
			{
				return;
			}

			if (h > w)
			{
				$thumb.css('width', thumbHeight);
				$thumb.css('top', ($thumb.height() - thumbHeight) / 2 * -1);
			}
			else
			{
				$thumb.css('height', thumbHeight);
				$thumb.css('left', ($thumb.width() - thumbHeight) / 2 * -1);
			}
		};

		$imgs.load(thumbProcessor);
		$imgs.each(thumbProcessor);
	};

	// *********************************************************************

	// Register overlay-loading controls
	// TODO: when we have a global click handler, change this to use rel="Overlay" instead of class="OverlayTrigger"
	XenForo.register(
		'a.OverlayTrigger, input.OverlayTrigger, button.OverlayTrigger, label.OverlayTrigger, a.username, a.avatar',
		'XenForo.OverlayTrigger'
	);

	XenForo.register('.ToggleTrigger', 'XenForo.ToggleTrigger');

	if (!XenForo.isTouchBrowser())
	{
		// Register tooltip elements for desktop browsers
		XenForo.register('.Tooltip', 'XenForo.Tooltip');
		XenForo.register('a.StatusTooltip', 'XenForo.StatusTooltip');
		XenForo.register('.PreviewTooltip', 'XenForo.PreviewTooltip');
	}

	XenForo.register('a.LbTrigger', 'XenForo.LightBoxTrigger');

	// Register click-proxy controls
	XenForo.register('.ClickProxy', 'XenForo.ClickProxy');

	// Register popup menu controls
	XenForo.register('.Popup', 'XenForo.PopupMenu', 'XenForoActivatePopups');

	// Register scrolly pagenav elements
	XenForo.register('.PageNav', 'XenForo.PageNav');

	// Register tabs
	XenForo.register('.Tabs', 'XenForo.Tabs');

	// Register square thumb cropper
	XenForo.register('.SquareThumbs', 'XenForo.SquareThumbs');

	// Handle all xenForms
	XenForo.register('form.xenForm, .MultiSubmitFix', 'XenForo.MultiSubmitFix');

	// Register check-all controls
	XenForo.register('input.CheckAll, a.CheckAll, label.CheckAll', 'XenForo.CheckAll');

	// Register auto-checker controls
	XenForo.register('input.AutoChecker', 'XenForo.AutoChecker');

	// Register toggle buttons
	XenForo.register('label.ToggleButton', 'XenForo.ToggleButton');

	// Register auto inline uploader controls
	XenForo.register('form.AutoInlineUploader', 'XenForo.AutoInlineUploader');

	// Register form auto validators
	XenForo.register('form.AutoValidator', 'XenForo.AutoValidator');

	// Register auto time zone selector
	XenForo.register('select.AutoTimeZone', 'XenForo.AutoTimeZone');

	// Register generic content loader
	XenForo.register('a.Loader, input.Loader', 'XenForo.Loader');

	var supportsStep = 'step' in document.createElement('input');

	// Register form controls
	XenForo.register('input, textarea', function(i)
	{
		var $this = $(this);

		switch ($this.attr('type'))
		{
			case 'hidden':
			case 'submit':
				return;
			case 'checkbox':
			case 'radio':
				// Register auto submitters
				if ($this.hasClass('SubmitOnChange'))
				{
					XenForo.create('XenForo.SubmitOnChange', this);
				}
				return;
		}

		// Spinbox / input[type=number]
		if ($this.attr('type') == 'number' && supportsStep)
		{
			// use the XenForo implementation instead, as browser implementations seem to be universally horrible
			this.type = 'text';
			$this.addClass('SpinBox number');
		}
		if ($this.hasClass('SpinBox'))
		{
			XenForo.create('XenForo.SpinBox', this);
		}

		// Prompt / placeholder
		if ($this.hasClass('Prompt'))
		{
			console.error('input.Prompt[title] is now deprecated. Please replace any instances with input[placeholder] and remove the Prompt class.');
			$this.attr({ placeholder: $this.attr('title'), title: '' });
		}
		if ($this.attr('placeholder'))
		{
			XenForo.create('XenForo.Prompt', this);
		}

		// LiveTitle
		if ($this.data('livetitletemplate'))
		{
			XenForo.create('XenForo.LiveTitle', this);
		}

		// DatePicker
		if ($this.is(':date'))
		{
			XenForo.create('XenForo.DatePicker', this);
		}

		// AutoComplete
		if ($this.hasClass('AutoComplete'))
		{
			XenForo.create('XenForo.AutoComplete', this);
		}

		// UserTagger
		if ($this.hasClass('UserTagger'))
		{
			XenForo.create('XenForo.UserTagger', this);
		}

		// AutoSelect
		if ($this.hasClass('AutoSelect'))
		{
			XenForo.create('XenForo.AutoSelect', this);
		}

		// AutoValidator
		if (XenForo.isAutoValidatorField(this))
		{
			XenForo.create('XenForo.AutoValidatorControl', this);
		}

		if ($this.is('textarea.StatusEditor'))
		{
			XenForo.create('XenForo.StatusEditor', this);
		}

		// Register Elastic textareas
		if ($this.is('textarea.Elastic'))
		{
			XenForo.create('XenForo.TextareaElastic', this);
		}
	});

	// Register form previewer
	XenForo.register('form.Preview', 'XenForo.PreviewForm');

	// Register field adder
	XenForo.register('a.FieldAdder, input.FieldAdder', 'XenForo.FieldAdder');

	// Read status toggler
	XenForo.register('a.ReadToggle', 'XenForo.ReadToggle');

	/**
	 * Public-only registrations
	 */
	if ($('html').hasClass('Public'))
	{
		// Register the login bar handle
		XenForo.register('#loginBar', 'XenForo.LoginBar');

		// Register the header search box
		XenForo.register('#QuickSearch', 'XenForo.QuickSearch');

		// Register attribution links
		XenForo.register('a.AttributionLink', 'XenForo.AttributionLink');

		// CAPTCHAS
		XenForo.register('#ReCaptcha', 'XenForo.ReCaptcha');
		XenForo.register('#SolveMediaCaptcha', 'XenForo.SolveMediaCaptcha');
		XenForo.register('#KeyCaptcha', 'XenForo.KeyCaptcha');
		XenForo.register('#Captcha', 'XenForo.Captcha');

		// Resize large BB code images
		XenForo.register('img.bbCodeImage', 'XenForo.BbCodeImage');

		// Handle like/unlike links
		XenForo.register('a.LikeLink', 'XenForo.LikeLink');

		// Register node description tooltips
		if (!XenForo.isTouchBrowser())
		{
			XenForo.register('h3.nodeTitle a', 'XenForo.NodeDescriptionTooltip');
		}

		// Register visitor menu
		XenForo.register('#AccountMenu', 'XenForo.AccountMenu');

		// Register follow / unfollow links
		XenForo.register('a.FollowLink', 'XenForo.FollowLink');

		XenForo.register('li.PopupItemLink', 'XenForo.PopupItemLink');

		// Register notices
		XenForo.register('#Notices', 'XenForo.Notices');

		// Spoiler BB Code
		XenForo.register('button.Spoiler', 'XenForo.SpoilerBBCode');
	}

	// Register control disablers last so they disable anything added by other behaviours
	XenForo.register('input:checkbox.Disabler, input:radio.Disabler', 'XenForo.Disabler');

	// *********************************************************************
	var isScrolled = false;
	$(window).on('load', function() {
		if (isScrolled || !window.location.hash)
		{
			return;
		}

		var hash = window.location.hash.replace(/[^a-zA-Z0-9_-]/g, ''),
			$match = hash ? $('#' + hash) : $();

		if ($match.length)
		{
			$match.get(0).scrollIntoView(true);
		}
	});

	/**
	 * Use jQuery to initialize the system
	 */
	$(function()
	{
		XenForo.Facebook.start();
		XenForo.init();

		if (window.location.hash)
		{
			// do this after the document is ready as triggering it too early
			// causes the initial hash to trigger a scroll
			$(window).one('scroll', function(e) {
				isScrolled = true;
			});
		}
	});
}
(jQuery, this, document);