/*
 * XenForo rating.min.js
 * Copyright 2010-2014 XenForo Ltd.
 * Released under the XenForo License Agreement: http://xenforo.com/license-agreement
 */
(function(c){XenForo.RatingWidget=function(d){var g=null,e=null,f=d.find(".Hint").each(function(){var b=c(this);b.data("text",b.text())}),h=d.find(".RatingValue .Number"),i=d.find("button").each(function(){var b=c(this);b.data("hint",b.attr("title")).removeAttr("title")}),j=function(b){i.each(function(a){c(this).toggleClass("Full",b>=a+1).toggleClass("Half",b>=a+0.5&&b<a+1)})},k=function(){j(h.text());f.text(f.data("text"))};i.bind({mouseenter:function(b){b.preventDefault();j(c(this).val());f.text(c(this).data("hint"))},
click:function(b){b.preventDefault();e?e.load():g=XenForo.ajax(d.attr("action"),{rating:c(this).val()},function(a){XenForo.hasResponseError(a)||(a._redirectMessage&&XenForo.alert(a._redirectMessage,"",1E3),a.newRating&&h.text(a.newRating),a.hintText&&f.data("text",a.hintText),a.templateHtml&&new XenForo.ExtLoader(a,function(){e=XenForo.createOverlay(null,a.templateHtml,{title:a.h1||a.title}).load();e.getOverlay().find(".OverlayCloser").click(function(){e=null})}));k();g=null})}});d.mouseleave(function(){g===
null&&k()})};XenForo.register("form.RatingWidget","XenForo.RatingWidget")})(jQuery,this,document);
