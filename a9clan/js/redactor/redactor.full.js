/**
 * @preserve
 * Redactor v8.2.6
 * Updated: April 6, 2013
 *
 * http://redactorjs.com/
 *
 * Copyright (c) 2009-2013, Imperavi Inc.
 * License: http://redactorjs.com/license/
 *
 * Usage: $('#content').redactor();
*/

var rwindow, rdocument;

if (typeof RELANG === 'undefined')
{
	var RELANG = {};
}

var RLANG = {
	html: 'HTML',
	video: 'Insert Video',
	image: 'Insert Image',
	table: 'Table',
	link: 'Link',
	link_insert: 'Insert link',
	unlink: 'Unlink',
	formatting: 'Formatting',
	paragraph: 'Paragraph',
	quote: 'Quote',
	code: 'Code',
	header1: 'Header 1',
	header2: 'Header 2',
	header3: 'Header 3',
	header4: 'Header 4',
	bold:  'Bold',
	italic: 'Italic',
	fontcolor: 'Font Color',
	backcolor: 'Back Color',
	unorderedlist: 'Unordered List',
	orderedlist: 'Ordered List',
	outdent: 'Outdent',
	indent: 'Indent',
	cancel: 'Cancel',
	insert: 'Insert',
	save: 'Save',
	_delete: 'Delete',
	insert_table: 'Insert Table',
	insert_row_above: 'Add Row Above',
	insert_row_below: 'Add Row Below',
	insert_column_left: 'Add Column Left',
	insert_column_right: 'Add Column Right',
	delete_column: 'Delete Column',
	delete_row: 'Delete Row',
	delete_table: 'Delete Table',
	rows: 'Rows',
	columns: 'Columns',
	add_head: 'Add Head',
	delete_head: 'Delete Head',
	title: 'Title',
	image_position: 'Position',
	none: 'None',
	left: 'Left',
	right: 'Right',
	image_web_link: 'Image Web Link',
	text: 'Text',
	mailto: 'Email',
	web: 'URL',
	video_html_code: 'Video Embed Code',
	file: 'Insert File',
	upload: 'Upload',
	download: 'Download',
	choose: 'Choose',
	or_choose: 'Or choose',
	drop_file_here: 'Drop file here',
	align_left:	'Align text to the left',
	align_center: 'Center text',
	align_right: 'Align text to the right',
	align_justify: 'Justify text',
	horizontalrule: 'Insert Horizontal Rule',
	deleted: 'Deleted',
	anchor: 'Anchor',
	link_new_tab: 'Open link in new tab',
	underline: 'Underline',
	alignment: 'Alignment'
};

