/*
 * XenForo conversation_invite.min.js
 * Copyright 2010-2014 XenForo Ltd.
 * Released under the XenForo License Agreement: http://xenforo.com/license-agreement
 */
(function(c){XenForo.ConversationInvitationForm=function(b){var d=c("#ConversationRecipientsPlaceholder");d.length?b.bind("AutoValidationComplete",function(a){a.preventDefault();b.get(0).reset();b.parents(".xenOverlay").length&&b.parents(".xenOverlay").data("overlay").close();XenForo.hasTemplateHtml(a.ajaxData)&&c("#ConversationRecipients").xfRemove("xfFadeOut",function(){c(a.ajaxData.templateHtml).xfInsert("appendTo",d,"xfFadeIn")})}):b.bind("AutoValidationBeforeSubmit",function(a){a.preventDefault();
a.target.submit()})};XenForo.register("#ConversationInvitationForm","XenForo.ConversationInvitationForm")})(jQuery,this,document);
