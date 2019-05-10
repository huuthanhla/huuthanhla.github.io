/*
 * XenForo personal_details_editor.min.js
 * Copyright 2010-2014 XenForo Ltd.
 * Released under the XenForo License Agreement: http://xenforo.com/license-agreement
 */
(function(b){XenForo.AvatarGenderUpdater=function(a){this.__construct(a)};XenForo.AvatarGenderUpdater.prototype={__construct:function(a){a.find('input[name="gender"]').length&&a.bind("AutoValidationComplete",b.context(this,"updateAvatars"))},updateAvatars:function(a){a.ajaxData.userId&&a.ajaxData.avatarUrls&&XenForo.updateUserAvatars(a.ajaxData.userId,a.ajaxData.avatarUrls)}};XenForo.register("form.AutoValidator","XenForo.AvatarGenderUpdater")})(jQuery,this,document);
