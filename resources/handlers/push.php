<?php
class push {
    use \thread;

    // Documentation: https://firebase.google.com/docs/cloud-messaging/migrate-v1
    // Google Cloud Console settings link: https://console.cloud.google.com/projectselector2/settings/general?authuser=1
    // AdminSDK URL to get credentials: https://console.firebase.google.com/u/0/project/{project}-c613b4df/settings/serviceaccounts/adminsdk

    private static $loaded = false; //default false always
    private static $cachedtoken = null;

    private static $credentials = [
        'project_id' => null,
        'client_email' => null,
        'private_key_id' => null,
        'private_key' => null
    ];

    public static function database() {
        pdo_query("CREATE TABLE IF NOT EXISTS `push_queue` (
            `id` int NOT NULL AUTO_INCREMENT,
            `uid` varchar(200) NULL DEFAULT NULL,
            `titulo` varchar(100) NULL DEFAULT NULL,
            `mensagem` longtext NULL DEFAULT NULL,
            `comando` longtext NULL DEFAULT NULL,
            `tags` longtext NULL DEFAULT NULL,
            `response` longtext NULL DEFAULT NULL,
            `send_at` int NULL DEFAULT '0',
            PRIMARY KEY (`id`)
        )");
    }

    protected static function load() {
        if(self::$loaded) return true;
        foreach(self::$credentials as $k => &$v)
            if(empty($v = ($_SERVER["push_$k"] ?? $v))) return false;
        return (self::$loaded = true);
    }

    public function __construct() { return self::load(); }

    public static function send($to=null,$body=null,$msg=null,$title=null,$send_at=null,$tags=null,$queueonly=false) {
        if($_SERVER['DEVELOPMENT'] ?? false) $queueonly = true;
        if(!empty($send_at) && !$queueonly) $queueonly = true;
        if(empty($send_at) || !is_numeric($send_at)) $send_at = strtotime('now');
        if(empty($to)) return 0;
        $result = pdo_insert("push_queue",[
            'uid' => $to, 'tags' => $tags,
            'titulo' => (empty($title) ? null : rmAentities($title)),
            'mensagem' => rmAentities($body ?? ''),
            'comando' => $msg, 'send_at' => $send_at ]);
        if(!$result && $queueonly !== 'database') return self::database(self::send($to,$body,$msg,$title,$send_at,$tags,'database'));
        if(!$queueonly) self::async(function(){ \push::process_queue(['id'=>$result]); },[ 'result' => $result ]);
        return $result;
    }

