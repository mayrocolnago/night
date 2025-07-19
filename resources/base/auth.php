<?php
abstract class auth {

    //Implement public $table = 'table_name'; on your class and any of desired configurations from above then extend this class

    public $lockRetries = false; // Enable brute force protection
    public $lockPenalty = 5; // Penalty time in minutes per error
    public $unlockRetry = 5; // Number of attempts before lockout

    public $CSRFprotection = false; // Enables CSRF protection module
    public $allowAPIaccess = false; // Enables possibility of accessing resources through an API key (this bypasses the CSRF)
    public $allow2FAsecret = false; // Whether to verify for 2FA secrets on signin for validation
    public $useDeviceAuth = false; // Use device authentication (a protection layer that return "verified" for a second hash)

    public $allowJWTaccess = false; // Enables the generation of JWT tokens on signin for user access via token parameter
    public $expireJWTtime  = "+1 month"; // Choose when to expire the JWT token (false = never or "+1 day")

    // Configuration for API access (if disabled you must access through static call methods)

    public $allowAPIget = true; // Allow getting user information by API
    public $allowAPIset = true; // Allow setting user information by API
    public $allowAPIsignup = false; // Allow user creation though API access
    public $allowAPIisauthed = true; // Allow verifying if user is authenticated by API
    public $allowAPIisverified = true; // Allow verifying if user is has a valid device authentication token by API
    public $allowAPIchpass = true; // Allow changing password through API access
    public $allowAPIrecovery = true; // Allow user possibility to recover their passwords (further frontend implementation required)
    public $allowAPIexists = true; // Allow API access to verify if a specific username/login already exists
    public $allowAPIsuggest = true; // Allow API access to get a username/login suggestion based on an input

    // More useful configurations

    public $infoKeys = ['email','tel','doc','birthdate','address','number','complement','neighborhood','city','state','country','zipcode']; // Changeable information on APIs that will appear masked

    public $secretAdd = null; // Append something to the secret in case of multiples authentications across interfaces
    public $masterKey = null; // Set a master password (in hash512) to access any account (not secure)

    public $table = 'users';

    private $cache = [];

    private function secret($default=null) {
        return (($this->secretAdd ?? $this->table).($_SERVER['signature'] ?? ($_SERVER['SIGNATURE'] ?? ($_SERVER['secret'] ?? ($_SERVER['SECRET'] ?? md5(REPODIR))))));
    }

    private function csrf($data = [], $generate = false) {
        //Verify if protection is active
        if(!($this->CSRFprotection ?? false)) return true;
        //Verify whether if its correct or generate a new one
        $csrf = @preg_replace('/[^0-9a-zA-Z]/','',($data['csrf'] ?? $data));
        if($generate || empty($_SESSION['csrf_token'] ?? '') || (($_SESSION['csrf_token'] ?? '') !== $csrf))
           $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        //In case it is generation a new one, just return it
        if($generate) return $_SESSION['csrf_token'];
        //If it is not generating, then verify if it is correct
        return (($_SESSION['csrf_token'] ?? '') === $csrf);
    }

    private function database() {
        $fields = [
            "id" => "bigint(20) NOT NULL AUTO_INCREMENT", //Core reference of user
            "active" => "tinyint(1) DEFAULT '1'", //Whether user is active or not
            "name" => "varchar(200) NULL DEFAULT NULL", //User social nome
            "login" => "varchar(200) NULL DEFAULT NULL", //login method (email, doc, phone, etc)
            "passw" => "longtext NULL", //user password 512 hashed
            "info" => "json NULL", //usually used to store other info besides login (email, phone, etc)
            "config" => "json NULL", //useful for storing configurations visible on the getuser api
            "tags" => "json NULL", //useful for storing tags or configurations not visible on the api
            "devices" => "json NULL", //when using single api token, this stores the devices user used
            "permission" => "longtext NULL" //used to reference user permissions by comma separated values
        ];
        $uniques = [ "UNIQUE KEY login (login)" ];
        if($this->allowAPIaccess ?? false) {
            $fields['apikey'] = "longtext NULL";
            $uniques[] = "UNIQUE KEY apikey (apikey)"; }
        if($this->lockRetries ?? false) $fields['lockpenalty'] = "bigint(20) NULL DEFAULT NULL";
        $fields["lastseen"] = "bigint(20) NULL DEFAULT NULL";
        $fields["created"] = "bigint(20) NULL DEFAULT NULL";
        return pdo_create($this->table,$fields,"id",$uniques);
    }

