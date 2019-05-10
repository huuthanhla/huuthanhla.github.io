/*
 * XenForo event_listener.min.js
 * Copyright 2010-2014 XenForo Ltd.
 * Released under the XenForo License Agreement: http://xenforo.com/license-agreement
 */
(function(b){XenForo.EventListenerOption=function(a){this.__construct(a)};XenForo.EventListenerOption.prototype={__construct:function(a){this.$select=a;this.url=a.data("descurl");this.$target=b(a.data("desctarget"));this.url&&this.$target.length&&(a.bind({keyup:b.context(this,"fetchDescriptionDelayed"),change:b.context(this,"fetchDescription")}),a.val().length&&this.fetchDescription())},fetchDescriptionDelayed:function(){this.delayTimer&&clearTimeout(this.delayTimer);this.delayTimer=setTimeout(b.context(this,
"fetchDescription"),250)},fetchDescription:function(){this.$select.val().length?(this.xhr&&this.xhr.abort(),this.xhr=XenForo.ajax(this.url,{event_id:this.$select.val()},b.context(this,"ajaxSuccess"),{error:!1})):this.$target.html("")},ajaxSuccess:function(a){a?this.$target.html(a.description):this.$target.html("")}};XenForo.register("select.EventListenerOption","XenForo.EventListenerOption")})(jQuery,this,document);
