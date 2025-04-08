<?php

// A Simple TO-DO LIST Implementation example with CRUD API

class example {
    use \crud; //This will use CRUD methods
    use \openapi; //This will allow API access for this class

    //This are the APIs we will allow to be accessed (including CRUD ones)
    public static $openapiOnly = ['index','create','list','read','update','delete'];

    //Name of the table we will be using
    public static $crudTable = 'todo';

    //This is a permission handler. It will be called before any CRUD operation
    public static function crudPermissionHandler(&$data=[]) {
        //We verify if there is an userid set
        if(empty($data[':userid'] = @preg_replace('/[^0-9a-z]/','',@strtolower($data[':userid'] ?? '')))) return false;
        if(strlen($data[':userid']) < 10) return false;
        //We can make changes
        if(!empty($data['title'] ?? '')) $data['title'] = ucfirst(trim($data['title']));
        //if($data['id'] == 1) return false; // not allowed
        return true; //allowed (not secure. Implement a validation of your own)
    }

    //This is the database schema. It is useful for auto-deploying your table schema for each module
    //For example, this module uses this specific table. Others will handle their own tables
    public static function database() {
        $fields = [
            "id" => "int NOT NULL AUTO_INCREMENT",
            "title" => "varchar(255) NOT NULL",
            "userid" => "varchar(255) NOT NULL",
            "details" => "longtext NULL DEFAULT NULL"
        ];
        //Create table if it not exists
        pdo_query("CREATE TABLE IF NOT EXISTS `".self::$crudTable."` (
            ".implode("\n",array_map(function($a,$b){ return "`$a` $b,"; },
            array_keys($fields),array_values($fields)))."
            PRIMARY KEY (".(array_keys($fields)[0] ?? 'id')."))");
        //Return table field names if needed
        return array_keys($fields);
    }

    //This is the main function that will be called when the module is loaded
    public static function index($data=[]) {
        exit(\resources::show(__CLASS__)); //This will call all assets to be loaded for this module
    }

    //This is the CSS for this module. It will bind with the rest of the app dinamically
    public static function css($data=[]) {
        ?><style>
            /* More CSS for this module */
            .container { max-width: 600px; margin:auto; padding:1rem; }
            .row,.line { margin:1rem 0; }
            .footer { margin-top:4rem; }

            #todo-list { margin:4rem 0px; padding:1rem 2rem; background-color:rgb(153,153,153,0.2); }
            #todo-list li:not(:last-child) { margin-bottom:1rem; }
        </style><?php
        \globals::css();
    }

    //This is the core HTML for this module.
    public static function html($data=[]) {
        ?><!-- Here goes the main screen called home with the "homelander" class which makes this primary -->
        <div id="home" class="screen homelander">
           <div class="container">
                <div class="row">
                    <div class="line">
                        <h1>A Simple TO-DO LIST</h1>
                        <div class="line">
                            <div style="background-color:<?=((!pdo_isconnected()) ? 'red' : 'green');?>;border-radius:50%;width:6px;height:6px;display:inline-block;margin-right:0.5rem;"></div> 
                            Database <?=((!pdo_isconnected()) ? 'not ' : '');?>connected
                            <br style="clear:both;"><br>
                        </div>
                    </div>
                    <div class="line">
                        <button class="btn btn-primary" onclick="todo.create();">Add</button>
                        <button class="btn btn-primary" onclick="todo.refresh();">Refresh</button>
                    </div>
                    <div class="line">
                        <ul id="todo-list"></ul>
                    </div>
                </div>
                <div class="footer">
                    <div class="line">
                        <a href="javascript:void(0);" onclick="switchtab('#thiscode');">See this implementation code</a>
                        <br style="clear:both;"><br>
                        <a href="javascript:void(0);" onclick="switchtab('#about');">See more about the framework</a>
                    </div>
                </div>
            </div>
        </div>
        <!-- Here is another screen accessible by "switchtab()" function from `globals` module -->
        <div id="about" class="screen">
            <div class="container">
                <div class="row">
                    <div class="line">
                        <a href="javascript:void(0);" onclick="switchtab('#home',true);">< Go back</a>
                    </div>
                </div>
                <div class="row">
                    <div class="line">
                        <?=markdowntohtml(@file_get_contents(REPODIR.'/README.md'));?>
                    </div>
                </div>
            </div>
        </div>
        <!-- Here is one more screen -->
        <div id="thiscode" class="screen">
            <div class="container">
                <div class="row">
                    <div class="line">
                        <a href="javascript:void(0);" onclick="switchtab('#home',true);">< Go back</a>
                    </div>
                </div>
                <div class="row">
                    <div class="line">
                        <h1>A Simple TO-DO LIST Implementation</h1>
                        <?=markdowntohtml("```\n".@file_get_contents(__FILE__)."\n```");?>
                    </div>
                </div>
            </div>
        </div><?php
    }

    //This is the JS for the usage of the HTML above
    public static function js($data=[]) {
        ?><script>
            /* This variable is responsable for the functions of "todo" method */
            var todo = {
                create: function() {
                    let title = prompt('Title');
                    if(empty(title)) return;
                    /* We can trigger the `post` function from `globals` everytime we need access an API */
                    post("<?=__CLASS__;?>/create",{
                        'title':title,
                        ':userid':getitem('@userid_example'), /* ":" would not be necessary on create, but its here for permission handling */
                        'details-created':(new Date().toLocaleString()), /* Dashed keys will store json values */
                        'details-user-desc':"nêw thing",
                        'details-user-agent':"<?=filtereduseragent();?>" /* Here is another useful function from `globals` */
                    },function(data){
                        if(!data || !data.result || data.error) return toast('Error');
                        todo.refresh();
                    });
                },
                update: function(el) {
                    let id = $(el).parent().attr('ref');
                    if(empty(id)) return;
                    let title = prompt('New title');
                    if(empty(title)) return;
                    post("<?=__CLASS__;?>/update",{
                        ':id':id,
                        ':userid':getitem('@userid_example'),
                        'title':title,
                        'details-updated':(new Date().toLocaleString()),
                        'details-user-desc':'spécial char',
                        'details-user-updating':1
                    },function(data){
                        if(!data || !data.result || data.error) return toast('Error');
                        todo.refresh();
                    });
                },
                delete: function(el) {
                    let id = $(el).parent().attr('ref');
                    if(empty(id)) return;
                    post("<?=__CLASS__;?>/delete",{':id':id, ':userid':getitem('@userid_example')},function(data) {
                        if(!data || !data.result || data.error) return toast('Error');
                        $(el).parent().remove();
                        if(empty($('#todo-list').html()))
                            $('#todo-list').html('<i>Empty list</i>');
                    });
                },
                refresh: function() {
                    $('#todo-list').html(`<li>
                        <span class="loadblink">Title of task</span><br>
                        <span class="loadblink">00/00/0000 00:00:00</span></li>`).append($('#todo-list').html());
                    post("<?=__CLASS__;?>/list",{':userid':getitem('@userid_example')},function(data) {
                        if(!data || !data.result || data.error) return $('#todo-list').html('<i>Empty list</i>');
                        $('#todo-list').html('');
                        $(data.data).each(function(index,item){
                            $('#todo-list').append(`
                                <li class="todo-item" ref="${item.id}">
                                    ${item.title}<br><font style="color:#999;font-size:12px;">${item.details.created}</font>&nbsp;&nbsp;
                                    <a href="#" onclick="todo.update(this);" 
                                       style="text-decoration:none;font-size:16px;vertical-align:middle;">
                                        &#9998;</a>&nbsp;
                                    <a href="#" onclick="todo.delete(this);" 
                                       style="text-decoration:none;font-size:20px;vertical-align:middle;">
                                        &times;</a>
                                </li>
                            `);
                        });
                    },
                    function(error){ 
                        toast('Connection error'); 
                    });
                }
            }

            /* This is an event handler that will be fired every screen loading */
            $(window).on('screen_onload',function(state){
                /* We figure out if the screen that is loading is actually the home screen */
                if(state.to !== '#home') return;
                /* Lets generate an user id if you do not have one for testing purposes */
                if(empty(getitem('@userid_example')))
                    setitem('@userid_example',(Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15)));
                /* We trigger the listing function */
                todo.refresh();
            });
            /* More JS for this module */
        </script><?php
        \globals::js(); //Include globals JS for screen switching and other stuff
    }
}