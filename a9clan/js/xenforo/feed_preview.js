/*
 * XenForo feed_preview.min.js
 * Copyright 2010-2014 XenForo Ltd.
 * Released under the XenForo License Agreement: http://xenforo.com/license-agreement
 */
(function(b){XenForo.FeedForm=function(c){c.bind({AutoValidationDataReceived:function(a){if(XenForo.hasResponseError(a.ajaxData))return!1;if(a.ajaxData._redirectStatus)return!0;new XenForo.ExtLoader(a.ajaxData,function(){XenForo.createOverlay(b("<span />"),a.ajaxData.templateHtml,a.ajaxData).load()});return!1}})};XenForo.register("#FeedForm","XenForo.FeedForm")})(jQuery,this,document);
