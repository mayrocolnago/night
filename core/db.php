<?php
/**
 * A seamless PDO class replacement of old mysql_functions
 * Method:
 *   **STATICLY MAIN/LOCAL CONNECTIONS**
 *     - (index already tries to start the default connection for static callings automatically)
 *     - (as a failsafe, the direct static calling will also try to connect to default configs automatically)
 *     - (if none of the above, you can still go like: new \db(true) - without setting it to a variable)
 *     - then, just: \db::query("select"); and you're done
 * 
 *   **OUTSIDE/OTHER CONNECTIONS**
 *     - just set a new class like this: $remote = new \db('mysql:host=localhost:3306;dbname=database','user','pass');
 *     - then, just: $remote->query("select"); and that's it
 */
class db { 

	private $connected = null;

	protected function database() {
		return $this->create("log_query",[
			"id" => "bigint(20) NOT NULL AUTO_INCREMENT",
			"query" => "longtext NULL DEFAULT NULL",
			"parameters" => "longtext NULL DEFAULT NULL",
			"response" => "longtext NULL DEFAULT NULL",
			"runat" => "int NULL"
		]);
	}

	public function __construct(...$params) {
		$connectionstr = null; $dbuser = null; $dbpass = null; $isdefault = false;
		foreach($params as $param)
			if(is_bool($param)) $isdefault = $param;
			else if(is_object($param)) $this->connected = $param;
			else if(empty($connectionstr)) $connectionstr = $param;
			else if(empty($dbuser)) $dbuser = $param;
			else if(empty($dbpass)) $dbpass = $param;
		try { if((debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['type'] ?? '') === '->') $isdefault = true; } catch(Exception $err) { }
		if(empty($connectionstr) && empty($dbuser) && empty($dbpass)) $isdefault = true;
		if(empty($connectionstr = ($connectionstr ?? ($_SERVER['dbstring'] ?? ($_SERVER['DB_STRING'] ?? ''))))) return false;
		if(empty($dbuser = ($dbuser ?? ($_SERVER['dbuser'] ?? ($_SERVER['DB_USER'] ?? ''))))) return false;
		if(empty($dbpass = ($dbpass ?? ($_SERVER['dbpass'] ?? ($_SERVER['DB_PASS'] ?? ''))))) return false;
		if($this->isconnected()) return true;
		if(!($this->connect($connectionstr, $dbuser, $dbpass))) return false;
		if($isdefault) $_SERVER['PDO_DEFAULT_SET'] = $this->connected;
		return true;
	}

