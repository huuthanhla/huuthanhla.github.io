/*
 * XenForo acp_login.min.js
 * Copyright 2010-2014 XenForo Ltd.
 * Released under the XenForo License Agreement: http://xenforo.com/license-agreement
 */
(function(f,j,k){XenForo.AcpLoginForm=function(c){var h=f("#loginControls"),a=f("#loginLogo"),i=f("#errorMessage"),d=c.find('input[name="login"]');d.length&&d.val()==""?d.focus():c.find('input[name="password"]').focus();c.submit(function(d){d.preventDefault();a.data("width")||(a.data("width",a.width()),a.data("margintop",a.css("margin-top")));h.xfFadeOut(XenForo.speed.normal);XenForo.ajax(c.attr("action"),c.serializeArray(),function(b){i.hide();b._redirectStatus&&b._redirectStatus=="ok"?a.animate({width:100,
marginTop:0},XenForo.speed.normal,function(){if(b.repost){var a=f("<form />").attr({action:b._redirectTarget,method:"POST"}).appendTo(k.body),c=function(a,b,d){var g,e;for(e in a)switch(g=d?d+"["+e+"]":e,typeof a[e]){case "array":case "object":c(a[e],b,g);break;default:b.append(f("<input />").attr({type:"hidden",name:g,value:a[e].toString()}))}};b.postVars&&c(b.postVars,a,"");a.submit()}else j.location=b._redirectTarget}):(i.html(b.error[0]).xfFadeIn(XenForo.speed.fast),h.xfFadeIn(XenForo.speed.fast))})})};
XenForo.register("form.AcpLoginForm","XenForo.AcpLoginForm")})(jQuery,this,document);
