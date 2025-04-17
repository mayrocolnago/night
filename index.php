<?php
if(!defined("REPODIR")) define("REPODIR", __DIR__);
if(!defined("THISURL")) define("THISURL", ('https://'.($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost'))));
  
spl_autoload_register(function($class) {
  if($class === 'openapi') return true;
  if(substr(($class=str_replace('\\','/',$class)),0,1) !== '/') $class = "/$class";
  if(!isset($_SERVER[$m = 'MODULES_PATH_ITERATOR']))
    $_SERVER[$m] = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(__DIR__.DIRECTORY_SEPARATOR.'resources'),
      RecursiveIteratorIterator::CHILD_FIRST, RecursiveIteratorIterator::SELF_FIRST);
  foreach(($i = $_SERVER[$m]) as $f)
    if(!$i->isFile())
      if(!(strpos(($fp=$f->getPathName()),DIRECTORY_SEPARATOR.'..') !== false))
        if(file_exists($fpd="$fp$class.php")) {
          @include_once($fpd);
          return true; }
});

if(!empty(($configdir = REPODIR).($configname = "config.".($_SERVER['PROJECT'] = basename(REPODIR)).".json").($confignp = "config.json")))
  for($i=0;$i<5;$i++) if(file_exists($configfile = "$configdir".DIRECTORY_SEPARATOR."config".DIRECTORY_SEPARATOR.".$configname") || file_exists($configfile = "$configdir".DIRECTORY_SEPARATOR.".$configname")
    ||(file_exists($configfile = "$configdir".DIRECTORY_SEPARATOR."config".DIRECTORY_SEPARATOR."$configname")) || file_exists($configfile = "$configdir".DIRECTORY_SEPARATOR."$configname")
    ||(file_exists($configfile = REPODIR.DIRECTORY_SEPARATOR.".$confignp")) || file_exists($configfile = REPODIR.DIRECTORY_SEPARATOR."$confignp"))
  $_SERVER = @array_merge($_SERVER, (@json_decode(@file_get_contents($configfile), true) ?? [])); else $configdir = realpath("$configdir".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR);

if(file_exists($srvcfg = (__DIR__.DIRECTORY_SEPARATOR.'config.inc.php'))) @include($srvcfg);

if(!file_exists($almodsfile = (__DIR__.DIRECTORY_SEPARATOR.'autoloader.json'))
&&(!file_exists($almodsfile = (__DIR__.DIRECTORY_SEPARATOR.'.autoloader.json'))))
  $almods = ['\\pdoclass', '\\globals'];
  
if(is_array($almods ?? '') || is_array($almods = @array_values(@json_decode(@file_get_contents($almodsfile)))))
  foreach($almods as $am) if(class_exists($am)) new $am;

$_SERVER['PRODUCTION'] = (!($_SERVER['DEVELOPMENT'] ?? false));

if($_SERVER['DEVELOPMENT'] ?? false) @ini_set('display_errors', '1'); 
@error_reporting(($_SERVER['DEVELOPMENT'] ?? false)?1:0);

@date_default_timezone_set('America/Sao_Paulo');
@session_start();

trait openapi {
  public static $headerContentType = 'application/json';
  public static $headerCORS = '*';

  public static function result($return=null) {
    if(is_null($return)) return null;
    if(!is_array($return)) $return = ['result'=>$return];
    if(!isset($return['result'])) $return = ['result'=>count($return), 'data'=>$return];
    if(is_numeric($return['result']) && floatval($return['result']) < 0) $return['error'] = true;
    if(!empty(self::$headerContentType)) $return['header'] = @header("Content-Type: ".self::$headerContentType);
    if(!empty(self::$headerCORS)) $return['policy'] = @header("Access-Control-Allow-Origin: ".self::$headerCORS);
    if($_SERVER['DEVELOPMENT'] ?? false) $return = @array_merge(['development'=>@array_merge($_REQUEST,
      ['class'=>($_SERVER['class'] ?? 'site'), 'function'=>($_SERVER['function'] ?? 'index')])],$return);
    if(is_array($return['data'] ?? false)) $return['page'] = intval($_REQUEST['page'] ?? 1);
    $return['elapsed'] = floatval(number_format(($mt = microtime(true)) - ($_SERVER["REQUEST_TIME_FLOAT"] ?? $mt),2,'.',''));
    if(!empty($_SERVER['curl_timer'] ?? 0)) {
      $return['overelapsed'] = floatval(number_format($_SERVER['curl_timer'],2,'.','')); 
      $return['elapsed'] = floatval(number_format(($return['elapsed'] - $_SERVER['curl_timer']),2,'.','')); }
    $return['http'] = http_response_code(); $return['state'] = 1;
    return (json_encode($return, JSON_PRETTY_PRINT)); 
  }
}

if(!empty($c = '\\'.str_replace('/','\\',preg_replace('/[^a-zA-Z0-9\_\/]/','',($_SERVER[$cr='class'] = ($_REQUEST[$cr] ?? 'site'))))))
  if(!empty($f = preg_replace('/[^a-zA-Z0-9\_]/','',($_SERVER[$cf='function'] = ($_REQUEST[$cf] ?? 'index'))))) {
    if($c === '\public') $c = '\site';
    /* request from all raw types */
    try { if(!empty($phpinput = @json_decode(@file_get_contents('php://input'),true)))
      $_REQUEST = @array_replace_recursive($_REQUEST,$phpinput); } catch(Exception $err) { }
    /* exhibit api or views */
    try { unset($_REQUEST[$cr]); unset($_REQUEST[$cf]); } catch(Exception $err) { }
    if(class_exists($c) && is_array($ot = @array_values(($rc = new ReflectionClass($c))->getTraitNames())))
      if(in_array('openapi',$ot) && method_exists($c,'result'))
        if(empty($c::$openapiOnly ?? []) || (is_array($c::$openapiOnly) && in_array($f,$c::$openapiOnly)))
          if(($dm=method_exists($c,$f)) || method_exists($c,'__callStatic'))
            if(is_callable($method = "$c::$f"))
              exit(((!is_null($r = ($c::result($method($_REQUEST)) ?? null))) ? $r : '')); }
