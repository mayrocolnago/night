<?php
class desktop {

    public static function css() { ?><style>

        #desktopsize_controller { flex-shrink: 0; }

        @media only screen and (min-width: 700px) {
            
            #desktopsize_controller { flex-shrink: 1; }
            #app.desktopsize { max-width:1024px; margin:auto; }
            #app.desktopsize .screen .heading { margin:0px; max-width:none; }
            #app.desktopsize .homelander .backbtn { display:none; }
            #app.desktopsize .homelander .heading { margin-top:2rem; }
            #app:not(.signinin) { display: flex; justify-content: space-between; gap: 1rem; }
            #app:not(.signinin) .screen:not(.homescreen) { flex-grow: 1; flex: 1; overflow-y: auto; }
            #app:not(.deskcheckloading):not(.signinin) .screen.homescreen { display:block !important; }
            #app:not(.signinin) .screen.homescreen {
                min-width:300px;
                max-width:400px;
                flex-shrink: 0;
                order: -1;
                overflow-y: auto;
            }
        }

        </style><?php
    }

    public static function html() { 
        ?><div id="desktopsize_controller" style="display:none;"></div><?php
    }

    public static function js() { ?><script>
        var defaultswitchtabinterval = switchtabinterval;

        function maindesktopsize() {
            let mainid = String('#'+($('.screen.homescreen').first().attr('id')));
            let isit = (!empty($('#desktopsize_controller').css('flex-shrink')));
            switchtabinterval = ((isit) ? 1 : defaultswitchtabinterval);
            if((!($('#app').hasClass('signinin'))) && isit) $('#app').addClass('desktopsize').find(mainid).addClass('fixed');
            else $('#app').removeClass('desktopsize').find(mainid).removeClass('fixed');
            return isit; 
        }

        function maindesktopscreencheck() {
            let mainid = String('#'+($('.screen.homescreen').first().attr('id')));
            let landerid = String('#'+($('.screen.homelander').first().attr('id')));
            if(!maindesktopsize()) return false;
            if($(`.screen:not(${mainid}):visible`).length) return false;
            else switchtab(landerid,true,{},0);
            return true;
        }

        $(window).on("screen_onload",function(state){
            let landerid = String('#'+($('.screen.homelander').first().attr('id')));
            if(state.to === landerid) return;
            maindesktopscreencheck();
        });

        $(window).on("resize",function(state){
            let landerid = String('#'+($('.screen.homelander').first().attr('id')));
            let mainid = String('#'+($('.screen.homescreen').first().attr('id')));
            let atual = $(mainid).hasClass('fixed');
            if(!maindesktopsize()) return (($('.screen').length > 1) ? ((atual) ? switchtab('#home',true,{},0) : '') : '');
            if(!atual && !($(`.screen:not(${mainid}):visible`).length)) return switchtab(landerid,true,{},0);
        });

        $(window).on("signininlabel",function(state){
            if(!state.to) return maindesktopsize();
            else $('#app').removeClass('desktopsize');
        });

        $(window).on("onload",function(state){
            maindesktopscreencheck();
            maindesktopsize();
        });

        </script><?php
    }

}