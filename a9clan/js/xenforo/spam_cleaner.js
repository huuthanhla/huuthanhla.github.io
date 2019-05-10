/*
 * XenForo spam_cleaner.min.js
 * Copyright 2010-2014 XenForo Ltd.
 * Released under the XenForo License Agreement: http://xenforo.com/license-agreement
 */
(function(d,e){XenForo.SpamCleaner=function(b){var c=b.closest(".xenOverlay");c.length&&b.submit(function(f){f.preventDefault();XenForo.ajax(b.attr("action"),b.serializeArray(),function(a){if(XenForo.hasResponseError(a))return!1;XenForo.hasTemplateHtml(a)?new XenForo.ExtLoader(a,function(){b.slideUp(XenForo.speed.fast,function(){b.remove();$template=d(a.templateHtml).prepend('<h2 class="heading">'+a.title+"</h2>");$template.xfInsert("appendTo",c,"slideDown",XenForo.speed.fast);c.data("overlay").getTrigger().bind("onClose",
function(){d(this).data("XenForo.OverlayTrigger")&&d(this).data("XenForo.OverlayTrigger").deCache()})})}):a._redirectTarget?e.location=a._redirectTarget:c.data("overlay").close()})})};XenForo.register(".SpamCleaner","XenForo.SpamCleaner")})(jQuery,this,document);
