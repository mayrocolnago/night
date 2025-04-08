<?php
class site {
    use \openapi;

    public static function index($data) { //this will be called if nothing goes after /
        ?><body style="background-color:#111;color:#FFF;">
            Hello world. <a href="/example">See TO-DO example</a>
        </div><?php
    }

    public static function pagename($data) { //this will be called if url ends with /site/pagename
        ?><div>
            Another page
        </div><?php
    }
}