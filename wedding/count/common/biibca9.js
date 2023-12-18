/*https://loktar00@github.com/loktar00/JQuery-Snowfall*/
// document.write(`
// <div style="z-index:999999;position: fixed;bottom: 0;width: 100%;font-size: 15px;padding: 10px 20px;background-color: #df4759;border-color: #df4759;color: #fff;">
// 	<p class="text-center mb-0" style="color:#fff; margin:0;">
// 		<strong>Thông báo!</strong> Chúng tôi tiếp tục đối mặt với sự cố dịch vụ lưu trữ hình ảnh. Biihappy đang mất niềm tin với nhà cung cấp dịch vụ hiện tại, nên chúng tôi sẽ chuyển đổi dữ liệu qua nhà cung cấp mới uy tín hơn. Cảm ơn bạn vì đã hiểu và chờ đợi chúng tôi!
// 	</p>
// </div>
// `);

Date.now||(Date.now=function(){return(new Date).getTime()}),function(){"use strict";for(var e=["webkit","moz"],t=0;t<e.length&&!window.requestAnimationFrame;++t){var i=e[t];window.requestAnimationFrame=window[i+"RequestAnimationFrame"],window.cancelAnimationFrame=window[i+"CancelAnimationFrame"]||window[i+"CancelRequestAnimationFrame"]}if(/iP(ad|hone|od).*OS 6/.test(window.navigator.userAgent)||!window.requestAnimationFrame||!window.cancelAnimationFrame){var n=0;window.requestAnimationFrame=function(e){var t=Date.now(),i=Math.max(n+16,t);return setTimeout(function(){e(n=i)},i-t)},window.cancelAnimationFrame=clearTimeout}}();var snowFall=function(){function e(){var e={flakeCount:35,flakeColor:"#ffffff",flakeIndex:999999,minSize:1,maxSize:2,minSpeed:1,maxSpeed:5,round:!1,shadow:!1,collection:!1,image:!1,collectionHeight:40},t=[],n={},o=0,s=0,a=0,r=0,l=function(e,t){for(var i in t)e.hasOwnProperty(i)&&(e[i]=t[i])},h=function(e,t){return Math.round(e+Math.random()*(t-e))},m=function(e,t){for(var i in t)e.style[i]=t[i]+("width"==i||"height"==i?"px":"")},d=function(t,i,n){this.x=h(a,s-a),this.y=h(0,o),this.size=i,this.speed=n,this.step=0,this.stepSize=h(1,10)/100,e.collection&&(this.target=canvasCollection[h(0,canvasCollection.length-1)]);var r=null;e.image?(r=new Image,r.src=e.image):(r=document.createElement("div"),m(r,{background:e.flakeColor})),r.className="snowfall-flakes",m(r,{width:this.size,height:this.size,position:"absolute",top:this.y,left:this.x,fontSize:0,zIndex:e.flakeIndex}),e.round&&m(r,{"-moz-border-radius":~~e.maxSize+"px","-webkit-border-radius":~~e.maxSize+"px",borderRadius:~~e.maxSize+"px"}),e.shadow&&m(r,{"-moz-box-shadow":"1px 1px 1px #555","-webkit-box-shadow":"1px 1px 1px #555",boxShadow:"1px 1px 1px #555"}),t.tagName===document.body.tagName?document.body.appendChild(r):t.appendChild(r),this.element=r,this.update=function(){this.y+=this.speed,this.y>o-(this.size+6)&&this.reset(),this.element.style.top=this.y+"px",this.element.style.left=this.x+"px",this.step+=this.stepSize,this.x+=Math.cos(this.step),(this.x+this.size>s-a||this.x<a)&&this.reset()},this.reset=function(){this.y=0,this.x=h(a,s-a),this.stepSize=h(1,10)/100,this.size=h(100*e.minSize,100*e.maxSize)/100,this.element.style.width=this.size+"px",this.element.style.height=this.size+"px",this.speed=h(e.minSpeed,e.maxSpeed)}},f=function(){for(var e=0;e<t.length;e+=1)t[e].update();r=requestAnimationFrame(function(){f()})};return{snow:function(r,m){for(l(e,m),n=r,o=n.offsetHeight,s=n.offsetWidth,n.snow=this,"body"===n.tagName.toLowerCase()&&(a=25),window.addEventListener("resize",function(){o=n.clientHeight,s=n.offsetWidth},!0),i=0;i<e.flakeCount;i+=1)t.push(new d(n,h(100*e.minSize,100*e.maxSize)/100,h(e.minSpeed,e.maxSpeed)));f()},clear:function(){var e=null;e=n.getElementsByClassName?n.getElementsByClassName("snowfall-flakes"):n.querySelectorAll(".snowfall-flakes");for(var t=e.length;t--;)e[t].parentNode===n&&n.removeChild(e[t]);cancelAnimationFrame(r)}}}return{snow:function(t,i){if("string"==typeof i)if(t.length>0)for(var n=0;n<t.length;n++)t[n].snow&&t[n].snow.clear();else t.snow.clear();else if(t.length>0)for(var n=0;n<t.length;n++)(new e).snow(t[n],i);else(new e).snow(t,i)}}}();
var SNOW_Picture = biicore.webroot + '/common/imgs/heart.png';
var special_custom = ['646f6e3d778825e6f306667f', '64a04f6beb89a210fc07656a'];
window.onload = (event) =>{
	if(biicore.effect.type == 'none') return false;
	setTimeout(function() {
		if(biicore.effect.type == 'heart') {
			let flakeCount = 30;
			if (typeof biicore.template_id !== 'undefined' && special_custom.includes(biicore.template_id)) {
				flakeCount = 5;
				if(window.innerWidth <= 650) {
					flakeCount = 3;
				}
			}
			snowFall.snow(document.getElementsByTagName('body')[0], {image : SNOW_Picture, minSize: 15, maxSize:32, flakeCount:flakeCount, maxSpeed: 3, minSpeed: 1});
		}else if(biicore.effect.type == 'snow') {
			let flakeCount = 250;
			if (typeof biicore.template_id !== 'undefined' && special_custom.includes(biicore.template_id)) {
				flakeCount = 50;
				if(window.innerWidth <= 1200) {
					flakeCount = 30;
				}
				if(window.innerWidth <= 650) {
					flakeCount = 25;
				}
			}
			snowFall.snow(document.getElementsByTagName('body')[0], {round : true, shadow : true, flakeCount : flakeCount, minSize: 1, maxSize:8});
		}else if(biicore.effect.type == 'custom') {
			let effectSetting = biicore.effect.setting;
			let minSpeed = (parseInt(effectSetting.speed) - 3);
			if(minSpeed <= 0) minSpeed = 1;
			snowFall.snow(document.getElementsByTagName('body')[0], {image : effectSetting.icon, minSize: effectSetting.minSize, maxSize:effectSetting.maxSize, flakeCount:effectSetting.number, maxSpeed: effectSetting.speed, minSpeed: minSpeed});
		}
	}, 300);
};

