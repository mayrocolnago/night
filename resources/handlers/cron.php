<?php

class cron {
    use \thread;

    public static $code = null;

    public static function run($data=[]):\route {
        //verify parameters and permissions
        if(intval($data['code'] ?? '') !== (self::$code ?? ($_SERVER['cron_code'] ?? ''))) return ['result'=>false, 'err'=>'not permited'];
        if(!($active = intval(getconfig('cron_enabled',0)))) return ['result'=>false, 'err'=>'disabled'];
        
        //list every queue processor module
        $processqueues = ['telegram', 'email', 'push', 'sms'];
        $processed = [];
        
        //execute them with thread async function
        foreach($processqueues as $proc)
            if(!empty($fn = "\\$proc::".($method="process_queue")))
                $processed[$proc] = ((!is_callable($fn)) //"\\$proc",$method
                    ? "Unable to access module $fn"
                    : str_maskmiddle(substr(preg_replace('/[^0-9a-zA-Z]/','',self::async(function(){ $fn(); },['fn'=>$fn])),-40,20)));
        
        //returns a different negative status different from -1
        return [
            'result'=>-2,
            'data'=>$processed
        ];
	}

}