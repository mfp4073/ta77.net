$f.addPlugin("ipad",function(y){var S=-1;var z=0;var A=1;var P=2;var E=3;var L=4;var j=5;var i=this;var U=1;var T=false;var I=false;var v=false;var s=0;var R=[];var l;var t=null;var d=0;var f={accelerated:false,autoBuffering:false,autoPlay:true,baseUrl:null,bufferLength:3,connectionProvider:null,cuepointMultiplier:1000,cuepoints:[],controls:{},duration:0,extension:"",fadeInSpeed:1000,fadeOutSpeed:1000,image:false,linkUrl:null,linkWindow:"_self",live:false,metaData:{},originalUrl:null,position:0,playlist:[],provider:"http",scaling:"scale",seekableOnBegin:false,start:0,url:null,urlResolvers:[]};var x=S;var r=S;var u=/iPad|iPhone|iPod/i.test(navigator.userAgent);var c=null;function n(Y,X,V){if(X){for(key in X){if(key){if(X[key]&&typeof X[key]=="function"&&!V){continue}if(X[key]&&typeof X[key]=="object"&&X[key].length===undefined){var W={};n(W,X[key]);Y[key]=W}else{Y[key]=X[key]}}}}return Y}var B={simulateiDevice:false,controlsSizeRatio:1.5,controls:true,debug:false,validExtensions:"mov|m4v|mp4|avi|mp3|m4a|aac|m3u8|m3u|pls",posterExtensions:"png|jpg"};n(B,y);var b=B.validExtensions?new RegExp("^.("+B.validExtensions+")$","i"):null;var e=new RegExp("^.("+B.posterExtensions+")$","i");function h(){if(B.debug){if(u){var V=[].splice.call(arguments,0).join(", ");console.log.apply(console,[V])}else{console.log.apply(console,arguments)}}}function m(V){switch(V){case -1:return"UNLOADED";case 0:return"LOADED";case 1:return"UNSTARTED";case 2:return"BUFFERING";case 3:return"PLAYING";case 4:return"PAUSED";case 5:return"ENDED"}return"UNKOWN"}function J(V){var W=$f.fireEvent(i.id(),"onBefore"+V,s);return W!==false}function O(V){V.stopPropagation();V.preventDefault();return false}function M(W,V){if(x==S&&!V){return}r=x;x=W;D();if(W==E){p()}h(m(W))}function C(){c.fp_stop();T=false;I=false;v=false;M(A);M(A)}var g=null;function p(){if(g){return}console.log("starting tracker");g=setInterval(G,100);G()}function D(){clearInterval(g);g=null}function G(){var W=Math.floor(c.fp_getTime()*10)*100;var X=Math.floor(c.duration*10)*100;var Y=(new Date()).time;function V(ab,Z){ab=ab>=0?ab:X-Math.abs(ab);for(var aa=0;aa<Z.length;aa++){if(Z[aa].lastTimeFired>Y){Z[aa].lastTimeFired=-1}else{if(Z[aa].lastTimeFired+500>Y){continue}else{if(ab==W||(W-500<ab&&W>ab)){Z[aa].lastTimeFired=Y;$f.fireEvent(i.id(),"onCuepoint",s,Z[aa].fnId,Z[aa].parameters)}}}}}$f.each(i.getCommonClip().cuepoints,V);$f.each(R[s].cuepoints,V)}function H(){C();v=true;c.fp_seek(0)}function N(V){}function q(){console.log(c);function V(X){var W={};n(W,f);n(W,i.getCommonClip());n(W,X);if(W.ipadUrl){url=decodeURIComponent(W.ipadUrl)}else{if(W.url){url=W.url}}if(url&&url.indexOf("://")==-1&&W.ipadBaseUrl){url=W.ipadBaseUrl+"/"+url}else{if(url&&url.indexOf("://")==-1&&W.baseUrl){url=W.baseUrl+"/"+url}}W.originalUrl=W.url;W.completeUrl=url;W.extension=W.completeUrl.substr(W.completeUrl.lastIndexOf("."));var Y=W.extension.indexOf("?");if(Y>-1){W.extension=W.extension.substr(0,Y)}W.type="video";delete W.index;h("fixed clip",W);return W}c.fp_play=function(Z,X,ab,ac){var W=null;var aa=true;var Y=true;h("Calling play() "+Z,Z);if(X){h("ERROR: inStream clips not yet supported");return}if(Z!==undefined){if(typeof Z=="number"){if(s>=R.length){return}s=Z;Z=R[s]}else{if(typeof Z=="string"){Z={url:Z}}c.fp_setPlaylist(Z.length!==undefined?Z:[Z])}if(s==0&&R.length>1&&e.test(R[s].extension)){var ac=R[s].url;console.log("Poster image available with url "+ac);++s;console.log("Not last clip in the playlist, moving to next one");c.fp_play(s,false,true,ac);return}if(b&&!b.test(R[s].extension)){return}Z=R[s];W=Z.completeUrl;if(Z.autoBuffering!==undefined&&Z.autoBuffering===false){aa=false}if(Z.autoPlay===undefined||Z.autoPlay===true||ab===true){aa=true;Y=true}else{Y=false}}else{h("clip was not given, simply calling video.play, if not already buffering");if(x!=P){c.play()}return}h("about to play "+W,aa,Y);C();if(W){h("Changing SRC attribute"+W);c.setAttribute("src",W)}if(aa){if(!J("Begin")){return false}if(ac){Y=Z.autoPlay;c.setAttribute("poster",ac);c.setAttribute("preload","none")}$f.fireEvent(i.id(),"onBegin",s);h("calling video.load()");c.load()}if(Y){h("calling video.play()");c.play()}};c.fp_pause=function(){h("pause called");if(!J("Pause")){return false}c.pause()};c.fp_resume=function(){h("resume called");if(!J("Resume")){return false}c.play()};c.fp_stop=function(){h("stop called");if(!J("Stop")){return false}I=true;c.pause();try{c.currentTime=0}catch(W){}};c.fp_seek=function(W){h("seek called "+W);if(!J("Seek")){return false}var aa=0;var W=W+"";if(W.charAt(W.length-1)=="%"){var X=parseInt(W.substr(0,W.length-1))/100;var Z=c.duration;aa=Z*X}else{aa=W}try{c.currentTime=aa}catch(Y){h("Wrong seek time")}};c.fp_getTime=function(){return c.currentTime};c.fp_mute=function(){h("mute called");if(!J("Mute")){return false}U=c.volume;c.volume=0};c.fp_unmute=function(){if(!J("Unmute")){return false}c.volume=U};c.fp_getVolume=function(){return c.volume*100};c.fp_setVolume=function(W){if(!J("Volume")){return false}c.volume=W/100};c.fp_toggle=function(){h("toggle called");if(i.getState()==j){H();return}if(c.paused){c.fp_play()}else{c.fp_pause()}};c.fp_isPaused=function(){return c.paused};c.fp_isPlaying=function(){return !c.paused};c.fp_getPlugin=function(X){if(X=="canvas"||X=="controls"){var W=i.getConfig();return W.plugins&&W.plugins[X]?W.plugins[X]:null}h("ERROR: no support for "+X+" plugin on iDevices");return null};c.fp_close=function(){M(S);c.parentNode.removeChild(c);c=null};c.fp_getStatus=function(){var X=0;var Y=0;try{X=c.buffered.start();Y=c.buffered.end()}catch(W){}return{bufferStart:X,bufferEnd:Y,state:x,time:c.fp_getTime(),muted:c.muted,volume:c.fp_getVolume()}};c.fp_getState=function(){return x};c.fp_startBuffering=function(){if(x==A){c.load()}};c.fp_setPlaylist=function(X){h("Setting playlist");s=0;for(var W=0;W<X.length;W++){X[W]=V(X[W])}R=X;$f.fireEvent(i.id(),"onPlaylistReplace",X)};c.fp_addClip=function(X,W){X=V(X);R.splice(W,0,X);$f.fireEvent(i.id(),"onClipAdd",X,W)};c.fp_updateClip=function(X,W){n(R[W],X);return R[W]};c.fp_getVersion=function(){return"3.2.3"};c.fp_isFullscreen=function(){var W=c.webkitDisplayingFullscreen;if(W!==undefined){return W}return false};c.fp_toggleFullscreen=function(){if(c.fp_isFullscreen()){c.webkitExitFullscreen()}else{c.webkitEnterFullscreen()}};c.fp_addCuepoints=function(Z,X,W){var ab=X==-1?i.getCommonClip():R[X];ab.cuepoints=ab.cuepoints||{};Z=Z instanceof Array?Z:[Z];for(var Y=0;Y<Z.length;Y++){var ac=typeof Z[Y]=="object"?(Z[Y]["time"]||null):Z[Y];if(ac==null){continue}ac=Math.floor(ac/100)*100;var aa=ac;if(typeof Z[Y]=="object"){aa=n({},Z[Y],false);if(aa.time===undefined){delete aa.time}if(aa.parameters!==undefined){n(aa,aa.parameters,false);delete aa.parameters}}ab.cuepoints[ac]=ab.cuepoints[ac]||[];ab.cuepoints[ac].push({fnId:W,lastTimeFired:-1,parameters:aa})}};$f.each(("toggleFullscreen,stopBuffering,reset,playFeed,setKeyboardShortcutsEnabled,isKeyboardShortcutsEnabled,css,animate,showPlugin,hidePlugin,togglePlugin,fadeTo,invoke,loadPlugin").split(","),function(){var W=this;c["fp_"+W]=function(){h("ERROR: unsupported API on iDevices "+W);return false}})}function K(){var ai=["abort","canplay","canplaythrough","durationchange","emptied","ended","error","loadeddata","loadedmetadata","loadstart","pause","play","playing","progress","ratechange","seeked","seeking","stalled","suspend","volumechange","waiting"];var aa=function(ak){h("Got event "+ak.type,ak)};for(var ac=0;ac<ai.length;ac++){c.addEventListener(ai[ac],aa,false)}var X=function(ak){h("got onBufferEmpty event "+ak.type);M(P);$f.fireEvent(i.id(),"onBufferEmpty",s)};c.addEventListener("emptied",X,false);c.addEventListener("waiting",X,false);var Z=function(ak){if(r==A||r==P){}else{h("Restoring old state "+m(r));M(r)}$f.fireEvent(i.id(),"onBufferFull",s)};c.addEventListener("canplay",Z,false);c.addEventListener("canplaythrough",Z,false);var Y=function(al){var ak;d=R[s].start;if(R[s].duration>0){ak=R[s].duration;t=ak+d}else{ak=c.duration;t=null}c.fp_updateClip({duration:ak,metaData:{duration:c.duration}},s);R[s].duration=c.duration;R[s].metaData={duration:c.duration};$f.fireEvent(i.id(),"onMetaData",s,R[s])};c.addEventListener("loadedmetadata",Y,false);c.addEventListener("durationchange",Y,false);var W=function(ak){if(t&&c.currentTime>t){c.fp_seek(d);C();return O(ak)}};c.addEventListener("timeupdate",W,false);var ah=function(ak){if(x==L){if(!J("Resume")){h("Resume disallowed, pausing");c.fp_pause();return O(ak)}$f.fireEvent(i.id(),"onResume",s)}M(E);if(!T){T=true;$f.fireEvent(i.id(),"onStart",s)}};c.addEventListener("playing",ah,false);var V=function(ak){F()};c.addEventListener("play",V,false);var ae=function(ak){if(!J("Finish")){if(R.length==1){h("Active playlist only has one clip, onBeforeFinish returned false. Replaying");H()}else{if(s!=(R.length-1)){h("Not the last clip in the playlist, but onBeforeFinish returned false. Returning to the beginning of current clip");c.fp_seek(0)}else{h("Last clip in playlist, but onBeforeFinish returned false, start again from the beginning");c.fp_play(0)}}return O(ak)}M(j);$f.fireEvent(i.id(),"onFinish",s);if(R.length>1&&s<(R.length-1)){h("Not last clip in the playlist, moving to next one");c.fp_play(++s,false,true)}};c.addEventListener("ended",ae,false);var ad=function(ak){M(z,true);$f.fireEvent(i.id(),"onError",s,201);if(B.onFail&&B.onFail instanceof Function){B.onFail.apply(i,[])}};c.addEventListener("error",ad,false);var ag=function(ak){h("got pause event from player"+i.id());if(I){return}if(x==P&&r==A){h("forcing play");setTimeout(function(){c.play()},0);return}if(!J("Pause")){c.fp_resume();return O(ak)}Q();M(L);$f.fireEvent(i.id(),"onPause",s)};c.addEventListener("pause",ag,false);var aj=function(ak){$f.fireEvent(i.id(),"onBeforeSeek",s)};c.addEventListener("seeking",aj,false);var ab=function(ak){if(I){I=false;$f.fireEvent(i.id(),"onStop",s)}else{$f.fireEvent(i.id(),"onSeek",s)}h("seek done, currentState",m(x));if(v){v=false;c.fp_play()}else{if(x!=E){c.fp_pause()}}};c.addEventListener("seeked",ab,false);var af=function(ak){$f.fireEvent(i.id(),"onVolume",c.fp_getVolume())};c.addEventListener("volumechange",af,false)}function F(){l=setInterval(function(){if(c.fp_getTime()>=c.duration-1){$f.fireEvent(i.id(),"onLastSecond",s);Q()}},100)}function Q(){clearInterval(l)}function o(){c.fp_play(0)}function w(){}if(u||B.simulateiDevice){if(!window.flashembed.__replaced){var k=window.flashembed;window.flashembed=function(X,ac,Y){if(typeof X=="string"){X=document.getElementById(X.replace("#",""))}if(!X){return}var ab=window.getComputedStyle(X,null);var aa=parseInt(ab.width);var V=parseInt(ab.height);while(X.firstChild){X.removeChild(X.firstChild)}var W=document.createElement("div");var Z=document.createElement("video");W.appendChild(Z);X.appendChild(W);W.style.height=V+"px";W.style.width=aa+"px";W.style.display="block";W.style.position="relative";W.style.background="-webkit-gradient(linear, left top, left bottom, from(rgba(0, 0, 0, 0.5)), to(rgba(0, 0, 0, 0.7)))";W.style.cursor="default";W.style.webkitUserDrag="none";Z.style.height="100%";Z.style.width="100%";Z.style.display="block";Z.id=ac.id;Z.name=ac.id;Z.style.cursor="pointer";Z.style.webkitUserDrag="none";Z.type="video/mp4";Z.playerConfig=Y.config;$f.fireEvent(Y.config.playerId,"onLoad","player")};flashembed.getVersion=k.getVersion;flashembed.asString=k.asString;flashembed.isSupported=function(){return true};flashembed.__replaced=true}var a=i._fireEvent;i._fireEvent=function(V){if(V[0]=="onLoad"&&V[1]=="player"){c=i.getParent().querySelector("video");if(B.controls){c.controls="controls"}q();K();M(z,true);c.fp_setPlaylist(c.playerConfig.playlist);o();a.apply(i,[V])}var W=x!=S;if(x==S&&typeof V=="string"){W=true}if(W){return a.apply(i,[V])}};i._swfHeight=function(){return parseInt(c.style.height)};i.hasiPadSupport=function(){return true}}return i});