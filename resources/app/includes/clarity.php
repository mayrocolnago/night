<?php
namespace app\includes;

class clarity {

    public static $appid = null;
    public static $webid = null;

    public static function js() { 
        if(($_SERVER['DEVELOPMENT'] ?? false) === true) return;

        if(!empty(self::$appid)) {
            ?><script>
                $(window).on('onload',function(){
                    
                    try {
                        if(typeof ClarityPlugin.initialize !== 'undefined') {
                            ClarityPlugin.initialize("<?=self::$appid;?>", 
                            function(s){ 
                                $(window).on("screen_onload",function(state){
                                    var uid = null; try { uid = getitem('uid'); } catch(e) { } if(empty(uid)) return;
                                    var nome = null; try { nome = getitem('user')['nome']; } catch(e) { } if(empty(nome)) return;
                                    var email = null; try { email = getitem('user')['email']; } catch(e) { } if(empty(email)) return;
                                    try {
                                        window.clarity("identify", email, uid, state.to, nome);
                                    } catch(err) { 
                                        console.log('Clarity: SDK-s Error',s,err);
                                    }
                                });
                            }, 
                            function(f){  
                                console.log('Clarity: SDK-f Error',f);
                            });
                            return; }
                    } catch(err) { }

                });        
            </script><?php
        }

        if(!empty(self::$webid)) {
            ?><script>
                $(window).on('onload',function(){

                    try {
                        (function(c,l,a,r,i,t,y){
                            c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
                            t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
                            y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
                        })(window, document, "clarity", "script", "<?=self::$webid;?>");
                    } catch(err) { console.log('Clarity: Webtag Error',err); }
        
                });
            </script><?php
        }
    }
}
