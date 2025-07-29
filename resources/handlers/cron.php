<?php

class cron {
    use \thread;

    public static $code = null;

    public static function run($data=[]):\route {
        //verify parameters and permissions
        $validator = '/[^0-9A-Za-z\!\@\*\;\.\:\,\+\-\_\=\(\)]/';
        if(@preg_replace($validator,'',($data['code'] ?? '')) !== @preg_replace($validator,'',(self::$code ?? ($_SERVER['cron_code'] ?? ''))))
            return response()->json('not permitted',-401);

        if(!($active = intval(getconfig('cron_enabled',0))))
            return response()->json('disabled',-402);
        
        //list every queue processor module
        $processqueues = ['telegram', 'email', 'push', 'sms'];
        $processed = [];
        
        //execute them with thread async function
        foreach($processqueues as $proc)
            if(!empty($fn = "\\$proc::".($method="process_queue")))
                $processed[$proc] = ((!is_callable($fn))
                    ? "Unable to access module $fn"
                    : str_maskmiddle(substr(preg_replace('/[^0-9a-zA-Z]/','',self::async(function(){ $fn(); },['fn'=>$fn])),-40,20)));
        
        //returns the result
        return response()->json($processed);
	}

}