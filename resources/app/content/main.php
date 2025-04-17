<?php
namespace app\content;

class main {

    public static function html() {
        ?><div id="main" class="screen homescreen">
            App content
        </div><?php
    }

    public static function js() {
        ?><script>
            $(window).on('screen_onload',function(state){
                if(state.to !== '#home') return;
                switchtab('#main');
            });
        </script><?php
    }

}