    private function isauthed($data=[]):\route {
        return response()->data(intval($this->id()) > 0);
    }

    private function isverified($data=[]):\route {
        if(!($this->useDeviceAuth ?? false)) return response()->data(true);
        if(is_null($token = null) && intval($this->id($token)) <= 0) return response()->data(false);
        if(empty($dv=($data['deviceauth'] ?? ''))) return response()->data(false);
        return response()->data($this->deviceauth($token) === $dv);
    }

    private function deviceauth($token) { return md5($token.$this->secret()); }

    private function get($data=null,$id=null,$mask=null):\route {
        //Configure whether we are masking values or not
        $mask = ($mask ?? (empty($id)));
        //If no id is provided, try to get the current user id
        if(empty($id = intval(@preg_replace('/[^0-9]/','',($id ?? $this->id()))))) return response()->data(0);
        //If we already built this information, there is no need to build it again
        if(empty($user = ($this->cache["userdata_$id".(($mask)?"_masked":"_unmasked")] ?? []))) {
            //If there is an id, get the user data
            if(empty($info = pdo_fetch_row("SELECT * FROM {$this->table} WHERE id='$id' and active='1' LIMIT 1"))) return response()->data(0);
            //Build user specific informations
            $user = ['id'=>intval($info['id'] ?? 0)];
            $arrays = ['info','config','tags','devices'];
            $treats = ['id','active','info','permission'];
            $prohibid = ['passw','devices','tags'];
            foreach($info as $k => $v) {
                if(in_array($k,$arrays)) $v = ((!is_array($v=((!is_array($v ?? '')) ? @json_decode(($v ?? '[]'),true) : $v))) ? [] : $v);
                if(!in_array($k,$treats) && !in_array($k,$prohibid)) $user[$k] = $v;
                else if(in_array($k,$prohibid) && (!$mask)) $user[$k] = $v;
                else if(in_array($k,$treats) && $k === 'permission') $user[$k] = array_filter(explode(',',','.($v ?? '')));
                else if(in_array($k,$treats) && $k === 'active') $user[$k] = (intval($v ?? 0) === 1); 
                else if(in_array($k,$treats) && $k === 'info') $user[$k] = str_maskmiddle_array($v,[],3); }
            //Gether specific function data
            if($this->lockRetries ?? false) $user['locked'] = (($info['lockpenalty'] ?? 0) > strtotime('now'));
            if($this->useDeviceAuth ?? false) $user['deviceauthed'] = $this->isverified();
            if($this->allowJWTaccess ?? false) $user['iat'] = strtotime('now');
            if(is_string($this->expireJWTtime ?? false)) $user['exp'] = strtotime($this->expireJWTtime);
            //Cache the user data
            $this->cache["userdata_$id".(($mask)?"_masked":"_unmasked")] = $user;
        }
        //Check whether we are returning a single value
        if($data === '*') $data = null;
        if(is_string($data) && !empty($data)) return response()->data($user[$data] ?? ($user['info'][$data] ?? ($user['tags'][$data] ?? ($user['config'][$data] ?? null))));
        //Otherwise return the entire array
        return response()->data($user);
    }

