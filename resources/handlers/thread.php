<?php
trait thread {

    /*
        # HOW TO USE

          - `use \thread;` on your class
          - `self::async(function(){ //code });` to run code asyncronously
          - `self::async(function(){ echo $var; },['var'=>'value']);` to pass variables to async function
    */

    protected static function async($func,$variables=[]) { 
        if(!function_exists('shell_exec')) return null;
        if(!is_array($variables)) $variables = [];
        if(!is_callable($func)) return null;
        $refl = new \ReflectionFunction($func); $rp = [];
        $code = explode("\n", file_get_contents($gfn = ($refl->getFileName())));
        $code = implode("\n",array_slice($code, ($begin = $refl->getStartLine()-1), (($refl->getEndLine())-($begin))));
        $code = str_replace((explode('{',$code.'{')[0] ?? '').'{','',$code);
        $code = str_replace('}'.(($lc = explode('}','}'.$code))[count($lc)-1] ?? ''),'',$code);
        $trgg = "nohup setsid ".PHP_BINDIR."/php ".(($inipath = php_ini_loaded_file()) ? "-c $inipath " : "");
        $incs = ""; foreach(get_included_files() as $ic) if(realpath($ic) !== realpath($gfn)) $incs .= " @include_once('$ic'); ";
        if(!empty($params = $refl->getParameters()) && is_array($dfvars = get_defined_vars()))
          foreach($params as $k => $v)
          if(!empty($k = ($v->name ?? null)) && !isset($variables[$k])) 
            $variables[$k] = ($dfvars[$k] ?? ($_REQUEST[$k] ?? ($_SERVER[$k] ?? null)));
        shell_exec($bash = ($trgg."-r \"@parse_str(base64_decode('".base64_encode(http_build_query($variables))."')); ".
            "$incs @eval(base64_decode('".base64_encode($code)."'));\" > /dev/null 2>&1 &"));
        return $bash;
    }

}