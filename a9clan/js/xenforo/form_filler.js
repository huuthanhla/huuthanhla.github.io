/*
 * XenForo form_filler.min.js
 * Copyright 2010-2014 XenForo Ltd.
 * Released under the XenForo License Agreement: http://xenforo.com/license-agreement
 */
(function(e){XenForo._FormFiller={};XenForo.FormFillerControl=function(d){var b=d.closest("form"),a=b.data("FormFiller");a||(a=new XenForo.FormFiller(b),b.data("FormFiller",a));a.addControl(d)};XenForo.FormFiller=function(d){function b(b,c){if(XenForo.hasResponseError(c))return!1;e.each(c.formValues,function(b,c){var a=d.find(b);a.length&&(a.is(":checkbox, :radio")?a.prop("checked",c).triggerHandler("click"):a.is("select, input, textarea")&&a.val(c))});b.focus()}function a(a){var c=e(a.target).data("choice")||
e(a.target).val();if(c==="")return!0;f[c]?b(this,f[c]):(g=this,XenForo.ajax(d.data("form-filler-url"),{choice:c},function(a){f[c]=a;b(g,a)}))}var f={},g=null;this.addControl=function(b){b.click(a)}};XenForo.register(".FormFiller","XenForo.FormFillerControl")})(jQuery,this,document);
