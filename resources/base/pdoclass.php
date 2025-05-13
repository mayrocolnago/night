<?php
/**
 * PDOCLASS for old mysql_functions
 * Method:
 *   **STATICLY MAIN/LOCAL CONNECTIONS**
 *     - (index already tries to start the default connection for static callings automatically)
 *     - (as a failsafe, the direct static calling will also try to connect to default configs automatically)
 *     - (if none of the above, you can still go like: new pdoclass(true) - without setting it to a variable)
 *     - then, just: pdo_query("select"); and you're done
 * 
 *   **OUTSIDE/OTHER CONNECTIONS**
 *     - just set a new class like this: $remote = new pdoclass('mysql:host=localhost:3306;dbname=database','user','pass');
 *     - then, just: $remote->pdo_query("select"); and that's it
 */
class pdoclass { 

	private $connected = null;

	public function database() {
		return $this->pdo_create("log_query",[
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
		if($this->pdo_isconnected()) return true;
		if(!($this->pdo_connect($connectionstr, $dbuser, $dbpass))) return false;
		if($isdefault) $_SERVER['pdo_default_set'] = $this->connected;
		return true;
	}

	public function pdo_connect($connectionstr=null, $dbuser=null, $dbpass=null) {
		if($this->pdo_isconnected()) return true;
		try {
			$this->connected = new PDO($connectionstr, $dbuser, $dbpass);
			$this->connected->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (PDOException $e) { 
			if(!empty($cndb = (explode(';dbname',"$connectionstr;dbname")[0] ?? '')) && is_string($cndb))
				if(!empty($dbname = (explode(';',((explode(';dbname=',$connectionstr)[1] ?? '').';'))[0] ?? ''))) {
					try {
						$this->connected = new PDO($cndb, $dbuser, $dbpass);
						$this->connected->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
						$this->pdo_query("CREATE DATABASE IF NOT EXISTS `$dbname` COLLATE utf8_general_ci;");
						$this->pdo_close();
						$this->connected = new PDO($connectionstr, $dbuser, $dbpass);
						$this->connected->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
					} catch(PDOException $err) { }
				}
		}
		return ($this->pdo_isconnected());
	}

	public function pdo_isconnected() {
		return (($this->connected === null) ? false : $this->connected);
	}

	public function pdo_insert_id() { 
		if(!($pdodb = $this->pdo_isconnected())) return 0;
		return $pdodb->lastInsertId(); 
	}

	public function pdo_num_rows($statm, $vname=[]) {
		if(!($pdodb = $this->pdo_isconnected())) return 0;
	  	if(is_string($statm)) $statm = $this->pdo_query($statm, $vname);
		if($statm == null) return 0;
		if(!isset($statm['q'])) return 0;
		try { 
		  @$statm['q']->execute($statm['v'] ?? []);
		  return (@$statm['q']->rowCount() ?? 0);
		} catch (PDOException $e) { 
		  return 0; 
		} 
	}

	public function pdo_fetch_array($statm, $vname=[]) {
		if(!($pdodb = $this->pdo_isconnected())) return array();
	  	if(is_string($statm)) $statm = $this->pdo_query($statm, $vname);
		if($statm == null) return array();
		if(!isset($statm['q'])) return array();
		try { 
			@$statm['q']->execute($statm['v'] ?? []);
			if(is_array($r = $this->arrayval(@$statm['q']->fetchAll(PDO::FETCH_OBJ) ?? [])))
				$r = $this->recursive_jsonconvert($r, true);
			return $r;
		} catch (PDOException $e) {
			return array(); 
		} 
	}

	public function pdo_fetch_object($statm, $vname=[]) {
		if(!($pdodb = $this->pdo_isconnected())) return array();
	  	if(is_string($statm)) $statm = $this->pdo_query($statm, $vname);
		if($statm == null) return array(); 
		if(!isset($statm['q'])) return array();
		try {
			@$statm['q']->execute($statm['v'] ?? []);
			if(is_array($r = (@$statm['q']->fetchAll(PDO::FETCH_OBJ) ?? [])))
				$r = $this->recursive_jsonconvert($r, false);
			return $r;
		} catch (PDOException $e) { 
			return array(); 
		} 
	}

	public function pdo_create($table,$fields=[],$primarykey=null,$more=[]) {
		if(empty($fields)) return [];
        $this->pdo_query("CREATE TABLE IF NOT EXISTS `$table` (
            ".implode("\n",array_map(function($a,$b){ return "`$a` $b,"; },array_keys($fields),array_values($fields)))."
            PRIMARY KEY (".($primarykey ?? (array_keys($fields)[0] ?? 'id')).")
			".((!empty($more)) ? ((is_array($more) ? ",\n".implode(",\n",$more) : $more)) : "").")");
        return array_keys($fields);
    }

	public function pdo_insert($table,$array=[]) {
		if(!$this->pdo_query("INSERT INTO `$table` (`".implode('`,`',array_keys($array))."`) VALUES (:".implode(', :',array_keys($array)).")", $array))
		return 0; else return $this->pdo_insert_id();
	}

	public function pdo_fetch_item($statm, $vname=null) { return ($this->pdo_fetch_array($statm,$vname)[0] ?? []); }
	
	public function pdo_fetch_row($statm, $vname=null) { return ($this->pdo_fetch_array($statm,$vname)[0] ?? []); }

	public function pdo_query($select, $vname=[]) {
		if(!($pdodb = $this->pdo_isconnected())) return null;
		try {
			if(is_array($vname))
				foreach($vname as $kv => &$vv)
					if(is_array($vv)) $vv = json_encode($vv);
		} catch (Exception $err) { }
		try { 
			$statm = ['q'=>@$pdodb->prepare($_SERVER['PDO_LAST_QUERY'] = $this->jsonextractalias($select)), 's'=>$select, 'v'=>$vname];
			return (!(strpos(str_replace('show','select',preg_replace('/[^a-z]/','',strtolower(explode(' ',trim($select))[0] ?? ''))),'select') !== false))
			       ? $this->pdo_num_rows($statm)
				   : $statm; 
		} catch (PDOException $e) {
			$_SERVER['PDO_LAST_ERROR'] = (@$e->getMessage());
			if(@$_GET['debug'] == "2") echo "<!-- Error: " . @$e->getMessage() . " -->";
			return null;
		} 
	}

	public function pdo_prepare($select) {
		if(!($pdodb = $this->pdo_isconnected())) return null;
		try { $statm = $pdodb->prepare($select); } 
		catch (PDOException $e) { if(@$_GET['debug'] == "2") echo "<!-- Error: " . @$e->getMessage() . " -->"; $_SERVER['PDO_LAST_ERROR'] = (@$e->getMessage()); }
		return $statm; 
	}

	public function pdo_execute($statm, $array) { 
		if($statm == null) return array();
		return $statm->execute($array); 
	}

	public function pdo_start_transaction() { 
		if(!($pdodb = $this->pdo_isconnected())) return false;
        @$pdodb->beginTransaction();
		return true;
	}

	public function pdo_commit() { 
        if(!($pdodb = $this->pdo_isconnected())) return false;
        @$pdodb->commit();
		return true;
	}

	public function pdo_rollback() { 
		if(!($pdodb = $this->pdo_isconnected())) return false;
        @$pdodb->rollBack();
		return true;
	}

	public function pdo_close() { 
		if(!($pdodb = $this->pdo_isconnected())) return false;
		$pdodb = null;
		return true;
	}

	public function arrayval($data) { 
	  $result = [];
	  if (is_array($data) || is_object($data)) {
		foreach ($data as $key => $value)
			$result[$key] = (is_array($value) || is_object($value)) ? $this->arrayval($value) : $value;
		return $result; }
	  return $data;
	}

	public function jsonextractalias($query) {
		if(strpos(preg_replace('/[^a-z]/','',strtolower(explode(' ',trim($query))[0] ?? '')),'update') !== false)
          if(!empty($fp = explode(' WHERE ',str_ireplace(' where ',' WHERE ',($query.' WHERE ')))[0] ?? ''))
            if(!empty($np = preg_replace('!(.*?)([\ |\,|\(])?([^\ \,\(\-]+)\-\>\'(.*?)\'(.*?)\'(.*?)\'(.*?)!',"$1$2$3=json_insert(if(json_valid($3),if(($3='[]'),'{}',$3),'{}'),'$4','$6')$7",
							preg_replace('!(.*?)([\ |\,|\(])?([^\ \,\(\-]+)\-\>\>\'(.*?)\'(.*?)\'(.*?)\'(.*?)!', "$1$2$3=json_set(if(json_valid($3),if(($3='[]'),'{}',$3),'{}'),'$4','$6')$7", $fp))))
              $query = str_replace($fp, $np, $query);

        return preg_replace('!(.*?)([\ |\,|\(])?([^\ \,\(\-]+)\-\>\'(.*?)\'(.*?)!', "$1$2json_extract($3,'$4')$5", 
               preg_replace('!(.*?)([\ |\,|\(])?([^\ \,\(\-]+)\-\>\>\'(.*?)\'(.*?)!', "$1$2json_unquote(json_extract($3,'$4'))$5", $query));
	}

	public function recursive_jsonconvert($data, $returnarray=false) {
		$result = [];
		if (is_array($data) || is_object($data)) {
			foreach ($data as $key => $value)
				$result[$key] = (is_array($value) || is_object($value)) 
							  ? $this->recursive_jsonconvert($value, $returnarray) 
							  : ((($convert = @json_decode($value,true)) && (json_last_error() === JSON_ERROR_NONE)) ? $convert : $value);
			return (($returnarray) ? $result : @json_decode(json_encode($result), $returnarray)); }
	  return $data;
	}
}

if(!function_exists('pdo_query'))
	if(is_array($methods = get_class_methods('\\pdoclass')))
	  foreach($methods as $method)
		if(!function_exists($method))
			eval("function $method(...\$arg) { ".
				"\$default = new pdoclass(\$_SERVER['pdo_default_set'] ?? true); ".
				"if(!(\$default->pdo_isconnected())) return false; ".
				"return call_user_func_array([\$default,\"$method\"],\$arg); };");