<?php

class cron {
    
    public static $code = null;

    public static $modules = [ 'telegram::cron', 'email::cron', 'push::cron', 'sms::cron', 'site::cron', 'tasks::cron' ];

    public static function run($data=[]):\route {
        //verify parameters and permissions
        $validator = '/[^0-9A-Za-z\!\@\*\;\.\:\,\+\-\_\=\(\)]/';
        if(@preg_replace($validator,'',($data['code'] ?? '')) !== @preg_replace($validator,'',(self::$code ?? ($_SERVER['cron_code'] ?? ''))))
            return response()->json('not permitted',-401);

        //check whether cron is enabled
        if(!($active = intval(getconfig('cron_enabled',0))))
            return response()->json('disabled',-402);

        //verify if there is more cron tasks scheduled
        if(is_array($mtasks = ($_SERVER['cron_tasks'] ?? ($_SERVER['cron_includes'] ?? ''))))
            foreach($mtasks as $task) self::$modules[] = $task;
        
        //execute module processors with thread async function
        foreach(self::$modules as $proc)
            if(!empty($fn = "\\$proc"))
                $processed[$proc] = ((!is_callable($fn))
                    ? "Unable to access module $fn"
                    : str_maskmiddle(substr(preg_replace('/[^0-9a-zA-Z]/','',async(function() use ($fn) { $fn(); })),-40,20)));
        
        //returns the result
        return response()->json($processed);
	}

}