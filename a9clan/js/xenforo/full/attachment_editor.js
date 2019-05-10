/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	// *********************************************************************

	var insertSpeed = XenForo.speed.normal,
		removeSpeed = XenForo.speed.fast;

	XenForo.AttachmentUploader = function($container)
	{
		var $trigger = $($container.data('trigger')),
			$form = $container.closest('form'),

			// SWFUpload-related
			$placeholder = $($container.data('placeholder')),
			postParams = {},
			swfUploader = null,
			swfAlert = null,
			maxFileSize = $container.data('maxfilesize'),
			maxUploads = $container.data('maxuploads'),
			extensions = $container.data('extensions'),
			uniqueKey = $container.data('uniquekey');

		// --------------------------------------

		// un-hide the upload button
		$container.show();

		var flashUrl = XenForo.canonicalizeUrl($container.data('flashurl') || 'js/swfupload/Flash/swfupload.swf');

		console.info('flash url: %s', flashUrl);

		// Attempt to init SWFUpload
		if (typeof SWFUpload == 'function' && !window.navigator.userAgent.match(/Android|iOS|iPhone|iPad|Mobile Safari/i))
		{
			swfUploader = new SWFUpload(
			{
				upload_url: $container.data('action'),

				file_post_name: $container.data('postname'),
				file_types: '*.' + (extensions.toLowerCase() + ',' + extensions.toUpperCase()).replace(/,/g, ';*.'),

				/*file_upload_limit: maxUploads,
				file_quote_limit: maxUploads,*/
				// commented out as the behavior of triggering an error with no uploads is possibly more annoying
				// TODO: different approach: upload up to limit and ignore the rest

				post_params: $.extend(
				{
					_xfToken: XenForo._csrfToken,
					_xfNoRedirect: 1,
					_xfResponseType: 'json'
				}, postParams),

				button_placeholder_id: $placeholder.attr('id'),
				button_width: 1,
				button_height: 1,

				button_window_mode: SWFUpload.WINDOW_MODE.TRANSPARENT,
				button_cursor: SWFUpload.CURSOR.HAND,

				flash_url: flashUrl,
				prevent_swf_caching: false,

				swfupload_loaded_handler: function()
				{
					// give the button its correct dimensions
					this.setButtonDimensions($trigger.outerWidth(), $trigger.outerHeight());
					$(this.movieElement).css('top', $trigger.position().top);

					// add extra post params
					$container.find('.HiddenInput').each(function(i, element)
					{
						swfUploader.addPostParam($(element).data('name'), $(element).data('value'));
					});

					// Keep the CSRF token and the session ID up to date
					$(document).bind('CSRFRefresh', function(e)
					{
						if (e.ajaxData)
						{
							swfUploader.addPostParam('_xfToken', e.ajaxData.csrfToken);
							swfUploader.addPostParam('_xfSessionId', e.ajaxData.sessionId);
						}
					});
				},

				/**
				 * Fires when the file selction dialog is closed
				 *
				 * @param integer Number of files selected
				 * @param integer Number of files queued
				 */
				file_dialog_complete_handler: function(numSelected, numQueued)
				{
					try
					{
						if (this.getStats().files_queued > 0)
						{
							this.startUpload(this.getFile(0).ID);
						}
					}
					catch (exception)
					{
						this.debug(exception);
					}
				},

				/**
				 * Fires when a file is added to the upload queue
				 *
				 * @param SWFUpload.File
				 */
				file_queued_handler: function(file)
				{
					var isImage;

					switch (file.name.substr(file.name.lastIndexOf('.')).toLowerCase())
					{
						case '.jpg':
						case '.jpeg':
						case '.jpe':
						case '.png':
						case '.gif':
						{
							isImage = true;
							break;
						}

						default:
						{
							isImage = false;
						}
					}

					var event = $.Event('AttachmentQueueValidation');
					event.file = file;
					event.swfUpload = this;
					event.isImage = isImage;
					$container.trigger(event);

					if (event.isDefaultPrevented())
					{
						return;
					}

					if (file.size > maxFileSize && !isImage) // allow web images to bypass the file size check, as they (may) be resized on the server
					{
						// non image type, abort the upload with a file-too-large error
						this.cancelUpload(file.id, false);
						if (typeof this.settings.file_queue_error_handler == 'function')
						{
							this.settings.file_queue_error_handler.call(
								this, file, SWFUpload.QUEUE_ERROR.FILE_EXCEEDS_SIZE_LIMIT, 'The uploaded file is too large.'
							);
						}
						return;
					}

					event = $.Event('AttachmentQueued');
					event.file = file;
					event.swfUpload = this;
					event.isImage = isImage;
					$container.trigger(event);
				},

				/**
				 * Fires when a file fails to be queued
				 *
				 * @param SWFUpload.File
				 * @param integer Error code
				 * @param string Message
				 */
				file_queue_error_handler: function(file, errorCode, message)
				{
					var $event = $.Event('AttachmentQueueError');
					$event.file = file;
					$event.errorCode = errorCode;
					$event.message = message;
					$event.swfUpload = this;

					$container.trigger($event);

					if (!$event.isDefaultPrevented())
					{
						swfAlert(file, errorCode, message);
					}
				},

				/**
				 * Fires when a file upload begins
				 *
				 * @param SWFUpload.File
				 */
				upload_start_handler: function(file)
				{
					console.log('Uploading %s', file.name);
				},

				/**
				 * Fires upon receiving a progress update\
				 *
				 * @param SWFUpload.File
				 * @param integer Bytes uploaded so far
				 */
				upload_progress_handler: function(file, bytes)
				{
					$container.trigger(
					{
						type: 'AttachmentUploadProgress',
						file: file,
						bytes: bytes,
						swfUpload: this
					});
				},

				/**
				 * Fires when receiving an upload success code from the server
				 *
				 * @param SWFUpload.File
				 * @param string Server response text
				 * @param string Server response status
				 */
				upload_success_handler: function(file, serverData, status)
				{
					try
					{
						var ajaxData = $.parseJSON(serverData);
					}
					catch (exception)
					{
						console.warn(exception);
						return;
					}

					if (ajaxData.error)
					{
						$container.trigger(
						{
							type: 'AttachmentUploadError',
							file: file,
							ajaxData: ajaxData,
							swfUpload: this
						});
					}
					else
					{
						$container.trigger(
						{
							type: 'AttachmentUploaded',
							file: file,
							ajaxData: ajaxData,
							swfUpload: this
						});
					}
				},

				upload_error_handler: function(file, errorCode, message)
				{
					if (errorCode == SWFUpload.UPLOAD_ERROR.FILE_CANCELLED)
					{
						return;
					}

					console.warn('Upload failed: %o', arguments);

					$container.trigger(
					{
						type: 'AttachmentUploadError',
						file: file,
						errorCode: errorCode,
						message: message,
						ajaxData: { error: [ $container.data('err-unknown') ] },
						swfUpload: this
					});
				},

				/**
				 * Fires when an upload is completed, either success or failure
				 *
				 * @param SWFUpload.File
				 */
				upload_complete_handler: function(file)
				{
					try
					{
						if (this.getStats().files_queued > 0)
						{
							this.startUpload(this.getFile(0).ID);
						}
						else
						{
							console.info('All files uploaded.');
						}
					}
					catch (exception)
					{
						this.debug(exception);
					}
				}
			});

			/**
			 * Fires a phrased error
			 *
			 * @param SWFUpload.File
			 * @param integer
			 * @param string
			 */
			swfAlert = function(file, errorCode, message)
			{
				var messageText = $container.data('err' + errorCode) || message;

				if (file)
				{
					XenForo.alert(messageText + '<br /><br />' + file.name);
				}
				else
				{
					XenForo.alert(messageText);
				}
			};
		}

		/**
		 * Bind to the AutoInlineUploadEvent of the document, just in case SWFUpload failed
		 */
		$(document).bind('AutoInlineUploadComplete', function(e)
		{
			if (uniqueKey && e.ajaxData && uniqueKey !== e.ajaxData.key)
			{
				return false;
			}

			var $target = $(e.target);

			if ($target.is('form.AttachmentUploadForm'))
			{
				if ($trigger.overlay())
				{
					$trigger.overlay().close();
				}

				$container.trigger(
				{
					type: 'AttachmentUploaded',
					ajaxData: e.ajaxData
				});

				return false;
			}
		});

		return {
			getSwfUploader: function()
			{
				return swfUploader;
			},
			swfAlert: swfAlert
		};
	};

	// *********************************************************************

	XenForo.AttachmentEditor = function($editor)
	{
		this.setVisibility = function(instant)
		{
			var $hideElement = $editor.closest('.ctrlUnit'),
				$insertAll = $editor.find('.AttachmentInsertAllBlock'),
				$files = $editor.find('.AttachedFile:not(#AttachedFileTemplate)'),
				$images = $files.filter('.AttachedImage');

			console.log('Attachments changed, total files: %d, images: %d', $files.length, $images.length);

			if ($hideElement.length == 0)
			{
				$hideElement = $editor;
			}

			if (instant === true)
			{
				if ($files.length)
				{
					if ($images.length > 1)
					{
						$insertAll.show();
					}
					else
					{
						$insertAll.hide();
					}

					$hideElement.show();
				}
				else
				{
					$hideElement.hide();
				}
			}
			else
			{
				if ($files.length)
				{
					if ($images.length > 1)
					{
						if ($hideElement.is(':hidden'))
						{
							$insertAll.show();
						}
						else
						{
							$insertAll.xfFadeDown(XenForo.speed.fast);
						}
					}
					else
					{
						if ($hideElement.is(':hidden'))
						{
							$insertAll.hide();
						}
						else
						{
							$insertAll.xfFadeUp(XenForo.speed.fast, false, XenForo.speed.fast, 'swing');
						}
					}

					$hideElement.xfFadeDown(XenForo.speed.normal);
				}
				else
				{
					$insertAll.slideUp(XenForo.speed.fast);

					$hideElement.xfFadeUp(XenForo.speed.normal, false, false, 'swing');
				}
			}
		};

		this.setVisibility(true);

		$('#AttachmentUploader').bind(
		{
			/**
			 * Fires when a file is added to the upload queue
			 *
			 * @param event Including e.file
			 */
			AttachmentQueued: function(e)
			{
				console.info('Queued file %s (%d bytes).', e.file.name, e.file.size);

				var $template = $('#AttachedFileTemplate').clone().attr('id', e.file.id);

				$template.find('.Filename').text(e.file.name);
				$template.find('.ProgressCounter').text('0%');
				$template.find('.ProgressGraphic span').css('width', '0%');

				if (e.isImage)
				{
					$template.addClass('AttachedImage');
				}

				$template.xfInsert('appendTo', '.AttachmentList.New', null, insertSpeed);

				$template.find('.AttachmentCanceller').css('display', 'block').click(function()
				{
					e.swfUpload.cancelUpload(e.file.id);
					$template.xfRemove(null, function() {
						$editor.trigger('AttachmentsChanged');
					}, removeSpeed, 'swing');
				});

				$editor.trigger('AttachmentsChanged');
			},

			/**
			 * Fires when an upload progress update is received
			 *
			 * @param event Including e.file and e.bytes
			 */
			AttachmentUploadProgress: function(e)
			{
				console.log('Uploaded %d/%d bytes.', e.bytes, e.file.size);

				var percentNum = Math.min(100, Math.ceil(e.bytes * 100 / e.file.size)),
					percentage = percentNum + '%',
					$placeholder = $('#' + e.file.id),
					$counter = $placeholder.find('.ProgressCounter'),
					$graphic = $placeholder.find('.ProgressGraphic');

				$counter.text(percentage);
				$graphic.css('width', percentage);

				if (percentNum >= 100)
				{
					$placeholder.find('.AttachmentCanceller').prop('disabled', true).addClass('disabled');
				}

				if ($graphic.width() > $counter.outerWidth())
				{
					$counter.appendTo($graphic);
				}
			},

			/**
			 * Fires if an error occurs during the upload
			 *
			 * @param event
			 */
			AttachmentUploadError: function(e)
			{
				var error = '';

				$.each(e.ajaxData.error, function(i, errorText) { error += errorText + "\n"; });

				XenForo.alert(error + '<br /><br />' + e.file.name);

				$('#' + e.file.id).xfRemove();

				console.warn('AttachmentUploadError: %o', e);
			},

			/**
			 * Fires when a file has been successfully uploaded
			 *
			 * @param event
			 */
			AttachmentUploaded: function(e)
			{
				if (e.file) // SWFupload method
				{
					var $attachment = $('#' + e.file.id),
						$attachmentText = $attachment.find('.AttachmentText'),
						$templateHtml = $(e.ajaxData.templateHtml),
						$thumbnail;

					$attachmentText.fadeOut(XenForo.speed.fast, function()
					{
						$templateHtml.find('.AttachmentText').xfInsert('insertBefore', $attachmentText, 'fadeIn', XenForo.speed.fast);

						$thumbnail = $attachment.find('.Thumbnail');
						$thumbnail.html($templateHtml.find('.Thumbnail').html());
						//XenForo.activate($thumbnail);

						$attachmentText.xfRemove();

						$attachment.attr('id', 'attachment' + e.ajaxData.attachment_id);
					});
				}
				else // regular javascript method
				{
					var $attachment = $('#attachment' + e.ajaxData.attachment_id);

					if (!$attachment.length)
					{
						$attachment = $(e.ajaxData.templateHtml).xfInsert('appendTo', $editor.find('.AttachmentList.New'), null, insertSpeed);
					}
				}

				$editor.trigger('AttachmentsChanged');
			}
		});

		var thisVis = $.context(this, 'setVisibility');

		$('#QuickReply').bind('QuickReplyComplete', function(e)
		{
			$editor.find('.AttachmentList.New li:not(#AttachedFileTemplate)').xfRemove().promise().always(thisVis);
		});

		$editor.bind('AttachmentsChanged', thisVis);
	};

	// *********************************************************************

	XenForo.AttachmentInserter = function($trigger)
	{
		$trigger.click(function(e)
		{
			var $attachment = $trigger.closest('.AttachedFile').find('.Thumbnail a'),
				attachmentId = $attachment.data('attachmentid'),
			 	editor,
				bbcode,
				html,
				thumb = $attachment.find('img').attr('src'),
				img = $attachment.attr('href');

			e.preventDefault();

			if ($trigger.attr('name') == 'thumb')
			{
				bbcode = '[ATTACH]' + attachmentId + '[/ATTACH] ';
				html = '<img src="' + thumb + '" class="attachThumb bbCodeImage" alt="attachThumb' + attachmentId + '" /> ';
			}
			else
			{
				bbcode = '[ATTACH=full]' + attachmentId + '[/ATTACH] ';
				html = '<img src="' + img + '" class="attachFull bbCodeImage" alt="attachFull' + attachmentId + '" /> ';
			}

			var editor = XenForo.getEditorInForm($trigger.closest('form'), ':not(.NoAttachment)');
			if (editor)
			{
				if (editor.$editor)
				{
					editor.insertHtml(html);
					var update = editor.$editor.data('xenForoElastic');
					if (update)
					{
						setTimeout(function() { update(); }, 250);
						setTimeout(function() { update(); }, 1000);
					}
				}
				else
				{
					editor.val(editor.val() + bbcode);
				}
			}
		});
	};

	// *********************************************************************

	XenForo.AttachmentDeleter = function($trigger)
	{
		$trigger.css('display', 'block').click(function(e)
		{
			var $trigger = $(e.target),
				href = $trigger.attr('href') || $trigger.data('href'),
				$attachment = $trigger.closest('.AttachedFile'),
				$thumb = $trigger.closest('.AttachedFile').find('.Thumbnail a'),
				attachmentId = $thumb.data('attachmentid');

			if (href)
			{
				$attachment.xfFadeUp(XenForo.speed.normal, null, removeSpeed, 'swing');

				XenForo.ajax(href, '', function(ajaxData, textStatus)
				{
					if (XenForo.hasResponseError(ajaxData))
					{
						$attachment.xfFadeDown(XenForo.speed.normal);
						return false;
					}

					var $editor = $attachment.closest('.AttachmentEditor');

					$attachment.xfRemove(null, function() {
						$editor.trigger('AttachmentsChanged');
					}, removeSpeed, 'swing');
				});

				if (attachmentId)
				{
					var editor = XenForo.getEditorInForm($trigger.closest('form'), ':not(.NoAttachment)');
					if (editor && editor.$editor)
					{
						editor.$editor.find('img[alt=attachFull' + attachmentId + '], img[alt=attachThumb' + attachmentId + ']').remove();
						var update = editor.$editor.data('xenForoElastic');
						if (update)
						{
							update();
						}
					}
				}

				return false;
			}

			console.warn('Unable to locate href for attachment deletion from %o', $trigger);
		});
	};

	// *********************************************************************

	XenForo.AttachmentInsertAll = function($trigger)
	{
		$trigger.click(function()
		{
			$('.AttachmentInserter[name=' + $trigger.attr('name') + ']').each(function(i, input)
			{
				$(input).trigger('click');
			});
		});
	};

	// *********************************************************************

	XenForo.AttachmentDeleteAll = function($trigger)
	{
		$trigger.click(function()
		{
			// TODO: This is a fairly horrible way of doing this, but it's going to be used very infrequently.
			$('.AttachmentDeleter').each(function(i, input)
			{
				$(input).trigger('click');
			});
		});
	};

	// *********************************************************************

	XenForo.register('.AttachmentUploader', 'XenForo.AttachmentUploader');

	XenForo.register('.AttachmentEditor', 'XenForo.AttachmentEditor');

	XenForo.register('.AttachmentInserter', 'XenForo.AttachmentInserter');

	XenForo.register('.AttachmentDeleter', 'XenForo.AttachmentDeleter');

	XenForo.register('.AttachmentInsertAll', 'XenForo.AttachmentInsertAll');

	XenForo.register('.AttachmentDeleteAll', 'XenForo.AttachmentDeleteAll');

	if ($.browser.mozilla)
	{
		$(function ()
		{
			var l = XenForo.isRTL() ? '9999em' : '-9999em',
				$o = $('<object width="100" height="100" type="application/x-shockwave-flash" style="visibility: hidden; position: absolute; top: 0; left: '+l+'" />').appendTo('body');
			setTimeout(function() { $o.remove(); }, 250);
		});
	}
}
(jQuery, this, document);