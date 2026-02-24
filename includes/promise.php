<?php
class Promises { public static $list=[]; }
class Promise {
  public $id=null;
  private function getid($func){
    if(!is_callable($func)||($func instanceof \Promise)) return md5(uniqid());
    $refl=new \ReflectionFunction($func);
    $code=explode("\n",file_get_contents($gfn=$refl->getFileName()));
    $code=implode("\n",array_slice($code,$refl->getStartLine()-1,$refl->getEndLine()-$refl->getStartLine()+1));
    $code=str_replace((explode('{',$code.'{')[0]??'').'{','',$code);
    $code=str_replace('}'.(($lc=explode('}','}'.$code))[count($lc)-1]??''),'',$code);
    return md5($code); }
  public function __construct($func=null){
    if($func instanceof \Promise) return $func;
    if(is_callable($func))$this->spawn($func); }
  private function spawn($func=null){
    if(!is_callable($func)) return;
    if(!function_exists('shell_exec')) return;
    $refl=new \ReflectionFunction($func);
    if(!is_array($variables=$refl->getStaticVariables())) $variables=[];
    $code = explode("\n", file_get_contents($gfn = ($refl->getFileName())));
    $code = implode("\n",array_slice($code, ($begin = $refl->getStartLine()-1), (($refl->getEndLine())-($begin))));
    $code = str_replace((explode('{',$code.'{')[0] ?? '').'{','',$code);
    $code = str_replace('}'.(($lc = explode('}','}'.$code))[count($lc)-1] ?? ''),'',$code);
    $trgg = "nohup setsid ".PHP_BINDIR.DIRECTORY_SEPARATOR."php ".(($inipath = php_ini_loaded_file()) ? "-c $inipath " : "");
    $incs = ""; foreach(get_included_files() as $ic) if(realpath($ic) !== realpath($gfn)) $incs .= " @include_once('$ic'); ";
    if(!empty($params = $refl->getParameters()) && is_array($dfvars = get_defined_vars()))
      foreach($params as $k => $v) if(!empty($k = ($v->name ?? null)) && !isset($variables[$k])) 
        $variables[$k] = ($dfvars[$k] ?? ($_REQUEST[$k] ?? ($_SERVER[$k] ?? null)));
    shell_exec($bash = ($trgg."-r \"@parse_str(base64_decode('".base64_encode(http_build_query($variables))."')); ".
        "$incs @eval(base64_decode('".base64_encode($code)."'));\" > /dev/null 2>&1 &")); return $bash; }
  public function then($func){
    if(!is_callable($func)) return $this;
    if(empty(\Promises::$list) && !function_exists('fastcgi_finish_request')) @ob_start();
    $this->id = $this->getid($func);
    \Promises::$list[$this->id] = $func;
    return $this; } }
function async($func) {
  if($func instanceof \Promise) return $func;
  $p=new \Promise(null);
  return $p->then($func); }
function await($promise){
  if($promise instanceof \Closure) $promise = async($promise);
  if(!($promise instanceof \Promise)) return null;
  if(empty($id = $promise->id)) return null;
  if(!isset(\Promises::$list[$id])) return null;
  $func = \Promises::$list[$id];
  try { unset(\Promises::$list[$id]);
    return (is_callable($func) ? $func(null) : null);
  }catch(\Throwable $e){ return null; } }

@register_shutdown_function(function(){
  if(empty(\Promises::$list))return;
  try { if(function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
    else { $size = @ob_get_length();
      @header("Content-Length: $size");
      @header("Connection: close");
      @header("Content-Encoding: none");
      @ob_end_flush(); @ob_flush(); @flush();
      if(@session_id()) @session_write_close(); }
  } catch(Exception$e){}
    foreach(\Promises::$list as $id=>$func)
      if(is_callable($func)) { try{ $func(null); }catch(Exception$e){ } }
});