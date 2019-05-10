/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	XenForo.LightBox = function(Overlay, containerSelector)
	{
		var $lightBox = $('#LightBox').prop('unselectable', true),

			$imageNav = $lightBox.find('.imageNav'),
			$image = $('#LbImg'),
			$imageContainer = $lightBox.find('.imageContainer'),
			images = [],

			$thumbStrip = $('#LbThumbs'),
			thumbHeight = $thumbStrip.data('thumbheight'),
			thumbOffset = 0,
			thumbShift = 0,
			selectedThumbSrc = '';

		/**
		 * Handle navigation clicks
		 */
		$('#LbPrev, #LbNext, #LightBox .imageContainer').click($.context(function(e)
		{
			e.preventDefault();

			this.shift($(e.target).closest('.imageNav').attr('id') == 'LbPrev' ? -1 : 1);

			return false;

		}, this));

		this.bindNav = function()
		{
			$(document).bind(
			{
				'keydown.lightbox': $.context(function(e)
				{
					switch (e.keyCode)
					{
						case 37:
						case 38:
							e.preventDefault();
							return this.shift(-1);
						case 39:
						case 40:
							e.preventDefault();
							return this.shift(1);
					}
				}, this),

				'wheel.lightbox': $.context(function(e, delta)
				{
					if (delta)
					{
						e.preventDefault();
						this.shift(delta < 0 ? 1 : -1);
					}
				}, this)
			});
		};

		this.unbindNav = function(e)
		{
			$(document).unbind('.lightbox');
		};

		/**
		 * Move the selection one image forward or backward
		 *
		 * @param integer -1 or 1
		 *
		 * @return boolean
		 */
		this.shift = function(shift)
		{
			var $thumbs = $thumbStrip.find('li:not(#LbThumbTemplate) a');

			$thumbs.each($.context(function(i, thumb)
			{
				if ($(thumb).data('src') == $image.attr('src'))
				{
					i += shift;

					if (i < 0)
					{
						i = $thumbs.length -1;
					}
					else if (i >= $thumbs.length)
					{
						i = 0;
					}

					$thumbs.eq(i).triggerHandler('click', [true]);

					return false;
				}
			}, this));
		};

		/**
		 * Sets up the strip of thumbnails for the lightbox
		 *
		 * @param jQuery $container
		 */
		this.setThumbStrip = function($container)
		{
			//console.group('Set lightbox thumbnails.');

			console.info('setThumbStrip(%o)', $container);

			var $thumbTemplate = $('#LbThumbTemplate'),
				self = this;

			images = [];

			// remove all existing thumbs
			$thumbStrip.find('li').not($thumbTemplate).remove();
			thumbShift = 0;

			$container.find('img.LbImage').each($.context(function(i, image)
			{
				var $thumb = $(image),
					src = $thumb.parent('.LbTrigger').attr('href') || $thumb.attr('src');

				if ($thumb.parents('.ignored').length)
				{
					return this;
				}

				if ($.inArray(src, images) == -1)
				{
					//console.log('Image: %s (%o)', src, image);

					images.push(src);

					setTimeout(function()
					{
						preLoader = new Image();
						preLoader.src = src;
					}, 1);

					$thumbTemplate.clone().removeAttr('id').appendTo($thumbStrip)

					// thumb > a
					.find('a').data('src', src).data('el', $thumb).click($.context(function(e, instant)
					{
						e.preventDefault();

						this.setImage($thumb, instant ? 0 : XenForo.speed.fast);

					}, this))

					// thumb > a > img
					.find('img').load(function() {
						// the setTimeout works around a chrome adblock bug
						var t = this;
						setTimeout(function() // TODO: this functionality is now provided by XenForo.SquareThumbs... migrate at some point?
						{
							var $thumb = $(t),
								w = $thumb.width(),
								h = $thumb.height();

							if (h > w)
							{
								$thumb.css('width', thumbHeight);
								$thumb.css('top', ($thumb.height() - thumbHeight) / 2 * -1);
							}
							else
							{
								$thumb.css('height', thumbHeight);
								$thumb.css(XenForo.switchStringRTL('left'), ($thumb.width() - thumbHeight) / 2 * -1);
							}

							$thumb.css('visibility', 'visible');

						}, 0);
					}).error(function() {
						self.removeFailedThumb(this);
					}).attr('src', $thumb.attr('src'));
				}
			}, this));

			//console.info('Lightbox images: %s', images.length);

			// show or hide the image nav depending on how many images there are
			switch (images.length)
			{
				case 0: return false;
				case 1: $imageNav.hide(); break;
				default: $imageNav.show(); break;
			}

			//console.groupEnd();

			return this;
		};

		this.removeFailedThumb = function(thumb)
		{
			$(thumb).closest('li').remove();

			switch ($thumbStrip.find('li:not(#LbThumbTemplate)').length)
			{
				case 0: Overlay.close(); return false;
				case 1: $imageNav.hide(); break;
				default: $imageNav.show(); break;
			}

			this.setDimensions(true);
			this.selectThumb(selectedThumbSrc, 0);
			return true;
		};

		/**
		 * Sets a new image to be shown in the lightbox
		 *
		 * @param jQuery $image
		 * @param integer animationSpeed
		 *
		 * @returns {XenForo.LightBox}
		 */
		this.setImage = function($thumb, animationSpeed)
		{
			if ($thumb === undefined)
			{
				$imageContainer.find('img.LbImg').not($image).remove();

				return this;
			}

			var $pic,
				$container = $thumb.closest(containerSelector),
				imageSource = $thumb.parent('.LbTrigger').attr('href') || $thumb.attr('src'),
				self = this;

			$pic = $image.clone(true).removeAttr('id').attr('src', 'about:blank');

			var loadFunc = $.context(function()
			{
				$imageContainer.find('img.LbImg').not($image).remove();

				$pic.css(
				{
					'maxWidth': $imageContainer.width(),
					'maxHeight': $imageContainer.height()
				});

				$pic.prependTo($image.parent()).css(
				{
					'position': 'static',
					'margin-top': ($imageContainer.height() - $pic.height()) / 2,
					'visibility': 'visible'
				});
				$pic.attr('src', imageSource);

				$image.attr('src', imageSource);
			}, this);

			// this approach works around a chrome adblock bug
			$pic.one('load', function() { setTimeout(loadFunc, 0); }).one('error', function() {
				$thumbStrip.find('li:not(#LbThumbTemplate) a').each(function(i, thumb)
				{
					if ($(thumb).data('src') == imageSource)
					{
						if (self.removeFailedThumb(thumb))
						{
							self.setImage(
								$($thumbStrip.find('li:not(#LbThumbTemplate) a').get(Math.max(0, i - 1))).data('el'),
								0
							);
						}
					}
				});
			});

			$pic.attr('src', imageSource);

			this.selectThumb(imageSource, animationSpeed);

			this.setImageInfo($container, imageSource);

			this.setContentLink($container);

			return this;
		};

		/**
		 * Selects the appropriate thumb from the list
		 *
		 * @param string src
		 * @param integer animateSpeed
		 *
		 * @returns {XenForo.LightBox}
		 */
		this.selectThumb = function(src, animateSpeed)
		{
			var $thumbs = $thumbStrip.find('li:not(#LbThumbTemplate) a');

			$thumbs.find('img').fadeTo(0, 0.5);

			if (src === undefined)
			{
				src = $image.attr('src');
			}

			$thumbs.each(function(i, thumb)
			{
				if ($(thumb).data('src') == src)
				{
					selectedThumbSrc = src;
					thumbShift = i * (thumbHeight + 3);

					//console.log('selectThumb, offset = %d, shift = %d', thumbOffset, thumbShift);

					var fadeUp = function(animateSpeed)
					{
						$(thumb).find('img').fadeTo(animateSpeed / 6, 1);
					};

					$thumbStrip.stop();

					if (animateSpeed)
					{
						var animateObj = {};
						animateObj[XenForo.switchStringRTL('left')] = thumbOffset - thumbShift;
						$thumbStrip.animate(animateObj, animateSpeed, function()
						{
							fadeUp(animateSpeed);
						});
					}
					else
					{
						$thumbStrip.css(XenForo.switchStringRTL('left'), thumbOffset - thumbShift);
						fadeUp(0);
					}

					$('#LbSelectedImage').text(i + 1);
					$('#LbTotalImages').text($thumbs.length);

					return false;
				}
			});

			return this;
		};

		/**
		 * Calculates the maximum allowable height of the lightbox image
		 *
		 * @returns {XenForo.LightBox}
		 */
		this.setDimensions = function(doAlert)
		{
			var maxHeight = $(window).height()
				- Overlay.getConf().top * 2
				- $('#LbUpper').outerHeight()
				- $('#LbLower').outerHeight();

			$imageContainer.css(
			{
				height: maxHeight,
				lineHeight: 0
			});

			$lightBox.find('img.LbImg').css(
			{
				maxWidth: $imageContainer.width(),
				maxHeight: maxHeight
			});

			thumbOffset = Math.max(0, ($thumbStrip.parent().width() - (thumbHeight + 2)) / 2);

			if (doAlert)
			{
				//alert($thumbStrip.parent().width());

				//console.info('thumbOffset = %d, thumbShift = %d', thumbOffset, thumbShift);

				console.log('thumbOffset = ' + thumbOffset + ', thumbShift = ' + thumbShift);
			}

			$thumbStrip.css(XenForo.switchStringRTL('left'), thumbOffset - thumbShift);

			$('#LbReveal').css(XenForo.switchStringRTL('left'), thumbOffset).show();

			return this;
		};

		/**
		 * Sets the image info of the lightbox to the poster of the shown image
		 *
		 * @param jQuery $container
		 * @param string imageSource
		 *
		 * @returns {XenForo.LightBox}
		 */
		this.setImageInfo = function($container, imageSource)
		{
			var $avatar = $container.find('a.avatar'),
				$avatarImg = $avatar.find('img'),
				avatarSrc = false;

			if ($avatarImg.length)
			{
				avatarSrc = $avatarImg.attr('src');
			}
			else if (avatarSrc = $avatar.find('span.img').css('background-image'))
			{
				avatarSrc = avatarSrc.replace(/^url\(("|'|)([^\1]+)\1\)$/i, '$2');
			}

			if (avatarSrc)
			{
				$('#LbAvatar img').attr('src', avatarSrc);
			}
			else
			{
				$('#LbAvatar img').attr('src', 'rgba.php?r=0&g=0&b=0');
			}

			$('#LbUsername').text($container.data('author'));

			$('#LbDateTime').text($container.find('.DateTime:first').text());

			$('#LbNewWindow').attr('href', imageSource);

			return this;
		};

		/**
		 * Sets the content link of the lightbox to the content containing the shown image
		 *
		 * @param jQuery $container
		 *
		 * @returns {XenForo.LightBox}
		 */
		this.setContentLink = function($container)
		{
			var id = $container.attr('id');

			if (id)
			{
				$('#LbContentLink, #LbDateTime')
					//.text('#' + id)
					.attr('href', window.location.href)
					.attr('hash', '#' + id);
			}
			else
			{
				$('#LbContentLink').text('').removeAttr('href');
			}

			return this;
		};
	};
}
(jQuery, this, document);