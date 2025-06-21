<?php
class telegram {
    use \thread;

    public static $openapiOnly = ['callback']; // Add `bot` and `group` to get bot informations if necessary

    public static $chatlog = "";
    private static $botapi = '';
    
    private static $loaded = false;
    public static $allowfrom = [];
    public static $chatinfo = [];

    public static $channels = [
        //'admin' => ''
    ];

    private static $commandslist = '
        id - Get chat id
        ping - Respond pong
        command - Do something
    ';

    public static function database() {
        return pdo_create("telegram_queue",[
            "id" => "int(11) NOT NULL AUTO_INCREMENT",
            "chatid" => "varchar(200) DEFAULT NULL",
            "message" => "longtext DEFAULT NULL",
            "response" => "longtext DEFAULT NULL",
            "botkey" => "longtext DEFAULT NULL",
            "method" => "varchar(200) NULL DEFAULT 'sendMessage'",
            "params" => "longtext NULL DEFAULT NULL",
            "sendat" => "int(11) DEFAULT 0"
        ]);
    }

    protected static function load() {
        if(self::$loaded) return true;
        if(empty(self::$chatlog = ($_SERVER['telegram_chatlog'] ?? ($_SERVER['telegram_channel_main'] ?? (self::$chatlog ?? ''))))) return false;
        if(empty(self::$botapi = ($_SERVER['telegram_botapi'] ?? (self::$botapi ?? '')))) return false;
        self::$allowfrom[] = self::$chatlog;
        foreach($_SERVER as $k => $v)
            if(strpos($k,($kct='telegram_channel_')) !== false)
                if(!empty(self::$channels[str_replace($kct,'',$k)] = $v))
                    if(!in_array($v, self::$allowfrom))
                        self::$allowfrom[] = $v;
        return (self::$loaded = true);
    }

    public function __construct() { return self::load(); }

