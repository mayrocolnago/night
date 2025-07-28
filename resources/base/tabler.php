<?php
class tabler {

    public static function index($request='class') {
        if(!is_string($request)) return;
        if(!class_exists($request)) return;
        return str_replace(['<div id="app"','<div id="coreloading"'],
            ['<div class="page"><div class="page-wrapper"><div class="page-body" id="app"',
            '</div></div><div id="coreloading"'],
            \assets::show($request));
    }

    public static function css() {
        ?><style>
            .tabler-body-initialize {
                display: none !important;
            }
            .tablerbar-menu-initialize {
                display: none !important;
            }
            .nav-item.separator { user-select: none; pointer-events: none; }
            .nav-item.separator hr { margin:0.5rem 0; opacity:0.1; }
        </style><?php
        \globals::css();
        \desktop::css();
    }

    public static function html() {
        ?><div id="home" class="screen" style="display:none;"></div><?php
    }

    public static function js() {
        ?><script>
            try { $('body').addClass('tabler-body-initialize'); } catch(err) { }

            $(window).on('tabler_onload', function(state){
                if(!($('.tablerbar-menu-initialize').length)) return;
                $('.tablerbar-menu-initialize').removeClass('tablerbar-menu-initialize');
            });

            $(window).on('onload',function(state){
                $('body').addClass('tabler-body-initialize');
                
                if($('.navbar').length)
                    $('.navbar').parent().parent().parent().prepend($('.navbar'));

                if($('.navbar .nav-item .nav-link').length)
                    $('.navbar .nav-item .nav-link').on('click',function(){
                        if(!($('.navbar .navbar-toggler').is(':visible'))) return;
                        $('.navbar .navbar-toggler').click(); });

                loadScript('/assets/www/js/tabler.min.js',function(){
                    loadCss('/assets/www/css/tabler.min.css',function(){
                        $('body').removeClass('tabler-body-initialize');
                        eventfire('tabler_onload',{});
                    });
                });
            });
        </script>
        <?php
        \globals::js();
        \desktop::js();
        \icon::js();
    }

}