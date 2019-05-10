/*
 * XenForo acp_forum_editor.min.js
 * Copyright 2010-2014 XenForo Ltd.
 * Released under the XenForo License Agreement: http://xenforo.com/license-agreement
 */
(function(c){XenForo.PrefixManager=function(b){function a(){d.each(function(){var a=b.find('option[value="'+this.value+'"]').prop("disabled",!this.checked);!this.checked&&a.is(":selected")&&b.find("option").first().prop("selected",!0)})}var d=c(b.data("checkboxes")).click(a);a()};XenForo.register("select.PrefixManager","XenForo.PrefixManager")})(jQuery,this,document);