if(biicore.alert && Object.keys(biicore.alert).length > 0 && biicore.alert.status == 1) {
	setTimeout(function(){
		Swal.fire({
			title: biicore.alert.title,
			html: biicore.alert.content,
			showCloseButton: false,
			showConfirmButton: false,
			showCancelButton: true,
			focusCancel: true,
			cancelButtonText: (typeof biicore.alert.cancel_button_text != 'undefined' && biicore.alert.cancel_button_text != '') ? biicore.alert.cancel_button_text : 'Tắt thông báo',
		});
	}, biicore.alert.timeout);
}

if(biicore.bgMusic){
	var audioPlayer = document.createElement("AUDIO");
	audioPlayer.style.display = "none";

	setTimeout(function(){
		if (audioPlayer.canPlayType("audio/mpeg")) {
			audioPlayer.setAttribute("src", biicore.bgMusic);
			document.getElementsByClassName("bii-player")[0].style.display = "block";
		}
		audioPlayer.volume = 0.3;
		audioPlayer.setAttribute("controls", "controls");
		document.body.appendChild(audioPlayer);
	}, 1000);
	
	var myInterval = setInterval(function(){
		if(document.querySelector(".bii-player")){
			setTimeout(function(){
				document.getElementsByClassName("bii-player")[0].classList.add("show-sec");
			},2000);
			setTimeout(function(){
				document.getElementsByClassName("bii-player")[0].classList.remove("show-sec");
			},7000);
			clearInterval(myInterval);
		}
	}, 200);

	function playPause() {
		document.getElementsByClassName("bii-player")[0].classList.remove("show-sec");
	    if (audioPlayer.paused) {
			audioPlayer.play();
			document.getElementById("playerVolumeOff").style.display = "none";
			document.getElementById("playerVolumeOn").style.display = "block";
		} else {
			audioPlayer.pause(); 
			document.getElementById("playerVolumeOff").style.display = "block";
			document.getElementById("playerVolumeOn").style.display = "none";
		}
	}
	document.write(`
	<style type="text/css">
	@-webkit-keyframes biilogo-pulse {
	  from {
	    -webkit-transform: scale3d(1, 1, 1);
	    transform: scale3d(1, 1, 1);
	  }
	  50% {
	    -webkit-transform: scale3d(0.95, 0.95, 0.95);
	    transform: scale3d(0.95, 0.95, 0.95);
	  }
	  to {
	    -webkit-transform: scale3d(1, 1, 1);
	    transform: scale3d(1, 1, 1);
	  }
	}
	
	@keyframes biilogo-pulse {
	  from {
	    -webkit-transform: scale3d(1, 1, 1);
	    transform: scale3d(1, 1, 1);
	  }
	  50% {
	    -webkit-transform: scale3d(0.95, 0.95, 0.95);
	    transform: scale3d(0.95, 0.95, 0.95);
	  }
	  to {
	    -webkit-transform: scale3d(1, 1, 1);
	    transform: scale3d(1, 1, 1);
	  }
	}
	.bii-player{position: fixed;bottom: 70px;left: 50px;width: 40px;height: 40px;z-index:99999;display:none;}
	.bii-player .playerIcon{cursor:pointer;display: block;width:40px;height:40px;-webkit-border-radius: 50%;-moz-border-radius: 50%;-o-border-radius: 50%;-ms-border-radius: 50%;border-radius: 50%;background-color: #df4758;padding-top: 7px;padding-left: 9px;position:absolute;z-index: 2;}
	.bii-player:after{content: "";position: absolute;-webkit-border-radius: 50%;-moz-border-radius: 50%;-o-border-radius: 50%;-ms-border-radius: 50%;border-radius: 50%;z-index: -1;background-color: rgba(242, 59, 67, 0.3);width: 120%;height: 120%;left: -10%;top: -10%;-webkit-animation: biilogo-pulse 1s infinite;animation: biilogo-pulse 1s infinite;z-index: 1;}
	.bii-player img{width: 100%;z-index: 99999;position: absolute;cursor:pointer;}
	.bii-player.show-sec .bii-player-secondary{visibility: visible;}
	.bii-player.show-sec .bii-player-secondary-content{ transform: translate3d(0, 0, 0);}
	.bii-player-secondary{position: absolute;width: 310px;left: 25px;height: 50px;overflow: hidden;visibility: hidden;}
	.bii-player-secondary-content{display: flex;align-items: center;cursor:pointer;user-select: none;position: absolute;width: 310px;left: -25px;background: #fff;height: 40px;padding: 8px 11px 8px 50px;border: 1px solid #df4759;border-radius: 30px;z-index: 1;font-size:14px;transform: translate3d(-100%, 0, 0);transition: transform 175ms ease;font-family: arial;font-weight: 200;color: #000;}
	@media (max-width: 799px) {
	  .bii-player{bottom: 30px;left: 20px;}
	}
	</style>
	<div class="bii-player">
		<div onclick="playPause();" class="bii-player-secondary"><div class="bii-player-secondary-content">Click vào đây nếu bạn muốn phát nhạc!</div></div>
		<div onclick="playPause();" class="playerIcon">
			<span id="playerVolumeOff">
				<svg xmlns="http://www.w3.org/2000/svg" width="25" height="27" fill="#fff" class="bi bi-volume-mute-fill" viewBox="0 0 16 16">
				  <path d="M6.717 3.55A.5.5 0 0 1 7 4v8a.5.5 0 0 1-.812.39L3.825 10.5H1.5A.5.5 0 0 1 1 10V6a.5.5 0 0 1 .5-.5h2.325l2.363-1.89a.5.5 0 0 1 .529-.06zm7.137 2.096a.5.5 0 0 1 0 .708L12.207 8l1.647 1.646a.5.5 0 0 1-.708.708L11.5 8.707l-1.646 1.647a.5.5 0 0 1-.708-.708L10.793 8 9.146 6.354a.5.5 0 1 1 .708-.708L11.5 7.293l1.646-1.647a.5.5 0 0 1 .708 0z"/>
				</svg>
			</span>
			<span style="display:none;" id="playerVolumeOn">
				<svg xmlns="http://www.w3.org/2000/svg" width="25" height="27" fill="#fff" class="bi bi-volume-up-fill" viewBox="0 0 16 16">
				  <path d="M11.536 14.01A8.473 8.473 0 0 0 14.026 8a8.473 8.473 0 0 0-2.49-6.01l-.708.707A7.476 7.476 0 0 1 13.025 8c0 2.071-.84 3.946-2.197 5.303l.708.707z"/>
				  <path d="M10.121 12.596A6.48 6.48 0 0 0 12.025 8a6.48 6.48 0 0 0-1.904-4.596l-.707.707A5.483 5.483 0 0 1 11.025 8a5.483 5.483 0 0 1-1.61 3.89l.706.706z"/>
				  <path d="M8.707 11.182A4.486 4.486 0 0 0 10.025 8a4.486 4.486 0 0 0-1.318-3.182L8 5.525A3.489 3.489 0 0 1 9.025 8 3.49 3.49 0 0 1 8 10.475l.707.707zM6.717 3.55A.5.5 0 0 1 7 4v8a.5.5 0 0 1-.812.39L3.825 10.5H1.5A.5.5 0 0 1 1 10V6a.5.5 0 0 1 .5-.5h2.325l2.363-1.89a.5.5 0 0 1 .529-.06z"/>
				</svg>
			</span>
		</div>
	</div>
	`);
}

