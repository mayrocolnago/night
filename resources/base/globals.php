<?php
class globals {

  public static function database() {
      pdo_query("CREATE TABLE IF NOT EXISTS global_configs (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        ckey varchar(100) NOT NULL,
        cvalue longtext NULL,
        updated_at bigint(20) NULL DEFAULT '0',
        PRIMARY KEY (id),
        UNIQUE KEY (ckey))");
  }

  /* simpler but stronger version of curl function */
  public static function curlsend($address, $data=null, $timeout=0, $content="http_build_query", $curlopts=[]) {
    $data = (is_array($data)) ? ((function_exists($content) || function_exists($content = $content.'_encode')) ? $content($data) : $data) : $data;
    $ch = curl_init();
    $_SERVER['curl_timer'] = ($_SERVER['curl_timer'] ?? 0);
    $_SERVER['curl_timer_start'] = microtime(true);
    if(!isset($curlopts[CURLOPT_HTTPHEADER]) && $content === "json_encode") $curlopts[CURLOPT_HTTPHEADER] = ['Content-Type: application/json'];
    if(!isset($curlopts[CURLOPT_URL])) curl_setopt($ch, CURLOPT_URL, $address);
    if(!isset($curlopts[CURLOPT_RETURNTRANSFER])) curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if(!isset($curlopts[CURLOPT_FOLLOWLOCATION])) curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    if(!isset($curlopts[CURLOPT_SSL_VERIFYPEER])) curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    if(!isset($curlopts[CURLOPT_SSL_VERIFYHOST])) curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    if(!isset($curlopts[CURLOPT_USERAGENT])) curl_setopt($ch, CURLOPT_USERAGENT, self::useragent());
    if(!isset($curlopts[CURLOPT_POST])) curl_setopt($ch, CURLOPT_POST, (($data !== null) ? true : false));
    if(!isset($curlopts[CURLOPT_POSTFIELDS]) && $data !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    if(!isset($curlopts[CURLOPT_CONNECTTIMEOUT]) && is_numeric($timeout) && $timeout > 0) curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    if(!isset($curlopts[CURLOPT_TIMEOUT]) && is_numeric($timeout) && $timeout > 0) curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt_array($ch, $curlopts);
    $result = curl_exec($ch);
    curl_close($ch);
    $_SERVER['curl_timer_elapsed'] = floatval(number_format(($mt = microtime(true)) - ($_SERVER['curl_timer_start'] ?? $mt),2,'.',''));
    $_SERVER['curl_timer'] = $_SERVER['curl_timer'] + $_SERVER['curl_timer_elapsed'];
    return $result;
  }

  /* improved version of method_exists to detect modules */
  public static function module_exists(...$params) {
    $ns = ''; $result = 0;
    if(empty($params) || !is_array($params)) return false;
    if(@call_user_func_array('method_exists',$params)) return true;
    foreach($params as $param)
      if(is_string($param))
        if(!empty($param = str_replace('(','',($pp = str_replace("/","\\",preg_replace('/[^0-9a-zA-Z\/\:\(]/','',str_replace("\\","/",$param)))))))
          if(strpos($param,'::') !== false && is_array($x = explode('::',$param)))
            $result = ((method_exists(($ns=("\\".trim($x[0],"\\"))),$x[1]))?(++$result):$result);
          else if(strpos($param, "\\") !== false)
              $result = ((class_exists(($ns=("\\".trim($param,"\\")))) || trait_exists($ns))?(++$result):$result);
            else if((!(strpos($pp,'(') !== false)) && (class_exists($ce=("\\".trim($param,"\\"))) || trait_exists($ce)) && !empty($ns=$ce)) ++$result;
              else $result = ((method_exists($ns,$param))?(++$result):$result);
    return ($result == count($params));
  }

  /* list modules */
  public static function listmodules($path = (REPODIR.'/resources/'), &$modules=[]) {
    if(is_array($dir = scandir($path)))
      foreach($dir as $item)
        if($item !== '.' && $item !== '..' && ($item[0] ?? '') !== '.')
          if(is_dir(realpath($path.'/'.$item))) self::listmodules($path.'/'.$item.'/', $modules);
          else $modules[] = preg_replace('/[^a-z]/','',str_ireplace('.php','',$item));
    return $modules;
  }

  /* make sure variables are arrays */
  public static function validatearray($array=[]) {
    if(is_object($array)) $array = json_encode($array);
    if(!is_array($array)) $array = @json_decode($array,true);
    if(!is_array($array)) $array = [];
    return $array;
  }

  /* sort a whole array by key recursively */
  public static function recursiveksort(&$array) {
    foreach ($array as &$value) if (is_array($value)) self::recursiveksort($value);
    return ksort($array);
  }


  /* return user-agent info for this ip address */
  public static function useragent($includepush=false) {
    if(!empty($hua = ($_SERVER['HTTP_USER_AGENT'] ?? ''))) return $hua;
    return (('Mozilla/5.0 (Linux; ').($_SERVER['REMOTE_ADDR'] ?? '').'; '.($_SERVER['GATEWAY_INTERFACE'] ?? '').'; '.($_SERVER['HTTP_ACCEPT_CHARSET'] ?? '').'; '.($_SERVER['SERVER_SIGNATURE'] ?? '').
           (($includepush && !empty($_REQUEST['pushid'] ?? '')) ? ' ('.substr(@strval($_REQUEST['pushid']),-7).')' : '')); }

  public static function isplatform($name) { return ($_SERVER['PLATFORM'] == $name); }
    
  /* Get a filtered version of the user-agent */ 
  public static function filtereduseragent($str='',$level=3,$match='lcnm') {
    if($str == '') $str = useragent(false); $str = str_replace(' ','_',trim($str));
    $str = str_replace(array('<','>','Mozilla/5.0_','(Linux;_','(iPhone;_','CPU_','AppleWebKit','Intel_Mac','Version/','KHTML',',','like_Gecko','address','(',')','__'),'_',$str);
    if(!((bool) strpos(strtolower($match),'p'))) $str = str_replace(';','_',$str);
    if(!((bool) strpos(strtolower($match),'b'))) $str = str_replace('/','_',$str);
    if(!((bool) strpos(strtolower($match),'n'))) $str = preg_replace('/[0-9]/','',$str);
    if(!(((bool) strpos(strtolower($match),'l')) || ((bool) strpos(strtolower($match),'c')))) $str = preg_replace('/[a-zA-Z]/','',$str); else
    if(!((bool) strpos(strtolower($match),'m'))) if((bool) strpos(strtolower($str),'mobile')) $str = str_replace(array('Chrome','Firefox','Safari','Opera','like_Mac_'),'_',$str);
    $str = str_replace(' ','_',trim(str_replace('_',' ',$str)));
    while ((bool) strpos($str,'__')) $str = str_replace('__','_',$str);
    if(($level > 0) && ($level < count(explode('_',$str)))) {
      $nstr = explode('_',$str); $str = '';
      for($i=0;$i<$level;$i++) $str .= $nstr[$i].'_'; }
    return trim(str_replace('_',' ',$str));
  }

  /* global configurations */
  public static function setconfig($key=null,$value=null) {
    if(!is_string($key) || empty($key = preg_replace('/[^0-9a-zA-Z\_\-]/','',$key))) return null;
    if(!isset($_SERVER['configs'])) \globals::getconfig();
    if(($_SERVER['configs'][$key] = $value) === null
    &&(is_numeric(pdo_query("DELETE FROM global_configs WHERE ckey='$key'")))) return $value;
    if(is_array($value) || is_object($value)) $value = json_encode($value);
    if(is_numeric($value) && $value === "0") $value = false;
    if(is_string($value)) $value = emojientities($value);
    if(is_bool($value)) $value = (($value) ? "1" : "0");
    \globals::database(); $now = strtotime('now');
    if(pdo_insert('global_configs',['ckey'=>$key, 'cvalue'=>$value, 'updated_at'=>$now])
    ||(pdo_query("UPDATE global_configs SET cvalue=:v, updated_at=:u WHERE ckey=:c",['v'=>$value, 'u'=>$now, 'c'=>$key])))
      return $_SERVER['configs'][$key];
    return null;
  }

  public static function getconfig($key=null,$def=null,$savedef=true) {
    if(is_string($key) && empty($key = preg_replace('/[^0-9a-zA-Z\_\-]/','',$key))) return null;
    if(!isset($_SERVER['configs']) && is_array($_SERVER['configs'] = []) && is_array($db = pdo_fetch_array("SELECT * FROM global_configs")))
    foreach($db as $item) $_SERVER['configs'][($item['ckey'] ?? '')] = ((!is_numeric($v=($item['cvalue'] ?? ''))) ? $v : ((is_float($v)) ? floatval($v) : intval($v)));
    if(empty($key)) return $_SERVER['configs'];
    if(!empty($def) && empty($_SERVER['configs'][$key] ?? null) && $savedef) \globals::setconfig($key,$def);
    return ($_SERVER['configs'][$key] ?? $def);
  }

  /* cookie functions */
  public static function delcookie($key) {
    @setcookie($key, '', (strtotime('now')-1), '/', ($_SERVER['SERVER_NAME'] ?? ""), false, false);
    unset($_COOKIE[$key]);
    return '';
  }

  public static function savecookie($key,$cvalue='',$tempo='auto') {
    if($tempo == 'auto') $tempo = strtotime("+1 year");
    if(isset($_COOKIE[$key])) self::delcookie($key);
    @setcookie($key, ($_COOKIE[$key] = $cvalue), $tempo, '/', ($_SERVER['SERVER_NAME'] ?? ""), false, false);
    return $cvalue;
  }

  /* get the reverse color of a background */
  public static function getcontrastcolor($hexcolor) {
    $hexcolor = str_replace('#','',$hexcolor);
    $r = @hexdec(substr($hexcolor, 0, 2));
    $g = @hexdec(substr($hexcolor, 2, 2));
    $b = @hexdec(substr($hexcolor, 4, 2));
    $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
    return ($yiq >= 128) ? '#333333' : '#f6f6f6'; 
  }

  /* merge two arrays into one */
  public static function mergearrays($a1,$a2=[],$priorfirst=false,&$instant=null) {
    $a1 = @json_decode(json_encode($a1),true);
    $a2 = @json_decode(json_encode($a2),true);
    if(!is_array($a1)) $a1 = array();
    if(!is_array($a2)) $a2 = array();
    foreach ((array)$a2 as $a => $v) 
      if(((in_array($a,$a1)) && (!$priorfirst)) || (!in_array($a,$a1)))
        $a1[$a]=$v;
    if($instant != null) $instant = $a1;
    return $a1;
  }

  /* calculate the remaining time between two timestamp */
  public static function remainingstr($value1,$value2='',$format='{dD} {hH} {iI} {sS}') {
    if($value1 > $value2) $sl = $value1 - $value2;
    else $sl = $value2 - $value1;
    $days = ((int) ($sl / 86400)); $sl = $sl % 86400;
    $hours = ((int) ($sl / 3600)); $sl = $sl % 3600;
    $minutes = ((int) ($sl / 60));
    $seconds = ((int) ($sl % 60));
    $format = str_replace(['{d}','{D}',"{d'}",'{dD}'],[$days, ($ds='dia'.(($days == 1)?'':'s')), (($days > 0)?"$days'":""), (($days > 0)?"$days $ds":"")],$format);
    $format = str_replace(['{h}','{H}','{h:}','{hH}'],[substr('00'.$hours,-2), ($hs='hora'.(($hours == 1)?'':'s')), (($hours > 0)?"$hours:":""), (($hours > 0)?"$hours $hs":"")],$format);
    $format = str_replace(['{i}','{I}','{i:}','{iI}'],[substr('00'.$minutes,-2), ($ms='minuto'.(($minutes == 1)?'':'s')), (($minutes > 0)?"$minutes:":""), (($minutes > 0)?"$minutes $ms":"")],$format);
    $format = str_replace(['{m}','{M}','{m:}','{mM}'],[substr('00'.$minutes,-2), ($ms='minuto'.(($minutes == 1)?'':'s')), (($minutes > 0)?"$minutes:":""), (($minutes > 0)?"$minutes $ms":"")],$format);
    $format = str_replace(['{s}','{S}','{s:}','{sS}'],[substr('00'.$seconds,-2), ($ss='segundo'.(($seconds == 1)?'':'s')), (($seconds > 0)?"$seconds:":""), (($seconds > 0)?"$seconds $ss":"")],$format);
    return $format;
  }

  /* convert date for strtotime usage */
  public static function datetostrtotime($date,$dmyonly=true) {
    if(is_numeric($date)) return date("Y-m-d".(($dmyonly)?"":" H:i:s"),$date);
    if(!is_string($date)) return date("Y-m-d".(($dmyonly)?"":" H:i:s"));
    if((strpos(strtolower($date),'n') !== false)
    ||((strpos(strtolower($date),'h') !== false)
    ||((strpos(strtolower($date),'r') !== false)
    ||((strpos(strtolower($date),'y') !== false))))) return date("Y-m-d".(($dmyonly)?"":" H:i:s"),strtotime($date));
    $date = explode(' ',($date = preg_replace('/[^0-9\/\-\:]/',' ',"$date ")));
    if(strpos(($date[0] = str_replace('/','-',($date[0] ?? ''))),'-') !== false)
      if(is_array($e = explode('-',$date[0]))) {
        if(strlen($e[0] ?? '') < 2) $e[0] = str_pad(($e[0] ?? ''),2,'0',STR_PAD_LEFT);
        if(strlen($e[2] ?? '') > strlen($e[0] ?? '')) $date[0] = ($e[2] ?? '').'-'.($e[1] ?? '').'-'.($e[0] ?? '');
        else if(strlen($e[2] ?? '') == strlen($e[0] ?? '')) $date[0] = str_pad(($e[2] ?? ''),4,substr(date("Y"),0,2),STR_PAD_LEFT).'-'.($e[1] ?? '').'-'.($e[0] ?? ''); }
    return trim($date[0].(($dmyonly)?"":" ".($date[1] ?? '')));
  }

  /* mask a portion of the middle of a string */
  public static function str_maskmiddle($input='') {
    if(strpos($input,'*') !== false) return $input;
    preg_match_all('/[0-9a-zA-Z]/', $input, $matches, PREG_OFFSET_CAPTURE);
    $totalAlnumChars = count($matches[0]);
    $numToMask = ceil($totalAlnumChars * 0.3);
    $positions = array_column($matches[0], 1);
    $middle = floor($totalAlnumChars / 2);
    $start = max(0, $middle - floor($numToMask / 2));
    $end = min($totalAlnumChars, $middle + ceil($numToMask / 2));
    $positionsToMask = array_slice($positions, $start, $end - $start);
    $inputArray = str_split($input);
    foreach ($positionsToMask as $pos) $inputArray[$pos] = '*';
    return implode('', $inputArray);
  }

  /* mask a portion of the middle of a string with str_maskmiddle recursively on an array */
  public static function str_maskmiddle_array($array=[],$onlykeys=[]) {
    if(is_array($array))
      foreach($array as $k => &$v)
        if(is_array($v)) $v = str_maskmiddle_array($v,$onlykeys);
        else if(is_string($v) && (empty($onlykeys) || in_array($k,$onlykeys))) $v = str_maskmiddle($v);
    return $array;
  }

  /* names in portuguese */
  public static function ucstrname($string, $delimiters = array(" ", "-", ".", "'", "O'", "D'", "Mc"), $exceptions = array("de", "da", "dos", "das", "do", "I", "II", "III", "IV", "V", "VI", "LTDA", "ME", "MEI")) {
      $string = mb_convert_case($string, MB_CASE_TITLE, "UTF-8");
      foreach ($delimiters as $dlnr => $delimiter) {
          $words = explode($delimiter, $string);
          $newwords = array();
          foreach ($words as $wordnr => $word) {
            if(in_array(mb_strtoupper($word, "UTF-8"), $exceptions)) $word = mb_strtoupper($word, "UTF-8");
            else if(in_array(mb_strtolower($word, "UTF-8"), $exceptions)) $word = mb_strtolower($word, "UTF-8");
                else if(!in_array($word, $exceptions)) $word = ucfirst($word);
            array_push($newwords, $word); }
          $string = join($delimiter, $newwords); }
    return trim($string);
  } 

  /* safer conversion for floatval values */
  public static function floatval_safe($number,$decimals=2) {
    $number = str_replace(',', '.', @preg_replace('/[^0-9\.\,\-]/', '', $number));
    $number = preg_replace('/\.(?=.*\.)/', '', $number);
    return floatval(number_format(floatval($number),$decimals,'.',''));
  }

  /* remove accents from string */
  public static function rmA($string) {
    return strtr(utf8_decode($string), utf8_decode('àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ'), 'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY');
  }

  /* convert accents to htmlentities */
  public static function rmAentities($string) {
    return str_replace(['&lt;','&gt;','&quot;','&amp;','&nbsp;'],['<','>','"','&',' '],htmlentities($string));
  }

  /* shows my ip */
  public static function myip() {
    return ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
  }
    
  /* language functions */
  public static function isbrazil() {
    $pref_br = array('66', '138', '154', '172', '177', '179', '187', '189', '192', '200', '201');
    $pref_ip = substr(($_SERVER['REMOTE_ADDR'] ?? '...'), 0, 3);
    return in_array($pref_ip, $pref_br);
  }

  public static function haslang($language) {
    if(!is_string($language)) return false; else $language = preg_replace('/[^a-z\;\,]/','',strtolower($language));
    return (in_array($language, explode(';',str_replace(',',';',preg_replace('/[^a-z\;\,]/','',strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''))))));
  }

