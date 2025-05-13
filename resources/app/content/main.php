<?php
namespace app\content;

class main {

    public static function css() {
        ?><style>
            .container { padding:0px 1.25rem; } /* This will affect "todo" screen also */
        </style><?php
    }

    public static function html() {
        ?><div id="main" class="screen">
            <div class="container">
                <h1>Home</h1>
                Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
            </div>
        </div><?php
    }

    public static function js() {
        ?><script>
            $(window).on('screen_onstart',function(state){
                if(state.to !== '#home') return;
                switchtab('#main');
            });
        </script><?php
    }

}