/*
 * XenForo quick_reply_profile.min.js
 * Copyright 2010-2014 XenForo Ltd.
 * Released under the XenForo License Agreement: http://xenforo.com/license-agreement
 */
(function(b,d){XenForo.ProfilePoster=function(a){this.__construct(a)};XenForo.ProfilePoster.prototype={__construct:function(a){this.$form=a.bind({AutoValidationBeforeSubmit:b.context(this,"beforeSubmit"),AutoValidationComplete:b.context(this,"formValidated")});XenForo.isPositive(a.data("hide-submit"))&&(a.find(".submitUnit").hide(),a.find(".StatusEditor").focus(function(){a.find(".submitUnit").show()}));this.listTarget=a.data("target")||"#ProfilePostList";this.submitEnableCallback=XenForo.MultiSubmitFix(this.$form)},
beforeSubmit:function(){},formValidated:function(a){if(a.ajaxData._redirectTarget)d.location=a.ajaxData._redirectTarget;this.submitEnableCallback&&this.submitEnableCallback();this.$form.find("input:submit").blur();a.ajaxData.statusHtml&&b("#UserStatus").html(a.ajaxData.statusHtml).xfActivate();if(XenForo.hasTemplateHtml(a.ajaxData)){var e=this.listTarget;new XenForo.ExtLoader(a.ajaxData,function(){b("#NoProfilePosts").remove();b(a.ajaxData.templateHtml).xfInsert("prependTo",e)})}var c;(c=this.$form.find('textarea[name="message"]').val("").trigger("XFRecalculate").blur().data("XenForo.StatusEditor"))&&
c.update();return!1}};XenForo.register("#ProfilePoster","XenForo.ProfilePoster")})(jQuery,this,document);