    private function set($data=null,$value=null,$id=null):\route {
        //If no id is provided, try to get the current user id
        if(empty($id = intval(@preg_replace('/[^0-9]/','',($id ?? $this->id()))))) return response()->data(0);
        //Get user to update directly from their contents
        if(empty($user = ($this->get('*',$id,false)->data))) return response()->data(0);
        //Configure updatables
        $infkeys = $this->infoKeys;
        $tfields = $this->database();
        $allowup = (!is_array($data));
        $payload = [ 'info' => ($user['info'] ?? []), 'config' => ($user['config'] ?? []), 'lastseen' => strtotime('now') ];
        //Clear cache information to force new load
        $this->cache["userdata_$id"."_masked"] = $this->cache["userdata_$id"."_unmasked"] = [];
        //Check if data is updatable string information
        if(in_array(((is_string($data)) ? $data : 'nop'),['name','login','passw','active','lastseen'])) $data = [$data=>$value];
        //Update informations within the data array to payload
        if(!is_array($data)) return response()->data(0);
        foreach($data as $k => $v)
            if($k === 'name') $payload[$k] = emojientities(html_entity_decode(@trim($v ?? '')));
            else if($k === 'login' && $allowup) $payload[$k] = @trim($v ?? '');
            else if($k === 'passw' && $allowup) $payload[$k] = hash('sha512',$v);
            else if($k === 'active' && ($allowup || @trim($v ?? '') !== '1')) $payload[$k] = ((@trim($v ?? '') === '1') ? '1' : '0');
            else if(!in_array($k,$tfields))
                    if(!empty($tofield = ((is_array($infkeys) && in_array($k,$infkeys)) ? "info" : "config")))
                        $payload[$tofield][$k] = $v;
        //Finally update the user
        return response()->data(pdo_query("UPDATE {$this->table} SET ".implode(', ',array_map(function($a){ return "`$a`=:$a"; }, array_keys($payload)))." WHERE id='$id' LIMIT 1",$payload));
    }

    private function chpass($data=[]):\route {
        if(empty($current = ($data['current'] ?? ($data['before'] ?? '')))) return response()->data(-1);
        if(empty($pass = ($data['passw'] ?? ($data['pass'] ?? ($data['password'] ?? ($data['senha'] ?? ($data['new'] ?? ''))))))) return response()->data(-2);
        if(empty($conf = ($data['confirm'] ?? ($data['passconf'] ?? ($data['conf'] ?? ($data['confirmation'] ?? '')))))) return response()->data(-3);
        if($this->get('passw',null,false)->data !== hash('sha512',$current)) return response()->data(-4);
        if($pass !== $conf) return response()->data(-5);
        return response()->data($this->set('passw', $pass)->data);
    }

    private function recovery($data=[], &$key=null):\route {
        if(!empty($tk = preg_replace('/[^0-9a-z\_]/','',strtolower($data['key'] ?? '')))) $key = $tk;
        //Hashing generator
        $hasher = function($id,$passw){ return ($id.'_'.hash('sha512',($id.strtotime(date('Y-m-d')).$passw.$this->secret()))); };
        //Getting a new hash
        if(empty($key))
            if(empty($login = preg_replace('/[^0-9a-z\@\_\+]/','',strtolower($data['login'] ?? '')))) return response()->data(-1);
            else if(!is_array($user = pdo_fetch_row("SELECT id, passw FROM {$this->table} WHERE login=:u AND active='1' LIMIT 1",['u'=>$login]))) return response()->data(-2);
            else if(empty($id = ($user['id'] ?? '')) || empty($passw = ($user['passw'] ?? ''))) return response()->data(-3);
            else if(empty($key = $hasher($id, $passw))) return response()->data(-4);
            else return response()->data(1);
        //Using recovery key
        if(!(strpos($key,'_') !== false)) return response()->data(-6);
        if(!is_array($parse = explode('_',$key.'_'))) return response()->data(-7);
        if(!is_numeric($id = preg_replace('/[^0-9]/','',($parse[0] ?? '')))) return response()->data(-8);
        if(!is_array($user = pdo_fetch_row("SELECT passw FROM {$this->table} WHERE id=:id AND active='1' LIMIT 1",['id'=>$id]))) return response()->data(-9);
        if(empty($hash = preg_replace('/[^0-9a-z]/','',($parse[1] ?? '')))) return response()->data(-10);
        if(empty($pass = ($data['pass'] ?? ($data['passw'] ?? ($data['password'] ?? ($data['senha'] ?? ($data['new'] ?? ''))))))) return response()->data(-11);
        if(empty($conf = ($data['conf'] ?? ($data['passconf'] ?? ($data['confirm'] ?? ($data['confirmation'] ?? ($data['confirma'] ?? ''))))))) return response()->data(-12);
        if($pass !== $conf) return response()->data(-13);
        if(($verif = ($hasher($id, ($user['passw'] ?? '')))) !== $key) return response()->data(-14);
        return response()->data($this->set('passw', $pass, $id)->data);
    }