if(!biicore.isPremium && !biicore.templatePremium) {
	setTimeout(function(){
		document.getElementsByClassName("bii-logo")[0].classList.add("show-sec");
	}, 8000);
	setTimeout(function(){
		document.getElementsByClassName("bii-logo")[0].classList.remove("show-sec");
	}, 11000);
	setTimeout(function(){
		document.getElementsByClassName("bii-logo")[0].classList.add("show-sec");
	}, 25000);
	setTimeout(function(){
		document.getElementsByClassName("bii-logo")[0].classList.remove("show-sec");
	}, 28000);
	var biiLogo = biicore.webroot + '/common/imgs/bii.png'
	var currentYear = new Date().getFullYear();
	document.write(`
	<style type="text/css">
	@-webkit-keyframes biilogo-pulse {
	  from {
	    -webkit-transform: scale3d(1, 1, 1);
	    transform: scale3d(1, 1, 1);
	  }
	  50% {
	    -webkit-transform: scale3d(0.95, 0.95, 0.95);
	    transform: scale3d(0.95, 0.95, 0.95);
	  }
	  to {
	    -webkit-transform: scale3d(1, 1, 1);
	    transform: scale3d(1, 1, 1);
	  }
	}
	
	@keyframes biilogo-pulse {
	  from {
	    -webkit-transform: scale3d(1, 1, 1);
	    transform: scale3d(1, 1, 1);
	  }
	  50% {
	    -webkit-transform: scale3d(0.95, 0.95, 0.95);
	    transform: scale3d(0.95, 0.95, 0.95);
	  }
	  to {
	    -webkit-transform: scale3d(1, 1, 1);
	    transform: scale3d(1, 1, 1);
	  }
	}
	.bii-logo{position: fixed;bottom: 70px;right: 50px;width: 40px;height: 40px;z-index:99998;}
	.bii-logo a{display: block;}
	.bii-logo:before{content: "";position: absolute;-webkit-border-radius: 50%;-moz-border-radius: 50%;-o-border-radius: 50%;-ms-border-radius: 50%;border-radius: 50%;z-index: -1;background-color: rgba(242, 59, 67, 0.3);width: 120%;height: 120%;left: -10%;top: -10%;-webkit-animation: biilogo-pulse 1s infinite;animation: biilogo-pulse 1s infinite;z-index: 1;}
	.bii-logo img{width: 100%;z-index: 99999;position: absolute;cursor:pointer;}
	.bii-logo:hover .bii-logo-secondary{visibility: visible;}
	.bii-logo:hover .bii-logo-secondary-content{ transform: translate3d(0, 0, 0);}
	.bii-logo.show-sec .bii-logo-secondary{visibility: visible;}
	.bii-logo.show-sec .bii-logo-secondary-content{ transform: translate3d(0, 0, 0);}
	
	.bii-logo-secondary{position: absolute;width: 320px;right: 25px;height: 40px;overflow: hidden;visibility: hidden;}
	.bii-logo-secondary-content{display: flex;align-items: center;position: absolute;width: 320px;right: -25px;background: #fff;height: 40px;padding: 8px 40px 8px 11px;border: 1px solid #df4759;border-radius: 30px;z-index: 9999;font-size:14px;transform: translate3d(100%, 0, 0);transition: transform 175ms ease;font-family: arial;font-weight: 200;color: #000;}
	.bii-footer .show-desktop{display: inline-block;}
	.bii-footer .show-mobile{display: none;}
	@media (max-width: 799px) {
	  .bii-logo{bottom: 30px;right: 20px;}
	  .bii-footer .show-desktop{display: none;}
	  .bii-footer .show-mobile{display: inline-block;}
	}
	</style>
	<div class="bii-logo"><div class="bii-logo-secondary"><div class="bii-logo-secondary-content">Nền tảng tạo Website đám cưới miễn phí!</div></div><a href="${biicore.coreSite}/wedding" target="_blank"><img src="${biiLogo}" /></a></div>
	<div class="bii-footer" style="z-index:9999;background-color: #000;border-top: 1px solid #df4759;color: #fff;text-align: center;letter-spacing: 1px;bottom: 0;width: 100%;font-size: 13px;">
		<div class="container">
			<a style="color:#fff;padding: 7px 0 9px;display: block;" href="https://biihappy.com/wedding" target="_blank">
				<span><img width="16" src="${biiLogo}" style="border: 1px solid #fff;border-radius: 50%;width:16px!important;" /> IWedding</span> | <span class="show-desktop">Nền tảng tạo website đám cưới miễn phí từ Biihappy</span><span class="show-mobile">Tạo website đám cưới miễn phí</span>
				<span style="line-height: 15px;vertical-align: middle;">
					<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-right-short" viewBox="0 0 16 16">
					  <path fill-rule="evenodd" d="M4 8a.5.5 0 0 1 .5-.5h5.793L8.146 5.354a.5.5 0 1 1 .708-.708l3 3a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708-.708L10.293 8.5H4.5A.5.5 0 0 1 4 8z"/>
					</svg>
				</span>
			</a>
		</div>
	</div>
	`);
}