function audioAjax(callback){
  var msg="birthday song"; //検索ワード
  var params = {
  "term": msg,
  "limit": 100, //データ数の上限
  "entry": "music",
  "media": "music",
  "country":"US",
  "primaryGenreName": "Pop",
  };

  $.ajax({
    "url":"https://itunes.apple.com/search",
    "method": "GET",
    "data": params,
    "dataType": "jsonp",

    success: function(json){
      console.log(json);
      var random = Math.floor( Math.random() * 100 );
      var music =  json.results[random].previewUrl,
          title =  json.results[random].trackName,
          art = json.results[random].artworkUrl100,
          src = json.results[random].artistName,
          iTunesUrl = json.results[random].collectionViewUrl;
          console.log(iTunesUrl);
      $('#art').attr("src", art);
      $('#title').html(title);
      $('#dowload a').attr("href", iTunesUrl + ' target="new"');

      if(maudio != null){
        maudio.pause();
       }
        maudio.src = music;
        maudio.load();
        if(callback){callback();}
        

    },error: function () {
      alert("Loading failed, Please try again");
    }
  });
}
// ここから

// ドリンク
var mouse;
var clickOffsetTop;
var clickOffsetLeft;
var drink1 = document.querySelector('#drink1');
  drink1.onmousedown = function(evt){
    $('.text_3').hide();
      mouse = 'down';
      evt = (evt) || window.event;
      clickOffsetTop = evt.clientY - drink1.offsetTop;
      clickOffsetLeft = evt.clientX - drink1.offsetLeft;
  };
      
  document.onmouseup = function(){
      mouse = 'up';
  };
  
  document.onmousemove = function(evt){
      evt = (evt) || window.event;
      if(mouse == 'down'){
          drink1.style.top = evt.clientY - clickOffsetTop + 'px';
          drink1.style.left = evt.clientX - clickOffsetLeft + 'px';
      }
  };

// ドリンク終わり


var maudio = document.getElementById("player");
audioAjax();
var firstload = true;


window.onload = function(){
  //Date
  var today = new Date();
  var year = today.getFullYear();
  var month = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October ", "November",  "December"][today.getMonth()];
  var day = today.getDate();
  // document.getElementById('date').innerHTML = year + '/' + month + '/' + day + "th";

  $("#balloon").click(function(){
    $(this).animate({top: '-50%'}, {duration: 2000});
    $('.text_1').hide();
  });

// music
  $(".note").click(function(){
    if(!firstload){
      audioAjax(function(){
        maudio.play();
        $('#music').show();
        maudio.loop = true;
      });
    } else {
      firstload = false;
      maudio.play();
      $('#music').show();
      maudio.loop = true;
    }
    
    $(this).addClass('spin');
    $('.fa-step-backward').click(function() {
      maudio.pause();
    });
    $('.fa-times').click(function() {
      maudio.pause();
      $('#music').hide();
      $(".note").removeClass('spin');
    });
    $('.fa-play').click(function() {
      maudio.play();
    });
  });

}