  public static function like($needle, $haystack='') {
    $regex = '/' . str_replace('%', '.*?', $needle) . '/';
    return preg_match($regex, $haystack) > 0;
  }

  public static function decode_unicode($str){
    for($i = 0;$i <= strlen(preg_replace('/[^u]/','',$str))*10;$i++)
      $str = str_replace('\\u','*123',str_replace(($unicodestr = substr($str, strpos($str, 'u'), 5)),preg_replace_callback('/\\\\u([0-9a-f]{4})/i', function($match){return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');}, '\\'.$unicodestr),$str));
    return str_replace('??????','',str_replace('*123','u',str_replace('\\','',mb_convert_encoding($str,"UTF-8"))));
  }

  /* source http://php.net/manual/en/function.ord.php#109812 */
  public static function ordutf8($string, &$offset) {
    $code = ord(substr($string, $offset,1));
    if ($code >= 128) {        //otherwise 0xxxxxxx
        if ($code < 224) $bytesnumber = 2;                //110xxxxx
        else if ($code < 240) $bytesnumber = 3;        //1110xxxx
        else if ($code < 248) $bytesnumber = 4;    //11110xxx
        $codetemp = $code - 192 - ($bytesnumber > 2 ? 32 : 0) - ($bytesnumber > 3 ? 16 : 0);
        for ($i = 2; $i <= $bytesnumber; $i++) {
            $offset ++;
            $code2 = ord(substr($string, $offset, 1)) - 128;        //10xxxxxx
            $codetemp = $codetemp*64 + $code2;
        }
        $code = $codetemp;
    }
    $offset += 1;
    if ($offset >= strlen($string)) $offset = -1;
    return $code;
  }

  /* source http://php.net/manual/en/function.chr.php#88611 */
  public static function unichr($u) { return mb_convert_encoding('&#' . intval($u) . ';', 'UTF-8', 'HTML-ENTITIES'); }

  public static function emojientities($string='') {
    $stringBuilder = "";
    $offset = 0;
    if(empty($string)) return "";
    /* conversao */
    while ( $offset >= 0 ) {
        $decValue = self::ordutf8( $string, $offset );
        $char = self::unichr($decValue);

        $htmlEntited = htmlentities( $char );
        if( $char != $htmlEntited )
            $stringBuilder .= $htmlEntited;
        else if( $decValue >= 128 )
            $stringBuilder .= "&#" . $decValue . ";";
        else
            $stringBuilder .= $char;
    }
    return $stringBuilder;
  }

  /* gets attribute information of a html parameter tag */
  public static function html_tag_attrib($tag="a", $attribute="href", $html="") {
    $pattern = '/<' . $tag . '[^>]*' . $attribute . '="([^"]*)"[^>]*>/i';
    preg_match($pattern, $html, $matches);
    if(!empty($matches[1] ?? '')) return $matches[1];
    else return '';
  }

  /* get content of a html tag */
  public static function html_tag_content($tag="a", $html="") {
    $pattern = '/<' . $tag . '[^>]*>(.*?)<\/' . $tag . '>/is';
    preg_match($pattern, $html, $matches);
    if(!empty($matches[1] ?? '')) return $matches[1];
    else return '';
  }

  /* validades if brazilian document number is valid */
  public static function valida_cpf_cnpj($val) {
    $val = preg_replace('/[^0-9]/', '', $val);
    $val = str_split($val);
    if(count($val) == 11) {
        $cpf = $val; $v1 = 0; $v2 = 0; $aux = false;
        for($i = 1; $i < count($cpf); $i++) if($cpf[$i - 1] != $cpf[$i]) { $aux = true; break; }
        if($aux == false) return false;
        for($i = 0, $p = 10; $i < (count($cpf) - 2); $i++, $p--) $v1 += $cpf[$i] * $p;
        $v1 = (($v1 * 10) % 11);
        if($v1 == 10) $v1 = 0;
        if($v1 != $cpf[9]) return false;
        for($i = 0, $p = 11; $i < (count($cpf) - 1); $i++, $p--) $v2 += $cpf[$i] * $p;
        $v2 = (($v2 * 10) % 11);
        if($v2 == 10) $v2 = 0;
        if($v2 != $cpf[10]) return false;
        return true;
    } else if(count($val) == 14) {
        $cnpj = $val; $v1 = 0; $v2 = 0; $aux = false;
        for($i = 1; $i < count($cnpj); $i++) if($cnpj[$i - 1] != $cnpj[$i]) { $aux = true; break; }
        if($aux == false) return false;
        for($i = 0, $p1 = 5, $p2 = 13; $i < (count($cnpj) - 2); $i++, $p1--, $p2--) 
          if($p1 >= 2) $v1 += $cnpj[$i] * $p1; else $v1 += $cnpj[$i] * $p2;
        $v1 = ($v1 % 11);
        if($v1 < 2) $v1 = 0;
        else $v1 = (11 - $v1);
        if($v1 != $cnpj[12]) return false;
        for($i = 0, $p1 = 6, $p2 = 14; $i < (count($cnpj) - 1); $i++, $p1--, $p2--) 
          if($p1 >= 2) $v2 += $cnpj[$i] * $p1; else $v2 += $cnpj[$i] * $p2;
        $v2 = ($v2 % 11);
        if($v2 < 2) $v2 = 0;
        else $v2 = (11 - $v2);
        if($v2 != $cnpj[13]) return false;
        return true;
    } else return false;
  }

  /* validades brazilian documents number */
  public static function formatar_cpf_cnpj($doc) {
    $doc = preg_replace("/[^0-9]/", "", $doc);
    $qtd = strlen($doc);
    if($qtd >= 11) {
      if($qtd === 11 ) $docFormatado = substr($doc, 0, 3) . '.' . substr($doc, 3, 3) . '.' . substr($doc, 6, 3) . '-' . substr($doc, 9, 2);
      else $docFormatado = substr($doc, 0, 2) . '.' . substr($doc, 2, 3) . '.' . substr($doc, 5, 3) . '/' . substr($doc, 8, 4) . '-' . substr($doc, -2);
      return $docFormatado;
    } else return $doc;
  }

  /* function to convert markdown to html */
  public static function markdowntohtml($markdown) {
    $html = '';
    $incode = false;
    $intable = false;
    $tableheader = false;
    $tablecontent = [];
    if(is_array($lines = explode("\n", str_replace('<br/>',"\n",$markdown))))
      foreach($lines as $i => $line) {
        if(empty(trim($line))) $html .= "<br/>\n";

        if(trim($line) === '-' && isset($lines[$i-1])) {
            $previousLine = trim($lines[$i-1]);
            $html = substr($html, 0, strrpos($html, $previousLine));
            $html .= "<h2 style=\"margin:1rem 0px 0px 0px;\">" . $previousLine . "</h2>\n";
            continue; }

        if(substr($line,0,3) === '```') {
            if(!$incode && !empty($html .= "<pre style=\"border:1px solid #999;padding:6px 6px;background-color:rgb(153,153,153,0.2);overflow:scroll;\"><code>")) $incode = true;
            else if(!empty($html .= "</code></pre>\n")) $incode = false;
            continue; }
        
        if($incode) {
          $line = preg_replace_callback('/^ +/', function($match) {
                return str_repeat('&nbsp;', strlen($match[0]));
            }, htmlspecialchars($line));
          $ca = function($s) { return htmlspecialchars($s); };
          if(substr(trim(str_replace('&nbsp;',' ',$line)),0,2) === '//') $line = "<span style=\"color:#999;\">$line</span>";
          else {
            $line = preg_replace('/([0-9])/','<span style="color:#FF9800;">$1</span>',$line);
            $line = preg_replace('/\'(.*?)\'(.*?)\'(.*?)\'/', '\'<span style="color:normal;">$1</span>\'$2\'<span style="color:#FF9800;">$3</span>\'', $line);
            $line = preg_replace('/&quot;(.*?)&quot;(.*?)&quot;(.*?)&quot;/', '&quot;<span style="color:normal;">$1</span>&quot;$2&quot;<span style="color:#FF9800;">$3</span>&quot;', $line);
            $line = preg_replace('/(.*?)\((.*?)\)/', '<span style="color:#03A9F4;">$1</span>(<span style="color:#8BC34A;">$2</span>)', $line);
            $line = str_replace([$ca('['),$ca(']')],['<span style="color:#3F51B5;">'.$ca('[').'</span>','<span style="color:#3F51B5;">'.$ca(']').'</span>'],
                    str_replace([$ca('{'),$ca('}')],['<span style="color:#607D8B;">'.$ca('{').'</span>','<span style="color:#607D8B;">'.$ca('}').'</span>'],
                    str_replace([$ca('"'),$ca(',')],['<span style="color:#999999;">'.$ca('"').'</span>','<span style="color:#795548;">'.$ca(',').'</span>'],
                    str_replace(['true','false'],['<span style="color:#D32F2F;">true</span>','<span style="color:#D32F2F;">false</span>'],$line))));
            $line = preg_replace('/\b(function|return|if|else|foreach|for|while|do|use|trait|class|namespace|public|private|protected|try|catch|finally|switch|case|break|continue|null|true|false)\b/', '<span style="color:#F92672;">$1</span>', $line);
            $line = str_replace(['/*','*/'],['<span style="color:#999;">/*','*/</span>'],$line);
          } $html .= "$line\n";
          continue;
        } else if(!$intable) $line = preg_replace('/(.*?)\ \ \ (.*?)/', '<div style="display:flex;"><div style="flex:1;">$1</div><div style="flex:2;">$2</div></div>', $line);
        
        if(!$incode) $line = preg_replace('/\`(.*?)\`/', '<div style="display:inline-block;padding:2px 4px;background-color:rgb(153,153,153,0.3);">$1</div>', $line);

        if(substr($line,0,1) === '|') {
            if(!$intable) {
                $intable = true;
                $tableheader = true;
                $html .= "<table border=\"1\" style=\"border-collapse:collapse;border:1px solid rgb(153,153,153,0.3);background-color:rgb(153,153,153,0.1);\">\n";
            }
            $cells = array_map('trim', explode('|', trim($line, '| ')));
            if(strpos($line, '--') !== false) {
                $tableheader = false;
                continue;
            }
            $tag = $tableheader ? "th" : "td";
            $html .= "<tr>\n";
            foreach($cells as $cell) {
                if(trim($cell) !== '')
                    $html .= "<$tag style=\"padding:8px;\">$cell</$tag>\n";
            }
            $html .= "</tr>\n";
            $tableheader = false;
            continue;
        } else if($intable) {
            $html .= "</table><br/>\n";
            $intable = false;
        }

        $processedLine = $line;
        $processedLine = preg_replace_callback('/\[!(.*?)\]\((.*?)\)/',
          function($matches) { return '<img src="'.($matches[2] ?? '').'" style="'.($matches[1] ?? '').'">'; }, $processedLine);

        $processedLine = preg_replace_callback('/\[([^\]!].*?)\]\((.*?)\)/',
          function($matches) {
            if(!($out=(strpos(($matches[2] ?? ''),'://') !== false)) && substr(($matches[2] ?? ''),0,1) !== '#') $matches[2] = "?".($matches[2] ?? '');
            return '<a href="'.($matches[2] ?? '').'" '.(($out)?'target="_new" ':'').'style="color:#999;">'.($matches[1] ?? '').'</a>'; }, $processedLine);

        $processedLine = preg_replace('/\*\*(.*?)\*\*/', '<b>$1</b>', $processedLine);
        $processedLine = preg_replace('/\*(.*?)\*/', '<i>$1</i>', $processedLine);

        if(substr($processedLine,0,2) === '> ') $processedLine = "<div style=\"margin:1rem 0px 0px 0px;padding:6px 6px;background-color:rgb(153,153,153,0.15);border-left:5px solid #999;\">".substr($processedLine,2)."</div>";

        if(substr($processedLine,0,4) === '### ') $processedLine = "<h3 id=\"".preg_replace('/[^0-9a-z]/','-',strtolower(substr($processedLine,4)))."\" style=\"margin:1rem 0px 0px 0px;\">".substr($processedLine,4)."</h3>";
        if(substr($processedLine,0,3) === '## ') $processedLine = "<h2 id=\"".preg_replace('/[^0-9a-z]/','-',strtolower(substr($processedLine,3)))."\" style=\"margin:1rem 0px 0px 0px;\">".substr($processedLine,3)."</h2>";
        if(substr($processedLine,0,2) === '# ') $processedLine = "<h1 id=\"".preg_replace('/[^0-9a-z]/','-',strtolower(substr($processedLine,2)))."\" style=\"margin:1rem 0px 0px 0px;\">".substr($processedLine,2)."</h1>";
        if(substr($processedLine,0,2) === '- ') $processedLine = "&#8226; ".substr($processedLine,2);

        if(trim($processedLine) === '---') $processedLine = "</hr>";
        
        if(!empty(trim($processedLine)))
          $html .= "<div".((strpos($processedLine,'&#8226;') !== false)?' style="margin-bottom:0.25rem;"':'').">$processedLine</div>\n";
      }
    if($intable) $html .= "</table>\n";
    return $html;
  }

  public static function css() { 
    ?><style>
        /* loadblink */
        body:not(.loadblink-disabled) .loadblink { color: transparent !important; 
          border-radius:10px; border:1px solid transparent; 
          background: linear-gradient(-45deg, #999, #555, #555, #999); 
          opacity:0.5; background-size: 500% 500%; 
          -webkit-animation: gradientlb 1.5s ease infinite; 
          -moz-animation: gradientlb 1.5s ease infinite; 
          animation: gradientlb 1.5s ease infinite; } 
        @-webkit-keyframes gradientlb { 0% { background-position: 0% 50% } 50% { background-position: 100% 50% } 100% { background-position: 0% 50% } } 
        @-moz-keyframes gradientlb { 0% { background-position: 0% 50% } 50% { background-position: 100% 50% } 100% { background-position: 0% 50% } } 
        @keyframes gradientlb { 0% { background-position: 0% 50% } 50% { background-position: 100% 50% } 100% { background-position: 0% 50% } } 

        body:not(.loadblink-disabled) div .loadblink:nth-of-type(2), body:not(.loadblink-disabled) table .loadblink:nth-of-type(2) { opacity:0.4; } 
        body:not(.loadblink-disabled) div .loadblink:nth-of-type(3), body:not(.loadblink-disabled) table .loadblink:nth-of-type(3) { opacity:0.4; } 
        body:not(.loadblink-disabled) div .loadblink:nth-of-type(4), body:not(.loadblink-disabled) table .loadblink:nth-of-type(4) { opacity:0.3; } 
        body:not(.loadblink-disabled) div .loadblink:nth-of-type(5), body:not(.loadblink-disabled) table .loadblink:nth-of-type(5) { opacity:0.3; } 
        body:not(.loadblink-disabled) div .loadblink:nth-of-type(6), body:not(.loadblink-disabled) table .loadblink:nth-of-type(6) { opacity:0.2; } 
        body:not(.loadblink-disabled) div .loadblink:nth-of-type(7), body:not(.loadblink-disabled) table .loadblink:nth-of-type(7) { opacity:0.2; } 
        body:not(.loadblink-disabled) div .loadblink:nth-of-type(8), body:not(.loadblink-disabled) table .loadblink:nth-of-type(8) { opacity:0.1; } 
        body:not(.loadblink-disabled) div .loadblink:nth-of-type(9), body:not(.loadblink-disabled) table .loadblink:nth-of-type(9) { opacity:0.1; } 
        body:not(.loadblink-disabled) .loadblink:nth-child(2) { opacity:0.4; } 
        body:not(.loadblink-disabled) .loadblink:nth-child(3) { opacity:0.4; } 
        body:not(.loadblink-disabled) .loadblink:nth-child(4) { opacity:0.3; } 
        body:not(.loadblink-disabled) .loadblink:nth-child(5) { opacity:0.3; } 
        body:not(.loadblink-disabled) .loadblink:nth-child(6) { opacity:0.2; } 
        body:not(.loadblink-disabled) .loadblink:nth-child(7) { opacity:0.2; } 
        body:not(.loadblink-disabled) .loadblink:nth-child(8) { opacity:0.1; } 
        body:not(.loadblink-disabled) .loadblink:nth-child(9) { opacity:0.1; }

        .disabled { opacity:0.5; pointer-events: none; }

        .footing { position:fixed; bottom:0px; left:0px; right:0px; height:auto; }
        .cursorgrab { cursor:grab; -webkit-touch-callout: none; -webkit-user-select: none; -khtml-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none; }

        .horizontalscroll { scrollbar-width: none; display: block; white-space: nowrap; justify-content: center; overflow-x: auto; -ms-overflow-style: none; scrollbar-width: none; }
        .horizontalscroll::-webkit-scrollbar { width: 10px;}
        .horizontalscroll::-webkit-scrollbar-track { border-radius: 10px;}
        .horizontalscroll::-webkit-scrollbar-thumb { background: #999; border-radius: 10px; border:4px solid var(--background); }

        @media only screen and (min-width: 769px) {
          #app.fullscreen .screen .heading { max-width:600px; margin:auto; }
          *::-webkit-scrollbar { display: none; }
          * { -ms-overflow-style: none; scrollbar-width: none; }
          .footing:not(.nofootingcenter) { max-width:598px; margin:auto; }
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes glowborderanimationframe {
          0% { background-position: 0% 50%; }
          50% { background-position: 100% 50%; }
          100% { background-position: 0% 50%; }
        }

        .spinning-circle {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: linear-gradient(45deg, #999, transparent);
            animation: spin 1s linear infinite;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
            display: inline-block;
        }

        .glowborderanimation {
          position: relative;
          padding: 12px 20px;
          background-color: var(--cardbackground,--background, #222);
          color: var(--text,#fff);
          border-radius: 8px;
          border: 2px solid transparent;
          background-image: linear-gradient(var(--cardbackground,--background, #222), var(--cardbackground,--background, #222)),
                            linear-gradient(135deg, var(--cardbackground,--background, #222), var(--maincolor), var(--cardbackground,--background, #222));
          background-origin: border-box;
          background-clip: padding-box, border-box;
          background-size: 999% 999%;
          animation: glowborderanimationframe 3s linear infinite;
        }
    </style><?php 
  }

  public static function js() {
    ?><script>
      var screen = "home";
      var inputdoctype = "CPF";
      var switchtabinterval = 100;
      var switchingscreen = false;
      var resizetimer = null;
      var cursordown = false;
      var cursorypos = 0;
      var cursorxpos = 0;
      var toastcall = [];
      var upfilebs4 = "";
      var callbacks = {};

      var serveraddress = (serveraddress || '/');

      /* app on load */
      $(window).on('onload',function(state){

          if($('.autouploadtosrc').length)
            loadScript(serveraddress + 'storage/upload.js?v=3',function(){ 
              $('.autouploadtosrc').each(function(index,elem){ 
                var ide = $(elem).attr('id');
                var ideuper = '#'+ide+'_uploader';
                  if(!($(ideuper).length)) {
                    $('body').append(`<form style="overflow:hidden;transform:translate(-9999999px,-999999px);"><input type="file" id="${ide+'_uploader'}"></form>`);
                    $('#'+ide).attr('for',String(ideuper).replace('#','')).attr('onclick',`$('${ideuper}').click();`+($('#'+ide).attr('onclick') || ''));
                  }
                  var paramset = { 'f': 'foto', 'p': '/', 'e': 'jpeg' };
                  try { if(!empty(autouploadextraparams) && (typeof autouploadextraparams == 'object' || typeof autouploadextraparams == 'array'))
                    paramset = { ...paramset, ...autouploadextraparams }; } catch(e) { }
                  bindupload(ideuper, paramset,
                  function (onstart) { $(elem).addClass('loadblink');
                      try {
                        upfilebs4 = "";
                        var reader = new FileReader();
                        reader.readAsDataURL(onstart.filedata.get('file'));
                        reader.onload = function () {
                          upfilebs4 = reader.result };
                        reader.onerror = function (error) {
                          console.log('error on reader', error); };
                      } catch(e) { console.log('error on bs4',e) }
                      $(elem).attr('src','img/transparent.png').show();
                      setTimeout(function(){ $(elem).removeClass('loadblink'); },5678);
                  }, function (ondone) {
                      if(ondone.result !== "") return $(elem).attr('src',ondone.url).removeClass('loadblink');
                      if(upfilebs4 === "") return $(elem).attr('src','error').removeClass('loadblink');
                      console.log('Error transmiting file. Starting alternative counter measures...');
                      var paramset = { 'f':'file4', 'p':'/', 'e':'jpeg', 'base64':'1', 'file':upfilebs4 };
                      try { if(!empty(autouploadextraparams) && (typeof autouploadextraparams == 'object' || typeof autouploadextraparams == 'array'))
                        paramset = { ...paramset, ...autouploadextraparams }; } catch(e) { }
                      post('storage/send', paramset, function(fup){
                        console.log('Sent by base64', JSON.stringify(fup));
                        $(elem).attr('src',fup.url); },function(error){ $(elem).attr('src','error'); },
                        function(always){ $(elem).removeClass('loadblink'); });
                  }); });
            });

          $('*[nextfield]').on("keyup",function(e){ 
              if(e.which !== 13) return;
              var este = $(this).attr('nextfield');
              try { if(este === 'blur') $(document.activeElement).blur(); } catch(err) { }
              var next = $(este);
              var type = ((next.prop('tagName') === 'button'
                      ||((next.attr('type') === 'submit'
                      ||((next.attr('type') === 'button'
                      ||((next.attr('type') === 'clickable'))))))) ? 'click' :
                          ((next.prop('tagName') === 'div'
                          ||((next.prop('tagName') === 'form'
                          ||((next.hasClass('screen')))))) ? 'blur' : 'focus'));
              try { if(type === 'blur') $(document.activeElement).blur();
              else if(type === 'click') next.click();
              else next.focus(); } catch(err) { }
          });

          $('.btns, .btns2, .autolockbtn').on('click',function(){
              if(!($(this).hasClass('keepunlocked'))) {
                if($(this).hasClass('disabled')) return false; 
                else $(this).addClass('disabled unlockscheduled'); 
                setTimeout(function(){ 
                  $('.unlockscheduled.disabled').removeClass('disabled unlockscheduled'); 
                },12345); }
          });
          
          try { $('.datamask').attr('type','tel').mask('99/99/9999'); } catch(err) { }
          
          try { $('.cepmask').attr('type','tel').mask('99999-999'); } catch(err) { }

          try { $('.telmask').attr('type','tel').mask('(99) 99999-9999'); } catch(err) { }

          try { $('.cpfmask').attr('type','tel').mask("000.000.000-00"); } catch(err) { }

          try { $('.cnpjmask').attr('type','tel').mask("00.000.000/0000-00"); } catch(err) { }

          try { $('.emailmask').attr('type','email').keydown(function(event){
              if(String(event.key).length > 1) return;
              if(event.key == ' ') return false;
              let org = $(this).val();
              let key = event.key;
              let val = org+key;
              val = (String(val).replace(/[^0-9a-zA-Z\@\.\_\+\-\%]/gi,''));
              let p = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
              if(String(val).indexOf('@') < 0) return ((org+key) == val);
              if(p.test(val)) return ((org+key) == val);
              let s = String(val).split('@');
              let d = String(s[1]).replace(/[^0-9a-zA-Z\.\-]/gi,'');
              if(String(d).indexOf('.') > -1) {
                let e = String(d).split('.');
                $(e).each(function(i,c){ if(i > 0) e[i] = String(e[i]).replace(/[^0-9a-z]/gi,'').substr(-10); });
                d = e.join('.'); }
              val = (s[0]+'@'+d);
              return ((org+key) == val);
          }).keyup(function(event){
              $(this).val(String($(this).val()).toLowerCase().replace(/[^0-9a-z\@\.\_\+\-\%]/gi,''));
          }); } catch(err) { }

          try { $('.docmask').attr('type','tel').mask("000.000.000-00")
                      .keyup(function(event) { try {
                          if(typeof event.which !== 'undefined')
                              if(((event.which >= 48) && (event.which <= 57)) || ((event.which >= 96) && (event.which <= 105)) || (event.which == 8) || (event.which == 229))
                                  if((inputdoctype == 'CNPJ') && ($(this).val().length <= 14) && (inputdoctype = 'CPF'))
                                      $(this).mask("000.000.000-00", { reverse: true }); } catch(e) { } })
                      .keydown(function(event) { try {
                          if(typeof event.which !== 'undefined')
                              if(((event.which >= 48) && (event.which <= 57)) || ((event.which >= 96) && (event.which <= 105)) || (event.which == 8) || (event.which == 229))
                                  if((inputdoctype == 'CPF') && ($(this).val().length == 14) && (inputdoctype = 'CNPJ'))
                                      if(!((event.which == 8) && ($(this).val().length == 14)))
                                          $(this).mask("00.000.000/0000-00", { reverse: true }); } catch(e) { } }); } catch(err) { }

          try { $('.cursorgrab').mousedown(function(e){
                cursordown = true; cursorxpos = $(this).scrollLeft() + e.clientX; cursorypos = $(this).scrollTop() + e.clientY;
              }).mousemove(function(e){
                if(!cursordown) return;
                try { $(this).scrollLeft(cursorxpos - e.clientX); } catch(err) { }
                try { $(this).scrollTop(cursorypos - e.clientY); } catch(err) { }
              }).mouseup(end = function(e){
                cursordown = false;
              }).mouseleave(end); } catch(err) { }

      });

      /* footing class alignment */
      function realignfooting() {
        let screen = getitem('screen');
        if($(screen+' .footing').length) {
            $(screen+' .footing').attr('style','');
            resizetimer = setInterval(function(){
                var foo = $(screen+' .footing').offset().top;
                if(!(parseInt(foo) > 0)) return;
                $(screen+' .footing').attr('style','top:'+(foo)+'px !important;bottom:auto;');
                clearInterval(resizetimer);
            },100);
        }
      }

      /* function to auto scroll the app to the end */
      function appscrolltobottom() { try {
        document.querySelector('#app').scrollTo({ top: Math.max(
              document.body.scrollHeight,
              document.documentElement.scrollHeight,
              document.body.offsetHeight,
              document.documentElement.offsetHeight ),
          behavior: 'smooth' }); } catch(err) { }
        return true;
      }

      /* animation to show there is an horizontal space to scroll */
      function animate_horizontalscroll() {
          if($('.horizontalscroll:visible').length)
              $('.horizontalscroll:visible').scrollLeft($(document).width() * 2).animate({ scrollLeft: 0 },1500);
      }
      
      $(window).on("screen_onload",function(state){ realignfooting(); animate_horizontalscroll(); });

      $(window).on('onload',function(){
          let [goes, querystr] = String(window.location.hash).split('/');
          let params = {};
          if(!empty(querystr)) {
            let paramstr = new URLSearchParams(querystr);
            for (let [key, value] of paramstr.entries()) params[key] = value;
          }
          goes = String(goes).replace('#','').replace('home','');
          if(empty(goes)) goes = 'home';
          if(String('#'+goes) === getitem('screen')) {
            if(!($('.screen:visible').length)) switchtab('#'+goes,true,params,0);
            return;
          }
          switchtab('#'+goes,true,params,0);
      });

      $(window).on('popstate',function(){
          if(empty(String(window.location.hash).replace('#',''))) return;
          let [screen, querystr] = String(window.location.hash).split('/');
          let paramstr = new URLSearchParams(querystr || '');
          let params = {};
          if(!($(screen).length)) return;
          if(!($(screen).hasClass('screen'))) return;
          for (let [key, value] of paramstr.entries()) params[key] = value;
          switchtab(screen,true,params,0);
      });

      /* animation switch between .screen class */
      function switchtab(to,backwards,params,stinterval) {
          if(typeof stinterval === 'undefined') stinterval = switchtabinterval;
          if(typeof params === 'undefined' || empty(params)) params = {};
          if(typeof backwards === 'object') {
            params = backwards;
            backwards = false;
          }
          var before = getitem('screen');
          if(before === to) stinterval = 0;
          if(before === '#home' || (to === '#home' && backwards)) stinterval = 0;
          eventfire('screen_onleft',{ ...{ 'from':before, 'to':to }, ...params });
          setitem('screen',to);
          var vt = [];
          var optin = ((backwards === true) ? { direction:'left' } : { direction:'right' });
          var optout = ((backwards === true) ? { direction:'right' } : { direction:'left' });
          var newview = function(){
              to = getitem('screen');
              if(!($(to).length)) to = screen = '#home';
              var gonext = function(){
                  if(backwards !== true) eventfire('screen_onload',{ ...{ 'from':before, 'to':to }, ...params });
                  eventfire(String(to).replace(/[^0-9a-z\_]/gi,'')+'_onload');
                  $('.unlockscheduled.disabled').removeClass('disabled unlockscheduled');
                  $('html, body, fullscreen').scrollTop(0);
                  const url = new URL(window.location);
                  const queryParams = new URLSearchParams(params).toString();
                  const newHash = String(`#${to}${queryParams ? '/' + queryParams : ''}`).replace('##','#');
                  history.pushState(null, '', url.pathname + newHash);
              };
              if(stinterval > 1) return $(to).show('slide', optin, stinterval, gonext);
              $(to).show((($(to).hasClass('fast')) ? 1 : stinterval),function(done){ gonext(); });
          };
          eventfire('switchtab',{ ...{ 'from':before, 'to':to }, ...params });
          eventfire('screen_onstart',{ ...{ 'from':before, 'to':to }, ...params });
          if(!($('.screen:visible').length)) return newview();
          $('.screen:not(.fixed):visible').each(function(index,item){ vt.push('#'+String($(item).attr('id'))); });
          if(empty(vt)) return newview();
          if(stinterval > 1) return $(vt.join(',')).hide('slide', optout, stinterval, newview);
          $(vt.join(',')).hide(); newview();
      }

      /* get value from memory */
      function getitem(qual,def) {
        if(typeof def == 'undefined') def = '';
        if(!((['uid', 'screen']).includes(qual)))
          if(String(qual).indexOf('@') > -1) qual = String(qual).replace('@','');
          else qual += "_"+String(window.localStorage.getItem('uid'));
        var rt = window.localStorage.getItem(qual);
        rt = ((rt == undefined) || (rt == null) || (rt == '')) ? def : rt;
        if(rt.indexOf('@array/object@') > -1) rt = JSON.parse(rt.replace('@array/object@',''));
        return rt;
      }

      /* set value in memory */
      function setitem(qual,val) {
        if(!((['uid', 'screen']).includes(qual)))
          if(String(qual).indexOf('@') > -1) qual = String(qual).replace('@','');
          else qual += "_"+String(window.localStorage.getItem('uid'));
        if((qual[String(qual).length-1]) == '_') return;
        if(typeof val === 'object') val = '@array/object@'+JSON.stringify(val);
        if((val == undefined) || (val == null)) val = ''; else val = val.toString();
        try { window.localStorage.setItem(qual,val);
          return ((val.indexOf('@array/object@') > -1) ? JSON.parse(val.replace('@array/object@','')) : val);
        } catch(e) { console.log(e); toast('Erro de mem&oacute;ria excedida',null,'Erro de memória excedida pode ser ocasionado por uma série de ferramentas que tentam salvar informações na memória local. Por favor, informe o erro ao suporte juntamente com informações da tela que está no momento e o que estava fazendo');
          return false; }
      }

      /* event handler */
      var eventlist = [];
      function eventfire(name,state) { 
        <?php if(($_SERVER['DEVELOPMENT'] ?? false) === true) { ?>
          if(getitem('@debug') == '1') console.log(name+' event called');
        <?php } ?>
        if(!eventlist[name]) eventlist[name] = true;
        var evt = $.Event(name);
        if((typeof state === 'object') && (!Array.isArray(state)))
          Object.keys(state).forEach(function(key) { evt[key] = state[key]; });
        evt.state = state;
        $(window).trigger(evt);
      }

      /* similar html entity decode */
      var ENT_QUOTES = true;
      var htmldecoderelement = null;
      
      function html_entity_decode(htmltext) {
        if(htmldecoderelement === null) htmldecoderelement = $('<textarea/>');
        return htmldecoderelement.html(htmltext).text();
      }

      function htmlentities(htmlstr,entquotes) {
          let c = String(htmlstr).replace(/[\u00A0-\u9999<>\&]/gim, function(i) {
            return '&#'+i.charCodeAt(0)+';';
          });
          if(entquotes === true) c = c.replace(/[\"]/gi,'&#x22;').replace(/[\']/gi,'&#x27;').replace(/[\`]/gi,'&#x60;');
          return c.replace(/&/gim, '&amp;');
      }

      /* get a cookie */
      var $_cookie = function(key) {
          let value = "";
          try { value = document.cookie
                  .split('; ')
                  .find(row => row.startsWith(key+'='))
                  .split('=')[1];
          } catch(e) { }
          return value;
      }
        
      /* set a cookie */
      function savecookie(key, value, expiry, domain, path) { try {
          var expires = new Date();
          var path = ';path='+((typeof path === 'undefined') ? "/" : path);
          var domain = ((typeof domain === 'undefined') ? '' : ';domain='+domain);
          if(typeof expiry === 'undefined') expiry = 365;
          expires.setTime(expires.getTime() + (expiry * 24 * 60 * 60 * 1000));
          document.cookie = key + '=' + value + ';expires=' + expires.toUTCString() + domain + path;
          } catch(e) { console.log('cant set cookie'); }
          return value;
      }
        
      /* remove any cookie */
      function delcookie(key) {
          savecookie(key, $_cookie(key), -1, '.'+window.location.hostname);
          savecookie(key, $_cookie(key), -1, window.location.hostname);
          savecookie(key, $_cookie(key), -1);
          return true;
      }

      /* check whether a function exists or not */
      function function_exists(function_name) {  
        if(typeof function_name === 'string')
              return (typeof window[function_name] === 'function');
          else
              return (function_name instanceof Function
                  || typeof function_name === 'function');
      }

      /* facilitated post function */
      var post_default_params = {};
      function post(url,params,success,error,always,timeout,cache) {
        let token = getitem('token');
        let dauth = getitem('deviceauth');
        let pushid = getitem('@pushid');
        if(typeof params === 'null' || typeof params === 'undefined') params = {}; 
        if(!(typeof params !== 'object' && typeof params !== 'array')) {
          try { params = { ...params, ...post_default_params }; } catch(e) { }
          if(token !== '') params.actk = token;
          if(dauth !== '') params.deviceauth = dauth;
          if(pushid !== '') params.pushid = pushid;
          if(cache === true) params.t = (new Date().getTime()); }
        else if(typeof params === 'string') {
                if(token !== '') params += '&actk='+token;
                if(dauth !== '') params += '&deviceauth='+dauth;
                if(cache === true) params += '&t='+(new Date().getTime()); }
        try { if(token !== $_cookie('actk') && delcookie('actk')) savecookie('actk',token); } catch (err) { }
        var e = String('//'+(String(url+'?').split('?')[0])).split('/');
            e = e[e.length-2]+e[e.length-1];
        var callback = function(cb){
          if(typeof always === 'function') always(cb);
          try { cb['params'] = params; } catch(e) { }
          $('.unlockscheduled.disabled').removeClass('disabled unlockscheduled');
          eventfire(e+'_onpostload', cb);
        };
        eventfire(e+'_onpoststart', params);
        return $.post(((String(url).indexOf('//') < 0) ? serveraddress+url : url), params)
                .done(function(data){
                  try { if(!empty(params.preventlogout)) data.preventlogout = 1; } catch(e) { }
                  if(data.result < 0) eventfire('resulterr',data); 
                  try { if(typeof success === 'function') success(data); } catch(e) { }
                  callback(data); })
                .fail(function(err){
                  try { if(typeof error === 'function') error(err); } catch(e) { }
                  callback(err);
                });
      }

      /* similar function from date on php */
      function date(stringtxt, unixtimestamp, zone) {
        if(typeof unixtimestamp === 'undefined') unixtimestamp = String(((new Date()).getTime() / 1000)+'.').split('.')[0];
        if(typeof zone === 'undefined') zone = (((new Date().getTimezoneOffset()) - 180) * 60);
        if(stringtxt == 'now') return unixtimestamp;
        let t = parseInt(String(unixtimestamp).replace(/[^0-9]/gi,'')) + zone;
        let d = new Date(t * 1000);
        let w = parseInt(d.getDay());
            if(w == 0) q = 'Domingo'; if(w == 1) q = 'Segunda-Feira'; if(w == 2) q = 'Terca-Feira';
            if(w == 3) q = 'Quarta-Feira'; if(w == 4) q = 'Quinta-Feira'; if(w == 5) q = 'Sexta-Feira';
            if(w == 6) q = 'Sabado';
        let u = m = parseInt(d.getMonth()+1);
            if(m == 1) m = 'Jan'; if(m == 2) m = 'Fev'; if(m == 3) m = 'Mar';
            if(m == 4) m = 'Abr'; if(m == 5) m = 'Mai'; if(m == 6) m = 'Jun';
            if(m == 7) m = 'Jul'; if(m == 8) m = 'Ago'; if(m == 9) m = 'Set';
            if(m == 10) m = 'Out'; if(m == 11) m = 'Nov'; if(m == 12) m = 'Dez';
        let h = parseInt(d.getHours());
            if(h <= 23) p = 'Noite';
            if(h <= 18) p = 'Tarde';
            if(h <= 11) p = 'Manh&atilde;';
            if(h <= 5) p = 'Madrug.';
            if(h == 0) p = 'Noite';
        let s = "";
        for(var i=0;i<stringtxt.length;i++) {
          if(stringtxt.charAt(i) == 'Y') s += d.getFullYear();
          else if(stringtxt.charAt(i) == 'y') s += String("0"+d.getFullYear()).substr(-2);
          else if(stringtxt.charAt(i) == 'm') s += String("0"+u).substr(-2);
          else if(stringtxt.charAt(i) == 'd') s += String("0"+d.getDate()).substr(-2);
          else if(stringtxt.charAt(i) == 'H') s += String("0"+h).substr(-2);
          else if(stringtxt.charAt(i) == 'i') s += String("0"+d.getMinutes()).substr(-2);
          else if(stringtxt.charAt(i) == 's') s += String("0"+d.getSeconds()).substr(-2);
          else if(stringtxt.charAt(i) == 'P') s += p;
          else if(stringtxt.charAt(i) == 'w') s += w;
          else if(stringtxt.charAt(i) == 'W') s += q;
          else if(stringtxt.charAt(i) == 'D') s += String(q).substr(0,3);
          else if(stringtxt.charAt(i) == 'M') s += m;
          else s += stringtxt.charAt(i); }
        return s;
      }

      /* empty php similar function */
      function empty(value, coalesce) {
        try {
          if(typeof value == 'object') return (Object.keys(value).length === 0);
          if(typeof value == 'array') return (value.length === 0); 
        } catch(err) { }
        let v = String(value).replace('undefined','').replace('null','').replace('[]','').replace('{}','').replace('NaN','').replace('0','').replace('false','').trim();
        if(typeof coalesce !== 'undefined') return ((v == '') ? coalesce : value);
        return (v == '');
      }

      /* equivalent numberformat function */
      function number_format(number, decimals, dec_point, thousands_sep) {
        number = String(number).replace(/[^0-9+\-Ee.]/g, '');
        var n = !isFinite(+number) ? 0 : +number,
            prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
            sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
            dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
            s = '',
            toFixedFix = function (n, prec) {
                var k = Math.pow(10, prec);
                return '' + Math.round(n * k) / k;
            };
        s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
        if(s[0].length > 3) s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
        
        if((s[1] || '').length < prec) {
            s[1] = s[1] || '';
            s[1] += new Array(prec - s[1].length + 1).join('0');
        }
        return s.join(dec);
      }

      /* parser of float values */
      function floatval(number) {
        if(String(number).indexOf(',') > -1) number = String(number).replace(/\./gi,'');
        number = String(number).replace(/(\,)(?!.*\1)/,'.');
        number = parseFloat(String(number).replace(/[^0-9\.]/gi,''));
        return number;
      }

      /* same new safe one from php to floatval */
      function floatval_safe(number,decimals) {
        if(typeof decimals == 'undefined') decimals = 2;
        number = String(number).replace(/[^0-9\.\,\-]/g, '').replace(/\,/g, '.');
        number = number.replace(/\.(?=.*\.)/g, '');
        return parseFloat(number_format(number,decimals,'.',''));
      }

      /* similar function to str_pad */
      function str_pad(input, length, padString, type) {
        input = String(input);
        if(typeof type === 'undefined') type = 'right';
        if(typeof padString === 'undefined') padString = ' ';
        if(type === 'left') return input.padStart(length, padString);
        else if(type === 'both') {
            const totalPad = length - input.length;
            const padLeft = Math.ceil(totalPad / 2);
            const padRight = totalPad - padLeft;
            return padString.repeat(padLeft) + input + padString.repeat(padRight);
        } else
            return input.padEnd(length, padString);
      }

      /* similar function to rmA */
      function rmA(string) {
        const accents = 'àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ';
        const replacements = 'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY';
        const mapping = {};
        for (let i = 0; i < accents.length; i++)
            mapping[accents[i]] = replacements[i];
        return string.split('').map(char => mapping[char] || char).join('');
      }

      /* similar function to mask middle strings */
      function str_maskmiddle(input) {
        if(empty(input)) return '*******';
        if(typeof input !== 'string') return '*******';
        if(input.includes('*')) return input;
        const matches = Array.from(input.matchAll(/[0-9a-zA-Z]/g));
        const totalAlnumChars = matches.length;
        if (totalAlnumChars === 0) return input;
        const numToMask = Math.ceil(totalAlnumChars * 0.3);
        const positions = matches.map(match => match.index);
        const middle = Math.floor(totalAlnumChars / 2);
        const start = Math.max(0, middle - Math.floor(numToMask / 2));
        const end = Math.min(totalAlnumChars, middle + Math.ceil(numToMask / 2));
        const positionsToMask = positions.slice(start, end);
        const inputArray = input.split('');
        positionsToMask.forEach(pos => { inputArray[pos] = '*'; });
        return inputArray.join('');
      }

      /* function to obtain a name initials */
      function strnameinitials(name) {
        if(typeof name !== 'string') return '';
        let nm = String(name).split(' ');
        nm = String(String(name).substr(0,1)+((empty(nm[nm.length-1]) || nm.length < 2) ? String(name).substr(1,1) : String(nm[nm.length-1]).substr(0,1))).toUpperCase();
        return nm;
      }

      /* calc geolocation distance in km */
      function distancia(position1, position2) {
        "use strict"; var deg2rad = function (deg) { return deg * (Math.PI / 180); }, R = 6371,
            dLat = deg2rad(position2.lat - position1.lat),
            dLng = deg2rad(position2.lng - position1.lng),
            a = Math.sin(dLat / 2) * Math.sin(dLat / 2) + Math.cos(deg2rad(position1.lat))
                * Math.cos(deg2rad(position1.lat)) * Math.sin(dLng / 2) * Math.sin(dLng / 2),
            c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        var result = (((R * c *1000).toFixed()) / 1000).toString().replace('.',',');
        if(result.indexOf(',') > -1) { result = result.split(','); 
          if(result[1].length > 2) result[1] = result[1].substr(0,2);
          result = result[0]+','+result[1]; }
        return result;
      }

      /* dynamically load javascript */
      function loadScript( url, callback, onerror) {
        var script = document.createElement("script");
        if(typeof onerror !== 'function') onerror = function(){ };
        script.type = "text/javascript";
        script.rel = "preload";
        script.as = "script";
        script.async = "true";
        script.onerror = onerror;
        if(script.readyState) {
          script.onreadystatechange = function() {
            if ( script.readyState === "loaded" || script.readyState === "complete" ) {
              script.onreadystatechange = null;
              if(typeof callback === 'function') callback(); }
          };
        } else script.onload = function() { if(typeof callback === 'function') callback(); };
        script.src = url;
        document.getElementsByTagName("head")[0].appendChild(script);
      }

      /* dynamically load css */
      function loadCss( url, callback ) {
        var css = document.createElement("link");
        css.rel = "stylesheet preload";
        css.as = "style";
        css.async = "true";
        if(css.readyState) {
          css.onreadystatechange = function() {
            if ( css.readyState === "loaded" || script.readyState === "complete" ) {
              css.onreadystatechange = null;
              if(typeof callback === 'function') callback(); }
          };
        } else css.onload = function() { if(typeof callback === 'function') callback(); };
        css.href = url;
        document.getElementsByTagName("head")[0].appendChild(css);
      }

      /* random blocks for loading text effect */
      function loadblink(width,height) {
        var minimum = 24;
        if(!width) width = Math.floor(Math.random() * (300 - minimum + 1) ) + minimum;
        if(!height) height = minimum;
        return '<div class="loadblink flbblock" style="height:'+height+';width:'+width+';"></div>';
      }

      /* easy toast */
      function toast(text,callback,moredetails) {
        if(text == ':close') { $('#geasytoast-balloon').slideUp(200); return toastcall(); }
        if(callback == undefined) callback = function() { }; toastcall = callback;
        if(moredetails == undefined) moredetails = '';
        try { if(typeof moredetails === 'object') moredetails = JSON.stringify(moredetails).replace(/[^a-zA-Z0-9\ \(\)\-]/gi,':: '); } catch(e) { console.log('cant convert result data'); }
        if(!($('body #geasytoast').length)) $('body').prepend('<div id="geasytoast"></div>');
        $('body #geasytoast').html(`<div id="geasytoast-balloon" onmousedown="$(this).addClass('touched').css('opacity',0.75);" onmouseout="if($(this).hasClass('touched')) toast(':close');"
                                         onclick="${((!empty(moredetails)) ? `alert('${String(moredetails)}');` : ``)} toast(':close');" 
                                         style="display:none;position:fixed;top:4rem;left:0px;right:0px;text-align:center;width:100%;z-index:999999999;">
          <div class="glowborderanimation" style="position:relative;display:inline-block;color:#fff;${((!empty(moredetails)) ? `min-height:40px;` : ``)}
                      border-radius:20px;margin:auto;width:250px;padding:0.75rem 2.5rem 0.75rem 1.5rem;overflow:hidden;background-color:#333;
                      -webkit-touch-callout: none; -webkit-user-select: none; -khtml-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none;
                      -webkit-box-shadow: 0px 5px 20px 10px rgba(0,0,0,0.75);
                      -moz-box-shadow: 0px 5px 20px 10px rgba(0,0,0,0.75);
                      box-shadow: 0px 5px 20px 10px rgba(0,0,0,0.75)">
            <div id="geasytoastclose" style="position:absolute;top:0.47rem;right:0.65rem;font-size:20px;color:#777;" onclick="toast(':close');">&times;</div>
            ${((!empty(moredetails)) ? `<div id="geasytoastalert" style="position:absolute;top:2.4rem;right:0.72rem;font-size:11px;color:#777;" onclick="alert('${String(moredetails)}');">&#9432;</div>` : ``)}
            ${text} </div></div>`).find('#geasytoast-balloon').slideDown(200);
        setTimeout(function(){ if(!($('#geasytoast-balloon').hasClass('touched'))) toast(':close'); },4321);
        return true;
      }

      /* contrast color from a given color */
      function getcontrastcolor(hexcolor) {
        hexcolor = String(hexcolor).toLowerCase().replace(/[^0-9a-z]/gi,'');
        const r = parseInt(hexcolor.slice(0, 2), 16);
        const g = parseInt(hexcolor.slice(2, 4), 16);
        const b = parseInt(hexcolor.slice(4, 6), 16);
        const luminance = (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255;
        return luminance > 0.5 ? '#333333' : '#f6f6f6';
      }

      /* function to get color of a string */
      function getcolorfromstr(string) {
        return '#'+String('000000'+String(string).replace(/[^0-9\a\b\c\d\e\f]/gi,'')).substr(-6);
      }

      /* validade brasilian document */
      function valida_cpf_cnpj(val) {
          val = val.trim().replace(/[^0-9]/gi,'').split('');
          if (val.length == 11) {
              var cpf = val; var v1 = 0; var v2 = 0; var aux = false;
              for (var i = 1; cpf.length > i; i++) if (cpf[i - 1] != cpf[i]) aux = true;
              if (aux == false) return false; 
              for (var i = 0, p = 10; (cpf.length - 2) > i; i++, p--) v1 += cpf[i] * p; 
              v1 = ((v1 * 10) % 11);
              if (v1 == 10) v1 = 0; 
              if (v1 != cpf[9]) return false; 
              for (var i = 0, p = 11; (cpf.length - 1) > i; i++, p--) v2 += cpf[i] * p;
              v2 = ((v2 * 10) % 11);
              if (v2 == 10) v2 = 0; 
              if (v2 != cpf[10]) return false; 
              else return true; 
          } else if (val.length == 14) {
              var cnpj = val; var v1 = 0; var v2 = 0; var aux = false;
              for (var i = 1; cnpj.length > i; i++) if (cnpj[i - 1] != cnpj[i]) aux = true;   
              if (aux == false) return false; 
              for (var i = 0, p1 = 5, p2 = 13; (cnpj.length - 2) > i; i++, p1--, p2--) 
                  if (p1 >= 2) v1 += cnpj[i] * p1;  
                  else v1 += cnpj[i] * p2;  
              v1 = (v1 % 11);
              if (v1 < 2) v1 = 0; else v1 = (11 - v1); 
              if (v1 != cnpj[12]) return false; 
              for (var i = 0, p1 = 6, p2 = 14; (cnpj.length - 1) > i; i++, p1--, p2--)  
                  if (p1 >= 2) v2 += cnpj[i] * p1;  
                  else v2 += cnpj[i] * p2; 
              v2 = (v2 % 11); 
              if (v2 < 2) v2 = 0; else v2 = (11 - v2); 
              if (v2 != cnpj[13]) return false; 
              else return true; 
          } else return false;
      }

      /* alert by clean auto modal */
      function alertpormodal(text,closebutton,animate) {
          if(text === ':close') if(animate === false) return $('.alertpormodaldivmsg').hide().find('#alertpmconteudo').html(``); else return $('.alertpormodaldivmsg').slideUp(400,function(){ setTimeout(function() { $('.alertpormodaldivmsg #alertpmconteudo').html(``); },400); });
          if(text === ':isvisible') return $('body .alertpormodaldivmsg:visible').length;
          if(text === ':ishidden') return (!($('body .alertpormodaldivmsg:visible').length));
          if(!($('body .alertpormodaldivmsg').length))
              $('body').append('<div class="alertpormodaldivmsg fxcolor" style="position: fixed; top: 0px; left: 0px; right: 0px; bottom: 0px; background-color: rgba(0, 0, 0, 0.7); z-index: 9999998;"></div>'+
                  '<div class="alertpormodaldivmsg" style="position: fixed; top: 0px; left: 0px; right: 0px; bottom: 0px; z-index: 9999999;text-align:center;">'+
                  '<center class="classapmdm" style="position:relative;display:inline-block;width:auto;min-width:300px;max-width:88%;margin:4rem auto;float:center;text-align:center;">'+
                  '<div class="closebtnnoalertpormodal" onclick="alertpormodal(`:close`);" style="position:absolute;right: -1rem;top: -1rem;border:1px solid #999;border-radius:50%;background-color:var(--background,#eee);color:#999;padding: 0px 0.7rem 2px;font-size:24px;font-weight:bold;">&times;</div>'+
                  '<div style="width:90%;height:auto;max-height:480px;max-height:77vh;overflow:auto;padding:1.5rem 1rem 1rem 1rem;background-color:var(--background,#fff);border-radius:10px;min-height: 70px;font-size:16px;">'+
                  '<div id="alertpmconteudo" style="max-width:100%;overflow:hidden;position:relative;"></div>'+
                  '</div></center></div>');
          if(closebutton === false) $('.alertpormodaldivmsg .closebtnnoalertpormodal').hide();
          else $('.alertpormodaldivmsg .closebtnnoalertpormodal').show(); $('.alertpormodaldivmsg #alertpmconteudo').html(text);
          eventfire('alertpormodal',{ 'text':text, 'closebutton':closebutton, 'animate':animate });
          return ((animate === false) ? $('.alertpormodaldivmsg').show() : $('.alertpormodaldivmsg').slideDown());
      }

      /* format document */
      function formatar_cpf_cnpj(data) {
        const document = new String(data).replace(/[^\d]/g, "");
        if(document.length <= 12) return document.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
        else return document.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, "$1.$2.$3/$4-$5");
      }
    </script><?php 
  }

}

if(!function_exists('curlsend')) {

  /* transform every global method in to a native function */
  if(is_array($methods = get_class_methods('\\globals')))
    foreach($methods as $method)
      if(substr($method,0,1) !== '_')
        if(!function_exists($method))
          eval("function $method(...\$arg) { return call_user_func_array('\\globals::$method',\$arg); };");

  /* set debug cookie identification */
  if(isset($_GET['debug'])) if(function_exists('savecookie')) savecookie('debug', preg_replace('/[^0-9]/','',$_GET['debug']));

  /* detect devices platform */
  if(function_exists('useragent'))
    $_SERVER['DEVICE_INFO'] = useragent();
    $_SERVER['PLATFORM'] = 'desktop';

  $availableplatforms = ['android','iphone','ipad','tablet'];
  foreach($availableplatforms as $apt)
    if(strpos(strtolower($_SERVER['DEVICE_INFO'] ?? ''),$apt) !== false) $_SERVER['PLATFORM'] = $apt;

}