    protected static function api($method='',$data=null,$debug=false) {
        if(!self::load()) return false;
        $r = @json_decode(($buffer = curlsend($url = "https://api.telegram.org/bot".self::$botapi."/".$method, $data, 15, 'json', 
            [CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36'])),true);
        return ((!is_array($r)) ? $buffer : $r);
    }

    private static function urlendpoint() { return THISURL.'/'.__CLASS__; }

    public static function configure($data=[]):\route {
        if(!self::load()) return response()->json(false);
        if((!($_SERVER['DEVELOPMENT'] ?? false)) && ($data['hash'] ?? '') !== md5(self::$botapi)) return response()->json(false);
        $url = self::urlendpoint().'/callback?hash='.($tk=md5(self::$botapi));
        return response()->json([
            'response'=>self::api('setWebhook',[ 'url' => $url ]),
            'url'=>str_replace($tk,'***',$url)
        ]);
    }

    public static function bot($data=[]):\route {
        if(!self::load()) return response()->json(false);
        if((!($_SERVER['DEVELOPMENT'] ?? false)) && ($request['hash'] ?? '') !== md5(self::$botapi)) return response()->json(false);
        return response()->json(self::api('getMe'));
    }

    public static function groups($data=[]):\route {
        if(!self::load()) return response()->json(false);
        if((!($_SERVER['DEVELOPMENT'] ?? false)) && ($request['hash'] ?? '') !== md5(self::$botapi)) return response()->json(false);
        return response()->json(self::api('getUpdates'));
    }

    public static function disable($data=[]):\route {
        if(!self::load()) return response()->json(false);
        if((!($_SERVER['DEVELOPMENT'] ?? false)) && ($request['hash'] ?? '') !== md5(self::$botapi)) return response()->json(false);
        return response()->json(self::api('deleteWebhook'));
    }

    public static function update($data=[]):\route {
        $commandslist = self::$commandslist;
        $ls = explode("\n",trim($commandslist));
        $response = [];
        foreach($ls as $line)
            if(is_array($line = explode(' - ',trim($line))))
                $response[] = ['command' => ($line[0] ?? ''), 'description' => ($line[1] ?? 'N/D')];
        return response()->json([
            'response'=>self::api('setMyCommands',[ 'commands' => $response ]),
            'commands'=>((!($_SERVER['DEVELOPMENT'] ?? false)) ? count($response) : $response)
        ]);
    }

    public static function process_queue($data=[], $log=[]) {
        if(!self::load()) return false;
        //catch either the queue or a specific item
        if(!is_array($fila=pdo_fetch_array("SELECT * FROM telegram_queue  
            WHERE (response is null or response='')
                AND (".((!empty($id=preg_replace('/[^0-9]/','',($data['id'] ?? '')))) ? "id='$id'"
                  : "sendat < ".strtotime('now')).")
            ORDER BY sendat asc limit 20 ")) || empty($fila)) return -400;
        // start sending
        foreach($fila as $item)
            if(!empty($id=($item['id'] ?? ''))) { sleep(1);
                //trigger the processing status
                if(!pdo_query("UPDATE telegram_queue SET response='".json_encode(['result'=>'processing'])."' WHERE id='$id' AND (response is null or response='')")) continue;
                //validade informations to send
                if(empty($item['botkey'] = trim($item['botkey'] ?? ''))) $item['botkey'] = self::$botapi;
                if(!is_array($item['params'] = ($item['params'] ?? ''))) $item['params'] = [];
                //validade sending method
                if($item['method'] === trim("sendMessage") && empty($item['params']['parse_mode'] ?? '')) $item['params']['parse_mode'] = 'html';
                $item['params'][(($item['method'] === trim("sendMessage")) ? 'text' : 'caption')] = html_entity_decode(str_ireplace('<br>',"\n",($item['message'] ?? '')));
                //validades chat id
                $item['params']['chat_id'] = ($item['params']['chat_id'] ?? ($item['chatid'] ?? self::$chatlog));
                $item['params']['chat_id'] = (self::$channels[strval($item['params']['chat_id'])] ?? $item['params']['chat_id']);
                if(empty($item['params']['chat_id']) || !is_numeric($item['params']['chat_id'])) $item['params']['chat_id'] = self::$chatlog;
                //validade buttons
                if(is_array($item['params']['reply_markup']['inline_keyboard'] ?? ''))
                    foreach($item['params']['reply_markup']['inline_keyboard'] as &$buttonrow)
                        foreach($buttonrow as &$buttonline) if(!empty($buttonline['text'] ?? ''))
                                $buttonline['text'] = html_entity_decode($buttonline['text']);
                //log of sending
                $log[] = "[****".substr($item['botkey'],-6)."@$id/telegram] processing...";
                if(empty($item['botkey']) || empty($item['params']['chat_id']))
                    if(pdo_query("UPDATE telegram_queue SET response='".json_encode(['result'=>'lack parameters'])."' WHERE id='$id' ") > -1) continue;
                //catches the job to do
                if(pdo_query("UPDATE telegram_queue SET response='".
                        json_encode(['result'=>'sending', 'date'=>date('Y-m-d H:i:s')])."'
                    WHERE id='".$item['id']."' ") > 0) {
            
                        $log[] = "[$id/telegram] sending to ****".substr($item['params']['chat_id'],-4)."...";
                        
                        $log[] = "[$id/telegram] ".((pdo_query("UPDATE telegram_queue SET response=:r WHERE id='$id' ",[
                            'r' => ['response'=>self::api($item['method'], $item['params'])]
                        ])) ? 'sent' : 'error').".";
                } else
                  $log[] = "[$id/telegram] job already taken.";     
            }
        return $log;
    }

    public static function send($message='',$chatid=null,$method='sendMessage',$params=[],$sendat=null,$queueonly=false) {
        if(($_SERVER['DEVELOPMENT'] ?? false) && is_bool($queueonly)) $queueonly = true;
        if(!empty($sendat) && ($queueonly === false)) $queueonly = true;
        if(empty($params['photo'] ?? '') && (!is_string($message) || empty($message))) return -1;
        if(empty($sendat) || !is_numeric($sendat)) $sendat = strtotime('now'); $params['sent'] = strtotime('now');
        if(!empty($bp=($params['buttons'] ?? '')) && !empty($params['reply_markup'] = ['inline_keyboard' => ($params['buttons'] = [])]))
            foreach($bp as $b => $c) $params['reply_markup']['inline_keyboard'][] = [['text'=>($b), ((strpos($c,'://') !== false)?'url':'callback_data')=>substr($c,0,64)]];
        //protecao de pegar o id, pra nao deixar duplicar
        if(!empty($lastid = (pdo_fetch_row("SELECT id FROM telegram_queue ORDER BY id DESC LIMIT 1")['id'] ?? null))) $lastid++;
        $result = pdo_insert("telegram_queue", ['id'=>$lastid, 'message'=>rmAentities($message ?? ''), 'chatid'=>$chatid, 'method'=>$method, 'params'=>$params, 'sendat'=>$sendat]);
        if(!$result && !is_array($queueonly)) return self::send($message,$chatid,$method,$params,$sendat,self::database());
        if(!$queueonly) self::async(function(){ \telegram::process_queue(['id'=>$result]); },[ 'result' => $result ]);
        return $result;
    }

    public static function log($message='',$attach=null,$chatid=null,$queue=null) {
        if(empty($attach) && (!is_string($message) || empty($message))) return -1;
        //send the group message
        if(empty($attach)) self::send($message,$chatid,'sendMessage',['parse_mode'=>'html','disable_web_page_preview'=>true],$queue);
        else if(is_array($attach)) self::send($message,$chatid,'sendMessage',array_merge(['parse_mode'=>'html'], $attach),$queue);
        else self::send($message,$chatid,'sendPhoto',['parse_mode'=>'html','photo'=>$attach],$queue);
        return true;
    }

    /* # If callback needed for commands use this route */

    public static function callback($data=[]):\route {
        if(!self::load()) return response()->json(false);
        if(md5(self::$botapi) !== ($request['hash'] ?? '')) return response()->json(-405);
        if(is_array($request['callback_query'] ?? ''))
            if(!empty($cqd=($request['callback_query']['data'] ?? '')))
                $request['message'] = ['text'=>$cqd, 'from'=>($request['callback_query']['from'] ?? ''), 'chat'=>($request['callback_query']['message']['chat'] ?? ''),
                                    'from_reply'=>($request['callback_query']['message']['reply_markup']['inline_keyboard'] ?? '')];
        if(empty($msg = (str_replace('\\','',($request['message']['text'] ?? ($request['msg'] ?? '')))))) return response()->json(['result'=>-1, 'data'=>$request]);
        if(empty($chatlog = self::$chatlog = ($request['message']['chat']['id'] ?? self::$chatlog))) return response()->json(['result'=>-2, 'data'=>$request]);
        if(empty($from = ($request['message']['from']['id'] ?? '*'))) return response()->json(['result'=>-3, 'data'=>$request]);
        if(empty($cmd = preg_split('~(?:\'[^\']*\'|"[^"]*")(*SKIP)(*F)|\h+~', $msg))) return response()->json(['result'=>-4, 'data'=>$request]);
        if(empty($cmd[0] ?? '')) return response()->json(['result'=>-5, 'data'=>$request]);
        if(empty($cmd[0] = (explode('@',($cmd[0]."@"))[0] ?? ''))) return response()->json(['result'=>-5.1, 'data'=>$request]);
        if(substr($msg,0,3) !== '/id' && !in_array($chatlog,self::$allowfrom)) return response()->json(['result'=>-7, 'data'=>$request, 'to'=>$chatlog, 'send'=>self::respond("Not authorized: $chatlog",[],$chatlog)]);
        if(!module_exists(__CLASS__,($method = (str_replace('/','_',array_shift($cmd)))))) return response()->json(['result'=>-6, 'data'=>$request]);
        if(is_array($fr=($request['message']['from_reply'] ?? ''))) foreach($fr as $b) foreach($b as $d)
            if(!empty($m=($b['callback_data'] ?? ($d['callback_data'] ?? ''))))
                if($m == $msg && self::respond('<code>'.($request['message']['from']['first_name'] ?? $from).'</code> selecionou: '.($b['text'] ?? ($d['text'] ?? ''))." <code>$msg</code>",[],$chatlog)) break 2;
        return response()->json(self::$method([
            'chatid' => $chatlog,
            'from' => $from,
            'msg' => $msg,
            'callback' => ($request['message'] ?? [])
        ], $cmd));
    }

    public static function respond($data=[], $attach=null, $chatlog=null) {
        if(!is_string($data)) return false;
        if(strlen($data) > ($max = 2000)) {
            if(is_array($sep = str_split($data, ($max / 2))))
                foreach($sep as $msg)
                    if(is_numeric($a=((($n=strrpos($msg,'<code>')) !== false) ? $n : 0)))
                        if(is_numeric($b=((($n=strrpos($msg,'</code>')) !== false) ? $n : 0))) 
                            if(is_bool($op = ((($a > $b) !== ($op ?? false)) ? ($a > $b) : ($op ?? false)))) {
                                self::respond(((!$op)?'<code>':'').$msg.(($op || $b <= 0)?'</code>':'')); sleep(3); } return; }
        $message = [
            "chat_id"=> ($chatlog ?? self::$chatlog), 
            "parse_mode" => "HTML",
            ((!empty($attach) && is_string($attach))?"caption":"text") => str_replace('<br>',"\n",html_entity_decode($data))
        ];
        if(!empty($attach) && is_string($attach)) $message['photo'] = $attach;
        else if(!empty($bp=($attach['buttons'] ?? '')) && !empty($message['reply_markup'] = ['inline_keyboard' => []]))
             foreach($bp as $b => $c) $message['reply_markup']['inline_keyboard'][] = [['text'=>($b), 
                ((strpos($c,'://') !== false)?'url':'callback_data')=>((strpos($c,'://') !== false)?$c:substr($c,0,64))]];
        return self::api(((!empty($attach) && is_string($attach))?'sendPhoto':'sendMessage'),$message);
    }

    /* # Command functions */

    protected static function _help($data=[], $cmd) {
        $commandslist = self::$commandslist;
        $ls = explode("\n",trim($commandslist));
        $response = '';
        foreach($ls as $line)
            if(!empty($line = trim($line)))
                $response .= "/$line<br>";
        return self::respond($response);
    }

    protected static function _update($data=[], $cmd) {
        if(self::update()['response']['ok'] ?? false) return self::respond('Done');
        return self::respond('Error. Use endpoint: '.self::urlendpoint().'/update');
    }

    protected static function _id($data=[], $cmd) {
        $data['channels'] = self::$channels;
        return ['result'=>-9, 'cmd'=>$cmd, 'answer'=>self::respond('<pre>'.json_encode($data,JSON_PRETTY_PRINT).'</pre>',[],($data['chatid'] ?? null))]; 
    }

    protected static function _ping($data=[], $cmd) {
        return ['result'=>-9, 'cmd'=>$cmd, 'answer'=>self::respond('Pong')];
    }

    protected static function _command($data=[], $cmd) {
        if(empty($id=preg_replace('/[^0-9]/','',($cmd[0] ?? '')))) return self::respond('Formato: '.str_replace('_','/',__FUNCTION__).' [ID]');
        return self::respond('Command '.$id);
    }
    

}