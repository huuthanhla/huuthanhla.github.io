/*
 * XenForo acp_quicksearch.min.js
 * Copyright 2010-2014 XenForo Ltd.
 * Released under the XenForo License Agreement: http://xenforo.com/license-agreement
 */
(function(c,j){XenForo.AdminSearchForm=function(f){var b=c("#AdminSearchInput"),d=c(f.data("target")),h=null,g=null,i="";b.attr("autocomplete","off").bind({keyup:function(){var a=b.strval();a!=i&&a.length>=2?(i=a,clearTimeout(h),h=setTimeout(function(){console.log('The input now reads "%s"',b.strval());g&&g.abort();g=XenForo.ajax(f.attr("action"),f.serializeArray(),function(a){if(XenForo.hasResponseError(a))return!1;XenForo.hasTemplateHtml(a)&&(d.empty().append(a.templateHtml),d.find("li").mouseleave(function(){c(this).removeClass("kbSelect")}),
d.find("li:first").addClass("kbSelect"))})},250)):a==""&&d.empty()},paste:function(){setTimeout(function(){b.trigger("keyup")},0)},keydown:function(a){switch(a.which){case 38:case 40:var b=d.find("li"),c=b.filter(".kbSelect"),e=0;if(c.length&&(e=b.index(c.get(0)),e+=a.which==40?1:-1,e<0||e>=b.length))e=0;b.removeClass("kbSelect").eq(e).addClass("kbSelect");return!1}}});b.closest("form").submit(function(a){a.preventDefault();a=d.find("li.kbSelect a");if(a.length)j.location=a.attr("href");return!1})};
XenForo.register("#AdminSearchForm","XenForo.AdminSearchForm")})(jQuery,this,document);