    private function signup($user=[],&$passunhashed=null,$autologin=true):\route {
        //If came only a full name as string, generate the user
        if(is_string($user) && strpos($user,' ') !== false) $user = ['name'=>$user, 'login'=>$this->suggest($user)->data];
        //Verify if variable is an array of data
        if(!is_array($user)) $user = ["login"=>$user];
        //Verify autologin setting
        if(isset($user['noautologin'])) $autologin = false;
        //Correct any possible wrong field name
        $previous = $user; $user = [];
        $known = $this->database();
        $forbidden = ['id','active','devices','tags','permission'];
        $convert = [
            'credential' => 'login', 'credencial' => 'login', 'username' => 'login', 'usuario' => 'login', 'acesso' => 'login', 'auth' => 'login', 
            'fullname' => 'name', 'nome' => 'name', 'senha' => 'passw', 'keypass' => 'passw', 'password' => 'passw', 'keyphrase' => 'passw', 
            'registered' => 'created' ];
        //Validade the arrays
        foreach(['info','config','devices','tags'] as $a) $user[$a] = (validatearray($user[$a] ?? []));
        foreach($previous as $k=>$v)
            if(!is_numeric($k) && !empty($c = preg_replace('/[^0-9a-z]/','',strtolower($k))))
                if(in_array(($c = ($convert[$c] ?? $c)),$known) && (!in_array($c,$forbidden))) $user[$c] = $v;
                else foreach(['info','config'] as $sub)
                    if(strpos($c,"$sub-") !== false && !empty($nc = @preg_replace('/[^0-9a-zA-Z\_]/','',str_replace("$sub-",'',$c))))
                        $user[$sub][$nc] = $v;
        //Creates a password if none is given
        if(empty($user["passw"] ?? '')) $user["passw"] = $passunhashed = ($passunhashed ?? substr(preg_replace('/[^0-9]/','',hash('sha512',uniqid())),0,6));
        //Verify if there is any of the other variables missing
        if(empty($user['name'] ?? '')) $user['name'] = ($user['login'] ?? 'User');
        if(empty($user['login'] ?? '')) $user['login'] = $this->suggest($user['name'])->data;
        if(empty($user['info']['email'] ?? '') && (!(strpos($user['login'],'@') !== false)))
            $user['info']['email'] = $user['login'].'@'.($_SERVER['SERVER_NAME'] ?? 'localhost');
        //validate remaning fields
        $user['name'] = emojientities(ucstrname(strtolower(urldecode($user['name']))));
        $user['passw'] = ((strlen($user['passw']) < 128) ? hash('sha512',$user['passw']) : $user['passw']);
        $user['passw'] = substr(preg_replace('/[^a-z0-9]/','',@strtolower($user['passw'])),0,200);
        $user['login'] = substr(preg_replace('/[^a-z0-9\@\.\_\-\+\/]/','',@strtolower($user['login'])),0,100);
        $user['created'] = substr(preg_replace('/[^0-9]/','',($user['created'] ?? strtotime('now'))),0,20);
        $user['lastseen'] = substr(preg_replace('/[^0-9]/','',($user['lastseen'] ?? strtotime('now'))),0,20);
        if(!empty($user['info']['doc'] ?? '')) $user['info']['doc'] = @preg_replace('/[^0-9\.\-\/]/','',($user['info']['doc']));
        if(!empty($user['info']['tel'] ?? '')) $user['info']['tel'] = @preg_replace('/[^0-9\(\)\ \-\+]/','',($user['info']['tel']));
        if(!empty($user['info']['email'] ?? '')) $user['info']['email'] = substr(preg_replace('/[^a-z0-9\@\.\_\-\+]/','',@strtolower($user['info']['email'])),0,100);
        //Create user and authenticate if must
        return response()->data([
            'result' => intval($id = pdo_insert($this->table,$user)), 
            'token' => (((intval($id) > 0) && $autologin) ? $this->token($id) : null),
            'jwt' => (((intval($id) > 0) && $autologin) ? "jwt.".jwt_encode($this->get('*',$id,true)->data, 512, $this->secret()) : null)
        ]);
    }

