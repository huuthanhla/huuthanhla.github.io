/*
 * XenForo sidebar.min.js
 * Copyright 2010-2014 XenForo Ltd.
 * Released under the XenForo License Agreement: http://xenforo.com/license-agreement
 */
(function(b,c,g){XenForo.FixedSidebar=function(a){if(!XenForo.isTouchBrowser()){var d=b(c),e=g.documentElement,f=function(){e.scrollWidth>e.clientWidth||a.offset().top+a.height()>d.scrollTop()+d.height()?a.css("position","static"):a.css("position","fixed")};b(c).resize(f);f()}};XenForo.register(".FixedSidebar","XenForo.FixedSidebar")})(jQuery,this,document);
