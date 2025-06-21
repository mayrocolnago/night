<?php
class sms {
    use \thread;

    private static $endpoint = "https://api.mobizon.com.br/service/message/";

    private static $apitoken = null;
    private static $loaded = false;

    public static function database() {
        return pdo_create("sms_queue",[
            "id" => "int NOT NULL AUTO_INCREMENT",
            "receipt" => "varchar(200) NULL DEFAULT NULL",
            "sender" => "varchar(200) NULL DEFAULT ''",
            "message" => "longtext NULL DEFAULT NULL",
            "response" => "longtext NULL DEFAULT NULL",
            "sendat" => "int DEFAULT '0'"
        ]);
    }

    protected static function load() {
        if(self::$loaded) return true;
        if(empty(self::$apitoken = (self::$apitoken ?? ($_SERVER['mobizon_apitoken'] ?? '')))) return false;
        return (self::$loaded = true);
    }

    public function __construct() { return self::load(); }

    public static function send($number='',$message='',$sendat=null,$sender='',$queueonly=false) {
        if(($_SERVER['DEVELOPMENT'] ?? false) && is_bool($queueonly)) $queueonly = true;
        if(!empty($sendat) && ($queueonly === false)) $queueonly = true;
        if(!is_string($message)) return -1;
        if(!is_numeric($number = preg_replace('/[^0-9]/','',$number))) return -2;
        if(empty($sendat) || !is_numeric($sendat)) $sendat = strtotime('now');
        if(function_exists('rmA')) if(empty($message = rmA(strip_tags($message)))) return -3;
        if(!empty(pdo_fetch_row("SELECT id FROM sms_queue WHERE (receipt='$number' or receipt='55$number') AND sendat > ".strtotime('-1 minute')." AND sendat < ".strtotime('+1 minute')))) return 1.1;
        $result = pdo_insert('sms_queue',['receipt'=>$number, 'sender'=>$sender, 'message'=>$message, 'sendat'=>$sendat]);
        if(!$result && !is_array($queueonly)) return self::send($number,$message,$sendat,$sender,self::database());
        if(!$queueonly) self::async(function(){ \sms::process_queue(['id'=>$result]); },[ 'result' => $result ]);
        return $result;
    }

    public static function process_queue($data=[], $log=[]) {
        if(!self::load()) return false;
        //catch either the queue or a specific item
        if(!is_array($fila=pdo_fetch_array("SELECT * FROM sms_queue  
            WHERE (response is null or response='')
                AND (".((!empty($id=preg_replace('/[^0-9]/','',($data['id'] ?? '')))) ? "id='$id'"
                  : "sendat < ".strtotime('now')).")
            ORDER BY sendat asc limit 20 ")) || empty($fila)) return -400;
        // start sending
        foreach($fila as $item)
            if(!empty($id=($item['id'] ?? ''))) {
                //log of sendings
                $log[] = "[****".substr(self::$apitoken,-6)."@$id/sms] processing...";
                if(empty($item['receipt'] ?? '') || empty($item['message'] ?? ''))
                    if(pdo_query("UPDATE sms_queue SET response='".json_encode(['result'=>'lack parameters'])."' WHERE id='$id' ") > -1) continue;
                //catch the job to execute
                if(pdo_query("UPDATE sms_queue SET response='".
                        json_encode(['result'=>'sending', 'date'=>date('Y-m-d H:i:s')])."'
                    WHERE id='$id' AND (response is null OR response='') ") > 0) {
            
                        $log[] = "[$id/sms] sending to ****".substr($item['receipt'],-4)."...";
                        
                        $log[] = "[$id/sms] ".((pdo_query("UPDATE sms_queue SET response=:r WHERE id='$id' ",[
                            'r' => ['response'=>self::api([ 'recipient' => $item['receipt'], 'text' => $item['message'] ])]
                        ])) ? 'sent' : 'error').".";
                } else
                  $log[] = "[$id/sms] job already taken.";     
            }
        return $log;
    }

    protected static function api($data=null,$method='sendSmsMessage',$debug=false) {
        if(!self::load()) return false;
        //validade parameters
        if(!is_array($data)) $data = [];
        if(empty($data['apiKey'] ?? '')) $data['apiKey'] = self::$apitoken;
        if(empty($data['output'] ?? '')) $data['output'] = 'json';
        if(empty($data['api'] ?? '')) $data['api'] = 'v1';
        if(!is_array($data['params'] ?? '')) $data['params'] = [];
        if(empty($data['params']['validity'] ?? '')) $data['params']['validity'] = '1440';
        //validades the destination number
        if(empty($data['recipient'] ?? '')) return -400.1;
        else if(strval(substr($data['recipient'],0,2)) != '55') $data['recipient'] = "55".$data['recipient'];
        //validate message
        if(empty($data['text'] ?? '')) return -400.2;
		else $data['text'] = (rmA($data['text']));
		//send the sms
		$ch = curl_init(self::$endpoint.$method);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('cache-control: no-cache', 'Content-Type: application/x-www-form-urlencoded'));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$result = @json_decode(($response = curl_exec($ch)),true);
		curl_close($ch);
		return ((is_array($result)) ? $result : $response);
    }

}