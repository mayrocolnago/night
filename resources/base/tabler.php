<?php
class tabler {

    public static function index($data='class') {
        if(!is_string($data)) return;
        if(!class_exists($data)) return;
        return str_replace(['<div id="app"','<div id="coreloading"'],
            ['<div class="page"><div class="page-wrapper"><div class="page-body" id="app"',
            '</div></div><div id="coreloading"'],
            \assets::show($data));
    }

    public static function css($data=[]) {
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
        echo \globals::css();
        echo \desktop::css();
    }

    public static function html($data=[]) {
        ?><div id="home" class="screen" style="display:none;"></div><?php
    }

    public static function js($data=[]) {
        ?><script>
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
        echo \globals::js();
        echo \desktop::js();
        echo \icons::js();

        if(module_exists('storage::upload')) echo \storage::upload(['_js' => true]);
    }

}