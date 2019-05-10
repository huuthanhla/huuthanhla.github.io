/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	XenForo.BbCodeWysiwygEditor = function($textarea) { this.__construct($textarea); };
	XenForo.BbCodeWysiwygEditor.prototype =
	{
		__construct: function($textarea)
		{
			this.$textarea = $textarea;
			this.options = $textarea.data('options') || {};
			this.dialogUrl = $textarea.data('dialog-url' )? XenForo.canonicalizeUrl($textarea.data('dialog-url'), XenForo.ajaxBaseHref) : 'index.php?editor/dialog';
			this.autoSaveUrl = $textarea.data('auto-save-url');
			this.autoCompleteUrl = $textarea.data('ac-url') || XenForo.AutoComplete.getDefaultUrl();
			this.pasteImageCounter = 1;

			var id = $textarea.attr('id');
			if (id)
			{
				var extraOptions = XenForo.BbCodeWysiwygEditor_EXTEND[id];
				if (extraOptions)
				{
					if (typeof extraOptions == 'function')
					{
						this.options = extraOptions(this.options, this);
					}
					else
					{
						this.options = $.extend(this.options, extraOptions);
					}
				}
			}

			var buttonConfig = this._adjustButtonConfig(this.getButtonConfig(), this.options.buttons || {}),
				execCommandHandler = this.getExecHandlers(),
				cssUrl = $textarea.data('css-url');

			this.editorConfig = $.extend({
					direction: $('html').attr('dir') || 'ltr',
					formattingTags: [],
					source: false,
					iframe: true,
					iframeBase: XenForo.baseUrl(),
					lang: 'xf',
					buttons: buttonConfig.buttons,
					css: cssUrl ? XenForo.canonicalizeUrl(cssUrl, XenForo.ajaxBaseHref) : false,
					buttonsCustom: buttonConfig.buttonsCustom,
					execCommandHandler: execCommandHandler,
					modal_link: { url: this.dialogUrl + '&dialog=link' },
					modal_image: { url: this.dialogUrl + '&dialog=image' },
					observeImages: false,
					allowJustify: false,
					cleanupFontTags: false,
					convertLinks: false,
					modalCreateCallback: $.context(this, 'modalCreateCallback'),
					callback: $.context(this, 'editorInit'),
					pastePreventCallback: $.context(this, 'pastePreventCallback'),
					pasteManipulateCallback: $.context(this, 'pasteManipulateCallback'),
					pasteCleanUpCallback: $.context(this, 'pasteCleanUpCallback'),
					insertHtmlCallback: $.context(this, 'insertHtmlCallback')
				}, this.options.editorOptions || {});

			$(document).triggerHandler('EditorInit', {
				editor: this,
				config: this.editorConfig,
				$textarea: $textarea
			});

			$textarea.css('visibility', '').show();
			$textarea.redactor(this.editorConfig);
		},

		getButtonConfig: function()
		{
			var self = this,
				buttons = [ ['switchmode'], ['removeformat']],
				bC = this.options.buttonConfig,
				group;

			if (!bC || bC.basic)
			{
				buttons.push(['bold', 'italic', 'underline']);
			}
			if (!bC || bC.extended)
			{
				buttons.push(['fontcolor', 'fontsize', 'fontfamily']);
			}
			if (!bC || bC.link)
			{
				buttons.push(['createlink', 'unlink']);
			}
			if (!bC || bC.align)
			{
				buttons.push(['alignment']);
			}
			if (!bC || bC.list)
			{
				buttons.push(['unorderedlist', 'orderedlist', 'outdent', 'indent']);
			}
			else if (bC.indent)
			{
				buttons.push(['outdent', 'indent']);
			}

			group = [];
			if (!bC || bC.smilies)
			{
				group.push(['smilies']);
			}
			if (!bC || bC.image)
			{
				group.push('image');
			}
			if (!bC || bC.media)
			{
				group.push('media');
			}
			if (!bC || bC.block)
			{
				group.push('insert');
			}
			if (group.length)
			{
				buttons.push(group);
			}

			group = [];
			if (this.options.bbCodes)
			{
				$.each(this.options.bbCodes, function(k, v)
				{
					if (!bC || bC[k])
					{
						group.push('custom_' + k);
					}
				});
			}
			if (group.length)
			{
				buttons.push(group);
			}

			if (this.autoSaveUrl)
			{
				buttons.push(['draft']);
			}

			buttons.push(['undo', 'redo']);

			var fonts = {
				'Arial': "arial,helvetica,sans-serif",
				'Book Antiqua': "'book antiqua',palatino,serif",
				'Courier New': "'courier new',courier,monospace",
				'Georgia': "georgia,palatino,serif",
				'Tahoma': 'tahoma,arial,helvetica,sans-serif',
				'Times New Roman': "'times new roman',times,serif",
				'Trebuchet MS': "'trebuchet ms',geneva,sans-serif",
				'Verdana': "verdana,geneva,sans-serif"
			};
			var sizes = {
				'1': "9px",
				'2': "10px",
				'3': "12px",
				'4': "15px",
				'5': "18px",
				'6': "22px",
				'7': "26px"
			};
			var setFontSize = function(ed, e, key)
			{
				ed.focus();
				var $sel = $(ed.analyzeSelection().selectedEls);
				$sel.find('[style]').css('font-size', '');
				$sel.filter('[style]').css('font-size', '');

				ed.execCommand('fontsize', key);
			};

			var setFontName = function(ed, e, key)
			{
				ed.focus();
				var $sel = $(ed.analyzeSelection().selectedEls);
				$sel.find('[style]').css('font-family', '');
				$sel.filter('[style]').css('font-family', '');

				ed.execCommand('fontname', key);
			};
			var fontDropdown = {}, sizeDropdown = {};

			$.each(fonts, function(k, v) {
				fontDropdown[k] = {
					title: k,
					callback: setFontName,
					style: 'font-family: ' + v
				};
			});
			$.each(sizes, function(k, v) {
				sizeDropdown[k] = {
					title: k,
					callback: setFontSize,
					style: 'font-size: ' + v
				};
			});

			var buttonsCustom = {
					
				switchmode: {
					title: this.getText('switch_mode_bb'),
					callback: $.context(this, 'wysiwygToBbCode')
				},
				removeformat: {
					title: this.getText('remove_formatting'),
					exec: 'removeformat'
				},
				fontsize: {
					title: this.getText('font_size'),
					func: 'show',
					dropdown: sizeDropdown
				},
				fontfamily: {
					title: this.getText('font_family'),
					func: 'show',
					dropdown: fontDropdown
				},
				smilies: {
					title: this.getText('smilies'),
					callback: $.context(this, 'toggleSmilies')
				},
				createlink:
				{
					title: this.getText('link'),
					callback:  $.context(this, 'getLinkModal')
				},
				unlink:
				{
					title: this.getText('unlink'),
					exec: 'unlink'
				},
				image: {
					title: this.getText('image'),
					callback: $.context(this, 'getImageModal')
				},
				media: {
					title: this.getText('media'),
					callback: $.context(this, 'getMediaModal')
				},
				draftsave: {
					title: this.getText('save_draft'),
					callback: $.proxy(function() {
						this.saveDraft(true);
						this.api.focus();
					}, this),
					className: 'icon saveDraft'
				},
				draftdelete: {
					title: this.getText('delete_draft'),
					callback: $.proxy(function() {
						this.saveDraft(true, true);
						this.api.focus();
					}, this),
					className: 'icon deleteDraft'
				},
				draft: {
					title: this.getText('drafts'),
					func: 'show',
					dropdown: {}
				},
				undo: {
					title: this.getText('undo'),
					exec: 'undo'
				},
				redo: {
					title: this.getText('redo'),
					exec: 'redo'
				},
				alignment:
				{
					title: this.getText('alignment'),
					func: 'show',
					dropdown:
					{
						alignleft:
						{
							title: this.getText('align_left'),
							exec: 'JustifyLeft',
							className: 'icon alignLeft'
						},
						aligncenter:
						{
							title: this.getText('align_center'),
							exec: 'JustifyCenter',
							className: 'icon alignCenter'
						},
						alignright:
						{
							title: this.getText('align_right'),
							exec: 'JustifyRight',
							className: 'icon alignRight'
						}
					}
				},
				insertquote: {
					title: this.getText('quote'),
					callback: function(ed)
					{
						self.wrapSelectionInHtml(ed, '[QUOTE]', '[/QUOTE]', true);
					},
					className: 'icon quote'
				},
				insertspoiler: {
					title: this.getText('spoiler'),
					callback: $.context(this, 'getSpoilerModal'),
					className: 'icon spoiler'
				},
				insertcode:
				{
					title: this.getText('code'),
					callback: $.context(this, 'getCodeModal'),
					className: 'icon code'
				},
				insert: {
					title: this.getText('insert'),
					func: 'show',
					dropdown: {}
				}
			};

			buttonsCustom.draft.dropdown = {
				'save': buttonsCustom.draftsave,
				'delete': buttonsCustom.draftdelete
			};
			buttonsCustom.insert.dropdown = {
				quote: buttonsCustom.insertquote,
				spoiler: buttonsCustom.insertspoiler,
				code: buttonsCustom.insertcode,
				deleted:
				{
					title: this.getText('deleted'),
					exec: 'StrikeThrough',
					className: 'icon strikethrough'
				}
			};

			if (this.options.bbCodes)
			{
				$.each(this.options.bbCodes, function(k, v)
				{
					var upper = k.toUpperCase();

					buttonsCustom['custom_' + k] = {
						title: v.title,
						callback: function(ed) {
							if (v.hasOption == 'yes')
							{
								self.wrapSelectionInHtml(ed, '[' + upper + '=]', '[/' + upper + ']', true);
							}
							else
							{
								self.wrapSelectionInHtml(ed, '[' + upper + ']', '[/' + upper + ']', true);
							}
						}
					};
				});
			}

			return {
				buttons: buttons,
				buttonsCustom: buttonsCustom
			};
		},

		_adjustButtonConfig: function(config, extraButtons)
		{
			var self = this,
				extra = [];

			for (var i in extraButtons)
			{
				if (!extraButtons.hasOwnProperty(i))
				{
					continue;
				}

				(function(i) {
					var button = extraButtons[i];

					config.buttonsCustom[i] = {
						title: self.getText(i, button.title),
						callback: function(ed)
						{
							if (button.exec)
							{
								ed.execCommand(button.exec);
							}
							else if (button.tag)
							{
								var tag = button.tag;
								self.wrapSelectionInHtml(ed, '[' + tag + ']', '[/' + tag + ']', true);
							}
						}
					};

					extra.push(i);
				})(i);
			}

			if (extra.length)
			{
				config.buttons.push(extra);
			}

			return config;
		},

		getExecHandlers: function()
		{
			return {};
		},

		editorInit: function(ed)
		{
			this.api = ed;

			var self = this,
				redactorApi = ed,
				$ed = redactorApi.$editor,
				editorBody = $ed.closest('body'),
				editorHtml = $ed.closest('html');

			if ($.browser.msie)
			{
				editorBody.click(function(e) {
					e.stopPropagation();
				});
				editorHtml.click(function() {
					redactorApi.focus();
					var sel = redactorApi.getSelection();
					sel.collapse(1);
				});
			}

			$ed.on('cut copy', $.context(this, 'editorCutCopyCallback'));

			$.each(['switchmode', 'removeformat'], function(i, v)
			{
				var $modeButton = ed.getBtn(v),
					$container = $modeButton.closest('.redactor_btn_group');
	
				if (!$container.length)
				{
					$container = $modeButton.parent();
				}
	
				$container.addClass('redactor_btn_right');
			});

			$ed.on('click', 'img', function(e) {
				redactorApi.focus();

				if ($(this).hasClass('mceSmilie') || $(this).hasClass('mceSmilieSprite')
					|| $(this).hasClass('attachFull') || $(this).hasClass('attachThumb')
				)
				{
					e.preventDefault();
					return;
				}

				var offset = 0, temp = this;
				while (temp.previousSibling)
				{
					offset++;
					temp = temp.previousSibling;
				}
				redactorApi.setSelection(this.parentNode, offset, this.parentNode, offset + 1);

				self.getImageModal(redactorApi);
			});

			$ed.on('click', 'a', function(e) {
				e.preventDefault();
			});

			this.initFocusWatch();
			this.initPlaceholder();
			this.initElastic();
			this.initDragDrop();
			this.initAutoComplete();

			if (this.autoSaveUrl)
			{
				// defer this until after it's built
				setTimeout(function() { self.initAutoSave(); }, 0);
			}
		},

		editorCutCopyCallback: function(e)
		{
			var redactorApi = this.api,
				$editorBody = redactorApi.$editor;

			var selInfo = redactorApi.analyzeSelection();
			if (selInfo.isCollapsed)
			{
				return;
			}

			var html = redactorApi.getSelectedHtml();
			html = html.replace(/<p/gi, '<div data-redactor="1"').replace(/<\/p>/gi, '</div>');
			if (!redactorApi.browser('msie'))
			{
				html = html.replace(/<(p|div)[^>]><\/(p|div)>/gi, '');
			}

			html = html.replace(/<font/gi, '<font data-redactor="1"');

			var $div = $('<div data-redactor-wrapper="1" />').html(html).css({
				position: 'absolute',
				left: '-9999px'
			});

			if (!html.match(/<(article|blockquote|dd|div|dl|fieldset|form|h\d|header|hr|ol|p|pre|section|table|ul)/))
			{
				selInfo.$commonAncestor.parents().addBack().filter('b, strong, i, em, u, s, span, strike, font').each(function() {
					$div.append($(this.cloneNode(false)).append($div[0].childNodes));
				});

				if (html.match(/^\s*<li/))
				{
					selInfo.$commonAncestor.parents().addBack().filter('ul, ol').first().each(function() {
						$div.append($(this.cloneNode(false)).append($div[0].childNodes));
					});
				}
			}

			if (e.type == 'cut')
			{
				redactorApi.pasteHtmlAtCaret('');
				redactorApi.formatEmpty();
			}

			redactorApi.saveSelection();

			$editorBody.append($div);

			var sel = redactorApi.getSelection();
			try {
				sel.selectAllChildren($div.get(0));
			} catch (e) {
				if (this.api.document.createRange && sel.removeAllRanges && sel.addRange) {
					var range = this.api.document.createRange();
					range.selectNode($div.get(0));
					sel.removeAllRanges();
					sel.addRange(range);
				}
				else if (sel.moveToElementText)
				{
					sel.moveToElementText($div.get(0));
					sel.select();
				}
			}

			setTimeout(function() {
				$div.remove();
				redactorApi.restoreSelection();
			}, 0);
		},

		initFocusWatch: function()
		{
			var ed = this.api,
				self = this,
				blurTimeout;

			ed.$editor.on('focus click', function(e) {
				if (blurTimeout)
				{
					clearTimeout(blurTimeout);
					blurTimeout = null;
				}
				ed.$box.addClass('focused');
			});
			ed.$editor.on('blur', function(e) {
				blurTimeout = setTimeout(function() {
					ed.$box.removeClass('focused');
				}, 200);
			});

			ed.$editor.on('focus click keypress', function() {
				if (!self.editorActivated)
				{
					ed.$box.addClass('activated');
					self.editorActivated = true;
				}
			});

			// mobiles have issues with keeping the caret or scrolling the page
			// when focusing, so adjust this
			if (ed.isMobile(true))
			{
				var wH = window.innerHeight;

				// this detects the virtual keyboard appearing as well as orientation changes
				$(window).on('resize', function() {
					if (ed.$box.hasClass('focused') && window.innerHeight < wH)
					{
						setTimeout(function() {
							if ($(window).scrollTop() != 0)
							{
								// let the browser do it - this is mostly to workaround an android bug
								return;
							}
							var f = ed.getFocus()[0];
							if (f)
							{
								ed.$editor[0].scrollIntoView();
								f.scrollIntoView ? f.scrollIntoView() : f.parentNode.scrollIntoView();
							}
						}, 50);
					}
					wH = window.innerHeight;
				});
			}
		},

		initPlaceholder: function()
		{
			if (!this.options.placeholder)
			{
				return;
			}

			var api = this.api,
				self = this;

			if (!this.$placeholder)
			{
				this.$placeholder = $('<div class="placeholder" />').append(
					$('<span />').text(this.getText(this.options.placeholder))
				);
				api.$content.before(this.$placeholder);

				this.$placeholder.click(function() {
					api.focus();
				});
			}

			this.placeholderVisible = false;

			api.$editor.on('focus click keydown', function() {
				if (self.placeholderVisible)
				{
					self.$placeholder.hide();
					self.placeholderVisible = false;
				}
			});
			if (api.$editor.html().match(/^$|(^\s*<p>(\s|&nbsp;|<br\s*\/?>)*<\/p>\s*$)/i))
			{
				this.$placeholder.show();
				this.placeholderVisible = true;
			}
		},

		initElastic: function()
		{
			var ed = this.api;

			var $iframe = ed.$box.find('iframe'),
				maxHeight = $(window).height() - 200,
				minHeight = ed.$el.outerHeight(),
				root = ed.$editor[0],
				curHeight = 0,
				eventResize,
				oldIe = ($.browser.msie && $.browser.version < 9);

			if ($iframe.closest('.xenOverlay').length)
			{
				maxHeight -= 175;
			}
			maxHeight = Math.max(maxHeight, minHeight);

			this.minHeight = minHeight;
			this.maxHeight = maxHeight;

			if (ed.isMobile(true))
			{
				var setEditorWidth = function() {
					var w = ed.$box.width();
					if (w)
					{
						$(root.ownerDocument.documentElement).width(w);
					}
				};
				setEditorWidth();
				ed.$editor.on('focus', setEditorWidth);
				$(window).on('orientationchange resize', function() {
					setTimeout(setEditorWidth, 0);
				});
				ed.$editor.addClass('noElastic');

				$iframe.height(Math.max(Math.min(175, window.innerHeight / 2), minHeight));
				return;
			}

			eventResize = function()
			{
				if (!$iframe)
				{
					return;
				}

				ed.$editor.css('min-height', '');

				var height = oldIe ? root.scrollHeight : Math.min(root.offsetHeight, root.scrollHeight);

				ed.$editor.css('min-height', minHeight - 1);

				// + 22 gives some space under the last line to expand into
				if (height < root.clientHeight || $.browser.msie)
				{
					height += 22;
				}

				if (height < minHeight)
				{
					height = minHeight;
				}
				else if (height > maxHeight)
				{
					height = maxHeight;
				}

				if (height != curHeight)
				{
					if (!oldIe) // IE doesn't need this ?!?! (full size images cause problems with this)
					{
						if (curHeight < height && height == maxHeight)
						{
							ed.$editor.css('overflow-y', '');
						}
						else if (curHeight == maxHeight && height < maxHeight)
						{
							ed.$editor.css('overflow-y', 'hidden');
						}
					}

					$iframe.height(height);
					curHeight = height;
				}
			};

			ed.$editor.on('paste change keydown focus click drop', function() {
				setTimeout(eventResize, 0);
			});
			ed.$editor.data('xenForoElastic', eventResize);

			if (!oldIe)
			{
				ed.$editor.css('overflow-y', 'hidden');
			}

			// the editor does some weird things in webkit with an inline-block body
			ed.$editor.on('drop', function(e) {
				ed.$editor.css('display', 'block');
				setTimeout(function() {
					ed.$editor.css('display', '');
				}, 0);
			});

			eventResize();
			setTimeout(eventResize, 250);
			this.watchImagesElastic();

			$(window).focus(eventResize);
		},

		triggerElastic: function()
		{
			if (!this.$textarea.data('redactor'))
			{
				return;
			}

			var ed = this.api,
				elasticCallback = ed.$editor.data('xenForoElastic');

			if (elasticCallback)
			{
				elasticCallback();
			}
		},

		watchImagesElastic: function(root)
		{
			var $nodes = (root === false || typeof root == 'undefined') ? this.api.$editor : $(root),
				elasticTimer,
				self = this,
				onImageLoad = function() {
					if (elasticTimer)
					{
						clearTimeout(elasticTimer);
					}
					elasticTimer = setTimeout(function() {
						self.triggerElastic();
					}, 100);
				};

			$nodes.find('img').one('load', onImageLoad);
			$nodes.filter('img').one('load', onImageLoad);
		},

		resetEditor: function(content, blur)
		{
			if (!this.$textarea.data('redactor'))
			{
				return;
			}

			var api = this.api;

			if (!content)
			{
				content = api.opts.emptyHtml;
			}

			api.setCode(content, false);
			api.observeFormatting();
			this.resetAutoSave();
			this.initPlaceholder();
			api.$box.find('.redactor_smilies').hide();

			api.$box.removeClass('activated');
			this.editorActivated = false;

			var elastic = api.$editor.data('xenForoElastic');
			if (elastic)
			{
				elastic();
			}

			if (blur)
			{
				var ed = api.$editor;
				if (api.opts.iframe && ed[0])
				{
					var doc = ed[0].ownerDocument;
					if (doc)
					{
						// strange, but a focus call here sorts and issue with the iOS8 cursor being stuck
						(doc.defaultView || doc.parentWindow).focus();
					}
				}

				ed.blur();
			}
		},

		initDragDrop: function()
		{
			if (this.api.isMobile(true) || this.$textarea.hasClass('NoAttachment'))
			{
				return;
			}

			var ed = this.api;

			var dragOverTimeout = function() { $droparea.removeClass('hover'); },
				$uploader = ed.$box.closest('form').find('.AttachmentUploader'),
				canUpload = ($uploader.length > 0),
				timer;

			var $droparea = $('<div class="redactor_editor_drop" />');
			$droparea.append(
				$('<span />').text(this.getText(canUpload ? 'drop_files_here_to_upload' : 'uploads_are_not_available'))
			).appendTo(ed.$box);
			if (!canUpload)
			{
				$droparea.addClass('dragDisabled');
			}

			var checkDraggable = function(e)
			{
				var dt = e.originalEvent.dataTransfer;
				if (!dt || typeof dt.files == 'undefined')
				{
					return false;
				}

				if (dt.types && ($.inArray('Files', dt.types) == -1 || dt.types[0] == 'text/x-moz-url'))
				{
					return false;
				}

				dt.dropEffect = 'copy';

				return true;
			};

			$([document, ed.document]).on('dragover', function(e) {
				if (!checkDraggable(e))
				{
					return;
				}

				$droparea.addClass('hover');

				clearTimeout(timer);
				timer = setTimeout(dragOverTimeout, 200);
			});
			$droparea.on('dragover', function(e) {
				if (!checkDraggable(e))
				{
					return;
				}

				e.preventDefault();
			});
			$droparea.on('drop', function(e) {
				e.preventDefault();
				clearTimeout(timer);
				dragOverTimeout();

				if (!canUpload)
				{
					return;
				}

				var dt = e.originalEvent.dataTransfer;

				if (dt && dt.files && dt.files.length)
				{
					for (var i = 0; i < dt.files.length; i++)
					{
						try {
							var form = new FormData();
							form.append('upload', dt.files[i]);
							form.append('_xfToken', XenForo._csrfToken);
							form.append('_xfNoRedirect', '1');
							$uploader.find('.HiddenInput').each(function() {
								var $input = $(this);
								form.append($input.data('name'), $input.data('value'));
							});
						} catch (e) {
							return;
						}

						// need to use the direct jQuery interface here
						$.ajax({
							url: $uploader.data('action'),
							method: 'POST',
							dataType: 'json',
							data: form,
							processData: false,
							contentType: false
						}).done(function(ajaxData) {
							if (!XenForo.hasResponseError(ajaxData))
							{
								$uploader.trigger({
									type: 'AttachmentUploaded',
									ajaxData: ajaxData
								});
							}
						}).fail(function(xhr) {
							try
							{
								var ajaxData = $.parseJSON(xhr.responseText);
								if (ajaxData && XenForo.hasResponseError(ajaxData))
								{
								}
							}
							catch (e) {}
						});
					}
				}
			});
		},

		initAutoComplete: function()
		{
			if (this.$textarea.hasClass('NoAutoComplete'))
			{
				return;
			}

			var api = this.api,
				$ed = api.$editor,
				doc = $ed[0].ownerDocument,
				self = this,
				hideCallback = function() {
					setTimeout(function() {
						self.acResults.hideResults();
					}, 200);
				};

			this.acVisible = false;
			this.acResults = new XenForo.AutoCompleteResults({
				onInsert: $.context(this, 'insertAutoComplete')
			});

			$(doc.defaultView || doc.parentWindow).on('scroll', hideCallback);
			$ed.on('click blur', hideCallback);

			$ed.on('keydown', function(e) {
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

			// I need this to be first to prevent the enter behavior
			$ed.data('events').keydown.reverse();

			$ed.on('keyup', function(e) {
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
			var api = this.api,
				focus = api.getFocus(),
				origin = api.getOrigin();

			if (!focus || !origin || focus[0] != origin[0] || focus[1] != origin[1])
			{
				return false;
			}

			var	$focus = $(focus[0]),
				testText = focus[0].nodeType == 3 ? $focus.text().substring(0, focus[1]) : $($focus.contents().get(focus[1] - 1)).text(),
				lastAt = testText.lastIndexOf('@');

			if (lastAt != -1 && (lastAt == 0 || testText.substr(lastAt - 1, 1).match(/(\s|[\](,]|--)/)))
			{
				var afterAt = testText.substr(lastAt + 1);
				if (!afterAt.match(/\s/) || afterAt.length <= 10)
				{
					afterAt = afterAt.replace(new RegExp(String.fromCharCode(160), 'g'), ' ');
					return afterAt;
				}
			}

			return false
		},

		insertAutoComplete: function(name)
		{
			this.api.focus();

			var api = this.api,
				focus = api.getFocus(),
				$focus = $(focus[0]),
				testText;

			if (focus[0].nodeType == 3)
			{
				// text node
				testText = $focus.text().substring(0, focus[1]);
			}
			else
			{
				focus[0] = $focus.contents().get(focus[1] - 1);
				$focus = $(focus[0]);
				testText = $focus.text();
			}

			var lastAt = testText.lastIndexOf('@');
			if (lastAt != -1)
			{
				api.setSelection(focus[0], lastAt, focus[0], testText.length);
				api.insertHtml('@' + XenForo.htmlspecialchars(name) + '&nbsp;');
				this.lastAcLookup = name + ' ';
			}

			api.focus();
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

			this.acXhr = XenForo.ajax(
				this.autoCompleteUrl,
				{ q: this.lastAcLookup },
				$.context(this, 'showAutoCompleteResults'),
				{ global: false, error: false }
			);
		},

		showAutoCompleteResults: function(ajaxData)
		{
			this.acXhr = false;

			if (this.lastAcLookup != this.findCurrentAutoCompleteOption())
			{
				return;
			}

			var api = this.api,
				$iframe = api.$box.find('iframe'),
				offset = $iframe.offset(),
				focus = api.getFocus()[0],
				$focus = focus.nodeType == 3 ?  $(focus).parent() : $(focus),
				focusOffset = $focus.offset();

			var css = {
				top: offset.top + focusOffset.top + $focus.height() - api.$editor.scrollTop(),
				left: offset.left
			};

			if (XenForo.isRTL())
			{
				css.right = $('html').width() - offset.left - $iframe.outerWidth();
				css.left = 'auto';
			}

			this.acResults.showResults(
				this.lastAcLookup,
				ajaxData.results,
				$iframe,
				css
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
		},

		syncEditor: function()
		{
			if (this.$textarea && this.$textarea.data('redactor'))
			{
				this.$textarea.data('redactor').syncCode();
			}
		},

		initAutoSave: function()
		{
			var api = this.api,
				self = this,
				$form = this.$textarea.closest('form');

			if (!$form.length)
			{
				return;
			}

			this.lastAutoSaveContent = api.getCode();

			var interval = setInterval(function() {
				if (!self.$textarea.data('redactor'))
				{
					clearInterval(interval);
					return;
				}

				self.saveDraft();
			}, (this.options.autoSaveFrequency || 60) * 1000);
		},

		saveDraft: function(forceUpdate, deleteDraft)
		{
			var api = this.api,
				self = this,
				$form = this.$textarea.closest('form'),
				content = api.$el.prop('disabled') ? this.$bbCodeTextArea.val() : api.getCode();

			if (!deleteDraft && !forceUpdate && content == this.lastAutoSaveContent)
			{
				return false;
			}

			api.syncCode();
			this.lastAutoSaveContent = content;

			var e = $.Event('BbCodeWysiwygEditorAutoSave');
			e.editor = this;
			e.content = content;
			e.deleteDraft = deleteDraft;
			$form.trigger(e);
			if (e.isDefaultPrevented())
			{
				return false;
			}

			if (this.autoSaveRunning)
			{
				return false;
			}
			this.autoSaveRunning = true;

			XenForo.ajax(
				this.autoSaveUrl,
				$form.serialize() + (deleteDraft ? '&delete_draft=1' : ''),
				function(ajaxData) {
					var e = $.Event('BbCodeWysiwygEditorAutoSaveComplete');
					e.ajaxData = ajaxData;
					$form.trigger(e);

					if (!e.isDefaultPrevented())
					{
						var $noticeContainer = api.$box.find('.draftNotice');
						if (!$noticeContainer.length)
						{
							$noticeContainer = $('<div class="draftNotice"><span></span></div>');
							api.$content.after($noticeContainer);
						}

						var $notice = $noticeContainer.find('span:first'),
							draftText;

						if (ajaxData.draftSaved)
						{
							draftText = self.getText('draft_saved');
						}
						else if (ajaxData.draftDeleted)
						{
							draftText = self.getText('draft_deleted');
						}

						if (draftText)
						{
							$notice.text(draftText);
							$noticeContainer.finish().hide().fadeIn().delay(2000).fadeOut();
						}
					}
				},
				{global: false}
			).complete(function() {
				self.autoSaveRunning = false;
			});

			return true;
		},

		triggerAutoSave: function()
		{
			this.saveDraft(true);
		},

		triggerAutoSaveDelete: function()
		{
			this.saveDraft(true, true);
		},

		resetAutoSave: function()
		{
			if (this.$textarea.data('redactor'))
			{
				var api = this.api,
					$form = this.$textarea.closest('form');
				this.lastAutoSaveContent = api.$el.prop('disabled') ? this.$bbCodeTextArea.val() : api.getCode();

				$form.find('.draftUpdate .draftDeleted, .draftUpdate .draftSaved').finish().fadeOut();
			}
		},

		insertHtmlCallback: function(nodes)
		{
			this.watchImagesElastic(nodes);

			var self = this;
			setTimeout(function() {
				self.triggerElastic();
			}, 300);
		},

		wrapSelectionInHtml: function(ed, start, end, selectInside)
		{
			if (selectInside)
			{
				end = '<ins class="selection"></ins>' + end;
			}

			var sel = ed.getSelectedHtml();
			sel = sel.replace(/^(<p[^>]*>)?/, '$1' + start).replace(/(<\/p>)?$/, end + '$1');

			ed.execCommand('inserthtml', sel);

			if (selectInside)
			{
				var $sel = ed.$editor.find('ins.selection');
				if ($sel.length)
				{
					ed.setSelection($sel[0], 0, $sel[0], 0);
					$sel.remove();
				}
				else
				{
					ed.focus();
				}
			}
		},

		toggleSmilies: function(ed)
		{
			var self = this,
				$smilies = ed.$box.find('.redactor_smilies');

			if ($smilies.length)
			{
				$smilies.slideToggle();
				return;
			}

			if (self.smiliesPending)
			{
				return;
			}
			self.smiliesPending = true;

			XenForo.ajax(
				'index.php?editor/smilies',
				{},
				function(ajaxData) {
					if (XenForo.hasResponseError(ajaxData))
					{
						return;
					}

					if (ajaxData.templateHtml)
					{
						$smilies = $('<div class="redactor_smilies" />').html(ajaxData.templateHtml);
						$smilies.hide();
						$smilies.on('click', '.Smilie', function(e) {
							e.preventDefault();

							var $smilie = $(this),
								html = $.trim($smilie.html());

							ed.execCommand('inserthtml', html);
							ed.focus();
						});
						ed.$box.append($smilies);
						$smilies.xfActivate();
						$smilies.slideToggle();
					}
				}
			).complete(function() {
				self.smiliesPending = false;
			});
		},

		modalCreateCallback: function(ed, modal)
		{
			modal.addClass('xenOverlay');

			var $form = $('<form class="formOverlay xenForm" />').append(modal.children()).appendTo(modal);
			$form.on('submit', function(e) {
				e.preventDefault();
				$form.find('.button.primary').click();
			});
			$form.on('click', 'input[type=submit]', function(e) {
				e.stopPropagation();
				e.preventDefault();
			});

			$('#redactor_modal_header').addClass('heading');

			return modal;
		},

		getLinkModal: function(ed)
		{
			var self = this;

			ed.saveSelection();

			var selNode = ed.getSelectedNode(), $link;
			if (selNode)
			{
				$link = $(selNode).closest('a', ed.$editor[0]);
			}

			ed.modalInit(this.getText('link'), { url: this.dialogUrl + '&dialog=link' }, 600, $.proxy(function()
			{
				var $input = $('#redactor_link_url');

				$('#redactor_insert_link_btn').click(function(e) {
					e.preventDefault();

					setTimeout(function()
					{
						ed.restoreSelection();

						var val = $input.val();
						var selInfo = ed.analyzeSelection();

						if (val !== '')
						{
							ed.pushUndoStack();

							var typedVal = val;

							if (val.match(/^mailto:/))
							{
								// already a mail link, do nothing
							}
							else if (val.match(/@[a-z0-9-]+\.[a-z0-9\.-]+$/i))
							{
								val = 'mailto:' + val;
							}
							else if (!val.match(/^https?:|ftp:/i))
							{
								val = 'http://' + val;
							}

							if ($link && $link.length)
							{
								var originalHref = $link.attr('href');
								$link.attr('href', val);
								if ($link.text() == originalHref)
								{
									$link.text(val);
									ed.setSelection($link[0], 1, $link[0], 1);
								}
							}
							else if (selInfo.isCollapsed)
							{
								ed.pasteHtmlAtCaret('<a href="' + XenForo.htmlspecialchars(val) + '">' + XenForo.htmlspecialchars(typedVal) + '</a>');
							}
							else
							{
								ed.execCommand('unlink', '', false);
								ed.execCommand('createlink', val, false);
							}
						}
						else if ($link && $link.length)
						{
							// removing the link
							ed.pushUndoStack();

							selInfo.bookmarkSelection();

							$link.after($link[0].childNodes);
							$link.remove();

							selInfo.restoreBookmark();
						}

						ed.modalClose();
						ed.focus();
					}, 150);
				});

				if ($link && $link.length)
				{
					$input.val($link.attr('href').replace(/^mailto:/, ''));
				}

				setTimeout(function() {
					$input.focus();
				}, 100);

			}, ed));
		},

		getImageModal: function(ed)
		{
			var self = this;

			ed.saveSelection();

			var selHtml = ed.getSelectedHtml(), defaultVal;
			if (selHtml.match(/^\s*<img[^>]+src="([^"]+)"[^>]*>\s*$/) && !selHtml.match(/mceSmilie|attachFull|attachThumb/))
			{
				defaultVal = RegExp.$1;
			}

			ed.modalInit(this.getText('image'), { url: this.dialogUrl + '&dialog=image' }, 600, $.proxy(function()
			{
				var $input = $('#redactor_image_link');

				$('#redactor_image_btn').click(function(e) {
					e.preventDefault();

					ed.restoreSelection();

					var val = $input.val();
					if (val !== '')
					{
						if (!val.match(/^https?:|ftp:/i))
						{
							val = 'http://' + val;
						}

						ed.pasteHtmlAtCaret('<img src="' + XenForo.htmlspecialchars(val) + '" alt="[IMG]" unselectable="on" />&nbsp;');
					}

					ed.modalClose();
					ed.observeImages();
					ed.focus();
				});

				if (defaultVal)
				{
					$input.val(defaultVal);
				}

				setTimeout(function() {
					$input.focus();
				}, 100);

			}, ed));
		},

		getMediaModal: function(ed)
		{
			var self = this;

			ed.saveSelection();
			ed.modalInit(this.getText('media'), { url: this.dialogUrl + '&dialog=media' }, 600, $.proxy(function()
			{
				$('#redactor_insert_media_btn').click(function(e) {
					e.preventDefault();
					self.insertMedia(e, ed);
				});

				setTimeout(function() {
					$('#redactor_media_link').focus();
				}, 100);

			}, ed));
		},

		insertMedia: function(e, ed)
		{
			XenForo.ajax(
				'index.php?editor/media',
				{ url: $('#redactor_media_link').val() },
				function(ajaxData) {
					if (XenForo.hasResponseError(ajaxData))
					{
						return;
					}

					if (ajaxData.matchBbCode)
					{
						ed.restoreSelection();
						ed.execCommand('inserthtml', ajaxData.matchBbCode);
						ed.modalClose();
					}
					else if (ajaxData.noMatch)
					{
						alert(ajaxData.noMatch);
					}
				}
			);
		},

		getCodeModal: function(ed)
		{
			var self = this;

			ed.saveSelection();
			ed.modalInit(this.getText('code'), { url: this.dialogUrl + '&dialog=code' }, 600, $.proxy(function()
			{
				$('#redactor_insert_code_btn').click(function(e) {
					e.preventDefault();
					self.insertCode(e, ed);
				});

				setTimeout(function() {
					$('#redactor_code_code').focus();
				}, 100);

			}, ed));
		},

		insertCode: function(e, ed)
		{
			var tag, code, output;

			switch ($('#redactor_code_type').val())
			{
				case 'html': tag = 'HTML'; break;
				case 'php':  tag = 'PHP'; break;
				default:     tag = 'CODE';
			}

			code = $('#redactor_code_code').val();
			code = code.replace(/&/g, '&amp;').replace(/</g, '&lt;')
				.replace(/>/g, '&gt;').replace(/"/g, '&quot;')
				.replace(/\t/g, '    ').replace(/  /g, '&nbsp; ')
				.replace(/  /g, '&nbsp; ') // need to do this twice to catch a situation where there are an odd number of spaces
				.replace(/\n/g, '</p>\n<p>');

			output = '[' + tag + ']' + code + '[/' + tag + ']';
			if (output.match(/\n/))
			{
				output = '<p>' + output + '</p>';
				output = output.replace(/<p><\/p>/g, '<p>' + (!$.browser.msie ? '<br>' : '&nbsp;') + '</p>');
			}

			ed.restoreSelection();
			ed.execCommand('inserthtml', output);
			ed.modalClose();
		},

		getSpoilerModal: function(ed)
		{
			var self = this;

			ed.saveSelection();
			ed.modalInit(this.getText('spoiler'), { url: this.dialogUrl + '&dialog=spoiler' }, 600, $.proxy(function()
			{
				$('#redactor_insert_spoiler_btn').click(function(e) {
					e.preventDefault();
					self.insertSpoiler(e, ed);
				});

				setTimeout(function() {
					$('#redactor_spoiler_title').focus();
				}, 100);

			}, ed));
		},
		
		insertSpoiler: function(e, ed)
		{
			var self = this,
				val = $('#redactor_spoiler_title').val();
			
			if (val)
			{
				self.wrapSelectionInHtml(ed, '[SPOILER="' + XenForo.htmlspecialchars(val) + '"]', '[/SPOILER]', true);
			}
			else
			{
				self.wrapSelectionInHtml(ed, '[SPOILER]', '[/SPOILER]', true);
			}
			
			ed.modalClose();
		},

		wysiwygToBbCode: function(ed)
		{
			var self = this,
				code = ed.getCode();

			if (code.match(/^<p>(<br\s*\/?>|\s|&nbsp;)*<\/p>$/i))
			{
				self.wysiwygToBbCodeSuccess(ed, {
					bbCode: ''
				});
			}
			else
			{
				XenForo.ajax(
					'index.php?editor/to-bb-code',
					{ html: ed.getCode() },
					function(ajaxData) { self.wysiwygToBbCodeSuccess(ed, ajaxData); }
				);
			}
		},

		wysiwygToBbCodeSuccess: function(ed, ajaxData)
		{
			if (XenForo.hasResponseError(ajaxData) || typeof(ajaxData.bbCode) == 'undefined')
			{
				return;
			}

			var $container = ed.$box,
				$existingTextArea = ed.$el,
				$textContainer = $('<div class="bbCodeEditorContainer" />'),
				$newTextArea = $('<textarea class="textCtrl Elastic" rows="5" />'),
				self = this;

			if ($existingTextArea.prop('disabled'))
			{
				return; // already using this
			}

			$newTextArea
				.attr('name', $existingTextArea.attr('name').replace(/_html(]|$)/, '$1'))
				.val(ajaxData.bbCode)
				.appendTo($textContainer);

			if (this.$textarea.hasClass('NoAttachment'))
			{
				$newTextArea.addClass('NoAttachment');
			}

			$('<a />')
				.attr('href', 'javascript:')
				.text(this.getText('switch_mode_rich'))
				.click(function() { self.bbCodeToWysiwyg(ed); })
				.appendTo(
					$('<div />').appendTo($textContainer)
				);

			$existingTextArea.prop('disabled', true);

			if (ed.browser('mozilla'))
			{
				// reloading the page needs to remove this as it will start in wysiwyg mode
				$(window)
					.unbind('unload.rte')
					.bind('unload.rte', function() {
						$existingTextArea.removeAttr('disabled');
					});
			}

			$container.hide();

			$textContainer.insertAfter($container).xfActivate();

			$newTextArea.focus();

			this.$bbCodeTextContainer = $textContainer;
			this.$bbCodeTextArea = $newTextArea;
		},

		bbCodeToWysiwyg: function(ed)
		{
			var self = this,
				val = this.$bbCodeTextArea.val();

			if ($.trim(val).length == 0)
			{
				this.bbCodeToWysiwygSuccess(ed, {
					html: '<p>' + (!$.browser.msie ? '<br />' : '') + '</p>'
				});
			}
			else
			{
				XenForo.ajax(
					'index.php?editor/to-html',
					{ bbCode: this.$bbCodeTextArea.val() },
					function(ajaxData) { self.bbCodeToWysiwygSuccess(ed, ajaxData); }
				);
			}
		},

		bbCodeToWysiwygSuccess: function(ed, ajaxData)
		{
			if (XenForo.hasResponseError(ajaxData) || typeof(ajaxData.html) == 'undefined')
			{
				return;
			}

			var $container = ed.$box,
				$existingTextArea = ed.$el;

			if (!$existingTextArea.prop('disabled'))
			{
				return; // already using
			}

			$existingTextArea.prop('disabled', false);

			$container.show();

			ed.setCode(ajaxData.html);
			ed.selectFirst();
			ed.observeFormatting();

			this.$bbCodeTextContainer.remove();
		},

		pastePreventCallback: function(e, ed, pasteEvent)
		{
			var oPaste = pasteEvent.originalEvent;
			if (!oPaste.clipboardData)
			{
				return;
			}

			var clipboard = oPaste.clipboardData,
				items = clipboard.items,
				types = clipboard.types;
			if (!items || !types)
			{
				return;
			}

			for (var j = 0; j < types.length; j++)
			{
				if (types[j] == 'text/html')
				{
					return;
				}
			}

			var hasImage = false;

			for (var i = 0; i < items.length; i++) {
				if (items[i].type.match(/^image\/([a-z0-9_-]+)$/i)) {
					var blob = items[i].getAsFile();
					var URLObj = window.URL || window.webkitURL;
					var source = URLObj.createObjectURL(blob);

					var pasteImageId = this.pasteImageCounter++;

					if (this.uploadPastedImage(ed, pasteImageId, RegExp.$1, blob)) {
						ed.insertHtml('<img src="' + source + '" data-paste-id="' + pasteImageId + '">');
						hasImage = true;
					}
				}
			}

			if (hasImage)
			{
				e.preventDefault();

				pasteEvent.preventDefault();
				pasteEvent.stopPropagation();
			}
		},
		pasteManipulateCallback: function(ed, pastedFrag)
		{
			if (pastedFrag && pastedFrag.querySelectorAll)
			{
				var imgs = pastedFrag.querySelectorAll('img');
				if (imgs) {
					for (var i = 0; i < imgs.length; i++) {
						imgs[i].setAttribute('style', '-x-ignore: 1');
						if (imgs[i].src.match(/^data:image\/([a-z0-9_-]+);([a-z0-9_-]+),([\W\w]+)$/i)) {
							var pasteImageId = this.pasteImageCounter++;
							imgs[i].setAttribute('data-paste-id',  pasteImageId);

							if (!this.uploadPastedImage(ed, pasteImageId, RegExp.$1, RegExp.$3, RegExp.$2)) {
								imgs[i].parentNode.removeChild(imgs[i]);
							}
						}
					}
				}
			}
		},

		pasteCleanUpCallback: function(e, ed, html)
		{
			var isIE = ed.browser('msie');

			html = $.trim(html);

			var fromRedactor = html.match(/<[a-zA-Z0-9-]+[^>]* data-redactor="1"/);

			html = html.replace(/^<div[^>]* data-redactor-wrapper="1"[^>]*>([\w\W]+)<\/div>$/, '$1');

			if (ed.browser('mozilla'))
			{
				html = html.replace(/<br>$/gi, '');
			}

			html = html.replace(/<img[^>]+src="webkit-fake-url:[^"]*"[^>]*>/ig, '');

			// since <p> only counts as one line break, we need to fix that
			html = html.replace(/<\/p>/gi, '</p><p>' + (isIE ? '' : '<br>') + '<span><span></span></span></p>');
			html = html.replace(/<p>(<br>)?<span><span><\/span><\/span><\/p>$/, '');

			// convert divs to p's and keep empty ones
			html = html.replace(/<div/gi, '<p').replace(/<\/div>/g, '</p>');
			html = html.replace(/<p([^>]*)>(\s*|<br\s*\/?>|&nbsp;)<\/p>/gi, '<p$1>' + (isIE ? '' : '<br>') + '<span><span></span></span></p>');
			html = html.replace(/(<[a-zA-Z0-9-]+[^>]*) data-redactor="1"/g, '$1');

			html = html.replace(/<span[^>]+style="[^"]*white-space:\s*pre[^"]*"[^>]*>([\w\W]+?)<\/span>/g,
				function(match, contents) {
					return contents.replace(/\t/g, '    ').replace(/  /g, '&nbsp; ').replace(/  /g, '&nbsp; ');
				}
			);

			html = html.replace(/(.|^)<a\s[^>]*data-user="(\d+, [^"]+)"[^>]*>([\w\W]+?)<\/a>/gi,
				function(match, prefix, user, username) {
					var userInfo = user.split(', ');
					if (!parseInt(userInfo[0], 10))
					{
						return match;
					}
					return prefix + (prefix == '@' ? '' : '@') + userInfo[1];
				}
			);

			html = html.replace(/(<img\s[^>]*)src="[^"]*"(\s[^>]*)data-url="([^"]+)"/gi,
				function(match, prefix, suffix, source) {
					return prefix + 'src="' + source + '"' + suffix;
				}
			);

			if (!fromRedactor)
			{
				var filterHtml = function(html, regex, replace)
				{
					var newHtml;

					do
					{
						newHtml = html.replace(regex, replace);
						if (newHtml == html)
						{
							break;
						}

						html = newHtml;
					}
					while (true);

					return newHtml;
				};

				html = filterHtml(html, /<article[^>]*>([\w\W]*?)<\/article>/gi, '$1');
				html = filterHtml(html, /<blockquote[^>]*>([\w\W]*?)<\/blockquote>/gi, '$1');
				html = filterHtml(html, /<del[^>]*>([\w\W]*?)<\/del>/gi, '$1');
				html = filterHtml(html, /<ins[^>]*>([\w\W]*?)<\/ins>/gi, '$1');
				html = filterHtml(html, /<code[^>]*>([\w\W]*?)<\/code>/gi, '$1');
				html = filterHtml(html, /<tr[^>]*>([\w\W]*?)<\/tr>/gi, '<p>$1</p>');
				html = filterHtml(html, /<td[^>]*>([\w\W]*?)<\/td>/gi, '$1 ');
				html = filterHtml(html, /<th[^>]*>([\w\W]*?)<\/th>/gi, '<b>$1</b> ');
				html = filterHtml(html, /<tbody[^>]*>([\w\W]*?)<\/tbody>/gi, '$1 ');
				html = filterHtml(html, /<table[^>]*>([\w\W]*?)<\/table>/gi, '$1 ');

				html = html.replace(/<h1[^>]*>([\w\W]+?)<\/h1>/ig, '<p>[paste:font size="6"]<b>$1</b>[/paste:font]</p>');
				html = html.replace(/<h2[^>]*>([\w\W]+?)<\/h2>/ig, '<p>[paste:font size="5"]<b>$1</b>[/paste:font]</p>');
				html = html.replace(/<h3[^>]*>([\w\W]+?)<\/h3>/ig, '<p>[paste:font size="4"]<b>$1</b>[/paste:font]</p>');
				html = html.replace(/<h4[^>]*>([\w\W]+?)<\/h4>/ig, '<p>[paste:font size="3"]<b>$1</b>[/paste:font]</p>');
				html = html.replace(/<h5[^>]*>([\w\W]+?)<\/h5>/ig, '<p><b>$1</b></p>');
				html = html.replace(/<h6[^>]*>([\w\W]+?)<\/h6>/ig, '<p><b>$1</b></p>');
				html = html.replace(/<pre[^>]*>([\w\W]+?)<\/pre>/ig, function(all, inner) {
					var output = '';
					if (!inner.length)
					{
						output = '<p></p>';
					}
					else
					{
						output = '<p>' + inner.replace(/\r?\n/g, '</p><p>') + '</p>';
					}

					return output.replace(/<p([^>]*)>(\s*|<br\s*\/?>|&nbsp;)<\/p>/gi, '<p$1>' + (isIE ? '' : '<br>') + '<span><span></span></span></p>');
				});
			}

			if (ed.$editor.data('xenForoElastic'))
			{
				setTimeout(function() {
					ed.$editor.data('xenForoElastic')();
				}, 0);
			}

			if (!html.match(/<(?!br\s*\/?)([a-z0-9-]+)(\s|\/|>)/i) && html.match(/<br\s*\/?>/i))
			{
				// no tags but brs, likely a plain text paste so convert
				html = '<p>' + html.replace(/<br\s*\/?>\s*/ig, '</p><p>') + '</p>';
				html = html.replace(/<p><\/p>/ig, '<p>' + (isIE ? '' : '<br>') + '<span><span></span></span></p>');
			}

			if (fromRedactor)
			{
				html = html.replace(/<span><span><\/span><\/span>/gi, '');
				e.preventDefault();
			}

			return html;
		},

		uploadPastedImage: function(ed, pasteId, type, data, encoding)
		{
			var $uploader = ed.$box.closest('form').find('.AttachmentUploader');
			if (!$uploader.length)
			{
				return false;
			}

			if (this.$textarea.hasClass('NoAttachment'))
			{
				return false;
			}

			try {
				var form = new FormData();
				if (typeof(data) == 'string') {
					// data URI
					var byteString;
					if (encoding == 'base64') {
						byteString = atob(data);
					} else {
						byteString = unescape(data);
					}

					var array = [];
					for(var i = 0; i < byteString.length; i++) {
						array.push(byteString.charCodeAt(i));
					}
					data = new Blob([new Uint8Array(array)], {type: 'image/' + type});
				}

				var date = new Date(),
					filename = 'upload_' + date.getFullYear() + '-' + (date.getMonth() + 1) + '-' + date.getDate()
						+ '_' + date.getHours() + '-' + date.getMinutes() + '-' + date.getSeconds()
						+ '.' + type;

				form.append('upload', data, filename);
				form.append('filename', filename);
				form.append('_xfToken', XenForo._csrfToken);
				form.append('_xfNoRedirect', '1');
				$uploader.find('.HiddenInput').each(function() {
					var $input = $(this);
					form.append($input.data('name'), $input.data('value'));
				});
			} catch (e) {
				return false;
			}

			var self = this;

			// need to use the direct jQuery interface here
			$.ajax({
				url: XenForo.canonicalizeUrl($uploader.data('action'), XenForo.ajaxBaseHref),
				method: 'POST',
				dataType: 'json',
				data: form,
				processData: false,
				contentType: false
			}).done(function(ajaxData) {
				if (!self.$textarea.data('redactor'))
				{
					return;
				}

				var $img = self.$textarea.getEditor().find('img[data-paste-id=' + pasteId + ']');
				if (XenForo.hasResponseError(ajaxData))
				{
					$img.remove();
				}
				else
				{
					$img.data('paste-id', '').attr('src', ajaxData.viewUrl).attr('alt', 'attachFull' + ajaxData.attachment_id).addClass('attachFull');
					$uploader.trigger({
						type: 'AttachmentUploaded',
						ajaxData: ajaxData
					});
				}
			}).fail(function(xhr) {
				self.$textarea.getEditor().find('img[data-paste-id=' + pasteId + ']').remove();

				try
				{
					var ajaxData = $.parseJSON(xhr.responseText);
					if (ajaxData && XenForo.hasResponseError(ajaxData))
					{
					}
				}
				catch (e) {}
			});

			return true;
		},

		getText: function(name, def)
		{
			var xfEditorLang = RELANG.xf || RLANG;
			if (typeof xfEditorLang[name] == 'string')
			{
				return xfEditorLang[name];
			}
			return def || name;
		}
	};
	XenForo.BbCodeWysiwygEditor_EXTEND = XenForo.BbCodeWysiwygEditor_EXTEND || {};

	XenForo.register('textarea.BbCodeWysiwygEditor', 'XenForo.BbCodeWysiwygEditor');
}
(jQuery, this, document);