    private function signin($data=[]):\route {
        //If CSRF protection is enabled we should check for it
        if(($this->CSRFprotection ?? false) && (!$this->csrf($data)))
            return response()->data(['result'=>0, 'refresh'=>((is_string($this->csrf('generate',true))) ? true : true)]);
        //Read header parameters if present
        if(!empty($hauser=($_SERVER['PHP_AUTH_USER'] ?? '')) && empty($data['login'] ?? '')) $data['login'] = $hauser;
        if(!empty($hapass=($_SERVER['PHP_AUTH_PW'] ?? '')) && empty($data['passw'] ?? '')) $data['passw'] = $hapass;
        //Get parameters
        if(empty($login = @preg_replace('/[^0-9a-zA-Z\-\.\_\/\@]/','',($data['login'] ?? '')))) return response()->data(-403.41); //Missing login
        if(empty($passw = @trim($data['passw'] ?? ''))) return response()->data(-403.42); //Missing password parameter
        $passw = hash('sha512',$passw);
        $now = strtotime('now');
        //Start by searching for the user
        if(empty($id = intval(($db = pdo_fetch_row("SELECT id ".(($this->lockRetries ?? false) ? ",lockpenalty " : "")."
            ".(($this->allow2FAsecret ?? false) ? ",json_value(tags,'$.2fasecret') as 2fa " : "")."
            FROM {$this->table} WHERE active='1' AND (login=:usr OR login=:usnc) LIMIT 1",[
                'usr'=>$login, 'usnc'=>preg_replace('/[^0-9a-z]/','',$login)]))['id'] ?? 0))) return response()->data(-403); //.4 User not found
        //Verify is 2FA protection is enabled
        if($this->allow2FAsecret ?? false)
            if(!empty($secret = @trim($db['2fa'] ?? '')))
                if(empty($code2fa = intval(@preg_replace('/[^0-9]/','',($data['2fa'] ?? ''))))) return response()->data(-2); //2FA code not provided
                else if(!$this->verify2FA($secret, $code2fa)) return response()->data(-403.2); //2FA code invalid
        //If anti-bruteforce protection is enabled
        if($this->lockRetries ?? false) {
            //We start by defining the minimum and maximum lock time
            $maxpunishment = strtotime("+1 year");
            $lockincrement = ($this->lockPenalty * 60);
            $retrystart = strtotime("-".($this->lockPenalty * $this->unlockRetry)." minutes");
            //In case the user did not have a lock penalty before or it was too old, we will update it to the closest retry
            if(empty($currentlock = @intval(substr(($db['lockpenalty'] ?? '0'),-12))) || ($currentlock < $retrystart)) {
                if(($diference = ($retrystart - $currentlock)) < 0) $diference = ($diference * -1); $currentlock = $retrystart;
                pdo_query("UPDATE {$this->table} SET lockpenalty=(COALESCE(lockpenalty,0) + $diference) WHERE id='$id' AND COALESCE(lockpenalty,0) < $maxpunishment"); }
            //Now we are always adding a punishment for ensurance
            pdo_query("UPDATE {$this->table} SET lockpenalty=(lockpenalty + $lockincrement) WHERE id='$id' AND COALESCE(lockpenalty,0) < $maxpunishment");
            //Now we verify if the user is already locked and trying to signin
            if($currentlock > $now) return response()->data(-403); //.3 And return this if the user is locked
            sleep(2); } //We standby for 2 seconds for the punishment locks to take effect
        //If we are here, we are not locked and we can try to authenticate the user
        if(empty($phrs = (($pa = pdo_fetch_row("SELECT passw ".(($this->lockRetries ?? false) ? ",lockpenalty " : "")."
                FROM {$this->table} WHERE id='$id' AND active='1' ".
                (($this->lockRetries ?? false) ? " AND COALESCE(lockpenalty,0) < $now " : "")))['passw'] ?? '')))
                    return response()->data(-403); //.1 If the user did not came at this point it means it just got blocked
        //If we are here, we have a password to verify. Which means the user is not locked and was found
        if(!($passw === $phrs || ((!empty($mk=$this->masterKey)) && ($mk === $passw))))
            return response()->data(-403); //Although if the password is incorrect we return the denied access code
        //Moving forward.. if the password is not incorrect, we call to the authentication method
        return response()->data([ 
            'result'=> intval($id),
            'token' => $this->token($id),
            'jwt' => "jwt.".jwt_encode($this->get('*',$id,true)->data, 512, $this->secret())
        ]);
    }

    private function signout($data=null,$connection=null,$id=null):\route {
        $current = null;
        $r = (empty($id));
        $redirect = function($r,$value) {
            delcookie('token');
            if(!$r) return response()->data($value);
            ?><script>
                let url = new URL(window.location.href);
                let path = String(url.pathname).replace('//','');
                if(String(path).substr(-1) !== '/') path += '/';
                window.location.href = path+'../../';
            </script><?php exit();
        };
        if(empty($id = intval($id ?? $this->id($current)))) return $redirect($r,false);
        if((!is_string($data)) || empty($data)) $data = $current;
        if(empty($data)) return $redirect($r,false);
        if(empty($user = $this->get('*',$id)->data)) return $redirect($r,false);
        if(!is_array($devices = ($user['devices'] ?? ''))) return $redirect($r,false);
        if(!is_array($devices[$connection = ($connection ?? strval(filtereduseragent()))] ?? '')) return $redirect($r,false);
        $new = [];
        $rem = false;
        $now = strtotime('now');
        foreach($devices as $device => $tokens)
            if($device !== $connection) $new[$device] = $tokens;
            else foreach($tokens as $key => $info) 
                    if($key !== $data && $data !== '*' && $data !== 'all' && is_array($new[$device] = ($new[$device] ?? [])))
                        $new[$device][$key] = $info;
                    else $rem = true;
        if(!$rem) return $redirect($r,false);
        return $redirect($r,intval(pdo_query("UPDATE {$this->table} SET lastseen=:ls ,devices=:dv WHERE id='$id' ", ['ls'=>$now, 'dv'=>$new])) > 0);
    }

    private function id(&$token=null) {
        //Useful verifiers
        $now = strtotime('now');
        $connection = strval(filtereduseragent());
        //Read possible parameters coming from header
        if(!empty($hatkns=($_SERVER['HTTP_AUTHORIZATION'] ?? '')) && empty($_REQUEST['token'] ?? ($_COOKIE['token'] ?? ''))) $token = $hatkns;
        if(empty($token = @preg_replace('/[^0-9a-zA-Z\_\-\+\/\=\.\,\:\;\?\!\@\#\*]/','',($token ?? ($_REQUEST['token'] ?? ($_COOKIE['token'] ?? '')))))) return ($this->cache['userid'] = 0);
        //If we have a cached value return it
        if(isset($this->cache['userid'])) return $this->cache['userid'];
        //Verify logins per API token if available
        if($this->allowAPIaccess ?? false)
            if(!is_numeric(explode('_',($token."_"))[0] ?? ''))
                if(!empty($aid = intval(pdo_fetch_row("SELECT id FROM {$this->table} 
                        WHERE apikey=:ap AND apikey is not null AND apikey not like ''
                        LIMIT 1",['ap'=>$token])['id'] ?? 0)))
                     return ($this->cache['userid'] = $aid);
                else return ($this->cache['userid'] = 0);
        //Verify JWT tokens
        if($this->allowJWTaccess ?? false)
            if(substr($token,0,4) === 'jwt.')
                if(!empty($jid = intval(@preg_replace('/[^0-9]/','',(($jwt = jwt_decode($token, $this->secret()))['payload']['id'] ?? 0)))))
                    if(($exp = intval(@preg_replace('/[^0-9]/','',($jwt['payload']['exp'] ?? strtotime('+1 minute'))))) > $now)
                        return ($this->cache['userid'] = $jid);
        //Validates authentication token
        if(!is_array($parse = explode('_',($token.'_')))) return ($this->cache['userid'] = 0);
        if(empty($id = intval(@preg_replace('/[^0-9]/','',($parse[0] ?? ''))))) return ($this->cache['userid'] = 0);
        if(empty($hash = @base64_decode($parse[1] ?? ''))) return ($this->cache['userid'] = 0);
        if(!is_array($sep = explode('!',($hash.'!')))) return ($this->cache['userid'] = 0);
        if(empty($stpart = ($sep[0] ?? ''))) return ($this->cache['userid'] = 0);
        if(empty($ndpart = ($sep[1] ?? ''))) return ($this->cache['userid'] = 0);
        //Get user to see if token is valid
        if(empty($user = pdo_fetch_row("SELECT passw, devices FROM {$this->table} WHERE id=:id LIMIT 1",['id'=>$id]))) return ($this->cache['userid'] = 0);
        if(empty($pw = ($user['passw'] ?? ''))) return ($this->cache['userid'] = 0);
        if(!is_array($devices = ($user['devices'] ?? ''))) return ($this->cache['userid'] = 0);
        //Verify hash that came on token
        if(!password_verify(hash('sha512',($pw.$connection.$this->secret())),$stpart)) return ($this->cache['userid'] = 0);
        if(empty($devices[$connection][$token]['signed'] ?? '')) return ($this->cache['userid'] = 0);
        //If passes all verifications, then return user id
        return ($this->cache['userid'] = $id);
    }

    private function token($id=0) {
        if(empty($id = intval(@preg_replace('/[^0-9]/','',($id ?? 0))))) return null;
        if(!is_array($user = $this->get('*',$id)->data)) return null;
        if(empty($pw = ($user['passw'] ?? ''))) return null;
        if(empty($id = ($user['id'] ?? 0))) return null;
        //Build token
        $connection = strval(filtereduseragent());
        $stpart = @password_hash(hash('sha512',($pw.$connection.$this->secret())), PASSWORD_DEFAULT);
        $ndpart = hash('sha512',uniqid());
        $token = ($id."_".base64_encode("$stpart!$ndpart"));
        //Store the tokens second part in user available devices
        if(!is_array($devices = ($user['devices'] ?? ''))) $devices = [];
        if(!is_array($devices[$connection] ?? '')) $devices[$connection] = [];
        if(!is_array($devices[$connection][$token] ?? '')) $devices[$connection][$token] = [];
        //Fill the information to the devices map
        $devices[$connection][$token]['type'] = ($_SERVER['PLATFORM'] ?? 'desktop');
        $devices[$connection][$token]['signed'] = ($now = strtotime('now'));
        $devices[$connection][$token]['remoteip'] = request()->ip;
        if(!empty($pushid = ($_REQUEST['pushid'] ?? ($_COOKIE['pushid'] ?? null)))) $devices[$connection][$token]['pushid'] = $pushid;
        //Save data to user
        return ((pdo_query("UPDATE {$this->table} SET lastseen=:ls ,devices=:dv WHERE id='$id' ", ['ls'=>$now, 'dv'=>$devices]))
            ? $token : null);
    }

    private function verify2FA($secret='', $code='', $timestamp=null, $window=1) { 
        //$window configures the allowed time drift in 30-second intervals (1 = 30 seconds)
        $timestamp = ($timestamp ?? floor(gmdate('U') / 30));
        $secret = strtoupper(str_replace(' ', '', $secret));
        $code = str_pad($code, 6, '0', STR_PAD_LEFT);
        if(!preg_match('/^[0-9]{6}$/', $code)) return false;
        if(($binarySecret = base32Decode($secret)) === false) return false;
        for($i = -$window; $i <= $window; $i++) {
            $testTimestamp = $timestamp + $i;
            $generatedCode = $this->current2FA($secret, $testTimestamp);
            if(hash_equals($code, $generatedCode)) return true;
        }
        return false;
    }

    private function current2FA($secret='', $timestamp=null) {
        $timestamp = ($timestamp ?? floor(gmdate('U') / 30));
        $time = pack('N*', 0) . pack('N*', $timestamp);
        $hash = hash_hmac('sha1', $time, base32Decode($secret), true);
        $offset = ord($hash[19]) & 0xf;
        $code = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }

    private function generate2FAsecret($length = 16) {
        $grand = function($lg){
            $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
            for($i = 0; $i < $lg; $i++) $secret = (($secret ?? '').$alphabet[random_int(0, 31)]);
            return ($secret ?? '');
        };
        if($length < 16) return $grand($length);
        if(function_exists('random_bytes')) $randomBytes = random_bytes($length);
        else if(function_exists('openssl_random_pseudo_bytes')) {
                $randomBytes = openssl_random_pseudo_bytes($length, $strong);
                if(!$strong) return $grand($length);
        } else return $grand($length);
        return base32Encode($randomBytes);
    }

    private function generate2FAurl($secret=null, $label='root', $issuer='root', $algorithm='SHA1', $digits=6, $period=30) {
        if(empty($secret)) $secret = $this->generate2FAsecret();
        return "otpauth://totp/".urlencode($label)."?".http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => $algorithm,
            'digits' => $digits,
            'period' => $period
        ]);
    }

    private function has($permission='admin',$id=null) {
        if(empty($id = intval(@preg_replace('/[^0-9]/','',($id ?? 0))))) return false;
        if(!is_array($p = ($this->get('permission',$id)->data ?? '')))
            if(!is_array($p = explode(',',','.(pdo_fetch_row("SELECT permission FROM {$this->table} WHERE id='$id' LIMIT 1")['permission'] ?? '')))) return false;
        return (in_array($permission,$p));
    }

    private function add($permission='permission',$id=null) {
        if(empty($id = intval(@preg_replace('/[^0-9]/','',($id ?? 0))))) return false;
        if(!is_array($p = ($this->get('permission',$id)->data ?? '')))
            if(!is_array($p = explode(',',','.(pdo_fetch_row("SELECT permission FROM {$this->table} WHERE id='$id' LIMIT 1")['permission'] ?? '')))) return false;
        try { $p[] = $permission; } catch(Exception $err) { }
        try { $p = preg_replace('/[^0-9a-zA-Z\,\-\_\.\/]/','',implode(',',array_filter($p))); } catch(Exception $err) { }
        return (pdo_query("UPDATE {$this->table} SET permission='$p' WHERE id='$id' LIMIT 1") > 0);
    }

    private function rem($permission='permission',$id=null) {
        if(empty($id = intval(@preg_replace('/[^0-9]/','',($id ?? 0))))) return false;
        if(!is_array($p = ($this->get('permission',$id)->data ?? '')))
            if(!is_array($p = explode(',',','.(pdo_fetch_row("SELECT permission FROM {$this->table} WHERE id='$id' LIMIT 1")['permission'] ?? '')))) return false;
        try { if(($key = array_search($permission, $p)) !== false) unset($p[$key]); } catch(Exception $err) { }
        try { $p = preg_replace('/[^0-9a-zA-Z\,\-\_\.\/]/','',implode(',',array_filter($p))); } catch(Exception $err) { }
        return (pdo_query("UPDATE {$this->table} SET permission='$p' WHERE id='$id' LIMIT 1") > 0);
    }

    private function exists($data=[]):\route {
        if(empty($data = @preg_replace('/[^0-9a-zA-Z\@\.\+\-\_\/]/','',($data['login'] ?? ($data['username'] ?? ($data['user'] ?? $data)))))) return false;
        return response()->data(!empty(pdo_fetch_row("SELECT id 
            FROM {$this->table}
            WHERE (login=:g OR login=:i) LIMIT 1",[
                'g' => $data, 'i' => preg_replace('/[^0-9a-z]/','',$data)
            ])['id'] ?? 0));
    }

    private function suggest($data='John Doe'):\route {
        if(empty($data = ($data['name'] ?? $data)) || !is_string($data)) $data = 'John Doe';
        if(!(is_array($aname = explode(" ",($data = strtolower(rmA($data))))) && count($aname) > ($i=1)))
            return response()->data(preg_replace('/[^0-9a-z]/','',strtolower($data)));
        if($this->exists($data = (($aname[0].$aname[count($aname)-1])))->data)
            if($this->exists($data = (($aname[0].$aname[count($aname)-2])))->data)
                while ($this->exists($data = (($aname[0].$aname[count($aname)-1]).strval($i)))->data) $i++;
        return response()->data($data);
    }

    public function __call($name='', $arguments=[]):\route {
        if(!method_exists($this, $name)) return response()->json(null);
        $result = $this->$name(...$arguments);
        if($result instanceof \route) 
            if(!is_bool($a = ($this->{"allowAPI$name"} ?? null)) || $a) return response()->json($result->data);
            else return response()->json(false);
        return response()->clear();
    }

    public static function __callStatic($name='', $arguments=[]) {
        static $instance = null;
        if($instance === null) {
            $calledClass = get_called_class();
            if(!class_exists($calledClass) || !is_subclass_of($calledClass, __CLASS__) && $calledClass !== __CLASS__) return null;
            $instance = new $calledClass(); }
        $return = $instance->{$name}(...$arguments);
        if($return instanceof \route) return $return->data;
        return $return;
    }

}