	protected function connect($connectionstr=null, $dbuser=null, $dbpass=null) {
		if($this->isconnected()) return true;
		try {
			$this->connected = new PDO($connectionstr, $dbuser, $dbpass);
			$this->connected->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (PDOException $e) { 
			if(!empty($cndb = (explode(';dbname',"$connectionstr;dbname")[0] ?? '')) && is_string($cndb))
				if(!empty($dbname = (explode(';',((explode(';dbname=',$connectionstr)[1] ?? '').';'))[0] ?? ''))) {
					try {
						$this->connected = new PDO($cndb, $dbuser, $dbpass);
						$this->connected->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
						$this->query("CREATE DATABASE IF NOT EXISTS `$dbname` COLLATE utf8_general_ci;");
						$this->close();
						$this->connected = new PDO($connectionstr, $dbuser, $dbpass);
						$this->connected->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
					} catch(PDOException $err) { }
				}
		}
		return ($this->isconnected());
	}

	protected function isconnected() {
		return (($this->connected === null) ? false : $this->connected);
	}

	protected function insert_id() { 
		if(!($pdodb = $this->isconnected())) return 0;
		return $pdodb->lastInsertId(); 
	}

	protected function num_rows($statm, $vname=[]) {
		if(!($pdodb = $this->isconnected())) return 0;
	  	if(is_string($statm)) $statm = $this->query($statm, $vname);
		if($statm == null) return 0;
		if(!isset($statm['q'])) return 0;
		try { 
		  	@$statm['q']->execute($statm['v'] ?? []);
		  	return (@$statm['q']->rowCount() ?? 0);
		} catch (PDOException $e) {
			log($statm, $e);
		  	return 0; 
		} 
	}

	protected function fetch_array($statm, $vname=[]) {
		if(!($pdodb = $this->isconnected())) return array();
	  	if(is_string($statm)) $statm = $this->query($statm, $vname);
		if($statm == null) return array();
		if(!isset($statm['q'])) return array();
		try { 
			@$statm['q']->execute($statm['v'] ?? []);
			if(is_array($r = $this->arrayval(@$statm['q']->fetchAll(PDO::FETCH_OBJ) ?? [])))
				$r = $this->recursive_jsonconvert($r, true);
			return $r;
		} catch (PDOException $e) {
			log($statm, $e);
			return array(); 
		} 
	}

	protected function fetch_object($statm, $vname=[]) {
		if(!($pdodb = $this->isconnected())) return array();
	  	if(is_string($statm)) $statm = $this->query($statm, $vname);
		if($statm == null) return array(); 
		if(!isset($statm['q'])) return array();
		try {
			@$statm['q']->execute($statm['v'] ?? []);
			if(is_array($r = (@$statm['q']->fetchAll(PDO::FETCH_OBJ) ?? [])))
				$r = $this->recursive_jsonconvert($r, false);
			return $r;
		} catch (PDOException $e) { 
			log($statm, $e);
			return array(); 
		} 
	}

	protected function create($table,$fields=[],$primarykey=null,$more=[]) {
		if(empty($table)) return [];
		if(empty($fields)) return [];
        $this->query("CREATE TABLE IF NOT EXISTS `$table` (
            ".implode("\n",array_map(function($a,$b){ return "`$a` $b,"; },array_keys($fields),array_values($fields)))."
            PRIMARY KEY (".($primarykey ?? (array_keys($fields)[0] ?? 'id')).")
			".((!empty($more)) ? ((is_array($more) ? ",\n".implode(",\n",$more) : $more)) : "").")");
        return array_keys($fields);
    }

	protected function insert($table,$array=[]) {
		if(!$this->query("INSERT INTO `$table` (`".implode('`,`',array_keys($array))."`) VALUES (:".implode(', :',array_keys($array)).")", $array))
		return 0; else return $this->insert_id();
	}

	protected function update($table,$data=[]) {
		$where = []; $values = []; $jsonset = []; $jsonvalues = [];
		$convchars = function($string) {
			if(is_null($string) || is_bool($string)) return $string;
			if(is_array($string) || is_object($string)) return $string;
			if(empty(@preg_replace('/[0-9\.\+\-]/','',($string ?? '')))) return $string;
			if(is_callable('emojientities')) return emojientities($string);
			return htmlentities($string,ENT_QUOTES|ENT_HTML5,'UTF-8',false); };
        if(is_array($data))
            foreach($data as $k => $v)
                if(!(strpos($k,':') !== false))
                   if(!empty(@preg_replace('/[^a-zA-Z]/','',($k = @preg_replace('/[^0-9a-zA-Z\.\_\|]/','',str_replace('-','.',$k))))))
                       if(!(strpos($k,'.') !== false)) $values[$k] = $convchars($v);
                       else if(is_array($parse = explode('.',$k)) && !empty($primary = ($parse[0] ?? '')))
                               if(!empty($subset = substr_replace($k, '', ((($p=strpos($k, ($n="$primary.")))===false)?0:$p), strlen($n)))) {
                                    if(!isset($jsonset[$primary])) $jsonset[$primary] = [];
                                    $jsonset[$primary][$subset] = $v; }
        foreach($jsonset as $primary => $subset) {
            $jsonvalues[$primary] = "json_set(if(json_valid($primary),if(($primary='[]'),'{}',$primary),'{}')";
            foreach($subset as $k => $v) {
                $jsonvalues[$primary] .= ",'\$.$k',?";
                $values["--".preg_replace('/[^0-9a-zA-Z]/','',"$primary$k")] = $convchars($v); }
            $jsonvalues[$primary] .= ")"; }
        if(is_array($data))
            foreach($data as $k => $v)
                if(strpos($k,':') !== false)
                    if(!empty(@preg_replace('/[^a-zA-Z]/','',($k = @preg_replace('/[^0-9a-zA-Z\.\_\|\!]/','',str_replace('-','.',$k)))))) {
                        if(($v = $convchars($v)) === null) $k .= '^';
                        if(!(strpos($k,'.') !== false)) $where[$k] = $v;
                        else if(is_array($parse = explode('.',$k)) && !empty($primary = ($parse[0] ?? '')))
                                if(!empty($subset = substr_replace($k, '', ((($p=strpos($k, ($n="$primary.")))===false)?0:$p), strlen($n))))
                                    $where["json_value($primary,'\$.$subset')"] = $v; }
        return intval($this->query("UPDATE $table SET ".implode(', ',array_merge_recursive(
                array_filter(array_map(function($a){ if(substr($a,0,2) == '--') return null; return " `$a` = ? "; }, array_keys($values))),
                array_map(function($a,$b){ return " `$a` = $b "; }, array_keys($jsonvalues), array_values($jsonvalues)))).
            " WHERE ".preg_replace('/^(OR |AND )|(OR |AND )$/', '', implode(" ", array_map(function($a){
                return (((strpos($a,'|') !== false) ? "OR " : "AND ").str_replace(['|','!','^','~'],'',$a).
                        ((strpos($a,'!') !== false && (!(strpos($a,'^') !== false))) ? " NOT" : "").
                        ((strpos($a,'^') !== false) ? " IS ".((strpos($a,'!') !== false) ? "NOT " : "") : " LIKE ")."?");
            }, array_keys($where)))), array_values(array_merge(array_values($values), array_values($where)))));
	}

	protected function fetch_item($statm, $vname=null) { return ($this->fetch_array($statm,$vname)[0] ?? []); }
	
	protected function fetch_row($statm, $vname=null) { return ($this->fetch_array($statm,$vname)[0] ?? []); }

	protected function fetch($statm, $vname=null) { return $this->fetch_array($statm,$vname); }

	protected function query($select, $vname=[]) {
		if(!($pdodb = $this->isconnected())) return null;
		try {
			if(is_array($vname))
				foreach($vname as $kv => &$vv)
					if(is_array($vv)) $vv = json_encode($vv);
		} catch (Exception $err) { }
		try { 
			$statm = ['q'=>@$pdodb->prepare($_SERVER['PDO_LAST_QUERY'] = $this->jsonextractalias($select)), 's'=>$select, 'v'=>$vname];
			return (!(strpos(str_replace('show','select',preg_replace('/[^a-z]/','',strtolower(explode(' ',trim($select))[0] ?? ''))),'select') !== false))
			       ? $this->num_rows($statm)
				   : $statm; 
		} catch (PDOException $e) {
			$_SERVER['PDO_LAST_ERROR'] = (@$e->getMessage());
			//echo "<!-- Error: " . @$e->getMessage() . " -->";
			log(($statm ?? []), $e);
			return null;
		} 
	}

	protected function log($statm, $eo = null, $force = false) {
		if((!$force) && (!($_SERVER['DEVELOPMENT'] ?? false)) && (!($_SERVER['PDO_ENABLE_LOGQUERY'] ?? false))) return null;
		if(!($pdodb = $this->isconnected())) return null;
		try { $this->database(); $e = $eo; if(is_array($e) || is_object($e)) $e = json_encode($e);
			if(($clear = @$pdodb->prepare("select count(*) qtd from log_query"))->execute())
				if(($qtd = intval($clear->fetchAll()[0]['qtd'] ?? -1)) > 1000)
					@$pdodb->prepare("delete from log_query order by id asc limit 100")->execute();
			if((!empty($statm['s'] ?? '')) && is_string($statm['s'] ?? ''))
				if(!(strpos(preg_replace('/[^a-z]/','',strtolower(explode(' ',trim($statm['s']))[0] ?? '')),'set') !== false))
						@$pdodb->prepare("INSERT INTO log_query (query, parameters, response, runat) VALUES (:q, :p, :r, :t)")
							->execute(['q'=>($statm['s'] ?? null), 'p'=>json_encode($statm['v'] ?? []), 
										'r'=>($statm['d'] ?? ($e ?? null)), 't'=>strtotime('now')]);
		} catch (PDOException $e) { } 
		return $eo; 
	}


	protected function prepare($select) {
		if(!($pdodb = $this->isconnected())) return null;
		try { $statm = $pdodb->prepare($select); } 
		catch (PDOException $e) { $_SERVER['PDO_LAST_ERROR'] = (@$e->getMessage()); }
		return $statm; 
	}

	protected function execute($statm, $array) { 
		if($statm == null) return array();
		return $statm->execute($array); 
	}

	protected function start_transaction() { 
		if(!($pdodb = $this->isconnected())) return false;
        @$pdodb->beginTransaction();
		return true;
	}

	protected function commit() { 
        if(!($pdodb = $this->isconnected())) return false;
        @$pdodb->commit();
		return true;
	}

	protected function rollback() { 
		if(!($pdodb = $this->isconnected())) return false;
        @$pdodb->rollBack();
		return true;
	}

	protected function close() { 
		if(!($pdodb = $this->isconnected())) return false;
		$pdodb = null;
		return true;
	}

	protected function arrayval($data) { 
	  $result = [];
	  if (is_array($data) || is_object($data)) {
		foreach ($data as $key => $value)
			$result[$key] = (is_array($value) || is_object($value)) ? $this->arrayval($value) : $value;
		return $result; }
	  return $data;
	}

	protected function jsonextractalias($query) {
		if(strpos(preg_replace('/[^a-z]/','',strtolower(explode(' ',trim($query))[0] ?? '')),'update') !== false)
          if(!empty($fp = explode(' WHERE ',str_ireplace(' where ',' WHERE ',($query.' WHERE ')))[0] ?? ''))
            if(!empty($np = preg_replace('!(.*?)([\ |\,|\(])?([^\ \,\(\-]+)\-\>\'(.*?)\'(.*?)\'(.*?)\'(.*?)!',"$1$2$3=json_insert(if(json_valid($3),if(($3='[]'),'{}',$3),'{}'),'$4','$6')$7",
							preg_replace('!(.*?)([\ |\,|\(])?([^\ \,\(\-]+)\-\>\>\'(.*?)\'(.*?)\'(.*?)\'(.*?)!', "$1$2$3=json_set(if(json_valid($3),if(($3='[]'),'{}',$3),'{}'),'$4','$6')$7", $fp))))
              $query = str_replace($fp, $np, $query);

        return preg_replace('!(.*?)([\ |\,|\(])?([^\ \,\(\-]+)\-\>\'(.*?)\'(.*?)!', "$1$2json_extract($3,'$4')$5", 
               preg_replace('!(.*?)([\ |\,|\(])?([^\ \,\(\-]+)\-\>\>\'(.*?)\'(.*?)!', "$1$2json_unquote(json_extract($3,'$4'))$5", $query));
	}

	protected function recursive_jsonconvert($data, $returnarray=false) {
		$result = [];
		if (is_array($data) || is_object($data)) {
			foreach ($data as $key => $value)
				$result[$key] = (is_array($value) || is_object($value)) 
							  ? $this->recursive_jsonconvert($value, $returnarray) 
							  : ((($convert = @json_decode($value,true)) && (json_last_error() === JSON_ERROR_NONE)) ? $convert : $value);
			return (($returnarray) ? $result : @json_decode(json_encode($result), $returnarray)); }
	  return $data;
	}

	public static function __callStatic($name='', $arguments=[]) {
        static $instance = null;
        if($instance === null) $instance = new \db();
        return ($instance->{@preg_replace('/[^a-zA-Z\0-9\_]/','',$name)} ?? ($instance->{$name}(...$arguments) ?? null));
    }
}