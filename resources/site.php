<?php
class site {

    public static function index($data):\route { //this will be called if nothing goes after /
        ?><body style="background-color:#111;color:#FFF;">
            <h1>Hello world</h1>
            <p>
                <ul>
                    <li><a href="/example">See TO-DO example</a></li>
                    <li><a href="/app">See dynamic DLC App example</a></li>
                    <li><a href="/site/pagename">See another page from module</a></li>
                </ul>
            </p>
        </div><?php
    }

    public static function pagename($data):\route { //this will be called if url ends with /site/pagename
        ?><div>
            Another page. <a href="/">Go back</a>
        </div><?php
    }
}