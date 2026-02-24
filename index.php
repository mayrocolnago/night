<?php
@header("Access-Control-Allow-Origin: *");
@header("Access-Control-Allow-Methods: GET, POST");
@header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
@session_start();

if(!defined("REPODIR")) define("REPODIR", __DIR__);
if(!defined("THISURL")) define("THISURL", ('http'.((!empty($_SERVER['HTTPS'] ?? '1')) ? 's':'').'://'.($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost'))));

if(!($_SERVER['DEVELOPMENT'] = empty($env=THISURL)))
  if(strpos($env,'localhost') !== false
  ||(strpos($env,'.night') !== false)
  ||(strpos($env,'.code') !== false)
  ||(strpos($env,'.dev') !== false)
) $_SERVER['DEVELOPMENT'] = true;
  
@spl_autoload_register(function($c) {
  if(empty($class=trim(@str_replace('\\',DIRECTORY_SEPARATOR,$c),':\\\/'))) return false;
  foreach(['resources','core','handlers','includes'] as $d) 
    if(file_exists($fpd=REPODIR.DIRECTORY_SEPARATOR.$d.DIRECTORY_SEPARATOR.$class.'.php')) {
      @include_once($fpd); if(!class_exists(@str_replace(DIRECTORY_SEPARATOR,'\\',"\\$class"),false)) continue;
      return true; } return false; });

if(!empty(($configdir = REPODIR).($configname = "config.".($_SERVER['PROJECT'] = substr(($r='-'.basename(REPODIR)),(strrpos($r,'-')+1),strlen($r))).".json").($confignp = "config.json")))
  for($i=0;$i<5;$i++) if(file_exists($configfile = "$configdir".DIRECTORY_SEPARATOR."config".DIRECTORY_SEPARATOR.".$configname") || file_exists($configfile = "$configdir".DIRECTORY_SEPARATOR.".$configname")
    ||(file_exists($configfile = "$configdir".DIRECTORY_SEPARATOR."config".DIRECTORY_SEPARATOR."$configname")) || file_exists($configfile = "$configdir".DIRECTORY_SEPARATOR."$configname")
    ||(file_exists($configfile = REPODIR.DIRECTORY_SEPARATOR.".$confignp")) || file_exists($configfile = REPODIR.DIRECTORY_SEPARATOR."$confignp"))
  $_SERVER = @array_merge($_SERVER, (@json_decode(@file_get_contents($configfile), true) ?? [])); else $configdir = realpath("$configdir".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR);

if(file_exists($incdir=REPODIR.DIRECTORY_SEPARATOR.($includespath='includes')))
  if(is_array($incdl = @scandir($incdir, 1))) foreach($incdl as $inc)
    if(substr(strtolower($incfile = $incdir.DIRECTORY_SEPARATOR.$inc),-4) === '.php') {
      if(is_file($incfile)) @include_once($incfile);
      if((!class_exists(($ic=preg_replace('/\.php$/i','',$inc)),false))
      &&((!class_exists(($ic="\\$includespath\\".preg_replace('/\.php$/i','',$inc)),false)))) continue;
      try { if(method_exists($ic,($rst='__onload')) && is_callable("$ic::$rst"))
        $ic::__onload($_REQUEST); } catch(Exception $err) { } }

$_SERVER['PRODUCTION'] = (!($_SERVER['DEVELOPMENT'] ?? false));
if($_SERVER['DEVELOPMENT'] ?? false) @ini_set('display_errors', '1'); 
@error_reporting(($_SERVER['DEVELOPMENT'] ?? false)?1:0);

class route {
  private $contentType = 'application/json';
  private $CORS = '*';
  private $data = null;
  private $result = null;
  private $output = null;
  public function __set($name,$value) {
    if(!is_array($vars = get_class_vars(__CLASS__))) return;
    if(!in_array($name, array_keys($vars))) return;
    if($name === 'CORS') @header("Access-Control-Allow-Origin: $value");
    if($name === 'contentType') @header("Content-Type: $value");
    if($name === 'http') @http_response_code($value);
    else $this->$name = $value; }
  public function __get($name) {
    $orgname = $name; $name = strtolower($name);
    if($name === 'params') return $_REQUEST;
    if($name === 'body') return $_REQUEST;
    if($name === 'ip') return ((function_exists('remote_addr')) ? remote_addr() : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
    if($name === 'server') return ($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost'));
    if($name === 'method') return strtoupper($_SERVER['REQUEST_METHOD'] ?? null);
    if($name === 'uri') return ($_SERVER['REQUEST_URI'] ?? '/');
    if($name === 'url') return THISURL;
    if($name === 'all') return $_SERVER;
    return ($_SERVER[strtoupper($name)] ?? ($_SERVER[$orgname] ?? ($_SERVER[$name] ?? ($this->$name ?? null)))); }
  public function __invoke() { return $this->data; }
  public function clear() { $this->output = $this->data = null; return $this; }
  public function exit($output='') { echo $output; return $this->clear(); }
  public function code($integer=0) { return $this->json($integer); }
  public function data($output=null) { $this->data = $output; return $this; }
  public function json($return=null, $code=null, $applycode=null) { $this->data = $return;
      if((is_bool($return) || is_numeric($return)) && is_array($code)) $return = ['result'=>$return, 'data'=>($this->data=$code)];
      if(is_string($return) && (is_numeric($code) || is_bool($code))) $return = ['result'=>$code, 'message'=>$return];
      if(!is_array($return)) { $return = ['result'=>$return]; $rtcv = true; }
      if((!($rtcv ?? false)) && !isset($return['result'])) $return = ['result'=>count($return), 'data'=>$return];
      if(is_numeric($return['result']) && floatval($return['result']) < 0) $return['error'] = true;
      if(!empty($sht=($this->contentType))) $return['header'] = @header("Content-Type: $sht");
      if(!empty($shc=($this->CORS))) $return['policy'] = @header("Access-Control-Allow-Origin: $shc");
      if($_SERVER['DEVELOPMENT'] ?? false) $return = @array_merge(['development'=>@array_merge($_REQUEST,
        ['class'=>($_SERVER['class'] ?? 'site'), 'function'=>($_SERVER['function'] ?? 'index')])],$return);
      if(is_array($return['data'] ?? false)) $return['page'] = intval($_REQUEST['page'] ?? 1);
      $return['elapsed'] = floatval(number_format(($mt = microtime(true)) - ($_SERVER["REQUEST_TIME_FLOAT"] ?? $mt),2,'.',''));
      if(!empty($_SERVER['curl_timer'] ?? 0)) {
        $return['overelapsed'] = floatval(number_format($_SERVER['curl_timer'],2,'.','')); 
        $return['elapsed'] = floatval(number_format(($return['elapsed'] - $_SERVER['curl_timer']),2,'.','')); }
      if((!empty($code)) && ($applycode ?? ($_SERVER['DEFAULT_RESPONSE_APPLYCODE'] ?? false))) http_response_code($code);
      $return['http'] = http_response_code(); $return['state'] = 1;
      $this->output = (json_encode(($this->result = $return), JSON_PRETTY_PRINT));
      return $this;
  }
}
@class_alias('route', 'response');
@class_alias('route', 'request');
function route() { static $instance = null; if($instance === null) $instance = new route(); return $instance; }
function request() { static $instance = null; if($instance === null) $instance = new request(); return $instance; }
function response($s=null, $c=null, $a=null) { if(!is_null($s)) return response()->json($s, $c, $a);
  static $instance = null; if($instance === null) $instance = new response(); return $instance; }
function result(...$p) { return response(...$p); }

if(!empty($c = '\\'.str_replace('/','\\',preg_replace('/[^a-zA-Z0-9\_\/]/','',($_SERVER[$cr='class'] = ($_REQUEST[$cr] ?? 'site'))))))
  if(!empty($f = preg_replace('/[^a-zA-Z0-9\_]/','',($_SERVER[$cf='function'] = ($_REQUEST[$cf] ?? 'index'))))) {
    if($c === '\public') $c = '\site';
    /* request from all raw types */
    try { if(!empty($phpinput = @json_decode(@file_get_contents('php://input'),true)))
      $_REQUEST = @array_replace_recursive($_REQUEST,$phpinput); } catch(Exception $err) { }
    /* exhibit api or views */
    try { unset($_REQUEST[$cr]); unset($_REQUEST[$cf]); } catch(Exception $err) { }
    if(class_exists($c)) foreach([$f, '__call', '__callStatic'] as $fme) if(method_exists($c,$fme))
      if(($rc = (new ReflectionClass($c))) && (!($rc->isTrait() || $rc->isAbstract())) && ($rf = $rc->getMethod($fme)))
        if(($rf->getReturnType()) && (substr(@strtolower($rf->getReturnType()->getName() ?? '.'),-7) === 'route'))
          if(($st = ($rf->isStatic() ?? false)) || (($c = new $c) || true)) {
            try { try { if((!is_null($r = (($st) ? ($c::$f($_REQUEST) ?? null) : ($c->$f($_REQUEST) ?? null)))))
              if($r instanceof \route) exit(response()->output); } catch(Exception $e) {
                if($_SERVER['DEVELOPMENT'] ?? false) var_dump($e); } } catch(Throwable $t) {
                if(!(strpos($te=@strtolower($t->getMessage()),'instance of route') !== false))
                if($_SERVER['DEVELOPMENT'] ?? false) var_dump($t); } } }