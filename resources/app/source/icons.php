<?php
namespace app\sources;

class icons {
    use \openapi;

    public static $default = 'regular/square';


    public static function get($data=[]) {
        @header('Access-Control-Allow-Origin: *');
        
        if(!is_string($icon = ($data['name'] ?? ($data['path'] ?? $data)))) $icon = self::$default;
        else if(empty($icon = preg_replace('/[^0-9a-z\/\-]/','',strtolower($icon)))) $icon = self::$default;
        
        if(!file_exists($file = REPODIR."/assets/www/svgs/$icon.svg")) $file = REPODIR."/assets/www/svgs/".self::$default.".svg";
        exit(@file_get_contents($file));
    }


    public static function js($data=[]) { ?><script>
            var icons_processed = [];

            function print_icons() {
                $('i[class^="fa-"]:not(.compiled):visible').each(function(index,item){
                    $(item).addClass('compiled');
                    let cname = 'square';
                    let bname = 'regular';
                    let classes = $(item).attr('class');
                    classes = String(classes).split(' ');
                    $(classes).each(function(ic,cl){ 
                        if(String(cl).indexOf('fa-') < 0) return;
                        if(cl == 'fa-brands') return (bname = 'brands');
                        if(cl == 'fa-regular') return (bname = 'regular');
                        if(cl == 'fa-solid') return (bname = 'solid');
                        cname = String(cl).replace('fa-',''); 
                    });

                    let printicon = function(el,content){
                        let size = '';
                        if(empty(content)) return;
                        if(String(size=($(el).css('font-size'))).replace('null','').replace('undefined','') !== '') {
                            size = `width:${size};height:${size};`;
                            $(el).css('font-size',''); }
                        content = String(content).replace(`<svg `,`<svg style="${size+($(el).attr('style') || '')}" `);
                        content = String(content).replace(`<path `,`<path fill="currentColor" `);
                        $(content).insertBefore(el);
                        $(el).remove();
                    };
                    $(item).html('&square;');

                    if(typeof cordova !== "undefined" && !thisisiphone) {

                        let link = `svgs/${bname}/${cname}.svg`;
                        $.ajax({ "url":link, "dataType": "text", 
                                 "success":function(data){ if(!empty(data)) printicon(item, data); } });

                    } else {
                        let link = `${serveraddress}app/source/icons/get?name=${bname}/${cname}`;
                        let cachename = `@cache_icon_${bname}_${cname}`;
                        let cache = getitem(cachename);

                        if(!empty(cache)) {
                            printicon(item, cache);
                            setTimeout(function(){ 
                                if(icons_processed.indexOf(cachename) > -1) return;
                                else icons_processed.push(cachename);
                                try { $.ajax({ "url":link, "dataType": "text", 
                                        "success":function(data){ if(!empty(data)) setitem(cachename, data); }
                                    }); } catch(err) { } },2100);
                        } else
                            $.ajax({ "url":link, "dataType": "text", "success":function(data){ 
                                if(!empty(data)) setitem(cachename, data);
                                printicon(item, data);
                            } });
                    }
                });
            }

            $(window).on('screen_onload',function(state){ print_icons(); });

        </script><?php
    }

}