    public static function process_queue($data=[], $log=[]) {
        //load module
        if(!self::load()) return false;
        //catch either the queue or a specific item
        if(!is_array($fila=pdo_fetch_array("SELECT * FROM push_queue  
            WHERE (response is null or response='')
                AND (".((!empty($id=preg_replace('/[^0-9]/','',($data['id'] ?? '')))) ? "id='$id'"
                  : "send_at < ".strtotime('now')).")
            ORDER BY send_at asc limit 20 ")) || empty($fila)) return -400;
        // start sending
        foreach($fila as $item)
            if(!empty($id=($item['id'] ?? ''))) {
                //log of every send
                $log[] = "[$id/push] processing...";
                if(empty($item['mensagem'] ?? ''))
                    if(pdo_query("UPDATE push_queue SET response='".json_encode(['result'=>'lack parameters'])."' WHERE id='$id' ") > -1) continue;
                //catches the job
                if(pdo_query("UPDATE push_queue SET response='".
                        json_encode(['result'=>'sending', 'date'=>date('Y-m-d H:i:s')])."'
                    WHERE id='$id' AND (response is null OR response='') ") > 0) {
                        //log sending
                        $log[] = "[$id/push] sending...";
                        //applies the answer
                        $log[] = "[$id/push] ".((pdo_query("UPDATE push_queue SET response=:r WHERE id='$id' ",[
                            'r' => ['response'=>self::api($item['uid'],$item['mensagem'],$item['comando'],$item['titulo'])]
                        ])) ? 'sent' : 'error').".";
                } else
                  $log[] = "[$id/push] job already taken.";
            }
        return $log;
    }

    protected static function api($to=null,$body=null,$msg=null,$title=null) {
        $apiurl = 'https://fcm.googleapis.com/v1/projects/'.(self::$credentials['project_id'] ?? '').'/messages:send'; // 'https://fcm.googleapis.com/fcm/send';
        self::$cachedtoken = (self::$cachedtoken ?? self::__getjwt());
        if(!self::load()) return false;
        if(empty($dest = str_replace('#','',($to['to'] ?? $to)))) return -400.1;
        if(empty($title = html_entity_decode($title ?? ($to['title'] ?? ($_SERVER['projecttitle'] ?? 'Notifica&ccedil;&atilde;o'))))) return -400.2;
        if(empty($body = html_entity_decode($body ?? ($to['body'] ?? '')))) return -400.3;
        if(empty($msg = ($msg ?? ($to['msg'] ?? '...')))) return -400.4;
        $post = [
            "message" => [
                "topic" => "topic$dest", 
                "notification" => [
                    "title" => $title, 
                    "body" => $body ],
                "data" => [ "msg" => $msg ] ] ];
        $headers = array('Authorization: Bearer '.(self::$cachedtoken['token'] ?? ''), 'Content-Type: application/json');
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $apiurl );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $post ) );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 15 );
        curl_setopt( $ch, CURLOPT_TIMEOUT, 15 );
        $result = curl_exec( $ch );
        curl_close( $ch );
        $result = @json_decode($result,true);
        return [
            'result'=>((!empty($result['name'] ?? '')) ? 1 : 0), 
            'data'=>((!empty($result['name'] ?? '')) ? $result : str_replace(["'",'"'],'',json_encode($result))),
            'credentials'=>((!empty($result['name'] ?? '')) ? null : self::$cachedtoken)
        ];
    }
    
    protected static function __getjwt($data=[]) {
        /* get jwt token */
        $scope = 'https://www.googleapis.com/auth/firebase.messaging';
        $lifetime = ($data['lifetime'] ?? 3600);
        $base64url_encode = function($data){ return rtrim(strtr(base64_encode($data), '+/', '-_'), '='); };
        $header = [
            "alg" => "RS256",
            "typ" => "JWT",
            "kid" => (self::$credentials['private_key_id'] ?? '')
        ];
        $now = strtotime('now');
        $payload = [
            "iss" => (self::$credentials['client_email'] ?? ''),
            "scope" => $scope,
            "aud" => ($tokenurl = "https://oauth2.googleapis.com/token"),
            "exp" => $now + $lifetime,
            "iat" => $now
        ];
        $base64_header = $base64url_encode(json_encode($header));
        $base64_payload = $base64url_encode(json_encode($payload));
        $signature_input = $base64_header.".".$base64_payload;
        $private_key = @openssl_pkey_get_private(self::$credentials['private_key'] ?? '');
        $opensslsignresult = @openssl_sign($signature_input, $signature, $private_key, OPENSSL_ALGO_SHA256);
        $base64_signature = $base64url_encode($signature);
        $jwt = $base64_header.".".$base64_payload.".".$base64_signature;
        /* get access_token */
        $data = array( 'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt );
        $ch = curl_init($tokenurl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = @json_decode(($result=curl_exec($ch)),true);
        if(!empty($token = ($response['access_token'] ?? ''))) $response['access_token'] = '[_parent]';
        return [
            'result' => $opensslsignresult,
            'jwt' => $jwt,
            'token' => $token,
            'openssl_signer' => function_exists('openssl_sign'),
            'openssl_pkeyget' => function_exists('openssl_pkey_get_private'),
            'openssl_sha256' => OPENSSL_ALGO_SHA256,
            'oauth' => ((!empty($response)) ? $response : $result)
        ];
    }
}