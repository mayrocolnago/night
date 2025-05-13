<?php
namespace app\includes;

class native {

    public static function js() {
        ?><script>
            var getqrphotofn = null;
            var takequickphotofn = null;
            var thisisandroid = false;
            var thisisiphone = false;
            var inAppBrowserRef;

            function uploadquickphoto(file,onsuccess) {
                var paramset = { 'f':'foto', 'e':'jpg', 'p':'/', 'base64':'1', 'file':file };
                try { if(!empty(autouploadextraparams) && (typeof autouploadextraparams == 'object' || typeof autouploadextraparams == 'array'))
                  paramset = { ...paramset, ...autouploadextraparams }; } catch(e) { }
                curlsend('storage/send', paramset, null, null,
                function(data){ if(typeof onsuccess !== 'function') return;
                    if(String(data.result).replace('null','').replace('undefined','').trim() == '') return onsuccess(file);
                    else onsuccess((typeof upstoragedefaultpathdir == 'undefined' ? '' : upstoragedefaultpathdir)+data.result); }); 
            }

            async function getquickphoto(onsuccess) {
                if(typeof navigator.camera !== 'undefined') {
                    try { navigator.camera.getPicture(function(data){ uploadquickphoto('data:image/jpg;base64,'+data,onsuccess); },
                        function(e) { console.log('could not get picture',e); },
                        { correctOrientation: true, saveToPhotoAlbum:true,
                        destinationType: Camera.DestinationType.DATA_URL,
                        quality:30, 
                        encodingType: Camera.EncodingType.JPEG });
                    } catch(e) { } /* targetWidth: 1024, targetHeight: 1024, */
                } else {
                    takequickphotofn = onsuccess;
                    alertpormodal(`<center>
                        <div class="videoareacomponent encolor" style="width:320px;height:240px;background-color:#222;margin:1rem 0px;">
                            <video id="tkpvideocomponent" width="320" height="240" autoplay></video>
                            <canvas id="tkpcanvascomponent" width="320" height="240" style="display:none;"></canvas>
                        </div><br style="clear:both;">
                        <button class="btns encolor" onclick="if($(this).hasClass('disabled')) return; 
                            $(this).addClass('disabled').html('Salvando...');
                            var rmmod = function(){ alertpormodal(':close'); };
                            let canvas = document.querySelector('#tkpcanvascomponent');
                            let video = document.querySelector('#tkpvideocomponent'); let i = null;
                            try { canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                            i = canvas.toDataURL('image/jpeg'); } catch(e) { rmmod(); toastdetails('Erro ao obter a foto',null,e); }
                            $('#tkpvideocomponent').hide(); $('#tkpcanvascomponent').show();
                            document.querySelector('#tkpvideocomponent').srcObject.getTracks().forEach(function(track){ track.stop(); });
                            uploadquickphoto(i,function(os){ rmmod(); if(!os) toastdetails('Erro ao salvar a foto',null,os);
                                if(typeof takequickphotofn !== 'function') return; takequickphotofn(os); });">Tirar foto</button>
                        <br style="clear:both;"><br><a href="#" style="margin-left:0.5rem;" 
                            onclick="alertpormodal(':close');
                                    document.querySelector('#tkpvideocomponent').srcObject.getTracks().forEach(function(track){ 
                                        track.stop(); });">Voltar</a><br>`);
                    try { let video = document.querySelector('#tkpvideocomponent');
                        let stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
                        video.srcObject = stream; } catch(e) { toastdetails('Erro ao obter v&iacute;deo da c&acirc;mera',null,e);
                        alertpormodal(':close'); }
                }
            }

            function getbarcode(callback,prompt) {
                if(typeof callback !== 'function') callback = function(d){ console.log(d); };
                
                /* https://github.com/marceloburegio/cordova-plugin-zxing */ 
                /* 'QR_CODE', 'CODE_128', 'CODE_39', 'EAN_13', 'ITF' */
                try { if(typeof window.plugins.zxingPlugin.scan !== 'undefined')
                        return window.plugins.zxingPlugin.scan({
                                'prompt_message':(prompt || 'Scan QR/Barcode'),
                                'orientation_locked':false,
                                'camera_id':0,
                                'beep_enabled':false,
                                'scan_type': 'normal',
                                'barcode_formats': [],
                                'extras':{}
                            },function(s){ callback(s); }, 
                            function(e){ console.log(e); callback(''); }); } catch(err) { }

                /* https://www.npmjs.com/package/cordova-plugin-barcodescanner */
                try { if(typeof cordova.plugins.barcodeScanner.scan !== 'undefined')
                        return cordova.plugins.barcodeScanner.scan(
                            function(s){ callback(s.text, s.format); },
                            function(e){ console.log(e); callback(''); },{
                                preferFrontCamera : false,
                                showFlipCameraButton : true,
                                showTorchButton : true,
                                torchOn: false,
                                saveHistory: false,
                                prompt : (prompt || 'Scan QR/Barcode'),
                                resultDisplayDuration: 0,
                                formats : "",
                                orientation : "portrait",
                                disableAnimations : true,
                                disableSuccessBeep: true
                            }); } catch(err) { }

                var qrlib = `https://unpkg.com/html5-qrcode@2.0.9/dist/html5-qrcode.min.js`;
                if(!($('script[src="'+qrlib+'"]')).length) return loadScript(qrlib,function(){ getbarcode(callback,prompt); });
                    
                alertpormodal(`<center>
                    <button class="btn3 encolor" style="display:block;width:100%;text-transform:none;" 
                        onclick="alertpormodal(':close');">Fechar</button>
                    <div class="qrareacomponent encolor" style="width:80vw;overflow:hidden;height:auto;background-color:#222;margin:2rem 0px 0px 0px;position:relative;">
                        <div id="tkeqrcomponent"></div></div>
                    <br style="clear:both;"></center>`);
                var html5QrcodeScanner = new Html5QrcodeScanner("tkeqrcomponent", { fps: 10, qrbox: 250 });
                html5QrcodeScanner.render(function(decodedText, decodedResult){
                    alertpormodal(':close');
                    callback(decodedText,decodedResult);
                });
            }

            function getlocation(onsuccess,onerror,options) { try {
                if(typeof options === 'undefined') options = {}; /* { maximumAge: 3000, timeout: 5000, enableHighAccuracy: true }; */
                navigator.geolocation.getCurrentPosition(function(position){
                    var data = new Object();
                    try { data.latitude = position.coords.latitude; } catch(e) { console.log('no param'); }
                    try { data.longitude = position.coords.longitude; } catch(e) { console.log('no param'); }
                    try { data.altitude = position.coords.altitude; } catch(e) { console.log('no param'); }
                    try { data.accuracy = position.coords.accuracy; } catch(e) { console.log('no param'); }
                    try { data.altitudeaccuracy = position.coords.altitudeAccuracy; } catch(e) { console.log('no param'); }
                    try { data.heading = position.coords.heading; } catch(e) { console.log('no param'); }
                    try { data.speed = position.coords.speed; } catch(e) { console.log('no param'); }
                    try { data.timestamp = position.timestamp; } catch(e) { console.log('no param'); }
                    try { data.coords = position.coords; } catch(e) { console.log('no param'); }
                    try { data.code = position.code; } catch(e) { console.log('no param'); }
                    try { data.message = position.message; } catch(e) { console.log('no param'); }
                    window.localStorage.setItem('geolat', data.latitude);
                    window.localStorage.setItem('geolong', data.longitude);
                    window.localStorage.setItem('geoaltitude', data.altitude);
                    window.localStorage.setItem('geoaccuracy', data.accuracy);
                    window.localStorage.setItem('geoaltitudeaccuracy', data.altitudeaccuracy);
                    window.localStorage.setItem('geoheading', data.heading);
                    window.localStorage.setItem('geospeed', data.speed);
                    window.localStorage.setItem('geotimestamp', data.timestamp);
                    if(!(typeof onsuccess !== 'function')) onsuccess(data);
                }, function(error) {
                    if(!(typeof onerror !== 'function')) onerror(error);
                },options);
                } catch(error) { if(!(typeof onerror !== 'function')) onerror(error); }
            }

            function shareurlfile(fileurl,filename) {
                if(typeof filename == 'undefined') filename = null;
                try { if(typeof window.plugins.socialsharing !== 'undefined') {
                        var xhr = new XMLHttpRequest();
                        xhr.open('GET', fileurl, true);
                        xhr.responseType = 'blob';
                        xhr.onload = function() {
                            if(this.status !== 200) return opennativebrowser(fileurl);
                            var blob = this.response;
                            var reader = new FileReader();
                            reader.onloadend = function() { window.plugins.socialsharing.share(null, filename, reader.result, null, filename); };
                            reader.readAsDataURL(blob);
                        };
                        xhr.onerror = function() { return opennativebrowser(fileurl); };
                        return xhr.send();
                    } } catch(err) { }
                return opennativebrowser(fileurl);
            }

            function opennativebrowser(strurl) {
                if(strurl.indexOf('http') < 0) strurl = 'http://'+strurl;
                strurl = strurl.replace('http:////','http://');
                strurl = strurl.replace(/[\\]/gi,'');
                console.log('external url: '+strurl);
                if(thisisiphone) { try { window.open(strurl,'_system'); } catch(e) { } return; }
                if(thisisandroid) { try { navigator.app.loadUrl(strurl, { openExternal:true }); } catch(e) { } return; }
                try { window.open(strurl,'_new'); } catch(e) { }
            }

            function openinnerbrowser(strurl,startcallback,stopcallback,errorcallback,exitcallback) {
                try {
                    inAppBrowserRef = cordova.InAppBrowser.open(strurl, '_blank','hidden=yes,location=no,toolbar=no,hardwareback=yes,fullscreen=yes,zoom=no');
                    if(typeof startcallback !== 'undefined') inAppBrowserRef.addEventListener('loadstart', startcallback);
                    if(typeof stopcallback !== 'undefined') inAppBrowserRef.addEventListener('loadstop', stopcallback);
                    if(typeof errorcallback !== 'undefined') inAppBrowserRef.addEventListener('loaderror', errorcallback);
                    if(typeof exitcallback !== 'undefined') inAppBrowserRef.addEventListener('exit', exitcallback);
                } catch(e) { console.log('error openinnerbrowser reference',e); }
            }
            
            function copy2clipboard(text) {
                if(typeof cordova === 'undefined') {
                  return navigator.permissions.query({ name: "clipboard-write" }).then((result) => {
                    if(result.state == "granted" || result.state == "prompt")
                      navigator.clipboard.writeText(text).then(() => {
                        return toast('Copiado'); }); }); return false; }
                else cordova.plugins.clipboard.copy(text);
                return toast('Copiado');
            }

            function readclipboard(callback) {
                if(!function_exists(callback)) return false;
                if(typeof cordova === 'undefined') {
                  return navigator.permissions.query({ name: "clipboard-read" }).then((result) => {
                    if(result.state == "granted" || result.state == "prompt")
                      navigator.clipboard.readText().then((text) => {
                        callback(text); return true; }); 
                    }); return false; }
                else cordova.plugins.clipboard.read();
                return true;
            }

            function sendMessage(msg) { window.parent.postMessage(msg, '*'); };


            function updatebatterymatter(status,callback) { try {
                if(!((status == undefined) || (status == null))) {
                    window.localStorage.setItem('batterypower', status.isPlugged);
                    window.localStorage.setItem('batterylevel', status.level);
                    return;
                } else
                navigator.getBattery().then(function(battery) { 
                    var status = { 'isPlugged':battery.charging, 'level':(battery.level * 100) };
                    window.localStorage.setItem('batterypower', status.isPlugged);
                    window.localStorage.setItem('batterylevel', status.level);
                    try { if(typeof callback === 'function') callback(status);
                    } catch(e) { console.log('error on callback battery return'); }
                });
                } catch(e) { }
            }

            $(window).on('hardware_back',function(state){
                if($(state.on+' .backbtn').length)
                    $(state.on+' .backbtn').click();
            });

            $(window).on('onload',function(){
                try {
                    let ua = navigator.userAgent.toLowerCase();
                    thisisandroid = ((/android/.test(ua)) ? true : false);
                    thisisiphone = ((/iphone|ipad|ipod/.test(ua)) ? true : false);
                } catch(err) { }

                try { if(window.cordova.platformId && window.cordova.platformId == 'android') thisisandroid = true; } catch(e) { }
                try { if(window.cordova.platformId && window.cordova.platformId == 'ios') thisisiphone = true; } catch(e) { }

                try {
                    if(thisisiphone)
                        $('body').append(`<style id="iostoppadding">.screen { padding:env(safe-area-inset-top, 0px) env(safe-area-inset-right, 0px) env(safe-area-inset-bottom, 0px) env(safe-area-inset-left, 0px); }</style>`);
                } catch(err) { }

                try {
                    window.addEventListener("batterystatus", function(status){
                        console.log("Level: " + status.level + " isPlugged: " + status.isPlugged);
                        updatebatterymatter(status); 
                    }, false);
                } catch(e) { console.log('couldnt run onload'); }

                try {
                    document.addEventListener("backbutton", function(){
                        eventfire('hardware_back', { 'on':getitem('screen') });
                    }, false);
                } catch(e) { }

                try {
                    if(typeof autouploadextraparams !== 'object' && typeof autouploadextraparams !== 'array') autouploadextraparams = {};
                    autouploadextraparams['latitude'] = function(){ return window.localStorage.getItem('geolat'); };
                    autouploadextraparams['longitude'] = function(){ return window.localStorage.getItem('geolong'); };
                    autouploadextraparams['altitude'] = function(){ return window.localStorage.getItem('geoaltitude'); };
                    autouploadextraparams['accuracy'] = function(){ return window.localStorage.getItem('geoaccuracy'); };
                    autouploadextraparams['altitudeaccuracy'] = function(){ return window.localStorage.getItem('geoaltitudeaccuracy'); };
                    autouploadextraparams['heading'] = function(){ return window.localStorage.getItem('geoheading'); };
                    autouploadextraparams['speed'] = function(){ return window.localStorage.getItem('geospeed'); };
                    autouploadextraparams['geotimestamp'] = function(){ return window.localStorage.getItem('geotimestamp'); };
                } catch(e) { }

            });

        </script><?php
    }

}