(function($){

	// Plugin
	jQuery.fn.redactor = function(option)
	{
		return this.each(function()
		{
			var $obj = $(this);

			var data = $obj.data('redactor');
			if (!data)
			{
				$obj.data('redactor', (data = new Redactor(this, option)));
			}
		});
	};


	// Initialization
	var Redactor = function(element, options)
	{
		// Element
		this.$el = $(element);

		// Lang
		if (typeof options !== 'undefined' && typeof options.lang !== 'undefined' && options.lang !== 'en' && typeof RELANG[options.lang] !== 'undefined')
		{
			RLANG = RELANG[options.lang];
		}

		// Options
		this.opts = $.extend({

			iframe: false,
			css: false, // url
			iframeBase: false, //

			lang: 'en',
			direction: 'ltr', // ltr or rtl

			callback: false, // function
			keyupCallback: false, // function
			keydownCallback: false, // function
			execCommandCallback: false, // function
			insertHtmlCallback: false, // function
			pastePreventCallback: false, // function
			pasteManipulateCallback: false, //function
			pasteCleanUpCallback: false, // function
			modalCreateCallback: false, // function
			modalLoadCallback: false, // function

			execCommandHandler: {},

			plugins: false,
			cleanup: true,

			focus: false,
			tabindex: false,
			autoresize: true,
			minHeight: false,
			fixed: false,
			fixedTop: 0, // pixels
			fixedBox: false,
			source: true,
			shortcuts: true,
			insertTab: false,
			undoSize: 30,

			mobile: true,
			air: false, // true or toolbar
			wym: false,

			convertLinks: true,
			convertDivs: true,
			protocol: 'http://', // for links http or https or ftp or false

			autosave: false, // false or url
			autosaveCallback: false, // function
			interval: 60, // seconds

			imageGetJson: false, // url (ex. /folder/images.json ) or false

			imageUpload: false, // url
			imageUploadCallback: false, // function
			imageUploadErrorCallback: false, // function

			fileUpload: false, // url
			fileUploadCallback: false, // function
			fileUploadErrorCallback: false, // function

			uploadCrossDomain: false,
			uploadFields: false,

			observeImages: true,
			overlay: true, // modal overlay

			allowedTags: ["form", "input", "button", "select", "option", "datalist", "output", "textarea", "fieldset", "legend",
					"section", "header", "hgroup", "aside", "footer", "article", "details", "nav", "progress", "time", "canvas",
					"code", "span", "div", "label", "a", "br", "p", "b", "i", "del", "strike", "u", "font",
					"img", "video", "source", "track", "audio", "iframe", "object", "embed", "param", "blockquote",
					"mark", "cite", "small", "ul", "ol", "li", "hr", "dl", "dt", "dd", "sup", "sub",
					"big", "pre", "code", "figure", "figcaption", "strong", "em", "table", "tr", "td",
					"th", "tbody", "thead", "tfoot", "h1", "h2", "h3", "h4", "h5", "h6"],

			toolbarExternal: false, // ID selector

			buttonsCustom: {},
			buttonsAdd: [],
			buttons: ['html', '|', 'formatting', '|', 'bold', 'italic', 'deleted', '|', 'unorderedlist', 'orderedlist', 'outdent', 'indent', '|',
					'image', 'video', 'file', 'table', 'link', '|',
					'fontcolor', 'backcolor', '|', 'alignment', '|', 'horizontalrule'], // 'underline', 'alignleft', 'aligncenter', 'alignright', 'justify'

			airButtons: ['formatting', '|', 'bold', 'italic', 'deleted', '|', 'unorderedlist', 'orderedlist', 'outdent', 'indent', '|', 'fontcolor', 'backcolor'],

			formattingTags: ['p', 'blockquote', 'pre', 'h1', 'h2', 'h3', 'h4'],

			activeButtons: ['deleted', 'italic', 'bold', 'underline', 'unorderedlist', 'orderedlist'], // 'alignleft', 'aligncenter', 'alignright', 'justify'
			activeButtonsStates: {
				b: 'bold',
				strong: 'bold',
				i: 'italic',
				em: 'italic',
				del: 'deleted',
				strike: 'deleted',
				s: 'deleted',
				ul: 'unorderedlist',
				ol: 'orderedlist',
				u: 'underline'
			},
			activeButtonStateMap: {
				bold: 'bold',
				italic: 'italic',
				underline: 'underline',
				deleted: 'strikethrough'
			},

			allowJustify: true,
			cleanupFontTags: true,

			colors: [
				'#ffffff', '#ff9999', '#ffcc99', '#ffff99', '#ccff99', '#99ff99', '#99ffcc', '#99ffff', '#99ccff', '#9999ff', '#cc99ff', '#ff99ff', '#ff99cc',
				'#bfbfbf', '#ff4d4d', '#ffa64d', '#ffff4d', '#a6ff4d', '#4dff4d', '#4dffa6', '#4dffff', '#4da6ff', '#4d4dff', '#a64dff', '#ff4dff', '#ff4da6',
				'#808080', '#ff0000', '#ff8000', '#ffff00', '#80ff00', '#00ff00', '#00ff80', '#00ffff', '#0080ff', '#0000ff', '#8000ff', '#ff00ff', '#ff0080',
				'#404040', '#b30000', '#b35900', '#b3b300', '#59b300', '#00b300', '#00b359', '#00b3b3', '#0059b3', '#0000b3', '#5900b3', '#b300b3', '#b30059',
				'#000000', '#660000', '#663300', '#666600', '#336600', '#006600', '#006633', '#006666', '#003366', '#000066', '#330066', '#660066', '#660033'
			],
			colorWidth: 247,

			// private
			emptyHtml: '<p>' + (!this.browser('msie') ? '<br />' : '') + '</p>',
			buffer: false,
			visual: true,

			// modal windows container
			modal_file: String() +
				'<div id="redactor_modal_content">' +
				'<form id="redactorUploadFileForm" method="post" action="" enctype="multipart/form-data">' +
					'<label>Name (optional)</label>' +
					'<input type="text" id="redactor_filename" class="redactor_input" />' +
					'<div style="margin-top: 7px;">' +
						'<input type="file" id="redactor_file" name="file" />' +
					'</div>' +
				'</form><br>' +
				'</div>',

			modal_image_edit: String() +
				'<div id="redactor_modal_content">' +
				'<label>' + RLANG.title + '</label>' +
				'<input id="redactor_file_alt" class="redactor_input" />' +
				'<label>' + RLANG.link + '</label>' +
				'<input id="redactor_file_link" class="redactor_input" />' +
				'<label>' + RLANG.image_position + '</label>' +
				'<select id="redactor_form_image_align">' +
					'<option value="none">' + RLANG.none + '</option>' +
					'<option value="left">' + RLANG.left + '</option>' +
					'<option value="right">' + RLANG.right + '</option>' +
				'</select>' +
				'</div>' +
				'<div id="redactor_modal_footer">' +
					'<a href="javascript:void(null);" id="redactor_image_delete_btn" class="redactor_modal_btn">' + RLANG._delete + '</a>&nbsp;&nbsp;&nbsp;' +
					'<a href="javascript:void(null);" class="redactor_modal_btn redactor_btn_modal_close">' + RLANG.cancel + '</a>' +
					'<input type="button" name="save" class="redactor_modal_btn" id="redactorSaveBtn" value="' + RLANG.save + '" />' +
				'</div>',

			modal_image: String() +
				'<div id="redactor_modal_content">' +
				'<div id="redactor_tabs">' +
					'<a href="javascript:void(null);" class="redactor_tabs_act">' + RLANG.upload + '</a>' +
					'<a href="javascript:void(null);">' + RLANG.choose + '</a>' +
					'<a href="javascript:void(null);">' + RLANG.link + '</a>' +
				'</div>' +
				'<form id="redactorInsertImageForm" method="post" action="" enctype="multipart/form-data">' +
					'<div id="redactor_tab1" class="redactor_tab">' +
						'<input type="file" id="redactor_file" name="file" />' +
					'</div>' +
					'<div id="redactor_tab2" class="redactor_tab" style="display: none;">' +
						'<div id="redactor_image_box"></div>' +
					'</div>' +
				'</form>' +
				'<div id="redactor_tab3" class="redactor_tab" style="display: none;">' +
					'<label>' + RLANG.image_web_link + '</label>' +
					'<input type="text" name="redactor_file_link" id="redactor_file_link" class="redactor_input"  />' +
				'</div>' +
				'</div>' +
				'<div id="redactor_modal_footer">' +
					'<a href="javascript:void(null);" class="redactor_modal_btn redactor_btn_modal_close">' + RLANG.cancel + '</a>' +
					'<input type="button" name="upload" class="redactor_modal_btn" id="redactor_upload_btn" value="' + RLANG.insert + '" />' +
				'</div>',

			modal_link: String() +
				'<div id="redactor_modal_content">' +
				'<form id="redactorInsertLinkForm" method="post" action="">' +
					'<div id="redactor_tabs">' +
						'<a href="javascript:void(null);" class="redactor_tabs_act">URL</a>' +
						'<a href="javascript:void(null);">Email</a>' +
						'<a href="javascript:void(null);">' + RLANG.anchor + '</a>' +
					'</div>' +
					'<input type="hidden" id="redactor_tab_selected" value="1" />' +
					'<div class="redactor_tab" id="redactor_tab1">' +
						'<label>URL</label><input type="text" id="redactor_link_url" class="redactor_input"  />' +
						'<label>' + RLANG.text + '</label><input type="text" class="redactor_input redactor_link_text" id="redactor_link_url_text" />' +
						'<label><input type="checkbox" id="redactor_link_blank"> ' + RLANG.link_new_tab + '</label>' +
					'</div>' +
					'<div class="redactor_tab" id="redactor_tab2" style="display: none;">' +
						'<label>Email</label><input type="text" id="redactor_link_mailto" class="redactor_input" />' +
						'<label>' + RLANG.text + '</label><input type="text" class="redactor_input redactor_link_text" id="redactor_link_mailto_text" />' +
					'</div>' +
					'<div class="redactor_tab" id="redactor_tab3" style="display: none;">' +
						'<label>' + RLANG.anchor + '</label><input type="text" class="redactor_input" id="redactor_link_anchor"  />' +
						'<label>' + RLANG.text + '</label><input type="text" class="redactor_input redactor_link_text" id="redactor_link_anchor_text" />' +
					'</div>' +
				'</form>' +
				'</div>' +
				'<div id="redactor_modal_footer">' +
					'<a href="javascript:void(null);" class="redactor_modal_btn redactor_btn_modal_close">' + RLANG.cancel + '</a>' +
					'<input type="button" class="redactor_modal_btn" id="redactor_insert_link_btn" value="' + RLANG.insert + '" />' +
				'</div>',

			modal_table: String() +
				'<div id="redactor_modal_content">' +
					'<label>' + RLANG.rows + '</label>' +
					'<input type="text" size="5" value="2" id="redactor_table_rows" />' +
					'<label>' + RLANG.columns + '</label>' +
					'<input type="text" size="5" value="3" id="redactor_table_columns" />' +
				'</div>' +
				'<div id="redactor_modal_footer">' +
					'<a href="javascript:void(null);" class="redactor_modal_btn redactor_btn_modal_close">' + RLANG.cancel + '</a>' +
					'<input type="button" name="upload" class="redactor_modal_btn" id="redactor_insert_table_btn" value="' + RLANG.insert + '" />' +
				'</div>',

			modal_video: String() +
				'<div id="redactor_modal_content">' +
				'<form id="redactorInsertVideoForm">' +
					'<label>' + RLANG.video_html_code + '</label>' +
					'<textarea id="redactor_insert_video_area" style="width: 99%; height: 160px;"></textarea>' +
				'</form>' +
				'</div>'+
				'<div id="redactor_modal_footer">' +
					'<a href="javascript:void(null);" class="redactor_modal_btn redactor_btn_modal_close">' + RLANG.cancel + '</a>' +
					'<input type="button" class="redactor_modal_btn" id="redactor_insert_video_btn" value="' + RLANG.insert + '" />' +
				'</div>',

			toolbar: {
				html:
				{
					title: RLANG.html,
					func: 'toggle'
				},
				formatting:
				{
					title: RLANG.formatting,
					func: 'show',
					dropdown:
					{
						p:
						{
							title: RLANG.paragraph,
							exec: 'formatblock'
						},
						blockquote:
						{
							title: RLANG.quote,
							exec: 'formatblock',
							className: 'redactor_format_blockquote'
						},
						pre:
						{
							title: RLANG.code,
							exec: 'formatblock',
							className: 'redactor_format_pre'
						},
						h1:
						{
							title: RLANG.header1,
							exec: 'formatblock',
							className: 'redactor_format_h1'
						},
						h2:
						{
							title: RLANG.header2,
							exec: 'formatblock',
							className: 'redactor_format_h2'
						},
						h3:
						{
							title: RLANG.header3,
							exec: 'formatblock',
							className: 'redactor_format_h3'
						},
						h4:
						{
							title: RLANG.header4,
							exec: 'formatblock',
							className: 'redactor_format_h4'
						}
					}
				},
				bold:
				{
					title: RLANG.bold,
					exec: 'bold'
				},
				italic:
				{
					title: RLANG.italic,
					exec: 'italic'
				},
				deleted:
				{
					title: RLANG.deleted,
					exec: 'strikethrough'
				},
				underline:
				{
					title: RLANG.underline,
					exec: 'underline'
				},
				unorderedlist:
				{
					title: '&bull; ' + RLANG.unorderedlist,
					exec: 'insertunorderedlist'
				},
				orderedlist:
				{
					title: '1. ' + RLANG.orderedlist,
					exec: 'insertorderedlist'
				},
				outdent:
				{
					title: '< ' + RLANG.outdent,
					exec: 'outdent'
				},
				indent:
				{
					title: '> ' + RLANG.indent,
					exec: 'indent'
				},
				image:
				{
					title: RLANG.image,
					func: 'showImage'
				},
				video:
				{
					title: RLANG.video,
					func: 'showVideo'
				},
				file:
				{
					title: RLANG.file,
					func: 'showFile'
				},
				table:
				{
					title: RLANG.table,
					func: 'show',
					dropdown:
					{
						insert_table:
						{
							title: RLANG.insert_table,
							func: 'showTable'
						},
						separator_drop1:
						{
							name: 'separator'
						},
						insert_row_above:
						{
							title: RLANG.insert_row_above,
							func: 'insertRowAbove'
						},
						insert_row_below:
						{
							title: RLANG.insert_row_below,
							func: 'insertRowBelow'
						},
						insert_column_left:
						{
							title: RLANG.insert_column_left,
							func: 'insertColumnLeft'
						},
						insert_column_right:
						{
							title: RLANG.insert_column_right,
							func: 'insertColumnRight'
						},
						separator_drop2:
						{
							name: 'separator'
						},
						add_head:
						{
							title: RLANG.add_head,
							func: 'addHead'
						},
						delete_head:
						{
							title: RLANG.delete_head,
							func: 'deleteHead'
						},
						separator_drop3:
						{
							name: 'separator'
						},
						delete_column:
						{
							title: RLANG.delete_column,
							func: 'deleteColumn'
						},
						delete_row:
						{
							title: RLANG.delete_row,
							func: 'deleteRow'
						},
						delete_table:
						{
							title: RLANG.delete_table,
							func: 'deleteTable'
						}
					}
				},
				link:
				{
					title: RLANG.link,
					func: 'show',
					dropdown:
					{
						link:
						{
							title: RLANG.link_insert,
							func: 'showLink'
						},
						unlink:
						{
							title: RLANG.unlink,
							exec: 'unlink'
						}
					}
				},
				fontcolor:
				{
					title: RLANG.fontcolor,
					func: 'show'
				},
				backcolor:
				{
					title: RLANG.backcolor,
					func: 'show'
				},
				alignment:
				{
					title: RLANG.alignment,
					func: 'show',
					dropdown:
					{
						alignleft:
						{
							title: RLANG.align_left,
							exec: 'JustifyLeft'
						},
						aligncenter:
						{
							title: RLANG.align_center,
							exec: 'JustifyCenter'
						},
						alignright:
						{
							title: RLANG.align_right,
							exec: 'JustifyRight'
						},
						justify:
						{
							title: RLANG.align_justify,
							exec: 'JustifyFull'
						}
					}
				},
				alignleft:
				{
					exec: 'JustifyLeft',
					title: RLANG.align_left
				},
				aligncenter:
				{
					exec: 'JustifyCenter',
					title: RLANG.align_center
				},
				alignright:
				{
					exec: 'JustifyRight',
					title: RLANG.align_right
				},
				justify:
				{
					exec: 'JustifyFull',
					title: RLANG.align_justify
				},
				horizontalrule:
				{
					exec: 'inserthorizontalrule',
					title: RLANG.horizontalrule
				}
			}


		}, options, this.$el.data());

		this.dropdowns = [];
		this.deferredModal = {};
		this.undoStack = [];
		this.redoStack = [];
		this.typingType = null;
		this.typingCount = 0;

		// Init
		this.init();
	};

	// Functionality
	Redactor.prototype = {


		// Initialization
		init: function()
		{
			// get dimensions
			this.height = this.$el.css('height');
			this.width = this.$el.css('width');

			rdocument = this.document = document;
			rwindow = this.window = window;

			// mobile
			if (this.opts.mobile === false && this.isMobile())
			{
				this.build(true);
				return false;
			}

			// iframe
			if (this.opts.iframe)
			{
				this.opts.autoresize = false;
			}

			// extend buttons
			if (this.opts.air)
			{
				$.extend(this.opts.toolbar, this.opts.buttonsCustom);							
				this.opts.buttons = this.opts.airButtons;
			}
			else if (this.opts.toolbar !== false)
			{
				if (this.opts.source === false)
				{
					var index = $.inArray('html', this.opts.buttons);
					if (index != -1)
					{
						var next = this.opts.buttons[index+1];
						this.opts.buttons.splice(index, 1);
						if (typeof next !== 'undefined' && next === '|')
						{
							this.opts.buttons.splice(index, 1);
						}
					}
				}

				$.extend(this.opts.toolbar, this.opts.buttonsCustom);
				$.each(this.opts.buttonsAdd, $.proxy(function(i,s)
				{
					this.opts.buttons.push(s);

				}, this));
			}

			// formatting tags
			if (this.opts.toolbar !== false)
			{
				$.each(this.opts.toolbar.formatting.dropdown, $.proxy(function(i,s)
				{
					if ($.inArray(i, this.opts.formattingTags) == '-1')
					{
						delete this.opts.toolbar.formatting.dropdown[i];
					}

				}, this));

				if (!this.opts.allowJustify)
				{
					delete this.opts.toolbar.alignment.dropdown.justify;
				}
			}

			function afterBuild()
			{
	      		// air enable
				this.enableAir();

				// toolbar
				this.buildToolbar();

				this.pushUndoStack(false);

				// PLUGINS
				if (typeof this.opts.plugins === 'object')
				{
					$.each(this.opts.plugins, $.proxy(function(i,s)
					{
						if (typeof RedactorPlugins[s] !== 'undefined')
						{
							$.extend(this, RedactorPlugins[s]);

							if (typeof RedactorPlugins[s].init !== 'undefined')
							{
								this.init();
							}
						}

					}, this));
				}

				// buttons response
				if (this.opts.activeButtons !== false && this.opts.toolbar !== false && !this.isMobile(true))
				{
					var observeFormatting = $.proxy(function() { this.observeFormatting(); }, this);
					this.$editor.click($.proxy(function() {
						this.observeFormatting();
					}, this)).keyup($.proxy(function(e) {
						var k = e.keyCode || e.which;
						switch (k)
						{
							case 17: // ctrl
							case 91: // cmd/windows key
							case 92: // windows key
							case 93: // cmd/windows key
							case 224: // cmd
								return;
						}

						this.observeFormatting(k);
					}, this));
				}

				// paste handling used to be disabled for mobiles, old safari, and opera but
				// this approach seems to work
				if (true)
				{
					this.$editor.bind('paste', $.proxy(function(e)
					{
						this.pushUndoStack();

						if (this.opts.cleanup === false)
						{
							return true;
						}

						if (this.opts.pastePreventCallback)
						{
							var customEvent = new $.Event();
							this.opts.pastePreventCallback(customEvent, this, e);
							if (customEvent.isDefaultPrevented())
							{
								return true;
							}
						}

						this.pasteRunning = true;

						this.setBuffer();

						if (this.opts.autoresize === true)
						{
							this.saveScroll = this.document.body.scrollTop;
						}
						else
						{
							this.saveScroll = this.$editor.scrollTop();
						}

						var frag = null, $pasteNode;
						if (this.browser('msie') || this.isMobile(true))
						{
							this.$editor.css('height', this.$editor.height() + 'px');
							frag = this.extractContent();
						}
						else
						{
							var t = this.document.createTextNode('\u200B\u200B'),
								focus = this.getFocus()[0],
								focusTop = focus
									? (focus.nodeType == 3 ? $(focus).parent().offset().top : $(focus).offset().top)
									: 0;

							this.$editor.find('.redactorPaste').removeClass('redactorPaste');

							$pasteNode = $('<div />', this.document).append(t).css({
								overflow: 'hidden',
								width: '1px',
								height: '1px',
								position: 'absolute',
								left: '-1000px',
								top: focusTop + 'px'
							}).addClass('redactorPaste');

							this.$editor.append($pasteNode);
							this.setSelection(t, 0, t, 2);
						}

						setTimeout($.proxy(function()
						{
							var pastedFrag;
							if ($pasteNode)
							{
								// there are some situations where pastes end up spanning multiple nodes, fold them back
								var $pastes = this.$editor.find('> .redactorPaste');
								$pastes.each(function(k) {
									if (k > 0)
									{
										$pasteNode.append($(this).contents());
									}
								});

								pastedFrag = this.extractContent($pasteNode[0]);
								$pastes.remove();
							}
							else
							{
								pastedFrag = this.extractContent();
								this.$editor.css('height', '').append(frag);
							}

							this.restoreSelection();

							if (this.opts.pasteManipulateCallback)
							{
								this.opts.pasteManipulateCallback(this, pastedFrag, e);
							}

							var html = this.getFragmentHtml(pastedFrag);

							this.pasteCleanUp(html);
							this.pasteRunning = false;

						}, this), 1);

					}, this));
				}
				else
				{
					this.$editor.bind('paste', $.proxy(function(e)
					{
						this.pushUndoStack();
					}, this));
				}

				// key handlers
				this.keyup();
				this.keydown();
				this.$editor.keypress($.proxy(function(e)
				{
					e.stopPropagation();
					if (!e.ctrlKey && !e.metaKey)
					{
						this.onTyping('key');
					}
				}, this));
				this.$editor.mousedown($.proxy(function(e)
				{
					this.onTyping('move');
				}, this));

				// autosave
				if (this.opts.autosave !== false)
				{
					this.autoSave();
				}

				// observers
				setTimeout($.proxy(function()
				{
					this.observeImages();
					this.observeTables();

				}, this), 1);

				// FF fix
				if (this.browser('mozilla'))
				{
					this.$editor.click($.proxy(function()
					{
						this.saveSelection();
					}, this));

					try
					{
						this.document.execCommand('enableObjectResizing', false, false);
						this.document.execCommand('enableInlineTableEditing', false, false);
					}
					catch (e) {}
				}

				// focus
				if (this.opts.focus)
				{
					setTimeout($.proxy(function(){
						this.focus();
					}, this), 1);
				}

				// fixed
				if (this.opts.fixed)
				{
					this.observeScroll();
					$(document).scroll($.proxy(this.observeScroll, this));
				}

				// callback
				if (typeof this.opts.callback === 'function')
				{
					this.opts.callback(this);
				}

				if (this.opts.toolbar !== false)
				{
					this.$toolbar.find('a').attr('tabindex', '-1');
				}

				if (this.browser('msie'))
				{
					// in some situations, IE doesn't let the editor be focused
					// which prevents you from editing.
					this.$editor.one('click', $.proxy(function() {
						this.focus();
					}, this));
				}
			}

			// construct editor
		    this.build(false, afterBuild);

		},
		shortcuts: function(e, cmd)
		{
			e.preventDefault();

			var toggleButton = false;
			$.each(this.opts.activeButtonStateMap, function(k, v) {
				if (v == cmd)
				{
					toggleButton = k;
					return false;
				}
			});

			if (toggleButton)
			{
				this.skipFormatObserveOnce = true;
				this.toggleBtnActive(toggleButton);
			}

			this.execCommand(cmd, false);
		},
		keyup: function()
		{
			this.$editor.keyup($.proxy(function(e)
			{
				var key = e.keyCode || e.which;

				if (this.browser('mozilla') && !this.pasteRunning)
				{
					this.saveSelection();
				}

				// callback as you type
				if (typeof this.opts.keyupCallback === 'function')
				{
					this.opts.keyupCallback(this, e);
				}

				// if empty
				if (key === 8 || key === 46)
				{
					this.observeImages();
					return this.formatEmpty(e);
				}

				// new line p
				if (key === 13 && !e.shiftKey && !e.ctrlKey && !e.metaKey)
				{
					if (this.browser('mozilla'))
					{
						try
						{
							this.document.execCommand('unlink', false, null);
						} catch (e) {}

						// we can still be inside an anchor, which becomes our target when pasting.
						// remove the link if it's empty
						var sel = this.window.getSelection(),
							anchor = sel.anchorNode;
						if (sel.isCollapsed
							&& sel.anchorOffset == 0
							&& anchor
							&& anchor.tagName === 'A'
							&& anchor.innerHTML == '<br>')
						{
							var parent = anchor.parentNode;
							sel.selectAllChildren(anchor.parentNode);
							parent.innerHTML = '<br>';
						}
					}

					if (this.browser('webkit'))
					{
						this.formatNewLine(e);
					}

					// convert links
					if (this.opts.convertLinks)
					{
						this.$editor.linkify();
					}
				}

				this.syncCode();

			}, this));
		},
		keydown: function()
		{
			this.$editor.keydown($.proxy(function(e)
			{
				var key = e.keyCode || e.which;
				if (!key)
				{
					// no point doing anything if we haven't gotten a key
					return;
				}

				var parent = this.getParentNode();
				var current = this.getCurrentNode();
				var pre = false;
				var ctrl = (e.ctrlKey || e.metaKey) && !e.altKey;
				var cmd = !e.ctrlKey && e.metaKey;

				if ((parent && $(parent).get(0).tagName === 'PRE') || (current && $(current).get(0).tagName === 'PRE'))
				{
					pre = true;
				}

				// callback keydown
				if (typeof this.opts.keydownCallback === 'function')
				{
					this.opts.keydownCallback(this, e);
				}

				if (key == 13)
				{
					this.pushUndoStack();
				}
				else if (key == 8 || key == 46) // backspace/delete
				{
					this.onTyping('delete');
				}
				else if (key >= 33 && key <= 40) // movement keys
				{
					this.onTyping('move');
				}

				if (cmd && this.opts.shortcuts)
				{
					var sel;
					if (this.window.getSelection && (sel = this.window.getSelection()) && sel.modify) {
						var adjustmentType = e.shiftKey ? "extend" : "move";

						switch (key) {
							case 39: // right - act like "end" in windows
								sel.modify(adjustmentType, "right", "lineboundary");
								e.preventDefault();
								break;

							case 37: // left - act like "home" in windows
								sel.modify(adjustmentType, "left", "lineboundary");
								e.preventDefault();
								break;
						}
					}
				}

				if (ctrl && this.opts.shortcuts)
				{
					if (key === 90)
					{
						if (e.shiftKey)
						{
							this.shortcuts(e, 'redo');	// Ctrl + Shift + z
						}
						else
						{
							this.shortcuts(e, 'undo'); // Ctrl + z
						}
					}
					else if (key == 89)
					{
						this.shortcuts(e, 'redo'); // Ctrl + y
					}
					else if (key === 77)
					{
						this.shortcuts(e, 'removeformat'); // Ctrl + m
					}
					else if (key === 66)
					{
						this.shortcuts(e, 'bold'); // Ctrl + b
					}
					else if (key === 73)
					{
						this.shortcuts(e, 'italic'); // Ctrl + i
					}
					else if (key === 85)
					{
						this.shortcuts(e, 'underline'); // Ctrl + u
					}
					else if (key === 74)
					{
						this.shortcuts(e, 'insertunorderedlist'); // Ctrl + j
					}
					else if (key === 75)
					{
						this.shortcuts(e, 'insertorderedlist'); // Ctrl + k
					}
					else if (key === 76)
					{
						this.shortcuts(e, 'superscript'); // Ctrl + l
					}
					else if (key === 72)
					{
						this.shortcuts(e, 'subscript'); // Ctrl + h
					}
				}

				// clear undo buffer
				if (!ctrl && key !== 90)
				{
					this.opts.buffer = false;
				}

				// enter
				if (pre === true && key === 13)
				{
					e.preventDefault();

					var html = $(current).parent().text();
					this.insertNodeAtCaret(this.document.createTextNode('\r\n'));
					if (html.search(/\s$/) == -1)
					{
						this.insertNodeAtCaret(this.document.createTextNode('\r\n'));
					}
					this.syncCode();

					return false;
				}

				// tab
				if (this.opts.shortcuts && !e.shiftKey && key === 9 && this.opts.insertTab)
				{
					if (pre === false)
					{
						this.shortcuts(e, 'indent'); // Tab
					}
					else
					{
						e.preventDefault();
						this.insertNodeAtCaret(this.document.createTextNode('\t'));
						this.syncCode();
						return false;
					}
				}
				else if (this.opts.shortcuts && e.shiftKey && key === 9 && this.opts.insertTab)
				{
					this.shortcuts(e, 'outdent'); // Shift + tab
				}

				// safari shift key + enter
				if (this.browser('webkit') && navigator.userAgent.indexOf('Chrome') === -1)
				{
					return this.safariShiftKeyEnter(e, key);
				}
			}, this));
		},
		build: function(mobile, whendone)
		{
			if (mobile !== true)
			{
				// container
				this.$box = $('<div class="redactor_box"></div>');

				// air box
				if (this.opts.air)
				{
					this.air = $('<div class="redactor_air" style="display: none;"></div>');
				}

				this.$content = null;

				function initFrame()
				{
					var $root = this.$content.contents(),
						$head = $root.find('head');

					this.$editor = $root.find("body").attr('contenteditable', true).attr('dir', this.opts.direction);

					rdocument = this.document = this.$editor[0].ownerDocument;
					rwindow = this.window = this.document.defaultView || window;

					// iOS makes the editor untypeable if there is a touchstart event without this
					$(rdocument).on('touchend', function(e) {
						setTimeout(function() {
							rwindow.focus();
						}, 100);
					});

					if (this.opts.iframeBase !== false)
					{
						$head.append('<base href="' + this.opts.iframeBase + '" />');
					}
					if (this.opts.css !== false)
					{
						$head.append('<link rel="stylesheet" href="' + this.opts.css + '" />');
					}

					this.$editor.html(html);

					if (whendone)
					{
						whendone.call(this);
						whendone = null;
					}
				}

				// editor
				this.textareamode = true;
				if (this.$el.get(0).tagName === 'TEXTAREA')
				{
					if(this.opts.iframe)
					{
						var me = this;
						this.$content = $('<iframe style="width: 100%;" frameborder="0"></iframe>').load(function()
						{
							initFrame.call(me);
						});
					}
					else
					{
						 this.$content = this.$editor = $('<div></div>');
					}

					var classlist = this.$el.get(0).className.split(/\s+/);
					$.each(classlist, $.proxy(function(i,s)
					{
						this.$content.addClass('redactor_' + s);
					}, this));
				}
				else
				{
					this.textareamode = false;
					this.$content = this.$editor = this.$el;
					this.$el = $('<textarea name="' + this.$editor.attr('id') + '"></textarea>').css('height', this.height);
				}

				if (this.$editor)
				{
					this.$editor.addClass('redactor_editor').attr('contenteditable', true).attr('dir', this.opts.direction);
				}

				if (this.opts.tabindex !== false)
				{
					this.$content.attr('tabindex', this.opts.tabindex);
				}

				if (this.opts.minHeight !== false)
				{
					this.$content.css('min-height', this.opts.minHeight + 'px');
				}

				if (this.opts.wym === true)
				{
					this.$content.addClass('redactor_editor_wym');
				}

				if (this.opts.autoresize === false)
				{
					this.$content.css('height', this.height);
				}

				// hide textarea
				this.$el.hide();

				// append box and frame
				var html = '';
				if (this.textareamode)
				{
					// get html
					html = this.$el.val();
					html = this.savePreCode(html);

					this.$box.insertAfter(this.$el).append(this.$content).append(this.$el);
				}
				else
				{
					// get html
					html = this.$editor.html();
					html = this.savePreCode(html);

					this.$box.insertAfter(this.$content).append(this.$el).append(this.$editor);

				}

				// conver newlines to p
				html = this.paragraphy(html);

				// enable
				if (this.$editor)
				{
					this.$editor.html(html);
				}

				if (this.textareamode === false)
				{
					this.syncCode();
				}
			}
			else
			{
				if (this.$el.get(0).tagName !== 'TEXTAREA')
				{
					var html = this.$el.val();
					var textarea = $('<textarea name="' + this.$editor.attr('id') + '"></textarea>').css('height', this.height).val(html);
					this.$el.hide();
					this.$el.after(textarea);
				}
			}

			if (whendone && this.$editor)
			{
				whendone.call(this);
			}

		},
		enableAir: function()
		{
			if (this.opts.air === false)
			{
				return false;
			}

			this.air.hide();

			this.$editor.bind('textselect', $.proxy(function(e)
			{
				this.showAir(e);

			}, this));

			this.$editor.bind('textunselect', $.proxy(function()
			{
				this.air.hide();

			}, this));

		},
		showAir: function(e)
		{
			$('.redactor_air').hide();

			var width = this.air.innerWidth();
			var left = e.clientX;

			if ($(this.document).width() < (left + width))
			{
				left = left - width;
			}

			var top = e.clientY + $(document).scrollTop() + 14;
			if (this.opts.iframe === true)
			{
				top = top + this.$box.position().top;
				left = left + this.$box.position().left;
			}

			this.air.css({ left: left + 'px', top: top + 'px' }).show();
		},

		pushUndoStack: function(resetTyping)
		{
			var state = this.getState();
			if (this.undoStack.length)
			{
				var previousState = this.undoStack[this.undoStack.length - 1];
				if (this.compareStates(previousState, state))
				{
					// no change, don't log
					return;
				}
			}

			if (resetTyping === undefined)
			{
				resetTyping = true;
			}

			if (this.undoStack.length == this.opts.undoSize)
			{
				this.undoStack.shift();
			}

			this.undoStack.push(state);
			this.redoStack = [];
			if (resetTyping)
			{
				this.resetTyping();
			}
		},

		popUndoStack: function()
		{
			var undo = this.undoStack.pop(),
				state;

			if (!undo)
			{
				return;
			}

			state = this.getState();

			if (!this.compareStates(undo, state))
			{
				this.redoStack.push(state);
				this.restoreState(undo);
			}
		},

		popRedoStack: function()
		{
			var redo = this.redoStack.pop();
			if (!redo)
			{
				return;
			}

			this.undoStack.push(this.getState());
			this.restoreState(redo);
		},

		restoreState: function(state)
		{
			this.$editor.html(state.html);

			this.focus();

			if (this.oldIE())
			{
				return;
			}

			if (state.origin)
			{
				var self = this;
				function followSelPath(sel)
				{
					if (!sel)
					{
						return [null, null];
					}

					var el = self.$editor.get(0), offset = 0, children;
					for (var i = 0; i < sel.length; i++)
					{
						switch(sel[i][0])
						{
							case 'node':
								el = el.childNodes[sel[i][1]];
								break;

							case 'text':
								offset = sel[i][1];
								break;
						}
					}

					return [el, offset];
				}

				var origin = followSelPath(state.origin),
					focus = followSelPath(state.focus);

				this.setSelection(origin[0], origin[1], focus[0], focus[1]);
				this.focus();
			}
			else
			{
				this.selectLast();
			}

			this.resetTyping();
		},

		getState: function()
		{
			var o = this.getOrigin(),
				f = this.getFocus(),
				self = this;

			function getSelPath(sel)
			{
				var path = [], root = self.$editor.get(0);
				if (sel[0].nodeType == 3)
				{
					// text node
					path.unshift(['text', sel[1]]);
				}

				var i, node = sel[0], prev;

				while (node && node != root)
				{
					i = 0;
					prev = node;
					while (prev = prev.previousSibling)
					{
						i++;
					}
					path.unshift(['node', i]);

					node = node.parentNode;
				}

				return path;
			}

			return {
				html: this.$editor.html(),
				origin: o ? getSelPath(o) : null,
				focus: f ? getSelPath(f) : null
			};
		},

		compareStates: function(a, b)
		{
			if (a.html != b.html)
			{
				return false;
			}
			if (!a.origin && !a.focus)
			{
				// special case - likely the initial state and we have a selection now so ignore it
				return true;
			}

			if ((!a.origin && b.origin) || (a.origin && !b.origin) || a.origin.length != b.origin.length)
			{
				return false;
			}
			if ((!a.focus && b.focus) || (a.focus && !b.focus) || a.focus.length != b.focus.length)
			{
				return false;
			}

			var compare = function(l, r)
			{
				for (var i = 0; i < l.length; i++)
				{
					if (l[i][0] != r[i][0] || l[i][1] != r[i][1])
					{
						return false;
					}
				}

				return true;
			};
			if (a.origin && !compare(a.origin, b.origin))
			{
				return false;
			}
			if (a.focus && !compare(a.focus, b.focus))
			{
				return false;
			}

			return true;
		},

		onTyping: function(typingType)
		{
			var self = this;

			if (this.typingType && this.typingType != typingType && this.typingCount > 0)
			{
				if (typingType != 'move')
				{
					self.pushUndoStack(false);
				}

				this.typingCount = 0;
			}

			this.typingType = typingType;
			this.typingCount++;

			if (this.typingCount > 25 && this.typingType != 'move')
			{
				setTimeout(function() { self.pushUndoStack(false); }, 0);
				this.typingCount = 0;
			}
		},

		resetTyping: function()
		{
			this.typingType = null;
			this.typingCount = 0;
		},

		syncCode: function()
		{
			this.$el.val(this.$editor.html());
		},

		// API functions
		focus: function(selectLast)
		{
			var f = this.getFocus(),
				o = this.getOrigin();

			if (selectLast)
			{
				if (f)
				{
					if (f[0] == o[0] && f[1] == o[1] && $(f[0]).is('body, .redactor_editor'))
					{
						// focused on the root, this is how FF usually focuses the start
						f = null;
					}
				}
				if (!f)
				{
					this.selectLast();
				}
			}
			else
			{
				if (!f || !o || (f[0] == o[0] && f[1] == 0 && o[1] == 0 && $(f[0]).is('body, .redactor_editor')))
				{
					var fc = this.$editor[0].firstChild;
					if (fc && fc.tagName !== 'BR' && fc.tagName !== 'IMG')
					{
						this.setSelection(fc, 0, fc, 0);
					}
				}
			}

			if (this.opts.iframe && this.$editor[0])
			{
				var doc = this.$editor[0].ownerDocument;
				if (doc)
				{
					(doc.defaultView || doc.parentWindow).focus();
				}
			}

			this.$editor.focus();
		},

		setCode: function(html, focus)
		{
			focus = (typeof focus == 'undefined' ? true : focus);

			html = this.stripTags(html);
			this.$editor.html(html);
			if (focus)
			{
				this.focus();
			}

			this.syncCode();
		},
		getCode: function()
		{
			var html = '';
			if (this.opts.visual)
			{
				html = this.$editor.html()
			}
			else
			{
				html = this.$el.val();
			}

			return this.stripTags(html);
		},
		insertHtml: function(html, skipUndoLevel)
		{
			this.focus();
			this.pasteHtmlAtCaret(html, skipUndoLevel);
			this.observeImages();
			this.syncCode();
		},

		pasteHtmlAtCaret: function (html, skipUndoLevel)
		{
			if (!skipUndoLevel)
			{
				this.pushUndoStack();
			}

			var nodes = false;

			var sel, range;
			if (this.document.getSelection)
			{
				sel = this.window.getSelection();
				if (sel.getRangeAt && sel.rangeCount)
				{
					// remove as many fully selected elements as we can
					var selInfo = this.analyzeSelection();

					if (!selInfo.isCollapsed && !$(selInfo.selectedEls).filter('body').length)
					{
						selInfo.selectPartials();
						var bookmark = selInfo.bookmarkSelection();
						$(selInfo.selectedEls).remove();
						bookmark[0].after('\u200B\u200B');
						selInfo.restoreBookmark();
					}

					nodes = [];
					range = sel.getRangeAt(0);
					range.deleteContents();

					if (range.startContainer.tagName == 'BODY')
					{
						// pasting at the root, so we need to make sure we have a block to wrap
						if (!html.match(/<(p|div|h\d|ul|ol|blockquote|pre)(\s|\/|>)/i))
						{
							html = '<p>' + html + '</p>';
						}
					}

					if (html.match(/<(p|div|h\d|ul|ol|blockquote|pre)(\s|\/|>)/i))
					{
						// range contains block - let's move outside of our current one if we can
						var $start = $(range.startContainer).closest('p, div, h1, h2, h3, h4, h5, h6, ul, ol, blockquote, pre');
						if ($start.length)
						{
							var testRange = range.cloneRange();

							testRange.setEnd($start[0], $start[0].childNodes.length);
							var contentsHolder = this.document.createElement('div');
							contentsHolder.appendChild(testRange.cloneContents());

							// is there anything after the selection in this block?
							// if not, we can move after - otherwise, we'd need to split
							// and do lots of other manipulation
							if ($.trim(contentsHolder.innerHTML) === '' || contentsHolder.innerHTML.match(/^<br\s*\/?>$/i))
							{
								range.setStartAfter($start[0]);
								range.setEndAfter($start[0]);

								var startHtml = $.trim($start.html());
								if (startHtml === '' || startHtml.match(/^<br\s*\/?>$/i))
								{
									$start.remove();
								}
							}
						}
					}

					var el = this.document.createElement("div");
					el.innerHTML = html;
					var frag = this.document.createDocumentFragment(), node, lastNode;
					while (node = el.firstChild)
					{
						lastNode = frag.appendChild(node);
						nodes.push(lastNode);
					}
					range.insertNode(frag);

					if (lastNode)
					{
						range = range.cloneRange();
						if (lastNode.nodeType == 1 && lastNode.childNodes.length)
						{
							var lastChild = lastNode.childNodes[lastNode.childNodes.length - 1];
							if (lastChild.tagName == 'BR')
							{
								if (lastChild.previousSibling)
								{
									range.setStartAfter(lastChild.previousSibling);
								}
								else
								{
									range.setStartBefore(lastChild);
								}
							}
							else
							{
								range.setStartAfter(lastChild);
							}
						}
						else
						{
							range.setStartAfter(lastNode);
						}

						range.collapse(true);
						sel.removeAllRanges();
						sel.addRange(range);
					}
				}
			}
			else if (this.document.selection && this.document.selection.type != "Control")
			{
				this.document.selection.createRange().pasteHTML(html);
			}

			if (this.opts.insertHtmlCallback)
			{
				this.opts.insertHtmlCallback(nodes, this);
			}
		},

		destroy: function()
		{
			var html = this.getCode();

			if (this.textareamode)
			{
				this.$box.after(this.$el);
				this.$box.remove();
				this.$el.height(this.height).val(html).show();
			}
			else
			{
				this.$box.after(this.$editor);
				this.$box.remove();
				this.$editor.removeClass('redactor_editor').removeClass('redactor_editor_wym').attr('contenteditable', false).html(html).show();
			}

			if (this.opts.toolbarExternal)
			{
				$(this.opts.toolbarExternal).empty();
			}

			$('.redactor_air').remove();

			for (var i = 0; i < this.dropdowns.length; i++)
			{
				this.dropdowns[i].remove();
				delete(this.dropdowns[i]);
			}

			this.undoStack = [];
			this.redoStack = [];

			if (this.opts.autosave !== false)
			{
				clearInterval(this.autosaveInterval);
			}

		},
		// end API functions

		// OBSERVERS
		observeFormatting: function(key)
		{
			if (this.skipFormatObserveOnce)
			{
				this.skipFormatObserveOnce = false;
				return;
			}

			var parent = this.getCurrentNode(),
				$parent = $(parent),
				editorContext = this.$editor.get(0);

			this.inactiveAllButtons();

			$.each(this.opts.activeButtonsStates, $.proxy(function(i,s)
			{
				if ($parent.closest(i, editorContext).length != 0)
				{
					this.setBtnActive(s);
				}

			}, this));

			if (key && key == 13)
			{
				// just pressed return, need to check states from the browser
				var self = this;
				$.each(this.opts.activeButtonStateMap, function(b, cmd) {
					try
					{
						if (self.document.queryCommandState(cmd))
						{
							self.setBtnActive(b);
						}
						else
						{
							self.setBtnInactive(b);
						}
					} catch(e) {}
				});
			}

			var tag = $parent.closest(['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'td']);

			if (typeof tag[0] !== 'undefined' && typeof tag[0].elem !== 'undefined' && $(tag[0].elem).size() != 0)
			{
				var align = $(tag[0].elem).css('text-align');

				switch (align)
				{
					case 'right':
						this.setBtnActive('alignright');
					break;
					case 'center':
						this.setBtnActive('aligncenter');
					break;
					case 'justify':
						this.setBtnActive('justify');
					break;
					default:
						this.setBtnActive('alignleft');
					break;
				}
			}
		},
		observeImages: function()
		{
			if (this.opts.observeImages === false)
			{
				return false;
			}

			this.$editor.find('img').each($.proxy(function(i,s)
			{
				if (this.browser('msie'))
				{
					$(s).attr('unselectable', 'on');
				}

				this.resizeImage(s);

			}, this));

		},
		observeTables: function()
		{
			this.$editor.find('table').click($.proxy(this.tableObserver, this));
		},
		observeScroll: function()
		{
			var scrolltop = $(this.document).scrollTop();
			var boxtop = this.$box.offset().top;
			var left = 0;

			if (scrolltop > boxtop)
			{
				var width = '100%';
				if (this.opts.fixedBox)
				{
					left = this.$box.offset().left;
					width = this.$box.innerWidth();
				}

				this.fixed = true;
				this.$toolbar.css({ position: 'fixed', width: width, zIndex: 1005, top: this.opts.fixedTop + 'px', left: left });
			}
			else
			{
				this.fixed = false;
				this.$toolbar.css({ position: 'relative', width: 'auto', zIndex: 1, top: 0, left: left });
			}
		},

		// BUFFER
		setBuffer: function()
		{
			this.saveSelection();
			this.opts.buffer = this.$editor.html();
		},
		getBuffer: function()
		{
			if (this.opts.buffer === false)
			{
				return false;
			}

			this.$editor.html(this.opts.buffer);

			if (!this.browser('msie'))
			{
				this.restoreSelection();
			}

			this.opts.buffer = false;
		},



		// EXECCOMMAND
		execCommand: function(cmd, param, pushUndoStack)
		{
			if (this.opts.visual == false)
			{
				this.$el.focus();
				return false;
			}

			if (typeof pushUndoStack == 'undefined')
			{
				pushUndoStack = true;
			}

			if (pushUndoStack && cmd != 'undo' && cmd != 'redo')
			{
				this.pushUndoStack();
			}

			try
			{

				var parent;

				if (cmd === 'inserthtml')
				{
					this.focus();
					this.pasteHtmlAtCaret(param, true);

					this.observeImages();
				}
				else if (cmd === 'removeformat')
				{
					this.removeSelectedFormatting('b, strong, i, em, u, s, span, strike, font', true);
				}
				else if (cmd === 'unlink')
				{
					this.removeSelectedFormatting('a', false);
				}
				else if (cmd === 'JustifyLeft' || cmd === 'JustifyCenter' || cmd === 'JustifyRight' || cmd === 'JustifyFull')
				{
					var origin = this.getOrigin(),
						focus = this.getFocus();

					if (!focus)
					{
						return false;
					}

					if (this.opts.iframe === false && $(focus[0]).closest('.redactor_editor').size() == 0)
					{
						return false;
					}

					var $alignEl;
					if (focus[0] == origin[0])
					{
						$alignEl = $(focus[0]).closest('p, div, h1, h2, h3, h4, h5, h6, blockquote, td, li', this.$editor.get(0))
					}

					if ($alignEl && $alignEl.length)
					{
						var align = false;

						if (cmd === 'JustifyCenter')
						{
							align = 'center';
						}
						else if (cmd === 'JustifyLeft')
						{
							align = 'left';
						}
						else if (cmd === 'JustifyRight')
						{
							align = 'right';
						}
						else if (cmd === 'JustifyFull')
						{
							align = 'justify';
						}

						if (align && align == ((this.opts.direction.toLowerCase() == 'rtl') ? 'right' : 'left'))
						{
							align = false;
						}

						if (align === false)
						{
							$alignEl.css('text-align', '').removeAttr('align');
						}
						else
						{
							$alignEl.css('text-align', align).removeAttr('align');
						}

						this.focus();
					}
					else
					{
						this.execRun(cmd, param);
					}
				}
				else if (cmd === 'formatblock' && param === 'blockquote')
				{
					parent = this.getCurrentNode();
					if ($(parent).get(0).tagName === 'BLOCKQUOTE')
					{
						if (this.browser('msie'))
						{
							var node = $('<p>' + $(parent).html() + '</p>');
							$(parent).replaceWith(node);
						}
						else
						{
							this.execRun(cmd, 'p');
						}
					}
					else if ($(parent).get(0).tagName === 'P')
					{
						var parent2 = $(parent).parent();
						if ($(parent2).get(0).tagName === 'BLOCKQUOTE')
						{
							var node = $('<p>' + $(parent).html() + '</p>');
							$(parent2).replaceWith(node);
							this.setSelection(node[0], 0, node[0], 0);
						}
						else
						{
							if (this.browser('msie'))
							{
								var node = $('<blockquote>' + $(parent).html() + '</blockquote>');
								$(parent).replaceWith(node);
							}
							else
							{
								this.execRun(cmd, param);
							}
						}
					}
					else
					{
						this.execRun(cmd, param);
					}
				}
				else if (cmd === 'formatblock' && (param === 'pre' || param === 'p'))
				{
					parent = this.getParentNode();

					if ($(parent).get(0).tagName === 'PRE')
					{
						$(parent).replaceWith('<p>' +  this.encodeEntities($(parent).text()) + '</p>');
					}
					else
					{
						this.execRun(cmd, param);
					}
				}
				else if (cmd == 'undo')
				{
					this.popUndoStack();
				}
				else if (cmd == 'redo')
				{
					this.popRedoStack();
				}
				else if (this.opts.execCommandHandler[cmd])
				{
					this.opts.execCommandHandler[cmd].call(this, cmd, param, this);
				}
				else
				{
					if (cmd === 'inserthorizontalrule' && this.browser('msie'))
					{
						this.focus();
					}

					if (cmd === 'formatblock' && this.browser('mozilla'))
					{
						this.focus();
					}

					this.execRun(cmd, param);
				}

				if (cmd === 'inserthorizontalrule')
				{
					this.$editor.find('hr').removeAttr('id');
				}

				this.syncCode();

				if (this.oldIE())
				{
					this.focus();
				}

				if (typeof this.opts.execCommandCallback === 'function')
				{
					this.opts.execCommandCallback(this, cmd);
				}

				if (this.opts.air)
				{
					this.air.hide();
				}
			}
			catch (e) { }
		},
		execRun: function(cmd, param)
		{
			if (cmd === 'formatblock' && this.browser('msie'))
			{
				param = '<' + param + '>';
			}

			this.document.execCommand(cmd, false, param);
		},

		// FORMAT NEW LINE
		formatNewLine: function(e)
		{
			var parent = this.getParentNode();

			var isParentRoot = (parent.nodeName === 'DIV' && (
				(this.opts.iframe == false && parent.className.match(/(\s|^)redactor_editor(\s|$)/))
				|| (this.opts.iframe == true && parent.tagName == 'BODY')
				));
			var isParentP = (parent.nodeName === 'P');

			if (isParentRoot || isParentP)
			{
				var element = this.getCurrentNode();
				var html = element.innerHTML || '';
				var isEmpty = (html === '' || html == '<br>');

				if (element.tagName === 'DIV')
				{
					if (element.parentNode.tagName === 'P')
					{
						var attachParent = element.parentNode;
						while (attachParent.parentNode && attachParent.parentNode.tagName === 'P')
						{
							attachParent = attachParent.parentNode;
						}
						attachParent.parentNode.insertBefore(element, attachParent.nextSibling)
						this.setSelection(element, 0, element, 0);
					}

					if (isEmpty)
					{
						var $element = $(element);
						var newElement = $('<p>').append($element.clone().get(0).childNodes);
						$element.replaceWith(newElement);
						newElement.html('<br />');
						this.setSelection(newElement[0], 0, newElement[0], 0);
					} 
				}
				else if (element.tagName === 'P' && !isEmpty)
				{
					// pressing enter at beginning of a line
					element.parentNode.insertBefore(this.document.createElement('p'), element);
					this.setSelection(element, 0, element, 0);
				}
			}
		},

		// SAFARI SHIFT KEY + ENTER
		safariShiftKeyEnter: function(e, key)
		{
			if (e.shiftKey && key === 13)
			{
				e.preventDefault();
				this.insertNodeAtCaret($('<span><br /></span>').get(0));
				this.syncCode();
				return false;
			}
			else
			{
				return true;
			}
		},

		// FORMAT EMPTY
		formatEmpty: function(e)
		{
			var html = $.trim(this.$editor.html());

			html = html.replace(/<br\s?\/?>/i, '');
			var thtml = html.replace(/<p>\s?<\/p>/gi, '');

			if (html === '' || thtml === '')
			{
				e && e.preventDefault();

				var node = $(this.opts.emptyHtml).get(0);
				this.$editor.html(node);
				this.setSelection(node, 0, node, 0);

				this.syncCode();
				return false;
			}
			else
			{
				this.syncCode();
			}
		},

		// PARAGRAPHY
		paragraphy: function (str)
		{
			str = $.trim(str);
			if (str === '' || str === '<p></p>' || str === '<p><br></p>')
			{
				return this.opts.emptyHtml;
			}

			// convert div to p
			if (this.opts.convertDivs)
			{
				str = str.replace(/<div(.*?)>([\w\W]*?)<\/div>/gi, '<p>$2</p>');
			}

			// inner functions
			var X = function(x, a, b) { return x.replace(new RegExp(a, 'g'), b); };
			var R = function(a, b) { return X(str, a, b); };

			// block elements
			var blocks = '(table|thead|tfoot|caption|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|select|form|blockquote|address|math|style|script|object|input|param|p|h[1-6])';

			//str = '<p>' + str;
			str += '\n';

			R('<br />\\s*<br />', '\n\n');
			R('(<' + blocks + '[^>]*>)', '\n$1');
			R('(</' + blocks + '>)', '$1\n\n');
			R('\r\n|\r', '\n'); // newlines
			R('\n\n+', '\n\n'); // remove duplicates
			R('\n?((.|\n)+?)$', '<p>$1</p>\n'); // including one at the end
			R('<p>\\s*?</p>', ''); // remove empty p
			R('<p>(<div[^>]*>\\s*)', '$1<p>');
			R('<p>([^<]+)\\s*?(</(div|address|form)[^>]*>)', '<p>$1</p>$2');
			R('<p>\\s*(</?' + blocks + '[^>]*>)\\s*</p>', '$1');
			R('<p>(<li.+?)</p>', '$1');
			R('<p>\\s*(</?' + blocks + '[^>]*>)', '$1');
			R('(</?' + blocks + '[^>]*>)\\s*</p>', '$1');
			R('(</?' + blocks + '[^>]*>)\\s*<br />', '$1');
			R('<br />(\\s*</?(p|li|div|dl|dd|dt|th|pre|td|ul|ol)[^>]*>)', '$1');

			// pre
			if (str.indexOf('<pre') != -1)
			{
				R('(<pre(.|\n)*?>)((.|\n)*?)</pre>', function(m0, m1, m2, m3)
				{
					return X(m1, '\\\\([\'\"\\\\])', '$1') + X(X(X(m3, '<p>', '\n'), '</p>|<br />', ''), '\\\\([\'\"\\\\])', '$1') + '</pre>';
				});
			}

			return R('\n</p>$', '</p>');
		},

		// REMOVE TAGS
		stripTags: function(html)
		{
			var allowed = this.opts.allowedTags;
			var tags = /<\/?([a-z][a-z0-9]*)\b[^>]*>/gi;
			return html.replace(tags, function ($0, $1)
			{
				return $.inArray($1.toLowerCase(), allowed) > '-1' ? $0 : '';
			});
		},

		removeSelectedFormatting: function(removeTagSelector, splitPartials)
		{
			var sel = this.analyzeSelection(),
				applyTo;

			if (splitPartials)
			{
				if (sel.isCollapsed)
				{
					return;
				}

				sel.selectPartials();
				applyTo = sel.selectedEls;
			}
			else
			{
				applyTo = sel.selectedEls;
				applyTo.push(sel.origin[0]);
				if (sel.focus[0] != sel.origin[0])
				{
					applyTo.push(sel.focus[0]);
				}
			}

			sel.bookmarkSelection();

			var removeCallback = function() {
					$(this).after(this.childNodes);
					$(this).remove();
				},
				$ed = this.$editor;

			$(applyTo).each(function() {
				var el = this,
					$el = $(el),
					$closest = $el.parent().closest(removeTagSelector, $ed);

				var infiniteCheck = 0;

				while ($closest.length)
				{
					infiniteCheck++;
					if (infiniteCheck > 1000)
					{
						console.error('infinite loop when removing formatting');
						break;
					}

					var closest = $closest[0];

					if (closest.childNodes.length == 1 || !splitPartials)
					{
						// special case: only child so we can just move it up
						// or we're applying this to the whole thing
						removeCallback.call(closest);
					}
					else
					{
						var $until = $el.parentsUntil($closest);
						if ($until.length)
						{
							var $stack = $(el),
								$after,
								$placeholder = $('<span class="__bogus" />');
							while (el.nextSibling)
							{
								if (!$after)
								{
									$after = $placeholder;
								}
								$after.append(el.nextSibling);
							}
							$until.each(function() {
								if ($after)
								{
									$after = $(this.cloneNode(false)).append($after);
								}
								while (this.nextSibling)
								{
									if (!$after)
									{
										$after = $placeholder;
									}
									$after.append(this.nextSibling);
								}

								$stack = $(this.cloneNode(false)).append($stack);
							});

							if ($after)
							{
								if ($after.is($placeholder))
								{
									$closest.after($(closest.cloneNode(false)).append($after[0].childNodes));
								}
								else
								{
									removeCallback.call($placeholder[0]);
									$closest.after($(closest.cloneNode(false)).append($after));
								}
							}
							$closest.after($stack);
						}
						else
						{
							if (!el.previousSibling)
							{
								// can simply move before
								$(closest).before(el);
							}
							else if (!el.nextSibling)
							{
								// can simply move after
								$(closest).after(el);
							}
							else
							{
								var $clone = $(closest.cloneNode(false));
								while (el.nextSibling)
								{
									$clone.append(el.nextSibling);
								}
								$(closest).after($clone).after(el);
							}
						}
					}

					$closest = $el.parent().closest(removeTagSelector, $ed);
				}

				$el.find(removeTagSelector).each(removeCallback);
				if ($el.is(removeTagSelector))
				{
					removeCallback.call(this);
				}
			});

			sel.restoreBookmark();
			this.observeFormatting();
		},

		savePreCode: function(html)
		{
			var pre = html.match(/<pre(.*?)>([\w\W]*?)<\/pre>/gi);
			if (pre !== null)
			{
				$.each(pre, $.proxy(function(i,s)
				{
					var arr = s.match(/<pre(.*?)>([\w\W]*?)<\/pre>/i);
					arr[2] = this.encodeEntities(arr[2]);
					html = html.replace(s, '<pre' + arr[1] + '>' + arr[2] + '</pre>');
				}, this));
			}

			return html;
		},
		encodeEntities: function(str)
		{
			str = String(str).replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"');
			return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
		},
		cleanupPre: function(s)
		{
			s = s.replace(/<br>/gi, '\n');
			s = s.replace(/<\/p>/gi, '\n');
			s = s.replace(/<\/div>/gi, '\n');

			var tmp = this.document.createElement("div");
			tmp.innerHTML = s;
			return tmp.textContent||tmp.innerText;

		},


		// PASTE CLEANUP
		pasteCleanUp: function(html)
		{
			var parent = this.getParentNode();

			// clean up pre
			if ($(parent).get(0) && $(parent).get(0).tagName === 'PRE')
			{
				html = this.cleanupPre(html);
				this.pasteCleanUpInsert(html);
				return true;
			}

			var e = new $.Event();

			if (this.opts.pasteCleanUpCallback)
			{
				html = this.opts.pasteCleanUpCallback(e, this, html);
			}

			if (e.isDefaultPrevented())
			{
				this.pasteCleanUpInsert(html);
				return;
			}

			// remove comments and php tags
			html = html.replace(/<!--[\s\S]*?-->|<\?(?:php)?[\s\S]*?\?>/gi, '');

			// remove nbsp
			html = html.replace(/(&nbsp;){2,}/gi, '&nbsp;');

			// remove google docs marker
			html = html.replace(/<b\sid="internal-source-marker(.*?)">([\w\W]*?)<\/b>/gi, "$2");

			// strip tags
			html = this.stripTags(html);

			// prevert
			html = html.replace(/\[/g, '&#91;');
			html = html.replace(/\]/g, '&#93;');
			html = html.replace(/<td><\/td>/gi, '[td]');
			html = html.replace(/<td>&nbsp;<\/td>/gi, '[td]');
			html = html.replace(/<td><br><\/td>/gi, '[td]');
			html = html.replace(/<a(.*?)href="(.*?)"(.*?)>([\w\W]*?)<\/a>/gi, '[a href="$2"]$4[/a]');
			html = html.replace(/<iframe(.*?)>([\w\W]*?)<\/iframe>/gi, '[iframe$1]$2[/iframe]');
			html = html.replace(/<video(.*?)>([\w\W]*?)<\/video>/gi, '[video$1]$2[/video]');
			html = html.replace(/<audio(.*?)>([\w\W]*?)<\/audio>/gi, '[audio$1]$2[/audio]');
			html = html.replace(/<embed(.*?)>([\w\W]*?)<\/embed>/gi, '[embed$1]$2[/embed]');
			html = html.replace(/<object(.*?)>([\w\W]*?)<\/object>/gi, '[object$1]$2[/object]');
			html = html.replace(/<param(.*?)>/gi, '[param$1]');
			html = html.replace(/<img(.*?)style="(.*?)"(.*?)>/gi, '[img$1$3]');

			// remove attributes
			html = html.replace(/<(\w+)([\w\W]*?)>/gi, '<$1>');

			// remove empty
			html = html.replace(/<[^\/>][^>]*>(\s*|\t*|\n*|&nbsp;|<br>)<\/[^>]+>/gi, '');
			html = html.replace(/<[^\/>][^>]*>(\s*|\t*|\n*|&nbsp;|<br>)<\/[^>]+>/gi, '');

			// revert
			html = html.replace(/\[td\]/gi, '<td>&nbsp;</td>');
			html = html.replace(/\[a href="(.*?)"\]([\w\W]*?)\[\/a\]/gi, '<a href="$1">$2</a>');
			html = html.replace(/\[iframe(.*?)\]([\w\W]*?)\[\/iframe\]/gi, '<iframe$1>$2</iframe>');
			html = html.replace(/\[video(.*?)\]([\w\W]*?)\[\/video\]/gi, '<video$1>$2</video>');
			html = html.replace(/\[audio(.*?)\]([\w\W]*?)\[\/audio\]/gi, '<audio$1>$2</audio>');
			html = html.replace(/\[embed(.*?)\]([\w\W]*?)\[\/embed\]/gi, '<embed$1>$2</embed>');
			html = html.replace(/\[object(.*?)\]([\w\W]*?)\[\/object\]/gi, '<object$1>$2</object>');
			html = html.replace(/\[param(.*?)\]/gi, '<param$1>');
			html = html.replace(/\[img(.*?)\]/gi, '<img$1>');
			html = html.replace(/&#91;paste:([a-zA-Z0-9]+)(.*?)&#93;([\w\W]*?)&#91;\/paste:\1&#93;/gi, '<$1$2>$3</$1>');
			html = html.replace(/&#91;/g, '[');
			html = html.replace(/&#93;/g, ']');

			// convert div to p
			if (this.opts.convertDivs)
			{
				html = html.replace(/<div(.*?)>([\w\W]*?)<\/div>/gi, '<p>$2</p>');
			}

			// remove span
			html = html.replace(/<span>([\w\W]*?)<\/span>/gi, '$1');

			html = html.replace(/\n{3,}/gi, '\n');

			// remove dirty p
			html = html.replace(/<p><p>/gi, '<p>');
			html = html.replace(/<\/p><\/p>/gi, '</p>');

			// FF fix
			if (this.browser('mozilla'))
			{
				html = html.replace(/<br>$/gi, '');
			}

			this.pasteCleanUpInsert(html);

		},

		pasteCleanUpInsert: function(html)
		{
			this.execCommand('inserthtml', html);

			if (this.opts.autoresize === true)
			{
				$(this.document.body).scrollTop(this.saveScroll);
			}
			else
			{
				this.$editor.scrollTop(this.saveScroll);
			}
		},


		// TEXTAREA CODE FORMATTING
		formattingRemove: function(html)
		{
			// save pre
			var prebuffer = [];
			var pre = html.match(/<pre(.*?)>([\w\W]*?)<\/pre>/gi);
			if (pre !== null)
			{
				$.each(pre, function(i,s)
				{
					html = html.replace(s, 'prebuffer_' + i);
					prebuffer.push(s);
				});
			}

			html = html.replace(/\s{2,}/g, ' ');
			html = html.replace(/\n/g, ' ');
			html = html.replace(/[\t]*/g, '');
			html = html.replace(/\n\s*\n/g, "\n");
			html = html.replace(/^[\s\n]*/g, '');
			html = html.replace(/[\s\n]*$/g, '');
			html = html.replace(/>\s+</g, '><');

			if (prebuffer)
			{
				$.each(prebuffer, function(i,s)
				{
					html = html.replace('prebuffer_' + i, s);
				});

				prebuffer = [];
			}

			return html;
		},
		formattingIndenting: function(html)
		{
			html = html.replace(/<li/g, "\t<li");
			html = html.replace(/<tr/g, "\t<tr");
			html = html.replace(/<td/g, "\t\t<td");
			html = html.replace(/<\/tr>/g, "\t</tr>");

			return html;
		},
		formattingEmptyTags: function(html)
		{
			var etags = ["<pre></pre>","<blockquote>\\s*</blockquote>","<em>\\s*</em>","<ul></ul>","<ol></ol>","<li></li>","<table></table>","<tr></tr>","<span>\\s*<span>", "<span>&nbsp;<span>", "<b>\\s*</b>", "<b>&nbsp;</b>"];
			if (this.browser('msie')) {
				etags.push("<p>\\s*</p>");
				etags.push("<p>&nbsp;</p>");
				etags.push("<p>&#8203;</p>");
				etags.push("<div>\\s*</div>");
			}
			for (var i = 0; i < etags.length; ++i)
			{
				var bbb = etags[i];
				html = html.replace(new RegExp(bbb,'gi'), "");
			}

			return html;
		},
		formattingAddBefore: function(html)
		{
			var lb = '\r\n';
			var btags = ["<p", "<form","</ul>", '</ol>', "<fieldset","<legend","<object","<embed","<select","<option","<input","<textarea","<pre","<blockquote","<ul","<ol","<li","<dl","<dt","<dd","<table", "<thead","<tbody","<caption","</caption>","<th","<tr","<td","<figure"];
			for (var i = 0; i < btags.length; ++i)
			{
				var eee = btags[i];
				html = html.replace(new RegExp(eee,'gi'),lb+eee);
			}

			return html;
		},
		formattingAddAfter: function(html)
		{
			var lb = '\r\n';
			var atags = ['</p>', '</div>', '</h1>', '</h2>', '</h3>', '</h4>', '</h5>', '</h6>', '<br>', '<br />', '</dl>', '</dt>', '</dd>', '</form>', '</blockquote>', '</pre>', '</legend>', '</fieldset>', '</object>', '</embed>', '</textarea>', '</select>', '</option>', '</table>', '</thead>', '</tbody>', '</tr>', '</td>', '</th>', '</figure>'];
			for (var i = 0; i < atags.length; ++i)
			{
				var aaa = atags[i];
				html = html.replace(new RegExp(aaa,'gi'),aaa+lb);
			}

			return html;
		},
		formatting: function(html)
		{
			html = this.formattingRemove(html);

			// empty tags
			html = this.formattingEmptyTags(html);

			// add formatting before
			html = this.formattingAddBefore(html);

			// add formatting after
			html = this.formattingAddAfter(html);

			// indenting
			html = this.formattingIndenting(html);

			return html;
		},

		// TOGGLE
		toggle: function()
		{
			var html;

			if (this.opts.visual)
			{
				var height = this.$editor.innerHeight();

				this.$editor.hide();
				this.$content.hide();

				html = this.$editor.html();
				//html = $.trim(this.formatting(html));

				this.$el.height(height).val(html).show().focus();

				this.setBtnActive('html');
				this.opts.visual = false;
			}
			else
			{
				this.$el.hide();
				var html = this.$el.val();

				//html = this.savePreCode(html);

				// clean up
				//html = this.stripTags(html);

				// set code
				this.$editor.html(html).show();
				this.$content.show();

				if (this.$editor.html() === '')
				{
					this.setCode(this.opts.emptyHtml, false);
				}

				this.focus();

				this.setBtnInactive('html');
				this.opts.visual = true;

				this.observeImages();
				this.observeTables();
			}
		},

		// AUTOSAVE
		autoSave: function()
		{
			this.autosaveInterval = setInterval($.proxy(function()
			{
				$.ajax({
					url: this.opts.autosave,
					type: 'post',
					data: this.$el.attr('name') + '=' + escape(encodeURIComponent(this.getCode())),
					success: $.proxy(function(data)
					{
						// callback
						if (typeof this.opts.autosaveCallback === 'function')
						{
							this.opts.autosaveCallback(data, this);
						}

					}, this)
				});


			}, this), this.opts.interval*1000);
		},

		// TOOLBAR
		buildToolbar: function()
		{
			if (this.opts.toolbar === false)
			{
				return false;
			}

			this.$toolbar = $('<ul>').addClass('redactor_toolbar');

			if (this.opts.air)
			{
				$(this.air).append(this.$toolbar);
				$('body').append(this.air);
			}
			else
			{
				if (this.opts.toolbarExternal === false)
				{
					this.$box.prepend(this.$toolbar);
				}
				else
				{
					$(this.opts.toolbarExternal).html(this.$toolbar);
				}
			}

			var self = this;
			var addToolbarButton = function($toolbar, key)
			{
				if (key !== '|' && typeof self.opts.toolbar[key] !== 'undefined')
				{
					var s = self.opts.toolbar[key];

					if (self.opts.fileUpload === false && key === 'file')
					{
						return;
					}

					$toolbar.append($('<li class="redactor_btn_container_' + key + '">').append(self.buildButton(key, s)));
				}


				if (key === '|')
				{
					$toolbar.append($('<li class="redactor_separator"></li>'));
				}
			};

			var $toolbar = this.$toolbar;

			$.each(this.opts.buttons, function(i,key)
			{
				if ($.isArray(key))
				{
					var $list = $('<ul />');
					$.each(key, function(j, child) {
						addToolbarButton($list, child);
					});

					$toolbar.append($('<li class="redactor_btn_group" />').append($list));
				}
				else
				{
					addToolbarButton($toolbar, key);
				}
			});

		},
		buildButton: function(key, s)
		{
			var button = $('<a href="javascript:void(null);" title="' + s.title + '" class="redactor_btn_' + key + '"></a>');

			button.attr({
				unselectable: 'on',
				tabindex: -1
			}).on('dragstart', function(e) {e.preventDefault();});

			if (typeof s.func === 'undefined')
			{
				button.click($.proxy(function()
				{
					if ($.inArray(key, this.opts.activeButtons) != -1)
					{
						//this.inactiveAllButtons();
						this.toggleBtnActive(key);
					}

					if (this.browser('mozilla') || this.opts.iframe)
					{
						this.focus();
						//this.restoreSelection();
					}

					this.execCommand(s.exec, key);

				}, this));
			}
			else if (s.func !== 'show')
			{
				button.click($.proxy(function(e) {

					this[s.func](e);

				}, this));
			}

			if (typeof s.callback !== 'undefined' && s.callback !== false)
			{
				button.click($.proxy(function(e) { s.callback(this, e, key); }, this));
			}

			// dropdown
			if (key === 'backcolor' || key === 'fontcolor' || typeof(s.dropdown) !== 'undefined')
			{
				var dropdown = $('<div class="redactor_dropdown" style="display: none;">');

				if (key === 'backcolor' || key === 'fontcolor')
				{
					dropdown = this.buildColorPicker(dropdown, key);
				}
				else
				{
					dropdown = this.buildDropdown(dropdown, s.dropdown);
				}

				this.dropdowns.push(dropdown.appendTo($(document.body)));

				// observing dropdown
				this.hdlShowDropDown = $.proxy(function(e) { this.showDropDown(e, dropdown, key, s); }, this);

				button.click(this.hdlShowDropDown);
			}

			return button;
		},
		buildDropdown: function(dropdown, obj)
		{
			$.each(obj, $.proxy(
				function (x, d)
				{
					if (typeof(d.className) === 'undefined')
					{
						d.className = '';
					}

					var drop_a;
					if (typeof d.name !== 'undefined' && d.name === 'separator')
					{
						drop_a = $('<a class="redactor_separator_drop" unselectable="on">');
					}
					else
					{
						drop_a = $('<a href="javascript:void(null);" unselectable="on" class="' + d.className + '" style="' + (d.style || '') + '">' + d.title + '</a>');

						if (typeof(d.callback) === 'function')
						{
							$(drop_a).click($.proxy(function(e) { d.callback(this, e, x); }, this));
						}
						else if (typeof(d.func) === 'undefined')
						{
							$(drop_a).click($.proxy(function() { this.execCommand(d.exec, x); this.focus(); }, this));
						}
						else
						{
							$(drop_a).click($.proxy(function(e) { this[d.func](e); }, this));
						}
					}

					$(dropdown).append(drop_a);

				}, this)
			);

			return dropdown;

		},
		buildColorPicker: function(dropdown, key)
		{
			var mode;
			if (key === 'backcolor')
			{
				if (this.browser('msie'))
				{
					mode = 'BackColor';
				}
				else
				{
					mode = 'hilitecolor';
				}
			}
			else
			{
				mode = 'forecolor';
			}

			$(dropdown).width(this.opts.colorWidth);

			var len = this.opts.colors.length;
			for (var i = 0; i < len; ++i)
			{
				var color = this.opts.colors[i];

				var swatch = $('<a rel="' + color + '" href="javascript:void(null);" class="redactor_color_link" unselectable="on"></a>').css({ 'backgroundColor': color });
				$(dropdown).append(swatch);

				var _self = this;
				$(swatch).click(function()
				{
					var color = $(this).attr('rel');
					_self.execCommand(mode, color);

					if (mode === 'forecolor')
					{
						if (_self.opts.cleanupFontTags)
						{
							_self.$editor.find('font').filter(function() {
								return $(this).attr('color') == color;
							}).replaceWith(function() {
								var $span = $('<span style="color: ' + $(this).attr('color') + ';">' + $(this).html() + '</span>');

								$span.find('[style]').css('color', '');

								return $span;
							});
						}
						else
						{
							var $sel = $(_self.analyzeSelection().selectedEls);
							$sel.find('[style]').css('color', '');
							$sel.filter('[style]').css('color', '');
						}
					}

					if (_self.browser('msie') && mode === 'BackColor')
					{
						_self.$editor.find('font').replaceWith(function() {

							return $('<span style="' + $(this).attr('style') + '">' + $(this).html() + '</span>');

						});
					}

					_self.focus();
				});
			}

			var elnone = $('<a href="javascript:void(null);" class="redactor_color_none" unselectable="on"></a>').html(RLANG.none);

			if (key === 'backcolor')
			{
				elnone.click($.proxy(this.setBackgroundNone, this));
			}
			else
			{
				elnone.click($.proxy(this.setColorNone, this));
			}

			$(dropdown).append(elnone);

			return dropdown;
		},
		setBackgroundNone: function()
		{
			$(this.getCurrentNode()).parentsUntil('.redactor_editor, body').each(function() {
				var $el = $(this);
				if ($el.style.backgroundColor)
				{
					$el.css('background-color', 'transparent');
					return false;
				}
			});
			this.syncCode();
			this.focus();
		},
		setColorNone: function()
		{
			$(this.getCurrentNode()).parentsUntil('.redactor_editor, body').addBack().each(function() {
				var $el = $(this);
				if (this.attributes.color || this.style.color)
				{
					$el.removeAttr('color').css('color', '');
					return false;
				}
			});
			this.syncCode();
			this.focus();
		},

		// DROPDOWNS
		showDropDown: function(e, dropdown, key, s)
		{
			var btn = this.getBtn(key);

			if (btn.hasClass('dropact'))
			{
				this.hideAllDropDown();
			}
			else
			{
				this.hideAllDropDown();

				this.setBtnActive(key);
				btn.addClass('dropact');

				var offset = btn.offset(),
					left = offset.left,
					windowWidth = $(window).width(),
					dropdownWidth = $(dropdown).outerWidth();

				if (s.dropdownOnShow)
				{
					s.dropdownOnShow(this, e, dropdown, key);
				}

				if (left + dropdownWidth > windowWidth)
				{
					left = Math.max(left - dropdownWidth + this.getBtn(key).outerWidth(), 0);
				}

				if (this.opts.air)
				{
					var air_top = this.air.offset().top;

					$(dropdown).css({ position: 'absolute', left: left + 'px', top: air_top+30 + 'px' }).show();
				}
				else if (this.opts.fixed && this.fixed)
				{
					$(dropdown).css({ position: 'fixed', left: left + 'px', top: '30px' }).show();
				}
				else
				{
					var top = offset.top + btn.outerHeight();
					$(dropdown).css({ position: 'absolute', left: left + 'px', top: top + 'px' }).show();
				}
			}

			var hdlHideDropDown = $.proxy(function(e) { this.hideDropDown(e, dropdown, key); }, this);

			$(document).one('click', hdlHideDropDown);
			this.$editor.one('click', hdlHideDropDown);
			this.$content.one('click', hdlHideDropDown);

			e.stopPropagation();

		},
		hideAllDropDown: function()
		{
			this.$toolbar.find('a.dropact').removeClass('redactor_act').removeClass('dropact');
			$('.redactor_dropdown').hide();
		},
		hideDropDown: function(e, dropdown, key)
		{
			if (!$(e.target).hasClass('dropact'))
			{
				$(dropdown).removeClass('dropact');
				this.showedDropDown = false;
				this.hideAllDropDown();
			}
		},

		// BUTTONS MANIPULATIONS
		getBtn: function(key)
		{
			if (this.opts.toolbar === false)
			{
				return false;
			}

			return $(this.$toolbar.find('a.redactor_btn_' + key));
		},
		toggleBtnActive: function(key)
		{
			if (this.opts.activeButtons == false || this.opts.toolbar === false || this.isMobile(true))
			{
				return;
			}

			this.getBtn(key).toggleClass('redactor_act');
		},
		setBtnActive: function(key)
		{
			if (this.opts.activeButtons == false || this.opts.toolbar === false || this.isMobile(true))
			{
				return;
			}

			this.getBtn(key).addClass('redactor_act');
		},
		setBtnInactive: function(key)
		{
			if (this.opts.activeButtons == false || this.opts.toolbar === false || this.isMobile(true))
			{
				return;
			}

			this.getBtn(key).removeClass('redactor_act');
		},
		inactiveAllButtons: function()
		{
			$.each(this.opts.activeButtons, $.proxy(function(i,s)
			{
				this.setBtnInactive(s);

			}, this));
		},
		changeBtnIcon: function(key, classname)
		{
			this.getBtn(key).addClass('redactor_btn_' + classname);
		},
		removeBtnIcon: function(key, classname)
		{
			this.getBtn(key).removeClass('redactor_btn_' + classname);
		},

		addBtnSeparator: function()
		{
			this.$toolbar.append($('<li class="redactor_separator"></li>'));
		},
		addBtnSeparatorAfter: function(key)
		{
			var $btn = this.getBtn(key);
			$btn.parent().after($('<li class="redactor_separator"></li>'));
		},
		addBtnSeparatorBefore: function(key)
		{
			var $btn = this.getBtn(key);
			$btn.parent().before($('<li class="redactor_separator"></li>'));
		},
		removeBtnSeparatorAfter: function(key)
		{
			var $btn = this.getBtn(key);
			$btn.parent().next().remove();
		},
		removeBtnSeparatorBefore: function(key)
		{
			var $btn = this.getBtn(key);
			$btn.parent().prev().remove();
		},

		setBtnRight: function(key)
		{
			if (this.opts.toolbar === false)
			{
				return false;
			}

			this.getBtn(key).parent().addClass('redactor_btn_right');
		},
		setBtnLeft: function(key)
		{
			if (this.opts.toolbar === false)
			{
				return false;
			}

			this.getBtn(key).parent().removeClass('redactor_btn_right');
		},
		addBtn: function(key, title, callback, dropdown)
		{
			if (this.opts.toolbar === false)
			{
				return false;
			}

			var btn = this.buildButton(key, { title: title, callback: callback, dropdown: dropdown });
			this.$toolbar.append($('<li>').append(btn));
		},
		addBtnFirst: function(key, title, callback, dropdown)
		{
			if (this.opts.toolbar === false)
			{
				return false;
			}

			var btn = this.buildButton(key, { title: title, callback: callback, dropdown: dropdown });
			this.$toolbar.prepend($('<li>').append(btn));
		},
		addBtnAfter: function(afterkey, key, title, callback, dropdown)
		{
			if (this.opts.toolbar === false)
			{
				return false;
			}

			var btn = this.buildButton(key, { title: title, callback: callback, dropdown: dropdown });
			var $btn = this.getBtn(afterkey);
			$btn.parent().after($('<li>').append(btn));
		},
		addBtnBefore: function(beforekey, key, title, callback, dropdown)
		{
			if (this.opts.toolbar === false)
			{
				return false;
			}

			var btn = this.buildButton(key, { title: title, callback: callback, dropdown: dropdown });
			var $btn = this.getBtn(beforekey);
			$btn.parent().before($('<li>').append(btn));
		},
		removeBtn: function(key, separator)
		{
			var $btn = this.getBtn(key);

			if (separator === true)
			{
				$btn.parent().next().remove();
			}

			$btn.parent().removeClass('redactor_btn_right');
			$btn.remove();
		},


		// SELECTION AND NODE MANIPULATION
		getFragmentHtml: function (fragment)
		{
			var cloned = fragment.cloneNode(true);
			var div = this.document.createElement('div');
			div.appendChild(cloned);
			return div.innerHTML;
		},
		extractContent: function(node)
		{
			var node = node || this.$editor.get(0);
			var frag = this.document.createDocumentFragment(), child;
			while ((child = node.firstChild))
			{
				frag.appendChild(child);
			}

			return frag;
		},

		// Save and Restore Selection
		saveSelection: function()
		{
			this.focus();

			this.savedSel = this.getOrigin();
			this.savedSelObj = this.getFocus();
		},
		restoreSelection: function()
		{
			if (typeof this.savedSel !== 'undefined' && this.savedSel !== null && this.savedSelObj !== null)
			{
				if (this.opts.iframe === false && $(this.savedSel[0]).closest('.redactor_editor').size() == 0)
				{
					this.focus();
				}
				else
				{
					if (this.browser('opera'))
					{
						this.focus();
					}

					this.setSelection(this.savedSel[0], this.savedSel[1], this.savedSelObj[0], this.savedSelObj[1]);

					if (this.browser('mozilla'))
					{
						this.focus();
					}
				}
			}
			else
			{
				this.focus();
			}
		},

		// Selection
		getSelection: function()
		{
			var doc = this.document;

			if (this.window.getSelection)
			{
				return this.window.getSelection();
			}
			else if (doc.getSelection)
			{
				return doc.getSelection();
			}
			else // IE
			{
				return doc.selection.createRange();
			}
		},
		hasSelection: function()
		{
			if (!this.oldIE())
			{
				var sel;
				return (sel = this.getSelection()) && (sel.focusNode != null) && (sel.anchorNode != null);
			}
			else // IE8
			{
				var node = this.$editor.get(0);

				var range;
				if (!node || !node.document || !node.document.selection)
				{
					return false;
				}

				//node.focus();
				range = node.document.selection.createRange();
				return range && range.parentElement().document === node.document;
			}
		},
		getOrigin: function()
		{
			if (!this.oldIE())
			{
				var sel;
				if (!((sel = this.getSelection()) && (sel.anchorNode != null)))
				{
					return null;
				}

				return [sel.anchorNode, sel.anchorOffset];
			}
			else
			{
				var node = this.$editor.get(0);

				var range;
				if (!this.hasSelection())
				{
					return null;
				}

				node.focus();
				range = node.document.selection.createRange();
				return this._getBoundary(node.document, range, true);
			}
		},
		getFocus: function()
		{
			if (!this.oldIE())
			{
				var sel;
				if (!((sel = this.getSelection()) && (sel.focusNode != null)))
				{
					return null;
				}

				return [sel.focusNode, sel.focusOffset];
			}
			else
			{
				var node = this.$editor.get(0);

				var range;
				if (!this.hasSelection())
				{
					return null;
				}

				node.focus();
				range = node.document.selection.createRange();
				return this._getBoundary(node.document, range, false);

			}
		},

		setSelection: function (orgn, orgo, focn, foco)
		{
			if (focn == null)
			{
				focn = orgn;
			}

			if (foco == null)
			{
				foco = orgo;
			}

			if (!this.oldIE())
			{
				var sel = this.getSelection();
				if (!sel)
				{
					return;
				}

				if (sel.collapse && sel.extend)
				{
					sel.collapse(orgn, orgo);
					sel.extend(focn, foco);
				}
				else // IE9
				{
					var r = this.document.createRange();
					r.setStart(orgn, orgo);
					r.setEnd(focn, foco);

					if (r.collapsed && (orgn != focn || (orgn == focn && orgo != foco)))
					{
						// IE has a situation where it doesn't set the range correctly - this fixes it
						r.setStart(focn, foco);
						r.setEnd(orgn, orgo);
					}

					try
					{
						sel.removeAllRanges();
					}
					catch (e) {}

					sel.addRange(r);
				}
			}
			else
			{
				var node = this.$editor.get(0);
				var range = node.document.body.createTextRange();

				this._moveBoundary(node.document, range, false, focn, foco);
				this._moveBoundary(node.document, range, true, orgn, orgo);
				return range.select();
			}
		},

		selectLast: function(root)
		{
			if (!root)
			{
				root = this.$editor;
			}

			root = $(root).get(0);
			if (!root)
			{
				return;
			}

			var el = root.lastChild;
			if (el)
			{
				while (el.lastChild)
				{
					el = el.lastChild;
				}

				if (el.nodeType == 3)
				{
					this.setSelection(el, el.nodeValue.length, el, el.nodeValue.length);
				}
				else if (el.tagName === 'BR' || el.tagName === 'IMG')
				{
					var p = el.parentNode;
					this.setSelection(p, p.length - 1, p, p.length - 1);
				}
				else
				{
					this.setSelection(el, 0, el, 0);
				}
			}
			else
			{
				this.setSelection(root, 0, root, 0);
			}
		},

		selectFirst: function(root)
		{
			if (!root)
			{
				root = this.$editor;
			}

			root = $(root).get(0);
			if (!root)
			{
				return;
			}

			var el = root.firstChild;
			if (el && el.tagName !== 'BR' && el.tagName !== 'IMG')
			{
				this.setSelection(el, 0, el, 0);
			}
			else
			{
				this.setSelection(root, 0, root, 0);
			}
		},

		// Get elements, html and text
		getCurrentNode: function()
		{
			if (typeof this.window.getSelection !== 'undefined')
			{
				var s = this.getSelectedNode();
				return s ? s.parentNode : false;
			}
			else if (typeof this.document.selection !== 'undefined')
			{
				return this.getSelection().parentElement();
			}
		},
		getParentNode: function()
		{
			return $(this.getCurrentNode()).parent()[0]
		},
		getSelectedNode: function()
		{
			if (this.oldIE())
			{
				return this.getSelection().parentElement();
			}
			else if (typeof this.window.getSelection !== 'undefined')
			{
				var s = this.window.getSelection();
				if (s && s.rangeCount > 0)
				{
					return this.getSelection().getRangeAt(0).commonAncestorContainer;
				}
				else
				{
					return false;
				}
			}
			else if (typeof this.document.selection !== 'undefined')
			{
				return this.getSelection();
			}
		},


		// IE8 specific selection
		_getBoundary: function(doc, textRange, bStart)
		{
			var cursor, cursorNode, node, offset, parent, inserted = false;

			cursorNode = doc.createElement('a');
			cursor = textRange.duplicate();
			cursor.collapse(bStart);
			parent = cursor.parentElement();
			while (true)
			{
				parent.insertBefore(cursorNode, inserted ? cursorNode.previousSibling : null);
				inserted = true;
				cursor.moveToElementText(cursorNode);
				if (!(cursor.compareEndPoints((bStart ? 'StartToStart' : 'StartToEnd'), textRange) > 0 && (cursorNode.previousSibling != null)))
				{
					break;
				}
			}

			if (cursor.compareEndPoints((bStart ? 'StartToStart' : 'StartToEnd'), textRange) === -1 && cursorNode.nextSibling)
			{
				cursor.setEndPoint((bStart ? 'EndToStart' : 'EndToEnd'), textRange);
				node = cursorNode.nextSibling;
				offset = cursor.text.length;
			}
			else
			{
				node = cursorNode.parentNode;
				offset = this._getChildIndex(cursorNode);
			}

			cursorNode.parentNode.removeChild(cursorNode);
			return [node, offset];
		},
		_moveBoundary: function(doc, textRange, bStart, node, offset)
		{
			var anchorNode, anchorParent, cursor, cursorNode, textOffset;

			textOffset = 0;
			anchorNode = this._isText(node) ? node : node.childNodes[offset];
			anchorParent = this._isText(node) ? node.parentNode : node;

			if (this._isText(node))
			{
				textOffset = offset;
			}

			cursorNode = doc.createElement('a');
			anchorParent.insertBefore(cursorNode, anchorNode || null);
			cursor = doc.body.createTextRange();
			cursor.moveToElementText(cursorNode);
			cursorNode.parentNode.removeChild(cursorNode);

			textRange.setEndPoint((bStart ? 'StartToStart' : 'EndToEnd'), cursor);
			return textRange[bStart ? 'moveStart' : 'moveEnd']('character', textOffset);
		},
		_isText: function (d)
		{
			return (d != null ? d.nodeType == 3 : false);
		},
		_getChildIndex: function (e)
		{
			var k = 0;
			while (e = e.previousSibling) {
				k++;
			}
			return k;
		},

		insertNodeAfterCaret: function(node)
		{
			this.saveSelection();
		    this.insertNodeAtCaret(node);
			this.restoreSelection();
		},

		insertNodeAtCaret: function(node)
		{
			if (this.window.getSelection)
			{
				var sel = this.getSelection();
				if (sel.rangeCount)
				{
					var range = sel.getRangeAt(0);
					range.collapse(false);
					range.insertNode(node);
					range = range.cloneRange();
					range.selectNodeContents(node);
					range.collapse(false);
					sel.removeAllRanges();
					sel.addRange(range);
				}
			}
			else if (this.document.selection)
			{
				var html = (node.nodeType === 1) ? node.outerHTML : node.data;
				var id = "marker_" + ("" + Math.random()).slice(2);
				html += '<span id="' + id + '"></span>';
				var textRange = this.getSelection();
				textRange.collapse(false);
				textRange.pasteHTML(html);
				var markerSpan = this.document.getElementById(id);
				textRange.moveToElementText(markerSpan);
				textRange.select();
				markerSpan.parentNode.removeChild(markerSpan);
			}
		},
		getSelectedHtml: function()
		{
			var html = '';
			if (this.window.getSelection)
			{
				var sel = this.window.getSelection();
				if (sel.rangeCount)
				{
					var container = this.document.createElement("div");
					for (var i = 0, len = sel.rangeCount; i < len; ++i)
					{
						container.appendChild(sel.getRangeAt(i).cloneContents());
					}

					html = container.innerHTML;

				}
			}
			else if (this.document.selection)
			{
				if (this.document.selection.type === "Text")
				{
					html = this.document.selection.createRange().htmlText;
				}
			}

			return html;
		},

		analyzeSelection: function()
		{
			var origin = this.getOrigin(),
				focus = this.getFocus(),
				isCollapsed = (focus[0] == origin[0] && focus[1] == origin[1]);

			if ($(origin[0]).is('.redactor_editor, body'))
			{
				origin[0] = origin[0].childNodes[origin[1]];
				origin[1] = 0;
			}

			if ($(focus[0]).is('.redactor_editor, body'))
			{
				focus[0] = focus[0].childNodes[focus[1] ? focus[1] - 1 : 0];
				focus[1] = focus[0].nodeType == 3 ? focus[0].nodeValue.length : focus[0].childNodes.length;
			}

			var $originParents = $(origin[0]).parentsUntil('.redactor_editor, body, html'),
				$focusParents = $(focus[0]).parentsUntil('.redactor_editor, body, html'),
				$commonAncestor,
				commonFocusDepth,
				commonOriginDepth;

			$focusParents.each(function(i) {
				var f = this;

				$originParents.each(function(j) {
					if (this === f)
					{
						$commonAncestor = $(this);
						commonOriginDepth = j;
						return false;
					}
				});

				if ($commonAncestor)
				{
					commonFocusDepth = i;
					return false;
				}
			});

			if (!$commonAncestor)
			{
				$commonAncestor = this.$editor;
				commonOriginDepth = $originParents.length;
				commonFocusDepth = $focusParents.length;
			}

			var originCheck = commonOriginDepth > 0 ? $originParents.get(commonOriginDepth - 1) : origin[0],
				focusCheck = commonFocusDepth > 0 ? $focusParents.get(commonFocusDepth - 1) : focus[0],
				selectedEls = [],
				foundOrigin = false,
				flipOriginFocus = function() {
					var temp;

					temp = origin; origin = focus; focus = temp;
					temp = $originParents; $originParents = $focusParents; $focusParents = temp;
					temp = commonOriginDepth; commonOriginDepth = commonFocusDepth; commonFocusDepth = temp;
					temp = originCheck; originCheck = focusCheck; focusCheck = temp;
				};

			// we need to make sure that the origin actually comes before the focus
			if (focus[0] == origin[0])
			{
				if (origin[1] > focus[1])
				{
					flipOriginFocus();
				}
			}
			else
			{
				var getSelectionPositionPath = function(el, offset)
				{
					var path = [], test;

					if (el.nodeType == 1)
					{
						path.push([el, offset]);
					}

					while (el && el != $commonAncestor[0])
					{
						offset = 0;
						test = el;
						while (test = test.previousSibling)
						{
							offset++;
						}
						path.push([el.parentNode, offset]);

						el = el.parentNode;
					}

					return path.reverse();
				};

				var originPath = getSelectionPositionPath(origin[0], origin[1]),
					focusPath = getSelectionPositionPath(focus[0], focus[1]);

				for (var i = 0; originPath[i] && focusPath[i]; i++)
				{
					if (originPath[i][0] != focusPath[i][0])
					{
						console.error('unexpected difference in selection path roots');
						break;
					}
					else if (originPath[i][1] < focusPath[i][1])
					{
						// right order
						break;
					}
					else if (originPath[i][1] > focusPath[i][1])
					{
						// wrong order
						flipOriginFocus();
						break;
					}
					// else continue with the next level
				}
			}

			var getMaxSelectable = function(el)
			{
				while (el.parentNode.childNodes.length == 1)
				{
					el = el.parentNode;
				}

				return el;
			};

			$.each($commonAncestor.get(0).childNodes, function(i) {
				if (this == focusCheck)
				{
					return false;
				}

				if (this == originCheck)
				{
					foundOrigin = true;
				}
				else if (foundOrigin)
				{
					selectedEls.push(getMaxSelectable(this));
				}
			});

			if (origin[0].parentNode != focus[0].parentNode)
			{
				var addSelected = function(el, stopOn, navKey, endFenceEl)
				{
					var endEls = $(endFenceEl).parentsUntil('.redactor_editor, body, html').toArray();
					endEls.push(endFenceEl);
					endEls.push(stopOn);

					var nextEl,
						end = false;

					while (el && !$(el).is(endEls))
					{
						while (el[navKey])
						{
							nextEl = el[navKey];
							if ($(nextEl).is(endEls))
							{
								end = true;
								break;
							}

							selectedEls.push(getMaxSelectable(nextEl));
							el = nextEl;
						}

						if (end)
						{
							break;
						}

						el = el.parentNode;
					}
				};

				if (commonOriginDepth && commonFocusDepth)
				{
					addSelected(origin[0], originCheck, 'nextSibling', focus[0]);
					addSelected(focus[0], focusCheck, 'previousSibling', origin[0]);
				}
			}

			/*console.log("Ancestor: %o", $commonAncestor[0]);
			console.log("Origin: %o", origin);
			console.log("Origin parents: %o", $originParents.toArray());
			console.log("Origin check: %o (depth: %i)", originCheck, commonOriginDepth);
			console.log("Focus: %o", focus);
			console.log("Focus parents: %o", $focusParents.toArray());
			console.log("Focus check: %o (depth: %i)", focusCheck, commonFocusDepth);
			console.log("Fully selected: %o", selectedEls);*/

			var selStart,
				selStartOffset = null,
				selEnd,
				selEndOffset = null;

			if (origin[0] == focus[0])
			{
				if (origin[0].nodeType == 3)
				{
					if (origin[1] == 0 && focus[1] >= origin[0].nodeValue.length)
					{
						// whole thing is selected
						selStart = selEnd = getMaxSelectable(origin[0]);
						selectedEls.push(selStart);
					}
					else
					{
						selStart = origin[0];
						selStartOffset = origin[1];

						selEnd = focus[0];
						selEndOffset = focus[1];
					}
				}
				else if (origin[1] == 0 && origin[0].childNodes.length == focus[1])
				{
					// whole thing is selected
					selStart = selEnd = getMaxSelectable(origin[0]);
					selectedEls.push(selStart);
				}
				else if (origin[1] == 0 && focus[1] == 0)
				{
					origin[0].insertBefore(origin[0].ownerDocument.createTextNode(''), origin[0].firstChild);
					selStart = selEnd = origin[0].childNodes[0];
					selectedEls.push(selStart);
				}
				else
				{
					$.each(origin[0].childNodes, function(i) {
						if (i >= focus[1])
						{
							// past the end
							return false;
						}
						if (i >= origin[1])
						{
							selectedEls.push(this);
						}
					});

					if (!origin[0].childNodes[origin[1]])
					{
						var tn = origin[0].ownerDocument.createTextNode('');
						origin[0].appendChild(tn);
						selectedEls.push(tn);
						focus[1]++;
					}

					selStart = origin[0].childNodes[origin[1]];
					selEnd = focus[0].childNodes[focus[1] ? focus[1] - 1 : 0];
				}
			}
			else
			{
				if (origin[0].nodeType == 3)
				{
					if (origin[1] > 0)
					{
						selStart = origin[0];
						selStartOffset = origin[1];
					}
					else
					{
						// whole thing is selected
						selStart = getMaxSelectable(origin[0]);
						selectedEls.push(selStart);
					}
				}
				else
				{
					var focusRelated = $focusParents.toArray();
					focusRelated.push(focus[0]);
					$.each(origin[0].childNodes, function(i) {
						if ($(this).is(focusRelated))
						{
							return false;
						}
						if (i >= origin[1])
						{
							selectedEls.push(this);
						}
					});

					selStart = origin[0].childNodes[origin[1]];
					if (!selStart)
					{
						selStart = origin[0].nextSibling;
					}
				}

				if (focus[0].nodeType == 3)
				{
					if (focus[1] < focus[0].nodeValue.length)
					{
						selEnd = focus[0];
						selEndOffset = focus[1];
					}
					else
					{
						// whole thing is selected
						selEnd = getMaxSelectable(focus[0]);
						selectedEls.push(selEnd);
					}
				}
				else
				{
					var originRelated = $originParents.toArray(),
						pastOrigin = false;
					originRelated.push(origin[0]);
					$.each(focus[0].childNodes, function(i) {
						if ($(this).is(originRelated))
						{
							pastOrigin = true;
							return;
						}

						if (pastOrigin && i < focus[1])
						{
							selectedEls.push(this);
						}
					});

					selEnd = focus[0].childNodes[focus[1] - 1];
					if (!selEnd)
					{
						selEnd = focus[0].previousSibling;
					}
				}
			}

			var selInfo = {
				origin: origin,
				focus: focus,
				$commonAncestor: $commonAncestor,
				isCollapsed: isCollapsed,
				selectedEls: selectedEls
			};

			var self = this,
				$selStartIns,
				$selEndIns;

			/*console.log("selStart: %o (offset: %o)", selStart, selStartOffset);
			console.log("selEnd: %o (offset: %o)", selEnd, selEndOffset);
			console.log("selectedEls: %o (count: %d)", selectedEls, selectedEls.length);*/

			selInfo.selectPartials = function(wrapEl)
			{
				if (selStart == selEnd && selStartOffset !== null)
				{
					// selected part of the same node
					selStart = selEnd = self.splitTextNode(
						selStart, wrapEl, selStartOffset, selEndOffset
					);
					selStartOffset = null;
					selEndOffset = null;

					selInfo.selectedEls.push(selStart);
				}
				else
				{
					if (selStartOffset !== null)
					{
						selStart = self.splitTextNode(selStart, wrapEl, selStartOffset);
						selStartOffset = null;

						selInfo.selectedEls.push(selStart);
					}

					if (selEndOffset !== null)
					{
						selEnd = self.splitTextNode(selEnd, wrapEl, 0, selEndOffset);
						selEndOffset = null;

						selInfo.selectedEls.push(selEnd);
					}
				}

				return selInfo;
			};

			selInfo.bookmarkSelection = function()
			{
				selInfo.selectPartials();
				selInfo.removeBookmarks();

				var setChildElementsSelected = function(el)
				{
					var newSelectedEls = [],
						elFound = false;
					for (var i = 0; i < selInfo.selectedEls.length; i++)
					{
						if (selInfo.selectedEls[i] == el)
						{
							elFound = true;
						}
						else
						{
							newSelectedEls.push(selInfo.selectedEls[i]);
						}
					}
					if (elFound)
					{
						var childEl = el.firstChild;
						while (childEl)
						{
							newSelectedEls.push(childEl);
							childEl = childEl.nextSibling;
						}

						selInfo.selectedEls = newSelectedEls;
					}
				};

				$selStartIns = $('<ins class="start"></ins>');
				if ($(selStart).is('.redactor_editor, body, li'))
				{
					setChildElementsSelected(selStart);
					$selStartIns.prependTo(selStart);
				}
				else
				{
					$selStartIns.insertBefore(selStart);
				}

				$selEndIns = $('<ins class="end"></ins>');
				if ($(selEnd).is('.redactor_editor, body, li'))
				{
					setChildElementsSelected(selEnd);
					$selEndIns.appendTo(selEnd);
				}
				else
				{
					$selEndIns.insertAfter(selEnd);
				}

				return [$selStartIns, $selEndIns];
			};

			selInfo.restoreBookmark = function()
			{
				if ($selStartIns && $selEndIns)
				{
					self.setSelection($selStartIns.get(0), 0, $selEndIns.get(0), 0);
				}
				selInfo.removeBookmarks();
			};

			selInfo.removeBookmarks = function()
			{
				if ($selStartIns)
				{
					$selStartIns.remove();
				}
				if ($selEndIns)
				{
					$selEndIns.remove();
				}
			};

			return selInfo;
		},

		splitTextNode: function(node, wrapEl, offsetStart, offsetEnd)
		{
			var content = node.nodeValue;

			if (typeof offsetEnd == 'undefined' || offsetEnd === null)
			{
				offsetEnd = content.length;
			}

			offsetStart = offsetStart || 0;

			if (offsetStart > offsetEnd)
			{
				var t = offsetStart;
				offsetStart = offsetEnd;
				offsetEnd = t;
			}

			var startText = content.substr(0, offsetStart),
				selectedText = content.substr(offsetStart, offsetEnd - offsetStart),
				afterText = content.substr(offsetEnd),
				$tempEl = wrapEl
					? $(wrapEl).text(selectedText)
					: $(node.ownerDocument.createTextNode(selectedText));

			if (startText.length && afterText.length)
			{
				node.nodeValue = startText;
				$tempEl = $tempEl.insertAfter(node);
				$tempEl.after(node.ownerDocument.createTextNode(afterText));
			}
			else if (startText.length)
			{
				node.nodeValue = startText;
				$tempEl = $tempEl.insertAfter(node);
			}
			else if (afterText.length)
			{
				node.nodeValue = afterText;
				$tempEl = $tempEl.insertBefore(node);
			}
			else
			{
				return node;
			}

			return $tempEl.get(0);
		},

		// RESIZE IMAGES
		resizeImage: function(resize)
		{
			var clicked = false;
			var clicker = false;
			var start_x;
			var start_y;
			var ratio = $(resize).width()/$(resize).height();
			var min_w = 10;
			var min_h = 10;

			$(resize).off('hover mousedown mouseup click mousemove');
 			$(resize).hover(function() { $(resize).css('cursor', 'nw-resize'); }, function() { $(resize).css('cursor',''); clicked = false; });

			$(resize).mousedown(function(e)
			{
				e.preventDefault();

				ratio = $(resize).width()/$(resize).height();

				clicked = true;
				clicker = true;

				start_x = Math.round(e.pageX - $(resize).eq(0).offset().left);
				start_y = Math.round(e.pageY - $(resize).eq(0).offset().top);
			});

			$(resize).mouseup($.proxy(function(e)
			{
				clicked = false;
				$(resize).css('cursor','');
				this.syncCode();

			}, this));

			$(resize).click($.proxy(function(e)
			{
				if (clicker)
				{
					this.imageEdit(e);
				}

			}, this));

			$(resize).mousemove(function(e)
			{
				if (clicked)
				{
					clicker = false;

					var mouse_x = Math.round(e.pageX - $(this).eq(0).offset().left) - start_x;
					var mouse_y = Math.round(e.pageY - $(this).eq(0).offset().top) - start_y;

					var div_h = $(resize).height();

					var new_h = parseInt(div_h, 10) + mouse_y;
					var new_w = new_h*ratio;

					if (new_w > min_w)
					{
						$(resize).width(new_w);
					}

					if (new_h > min_h)
					{
						$(resize).height(new_h);
					}

					start_x = Math.round(e.pageX - $(this).eq(0).offset().left);
					start_y = Math.round(e.pageY - $(this).eq(0).offset().top);
				}
			});
		},

		// TABLE
		showTable: function()
		{
			this.saveSelection();

			this.modalInit(RLANG.table, this.opts.modal_table, 300, $.proxy(function()
				{
					$('#redactor_insert_table_btn').click($.proxy(this.insertTable, this));

					setTimeout(function()
					{
						$('#redactor_table_rows').focus();
					}, 200);

				}, this)
			);
		},
		insertTable: function()
		{
			var rows = $('#redactor_table_rows').val();
			var columns = $('#redactor_table_columns').val();

			var table_box = $('<div></div>');

			var tableid = Math.floor(Math.random() * 99999);
			var table = $('<table id="table' + tableid + '"><tbody></tbody></table>');

			for (var i = 0; i < rows; i++)
			{
				var row = $('<tr></tr>');
				for (var z = 0; z < columns; z++)
				{
					var column = $('<td><br></td>');
					$(row).append(column);
				}
				$(table).append(row);
			}

			$(table_box).append(table);
			var html = $(table_box).html() + '<p></p>';

			this.restoreSelection();
			this.execCommand('inserthtml', html);
			this.modalClose();
			this.observeTables();

		},
		tableObserver: function(e)
		{
			this.$table = $(e.target).closest('table');

			this.$table_tr = this.$table.find('tr');
			this.$table_td = this.$table.find('td');

			this.$tbody = $(e.target).closest('tbody');
			this.$thead = $(this.$table).find('thead');

			this.$current_td = $(e.target);
			this.$current_tr = $(e.target).closest('tr');
		},
		deleteTable: function()
		{
			$(this.$table).remove();
			this.$table = false;
			this.syncCode();
		},
		deleteRow: function()
		{
			$(this.$current_tr).remove();
			this.syncCode();
		},
		deleteColumn: function()
		{
			var index = $(this.$current_td).get(0).cellIndex;

			$(this.$table).find('tr').each(function()
			{
				$(this).find('td').eq(index).remove();
			});

			this.syncCode();
		},
		addHead: function()
		{
			if ($(this.$table).find('thead').size() !== 0)
			{
				this.deleteHead();
			}
			else
			{
				var tr = $(this.$table).find('tr').first().clone();
				tr.find('td').html('&nbsp;');
				this.$thead = $('<thead></thead>');
				this.$thead.append(tr);
				$(this.$table).prepend(this.$thead);
				this.syncCode();
			}
		},
		deleteHead: function()
		{
			$(this.$thead).remove();
			this.$thead = false;
			this.syncCode();
		},
		insertRowAbove: function()
		{
			this.insertRow('before');
		},
		insertRowBelow: function()
		{
			this.insertRow('after');
		},
		insertColumnLeft: function()
		{
			this.insertColumn('before');
		},
		insertColumnRight: function()
		{
			this.insertColumn('after');
		},
		insertRow: function(type)
		{
			var new_tr = $(this.$current_tr).clone();
			new_tr.find('td').html('&nbsp;');
			if (type === 'after')
			{
				$(this.$current_tr).after(new_tr);
			}
			else
			{
				$(this.$current_tr).before(new_tr);
			}

			this.syncCode();
		},
		insertColumn: function(type)
		{
			var index = 0;

			this.$current_tr.find('td').each($.proxy(function(i,s)
			{
				if ($(s)[0] === this.$current_td[0])
				{
					index = i;
				}
			}, this));

			this.$table_tr.each(function(i,s)
			{
				var current = $(s).find('td').eq(index);

				var td = current.clone();
				td.html('&nbsp;');

				if (type === 'after')
				{
					$(current).after(td);
				}
				else
				{
					$(current).before(td);
				}

			});

			this.syncCode();
		},

		// INSERT VIDEO
		showVideo: function()
		{
			this.saveSelection();
			this.modalInit(RLANG.video, this.opts.modal_video, 600, $.proxy(function()
				{
					$('#redactor_insert_video_btn').click($.proxy(this.insertVideo, this));

					setTimeout(function()
					{
						$('#redactor_insert_video_area').focus();
					}, 200);

				}, this)
			);
		},
		insertVideo: function()
		{
			var data = $('#redactor_insert_video_area').val();
			data = this.stripTags(data);

			this.restoreSelection();
			this.execCommand('inserthtml', data);
			this.modalClose();
		},

		// INSERT IMAGE
		imageEdit: function(e)
		{
			var $el = $(e.target);
			var parent = $el.parent();

			var callback = $.proxy(function()
			{
				$('#redactor_file_alt').val($el.attr('alt'));
				$('#redactor_image_edit_src').attr('href', $el.attr('src'));
				$('#redactor_form_image_align').val($el.css('float'));

				if ($(parent).get(0).tagName === 'A')
				{
					$('#redactor_file_link').val($(parent).attr('href'));
				}

				$('#redactor_image_delete_btn').click($.proxy(function() { this.imageDelete($el); }, this));
				$('#redactorSaveBtn').click($.proxy(function() { this.imageSave($el); }, this));

			}, this);

			this.modalInit(RLANG.image, this.opts.modal_image_edit, 380, callback);

		},
		imageDelete: function(el)
		{
			var parent = $(el).parent();
			if (parent.size() != 0 && parent[0].tagName == 'A')
			{
				// image's link remove
				parent.remove();
			}
			else
			{
				$(el).remove();
			}

			this.modalClose();
			this.syncCode();
		},
		imageSave: function(el)
		{
			var parent = $(el).parent();

			$(el).attr('alt', $('#redactor_file_alt').val());

			var floating = $('#redactor_form_image_align').val();

			if (floating === 'left')
			{
				$(el).css({ 'float': 'left', margin: '0 10px 10px 0' });
			}
			else if (floating === 'right')
			{
				$(el).css({ 'float': 'right', margin: '0 0 10px 10px' });
			}
			else
			{
				$(el).css({ 'float': 'none', margin: '0' });
			}

			// as link
			var link = $.trim($('#redactor_file_link').val());
			if (link !== '')
			{
				if ($(parent).get(0).tagName !== 'A')
				{
					$(el).replaceWith('<a href="' + link + '">' + this.outerHTML(el) + '</a>');
				}
				else
				{
					$(parent).attr('href', link);
				}
			}
			else
			{
				if ($(parent).get(0).tagName === 'A')
				{
					$(parent).replaceWith(this.outerHTML(el));
				}
			}

			this.modalClose();
			this.observeImages();
			this.syncCode();

		},
		showImage: function()
		{
			this.saveSelection();

			var callback = $.proxy(function()
			{
				// json
				if (this.opts.imageGetJson !== false)
				{
					$.getJSON(this.opts.imageGetJson, $.proxy(function(data) {

						var folders = {};
						var z = 0;

						// folders
						$.each(data, $.proxy(function(key, val)
						{
							if (typeof val.folder !== 'undefined')
							{
								z++;
								folders[val.folder] = z;
							}

						}, this));

						var folderclass = false;
						$.each(data, $.proxy(function(key, val)
						{
							// title
							var thumbtitle = '';
							if (typeof val.title !== 'undefined')
							{
								thumbtitle = val.title;
							}

							var folderkey = 0;
							if (!$.isEmptyObject(folders) && typeof val.folder !== 'undefined')
							{
								folderkey = folders[val.folder];
								if (folderclass === false)
								{
									folderclass = '.redactorfolder' + folderkey;
								}
							}

							var img = $('<img src="' + val.thumb + '" class="redactorfolder redactorfolder' + folderkey + '" rel="' + val.image + '" title="' + thumbtitle + '" />');
							$('#redactor_image_box').append(img);
							$(img).click($.proxy(this.imageSetThumb, this));


						}, this));

						// folders
						if (!$.isEmptyObject(folders))
						{
							$('.redactorfolder').hide();
							$(folderclass).show();

							var onchangeFunc = function(e)
							{
								$('.redactorfolder').hide();
								$('.redactorfolder' + $(e.target).val()).show();
							}

							var select = $('<select id="redactor_image_box_select">');
							$.each(folders, function(k,v)
							{
								select.append($('<option value="' + v + '">' + k + '</option>'));
							});

							$('#redactor_image_box').before(select);
							select.change(onchangeFunc);
						}

					}, this));
				}
				else
				{
					$('#redactor_tabs a').eq(1).remove();
				}

				if (this.opts.imageUpload !== false)
				{

					// dragupload
					if (this.opts.uploadCrossDomain === false && this.isMobile() === false)
					{

						if ($('#redactor_file').size() !== 0)
						{
							$('#redactor_file').dragupload(
							{
								url: this.opts.imageUpload,
								uploadFields: this.opts.uploadFields,
								success: $.proxy(this.imageUploadCallback, this),
								error: $.proxy(this.opts.imageUploadErrorCallback, this)
							});
						}
					}

					// ajax upload
					this.uploadInit('redactor_file',
					{
						auto: true,
						url: this.opts.imageUpload,
						success: $.proxy(this.imageUploadCallback, this),
						error: $.proxy(this.opts.imageUploadErrorCallback, this)
					});
				}
				else
				{
					$('.redactor_tab').hide();
					if (this.opts.imageGetJson === false)
					{
						$('#redactor_tabs').remove();
						$('#redactor_tab3').show();
					}
					else
					{
						var tabs = $('#redactor_tabs a');
						tabs.eq(0).remove();
						tabs.eq(1).addClass('redactor_tabs_act');
						$('#redactor_tab2').show();
					}
				}

				$('#redactor_upload_btn').click($.proxy(this.imageUploadCallbackLink, this));

				if (this.opts.imageUpload === false && this.opts.imageGetJson === false)
				{
					setTimeout(function()
					{
						$('#redactor_file_link').focus();
					}, 200);

				}

			}, this);

			this.modalInit(RLANG.image, this.opts.modal_image, 600, callback);

		},
		imageSetThumb: function(e)
		{
			this._imageSet('<img src="' + $(e.target).attr('rel') + '" alt="' + $(e.target).attr('title') + '" />&nbsp;', true);
		},
		imageUploadCallbackLink: function()
		{
			if ($('#redactor_file_link').val() !== '')
			{
				var data = '<img src="' + $('#redactor_file_link').val() + '" />&nbsp;';
				this._imageSet(data, true);
			}
			else
			{
				this.modalClose();
			}
		},
		imageUploadCallback: function(data)
		{
			this._imageSet(data);
		},
		_imageSet: function(json, link)
		{
			this.restoreSelection();

			if (json !== false)
			{
				var html = '';
				if (link !== true)
				{
					html = '<p><img src="' + json.filelink + '" /></p>';
				}
				else
				{
					html = json;
				}

				this.execCommand('inserthtml', html);

				// upload image callback
				if (link !== true && typeof this.opts.imageUploadCallback === 'function')
				{
					this.opts.imageUploadCallback(this, json);
				}
			}

			this.modalClose();
			this.observeImages();
		},

		// INSERT LINK
		showLink: function()
		{
			this.saveSelection();

			var callback = $.proxy(function()
			{
				this.insert_link_node = false;
				var sel = this.getSelection();
				var url = '', text = '', target = '';

				if (this.browser('msie'))
				{
					var parent = this.getParentNode();
					if (parent.nodeName === 'A')
					{
						this.insert_link_node = $(parent);
						text = this.insert_link_node.text();
						url = this.insert_link_node.attr('href');
						target = this.insert_link_node.attr('target');
					}
					else
					{
						if (this.oldIE())
						{
							text = sel.text;
						}
						else
						{
							text = sel.toString();
						}
					}
				}
				else
				{
					if (sel && sel.anchorNode && sel.anchorNode.parentNode.tagName === 'A')
					{
						url = sel.anchorNode.parentNode.href;
						text = sel.anchorNode.parentNode.text;
						target = sel.anchorNode.parentNode.target;

						if (sel.toString() === '')
						{
							this.insert_link_node = sel.anchorNode.parentNode;
						}
					}
					else
					{
						text = sel.toString();
					}
				}

				$('#redactor_link_url_text').val(text);

				var thref = self.location.href.replace(/\/$/i, '');
				var turl = url.replace(thref, '');

				if (url.search('mailto:') === 0)
				{
					this.setModalTab(2);

					$('#redactor_tab_selected').val(2);
					$('#redactor_link_mailto').val(url.replace('mailto:', ''));
				}
				else if (turl.search(/^#/gi) === 0)
				{
					this.setModalTab(3);

					$('#redactor_tab_selected').val(3);
					$('#redactor_link_anchor').val(turl.replace(/^#/gi, ''));
				}
				else
				{
					$('#redactor_link_url').val(turl);
				}

				if (target === '_blank')
				{
					$('#redactor_link_blank').attr('checked', true);
				}

				$('#redactor_insert_link_btn').click($.proxy(this.insertLink, this));

				setTimeout(function()
				{
					$('#redactor_link_url').focus();
				}, 200);

			}, this);

			this.modalInit(RLANG.link, this.opts.modal_link, 600, callback);

		},
		insertLink: function()
		{
			var tab_selected = $('#redactor_tab_selected').val();
			var link = '', text = '', targettext = '', target = '';

			if (tab_selected === '1') // url
			{
				link = $('#redactor_link_url').val();
				text = $('#redactor_link_url_text').val();
				if (!text.length)
				{
					text = link;
				}

				if ($('#redactor_link_blank').prop('checked'))
				{
					targettext = ' target="_blank"';
					target = '_blank';
				}

				// test url
				if (link.length && !link.match(/^(\w+:)|\//))
				{
					link = this.opts.protocol + link;
				}

			}
			else if (tab_selected === '2') // mailto
			{
				link = 'mailto:' + $('#redactor_link_mailto').val();
				text = $('#redactor_link_mailto_text').val();
				if (!text.length)
				{
					text = $('#redactor_link_mailto').val();
				}
			}
			else if (tab_selected === '3') // anchor
			{
				link = '#' + $('#redactor_link_anchor').val();
				text = $('#redactor_link_anchor_text').val();
			}

			this._insertLink('<a href="' + link + '"' + targettext + '>' +  text + '</a>', $.trim(text), link, target);

		},
		_insertLink: function(a, text, link, target)
		{
			this.focus();
			this.restoreSelection();

			if (text !== '' && link !== '')
			{
				if (this.insert_link_node)
				{
					$(this.insert_link_node).text(text);
					$(this.insert_link_node).attr('href', link);
					if (target !== '')
					{
						$(this.insert_link_node).attr('target', target);
					}
					else
					{
						$(this.insert_link_node).removeAttr('target');
					}

					this.syncCode();
				}
				else
				{
					this.execCommand('inserthtml', a);
				}
			}

			this.modalClose();
		},

		// INSERT FILE
		showFile: function()
		{
			this.saveSelection();

			var callback = $.proxy(function()
			{
				var sel = this.getSelection();

				var text = '';

				if (this.oldIE())
				{
					text = sel.text;
				}
				else
				{
					text = sel.toString();
				}

				$('#redactor_filename').val(text);

				// dragupload
				if (this.opts.uploadCrossDomain === false && this.isMobile() === false)
				{
					$('#redactor_file').dragupload(
					{
						url: this.opts.fileUpload,
						uploadFields: this.opts.uploadFields,
						success: $.proxy(this.fileUploadCallback, this),
						error: $.proxy(this.opts.fileUploadErrorCallback, this)
					});
				}

				this.uploadInit('redactor_file',
				{
					auto: true,
					url: this.opts.fileUpload,
					success: $.proxy(this.fileUploadCallback, this),
					error: $.proxy(this.opts.fileUploadErrorCallback, this)
				});

			}, this);

			this.modalInit(RLANG.file, this.opts.modal_file, 600, callback);
		},
		fileUploadCallback: function(json)
		{
			this.restoreSelection();

			if (json !== false)
			{
				var text = $('#redactor_filename').val();

				if (text === '')
				{
					text = json.filename;
				}

				var link = '<a href="' + json.filelink + '">' + text + '</a>';

				// chrome fix
				if (this.browser('webkit') && !!this.window.chrome)
				{
					link = link + '&nbsp;';
				}

				this.execCommand('inserthtml', link);

				// file upload callback
				if (typeof this.opts.fileUploadCallback === 'function')
				{
					this.opts.fileUploadCallback(this, json);
				}
			}

			this.modalClose();
		},



		// MODAL
		modalDeferredInit: function(title, loadUrl, width, callback)
		{
			if (this.deferredModal[loadUrl])
			{
				this.modalInit(title, this.deferredModal[loadUrl], width, callback);
				return;
			}

			if (this.opts.modalLoadCallback)
			{
				this.opts.modalLoadCallback(this, title, loadUrl, width, callback);
			}
			else
			{
				var self = this;
				$.ajax(loadUrl).done(function(html)
				{
					self.deferredModal[loadUrl] = html;
					self.modalInit(title, self.deferredModal[loadUrl], width, callback);
				});
			}
		},

		modalInit: function(title, content, width, callback)
		{
			if (typeof content === 'object' && content.url)
			{
				this.modalDeferredInit(title, content.url, width, callback);
				return;
			}

			this.saveSelection();

			if (!this.restoreAndClose)
			{
				this.restoreAndClose = $.proxy(function() {
					this.restoreSelection();
					this.modalClose();
					return false;
				}, this);
			}

			width = Math.min(width, $(window).width());

			// modal overlay
			if ($('#redactor_modal_overlay').size() === 0)
			{
				this.overlay = $('<div id="redactor_modal_overlay" style="display: none;"></div>');
				$('body').append(this.overlay);
			}

			if (this.opts.overlay)
			{
				$('#redactor_modal_overlay').show();
				$('#redactor_modal_overlay').click(this.restoreAndClose);
			}

			if ($('#redactor_modal').size() === 0)
			{
				this.modal = $('<div id="redactor_modal" style="display: none;"><div id="redactor_modal_close">&times;</div><div id="redactor_modal_header"></div><div id="redactor_modal_inner"></div></div>');
				$('body').append(this.modal);
				if (this.opts.modalCreateCallback)
				{
					this.modal = this.opts.modalCreateCallback(this, this.modal);
				}
			}

			$('#redactor_modal_close').click(this.restoreAndClose);

			this.hdlModalClose = $.proxy(function(e) {
				if ( e.keyCode === 27) {
					this.restoreSelection();
					this.modalClose();
					return false;
				}
			}, this);

			$(document).keyup(this.hdlModalClose);
			this.$editor.keyup(this.hdlModalClose);

			// set content
			if (content.indexOf('#') == 0)
			{
				$('#redactor_modal_inner').empty().append($(content).html());
			}
			else
			{
				$('#redactor_modal_inner').empty().append(content);
			}


			$('#redactor_modal_header').html(title);

			// draggable
			if (typeof $.fn.draggable !== 'undefined')
			{
				$('#redactor_modal').draggable({ handle: '#redactor_modal_header' });
				$('#redactor_modal_header').css('cursor', 'move');
			}

			// tabs
			if ($('#redactor_tabs').size() !== 0)
			{
				var that = this;
				$('#redactor_tabs a').each(function(i,s)
				{
					i++;
					$(s).click(function()
					{
						$('#redactor_tabs a').removeClass('redactor_tabs_act');
						$(this).addClass('redactor_tabs_act');
						$('.redactor_tab').hide();
						$('#redactor_tab' + i).show();
						$('#redactor_tab_selected').val(i);

						if (that.isMobile() === false)
						{
							var height = $('#redactor_modal').outerHeight();
							$('#redactor_modal').css('margin-top', '-' + (height+10)/2 + 'px');
						}
					});
				});
			}

			$('#redactor_modal .redactor_btn_modal_close').click(this.restoreAndClose);

			if (this.isMobile() === false)
			{
				$('#redactor_modal').css({ position: 'fixed', top: '-2000px', left: '50%', width: width + 'px', marginLeft: '-' + width/2 + 'px' }).show();

				this.modalSaveBodyOveflow = $(document.body).css('overflow');
				//$(document.body).css('overflow', 'hidden');
			}
			else
			{
				$('#redactor_modal').css({ position: 'fixed', width: '100%', height: '100%', top: '0', left: '0', margin: '0', minHeight: '300px' }).show();
			}

			// callback
			if (typeof callback === 'function')
			{
				callback();
			}

			/*setTimeout(function() {
				$('#redactor_modal').find('input[type=text], textarea').first().focus();
			}, 50);*/

			if (this.isMobile() === false)
			{
				setTimeout(function()
				{
					var height = $('#redactor_modal').outerHeight();
					$('#redactor_modal').css({ top: '50%', height: 'auto', minHeight: 'auto', marginTop: '-' + (height+10)/2 + 'px' });

				}, 20);
			}

		},
		modalClose: function()
		{
			$('#redactor_modal_close').unbind('click', this.restoreAndClose);
			$('#redactor_modal').fadeOut('fast', $.proxy(function()
			{
				$('#redactor_modal_inner').html('');

				if (this.opts.overlay)
				{
					$('#redactor_modal_overlay').hide();
					$('#redactor_modal_overlay').unbind('click', this.restoreAndClose);
				}

				$(document).unbind('keyup', this.hdlModalClose);
				this.$editor.unbind('keyup', this.hdlModalClose);

			}, this));


			if (this.isMobile() === false)
			{
				$(document.body).css('overflow', this.modalSaveBodyOveflow ? this.modalSaveBodyOveflow : 'visible');
			}

			this.focus();

			return false;

		},
		setModalTab: function(num)
		{
			$('.redactor_tab').hide();
			var tabs = $('#redactor_tabs a');
			tabs.removeClass('redactor_tabs_act');
			tabs.eq(num-1).addClass('redactor_tabs_act');
			$('#redactor_tab' + num).show();
		},

		// UPLOAD
		uploadInit: function(element, options)
		{
			// Upload Options
			this.uploadOptions = {
				url: false,
				success: false,
				error: false,
				start: false,
				trigger: false,
				auto: false,
				input: false
			};

			$.extend(this.uploadOptions, options);

			// Test input or form
			if ($('#' + element).size() !== 0 && $('#' + element).get(0).tagName === 'INPUT')
			{
				this.uploadOptions.input = $('#' + element);
				this.element = $($('#' + element).get(0).form);
			}
			else
			{
				this.element = $('#' + element);
			}

			this.element_action = this.element.attr('action');

			// Auto or trigger
			if (this.uploadOptions.auto)
			{
				$(this.uploadOptions.input).change($.proxy(function()
				{
					this.element.submit(function(e) { return false; });
					this.uploadSubmit();
				}, this));

			}
			else if (this.uploadOptions.trigger)
			{
				$('#' + this.uploadOptions.trigger).click($.proxy(this.uploadSubmit, this));
			}
		},
		uploadSubmit : function()
		{
			this.uploadForm(this.element, this.uploadFrame());
		},
		uploadFrame : function()
		{
			this.id = 'f' + Math.floor(Math.random() * 99999);

			var d = this.document.createElement('div');
			var iframe = '<iframe style="display:none" id="'+this.id+'" name="'+this.id+'"></iframe>';
			d.innerHTML = iframe;
			$(d).appendTo("body");

			// Start
			if (this.uploadOptions.start)
			{
				this.uploadOptions.start();
			}

			$('#' + this.id).load($.proxy(this.uploadLoaded, this));

			return this.id;
		},
		uploadForm : function(f, name)
		{
			if (this.uploadOptions.input)
			{
				var formId = 'redactorUploadForm' + this.id;
				var fileId = 'redactorUploadFile' + this.id;
				this.form = $('<form  action="' + this.uploadOptions.url + '" method="POST" target="' + name + '" name="' + formId + '" id="' + formId + '" enctype="multipart/form-data"></form>');

				// append hidden fields
				if (this.opts.uploadFields !== false && typeof this.opts.uploadFields === 'object')
				{
					$.each(this.opts.uploadFields, $.proxy(function(k,v)
					{
						if (v.toString().indexOf('#') === 0)
						{
							v = $(v).val();
						}

						var hidden = $('<input/>', {'type': "hidden", 'name': k, 'value': v});
						$(this.form).append(hidden);

					}, this));
				}

				var oldElement = this.uploadOptions.input;
				var newElement = $(oldElement).clone();
				$(oldElement).attr('id', fileId);
				$(oldElement).before(newElement);
				$(oldElement).appendTo(this.form);
				$(this.form).css('position', 'absolute');
				$(this.form).css('top', '-2000px');
				$(this.form).css('left', '-2000px');
				$(this.form).appendTo('body');

				this.form.submit();
			}
			else
			{
				f.attr('target', name);
				f.attr('method', 'POST');
				f.attr('enctype', 'multipart/form-data');
				f.attr('action', this.uploadOptions.url);

				this.element.submit();
			}

		},
		uploadLoaded : function()
		{
			var i = $('#' + this.id)[0];
			var d;

			if (i.contentDocument)
			{
				d = i.contentDocument;
			}
			else if (i.contentWindow)
			{
				d = i.contentWindow.document;
			}
			else
			{
				d = window.frames[this.id].document;
			}

			// Success
			if (this.uploadOptions.success)
			{
				if (typeof d !== 'undefined')
				{
					// Remove bizarre <pre> tag wrappers around our json data:
					var rawString = d.body.innerHTML;
					var jsonString = rawString.match(/\{(.|\n)*\}/)[0];
					var json = $.parseJSON(jsonString);

					if (typeof json.error == 'undefined')
					{
						this.uploadOptions.success(json);
					}
					else
					{
						this.uploadOptions.error(this, json);
						this.modalClose();
					}
				}
				else
				{
					alert('Upload failed!');
					this.modalClose();
				}
			}

			this.element.attr('action', this.element_action);
			this.element.attr('target', '');

		},

		// UTILITY
		browser: function(browser)
		{
			var ua = navigator.userAgent.toLowerCase();
			var match = /(chrome)[ \/]([\w.]+)/.exec(ua)
				|| /(webkit)[ \/]([\w.]+)/.exec(ua)
				|| /(opera)(?:.*version|)[ \/]([\w.]+)/.exec(ua)
				|| /(msie) ([\w.]+)/.exec(ua)
				|| /(trident).*rv:([\w.]+)/.exec(ua)
				|| (ua.indexOf("compatible") < 0 && /(mozilla)(?:.*? rv:([\w.]+)|)/.exec(ua))
				|| [];

			if (browser == 'version')
			{
				return match[2];
			}

			if (browser == 'webkit')
			{
				return (match[1] == 'chrome' || match[1] == 'webkit');
			}

			if (browser == 'msie')
			{
				return (match[1] == 'msie' || match[1] == 'trident');
			}

			return match[1] == browser;
		},
		oldIE: function()
		{
			if (this.browser('msie') && parseInt(this.browser('version'), 10) < 9)
			{
				return true;
			}

			return false;
		},
		outerHTML: function(s)
		{
			return $("<p>").append($(s).eq(0).clone()).html();
		},
		normalize: function(str)
		{
			return parseInt(str.replace('px',''), 10);
		},
		isMobile: function(ipad)
		{
			if (ipad === true && /(iPhone|iPod|iPad|BlackBerry|Android)/.test(navigator.userAgent))
			{
				return true;
			}
			else if (/(iPhone|iPod|BlackBerry|Android)/.test(navigator.userAgent))
			{
				return true;
			}
			else
			{
				return false;
			}
		}

	};


	// API
	$.fn.getObject = function()
	{
		return this.data('redactor');
	};

	$.fn.getEditor = function()
	{
		return this.data('redactor').$editor;
	};

	$.fn.getCode = function()
	{
		return $.trim(this.data('redactor').getCode());
	};

	$.fn.getText = function()
	{
		return this.data('redactor').$editor.text();
	};

	$.fn.getSelected = function()
	{
		return this.data('redactor').getSelectedHtml();
	};

	$.fn.setCode = function(html, focus)
	{
		this.data('redactor').setCode(html, focus);
	};

	$.fn.insertHtml = function(html)
	{
		this.data('redactor').insertHtml(html);
	};

	$.fn.destroyEditor = function()
	{
		this.each(function()
		{
			if (typeof $(this).data('redactor') != 'undefined')
			{
				$(this).data('redactor').destroy();
				$(this).removeData('redactor');
			}
		});
	};

	$.fn.setFocus = function(selectLast)
	{
		this.data('redactor').focus(selectLast);
	};

	$.fn.execCommand = function(cmd, param)
	{
		this.data('redactor').execCommand(cmd, param);
	};

})(jQuery);

/*
	Plugin Drag and drop Upload v1.0.2
	http://imperavi.com/
	Copyright 2012, Imperavi Inc.
*/
(function($){

	"use strict";

	// Initialization
	$.fn.dragupload = function(options)
	{
		return this.each(function() {
			var obj = new Construct(this, options);
			obj.init();
		});
	};

	// Options and variables
	function Construct(el, options) {

		this.opts = $.extend({

			url: false,
			success: false,
			error: false,
			preview: false,
			uploadFields: false,

			text: RLANG.drop_file_here,
			atext: RLANG.or_choose

		}, options);

		this.$el = $(el);
	}

	// Functionality
	Construct.prototype = {
		init: function()
		{
			if (navigator.userAgent.search("MSIE") === -1)
			{
				this.droparea = $('<div class="redactor_droparea"></div>');
				this.dropareabox = $('<div class="redactor_dropareabox">' + this.opts.text + '</div>');
				this.dropalternative = $('<div class="redactor_dropalternative">' + this.opts.atext + '</div>');

				this.droparea.append(this.dropareabox);

				this.$el.before(this.droparea);
				this.$el.before(this.dropalternative);

				// drag over
				this.dropareabox.bind('dragover', $.proxy(function() { return this.ondrag(); }, this));

				// drag leave
				this.dropareabox.bind('dragleave', $.proxy(function() { return this.ondragleave(); }, this));

				var uploadProgress = $.proxy(function(e)
				{
					var percent = parseInt(e.loaded / e.total * 100, 10);
					this.dropareabox.text('Loading ' + percent + '%');

				}, this);

				var xhr = jQuery.ajaxSettings.xhr();

				if (xhr.upload)
				{
					xhr.upload.addEventListener('progress', uploadProgress, false);
				}

				var provider = function () { return xhr; };

				// drop
				this.dropareabox.get(0).ondrop = $.proxy(function(event)
				{
					event.preventDefault();

					this.dropareabox.removeClass('hover').addClass('drop');

					var file = event.dataTransfer.files[0];
					var fd = new FormData();

					// append hidden fields
					if (this.opts.uploadFields !== false && typeof this.opts.uploadFields === 'object')
					{
						$.each(this.opts.uploadFields, $.proxy(function(k,v)
						{
							if (v.toString().indexOf('#') === 0)
							{
								v = $(v).val();
							}

							fd.append(k, v);

						}, this));
					}

					// append file data
					fd.append('file', file);

					$.ajax({
						url: this.opts.url,
						dataType: 'html',
						data: fd,
						xhr: provider,
						cache: false,
						contentType: false,
						processData: false,
						type: 'POST',
						success: $.proxy(function(data)
						{
							var json = $.parseJSON(data);

							if (typeof json.error == 'undefined')
							{
								this.opts.success(json);
							}
							else
							{
								this.opts.error(this, json);
								this.opts.success(false);
							}

						}, this)
					});


				}, this);
			}
		},
		ondrag: function()
		{
			this.dropareabox.addClass('hover');
			return false;
		},
		ondragleave: function()
		{
			this.dropareabox.removeClass('hover');
			return false;
		}
	};

})(jQuery);



// Define: Linkify plugin from stackoverflow
(function($){

	"use strict";

	var protocol = 'http://';
	var url1 = /(^|&lt;|\s)(www\..+?\..+?)(\s|&gt;|$|\[|\])/g,
	url2 = /(^|&lt;|\s)(((https?|ftp):\/\/|mailto:).+?)(\s|&gt;|$|\[|\])/g,

		linkifyThis = function ()
		{
			var childNodes = this.childNodes,
			i = childNodes.length;
			while(i--)
			{
				var n = childNodes[i];
				if (n.nodeType === 3)
				{
					var html = n.nodeValue;
					if (html)
					{
						html = html.replace(/&/g, '&amp;')
									.replace(/</g, '&lt;')
									.replace(/>/g, '&gt;')
									.replace(url1, '$1<a href="' + protocol + '$2">$2</a>$3')
									.replace(url2, '$1<a href="$2">$2</a>$5');

						if (html != n.nodeValue)
						{
							$(n).after(html).remove();
						}
					}
				}
				else if (n.nodeType === 1  &&  !/^(a|button|textarea)$/i.test(n.tagName))
				{
					linkifyThis.call(n);
				}
			}
		};

	$.fn.linkify = function ()
	{
		this.each(linkifyThis);
	};

})(jQuery);


/* jQuery plugin textselect
 * version: 0.9
 * author: Josef Moravec
 * updated: Imperavi Inc.
 *
 */
(function ($) {
    $.event.special.textselect =
    {
        setup: function (data, namespaces)
        {

            $(this).data("textselected", false);
            $(this).data("ttt", data);
            $(this).on('mouseup', $.event.special.textselect.handler)
        },
        teardown: function (data)
        {
            $(this).off('mouseup', $.event.special.textselect.handler)
        },
        handler: function (event)
        {
            var data = $(this).data("ttt");
            var text = $.event.special.textselect.getSelectedText(data).toString();

            if (text != '')
            {
                $(this).data("textselected", true);
                event.type = "textselect";
                event.text = text;

                $.event.dispatch.apply(this, arguments)
            }
        },
        getSelectedText: function (data)
        {
            var text = '';
            if (rwindow.getSelection) text = rwindow.getSelection();
            else if (rdocument.getSelection) text = rdocument.getSelection();
            else if (rdocument.selection) text = rdocument.selection.createRange().text;
            return text
        }
    };
    $.event.special.textunselect =
    {
        setup: function (data, namespaces)
        {
            $(this).data("rttt", data);
            $(this).data("textselected", false);
            $(this).on('mouseup', $.event.special.textunselect.handler);
            $(this).on('keyup', $.event.special.textunselect.handlerKey)
        },
        teardown: function (data)
        {
            $(this).unbind('mouseup', $.event.special.textunselect.handler)
        },
        handler: function (event)
        {
            if ($(this).data("textselected"))
            {
                var data = $(this).data("rttt");
                var text = $.event.special.textselect.getSelectedText(data).toString();
                if (text == '')
                {
                    $(this).data("textselected", false);
                    event.type = "textunselect";
                    $.event.dispatch.apply(this, arguments)
                }
            }
        },
        handlerKey: function (event)
        {
            if ($(this).data("textselected"))
            {
                var data = $(this).data("rttt");
                var text = $.event.special.textselect.getSelectedText(data).toString();
                if ((event.keyCode = 27) && (text == ''))
                {
                    $(this).data("textselected", false);
                    event.type = "textunselect";
                    $.event.dispatch.apply(this, arguments)
                }
            }
        }
    }
})(jQuery);