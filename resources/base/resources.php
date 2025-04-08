<?php
class resources {
    use \openapi;

    public static $openapiOnly = ['get'];

    public static $allowedMethods = ['html','css','js'];

    public static function show($from='app',$server='/',$assets='/assets/www/') {
        if(!is_string($from)) return -1;
        $content = @file_get_contents(REPODIR.$assets.'index.html');
        $content = preg_replace('/(var namespaceaddress = ")(.*?)(";)/', '$1'.$from.'$3', $content);
        $content = preg_replace('/(var serveraddress = ")(.*?)(";)/', '$1'.$server.'$3', $content);
        $content = str_replace('<script src="js/firebase.js"></script>','',$content);
        $content = str_replace('<script src="cordova.js"></script>','',$content);
        $content = str_replace('<script src="','<script src="'.$assets,$content);
        $content = str_replace('<link href="','<link href="'.$assets,$content);
        $content = str_replace('src:url("','src:url("'.$assets,$content);
        return $content;
    }

    public static function listmodules($root='', $path='/', &$modules=[]) {
        if(!is_string($root)) return -1;
        if(empty($root)) $root = REPODIR.DIRECTORY_SEPARATOR.'resources';
        if(is_array($dir = @scandir($root.$path)))
          foreach($dir as $item)
            if($item !== '.' && $item !== '..' && ($item[0] ?? '') !== '.')
              if(is_dir(realpath($root.$path.$item))) self::listmodules($root,$path.$item.'/', $modules);
              else $modules[] = trim(str_replace('/','\\',preg_replace('/[^0-9a-zA-Z\_\/]/','',str_ireplace('.php','',$path.$item))),'\\');
        return $modules;
    }

    public static function get($data=[]) {
        $root = ($data['namespace'] ?? ($data['name'] ?? ($data['space'] ?? ($data['root'] ?? 'app'))));
        $methods = ($data['methods'] ?? ($data['method'] ?? ($data['types'] ?? ($data['type'] ?? ''))));
        $methods = array_filter(explode(',',@preg_replace('/[^0-9a-zA-Z\,\_]/','',$methods)));
        $retorno = ['result'=>1, 'buffer'=>null];
        if(!(is_array($modules = self::listmodules(REPODIR.DIRECTORY_SEPARATOR."resources".DIRECTORY_SEPARATOR."$root")))) return false;
        /* load root module first */
        foreach($methods as $method) 
            if(!empty($call = str_replace($root,'',$method)))
                if(in_array($call,self::$allowedMethods))
                    if((module_exists("\\$root",$call) && !empty($f = "\\$root::$call"))
                    ||((module_exists("\\$root",$method) && !empty($f = "\\$root::$method"))))
                        if(@ob_start() && (@$f() ?? true) && ($r = @ob_get_clean()))
                            $retorno[$method] = ($retorno[$method] ?? '')."\n".self::parselines(@$r);
        /* load every module on the folder except the root one */
        foreach($methods as $method) 
            if(!empty($call = str_replace($root,'',$method)))
                if(in_array($call,self::$allowedMethods))
                    foreach($modules as $c) if($c !== $root)
                        if(((module_exists(($cd="\\$root\\$c"),$call) || module_exists(($cd="\\$c"),$call)) && (!empty($f = "$cd::$call")))
                        ||(((module_exists(($cd="\\$root\\$c"),$method) || module_exists(($cd="\\$c"),$method)) && (!empty($f = "$cd::$method")))))
                            if(@ob_start() && (@$f() ?? true) && ($r = @ob_get_clean()))
                                $retorno[$method] = ($retorno[$method] ?? '')."\n".self::parselines($r);
        /* return modules content */
        return $retorno;
    }

    public static function parselines($data='') {
        if(is_array($data)) return '';
        $result = ''; $data = explode("\n", ($data."\n"));
        $rmnbsp = function($t) { while (strpos($t,($s='   ')) !== false) $t = str_replace($s,' ',$t); return $t; };
        foreach($data as $line) if(!empty($line = trim($rmnbsp($line)))) $result .= $line."\n";
        return $result;
    }

}