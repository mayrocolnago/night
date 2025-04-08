<?php
trait crud {

    //Implement $crudTable = 'table_name'; on your class

    //Implement crudPermissionHandler($data){ return true; }; function on your class

    private static function convchars($string) {
        if(is_callable('emojientities')) return emojientities($string);
        return htmlentities($string,ENT_QUOTES|ENT_HTML5,'UTF-8',false);
    }

    public static function create($data=[]) {
        //connects to table
        if(empty($table = (self::$crudTable ?? null))) return 0;
        //verify permissions
        if(is_callable('self::crudPermissionHandler'))
            if(!self::crudPermissionHandler($data)) return -405.2;
        //check whether there is a database creation function
        if(is_callable($createdb = 'self::database')) @call_user_func($createdb,$data);
        //recursive function to get each dot of a string, catches the first value and return an array of the rest
        $recursivesubarray = function($values,$recursivesubarray) {
            if(!is_array($values)) return $values;
            $value = [];
            foreach($values as $k => $v)
                if(!empty(@preg_replace('/[^a-zA-Z]/','',($k = @preg_replace('/[^0-9a-zA-Z\.\_]/','',str_replace('-','.',$k))))))
                    if(!(strpos($k,'.') !== false)) $value[$k] = self::convchars($v);
                    else if(is_array($parse = explode('.',$k)) && !empty($primary = ($parse[0] ?? '')))
                            if(!empty($subset = substr_replace($k, '', ((($p=strpos($k, ($n="$primary.")))===false)?0:$p), strlen($n)))) {
                                if(!isset($value[str_replace('.','',$primary)])) $value[str_replace('.','',$primary)] = [];
                                $value[str_replace('.','',$primary)] = array_merge_recursive(
                                    $value[str_replace('.','',$primary)],
                                    $recursivesubarray([$subset => $v],$recursivesubarray)); }
            return $value;
        };
        //faz a recursao
        $values = $recursivesubarray($data,$recursivesubarray);
        //retorna
        return pdo_insert($table, $values);
    }

    public static function list($data=null,$order=null,$limit=100) {
        //sets the default order if none
        if(empty($order)) $order = (self::$crudDefaultSort ?? "id DESC");
        //connects to table
        if(empty($table = (self::$crudTable ?? null))) return 0;
        //verify permissions
        if(is_callable('self::crudPermissionHandler'))
            if(!self::crudPermissionHandler($data)) return -405.2;
        //organizes the where conditions
        $where = [];
        if(is_array($data))
            foreach($data as $k => $v)
                if(!empty(@preg_replace('/[^a-zA-Z]/','',($k = @preg_replace('/[^0-9a-zA-Z\.\_\|]/','',str_replace('-','.',$k))))))
                    if(!(strpos($k,'.') !== false)) $where[$k] = $v;
                    else if(is_array($parse = explode('.',$k)) && !empty($primary = ($parse[0] ?? '')))
                            if(!empty($subset = substr_replace($k, '', ((($p=strpos($k, ($n="$primary.")))===false)?0:$p), strlen($n))))
                                $where["json_value($primary,'\$.$subset')"] = self::convchars($value);
        //returns
        return (pdo_fetch_array("SELECT * FROM $table ".((empty($where))?"":"WHERE ".
            preg_replace('/^(OR |AND )|(OR |AND )$/', '', implode(" ", array_map(function($a){
                return (((strpos($a,'|') !== false) ? "OR " : "AND ")."`".str_replace('|','',$a)."` LIKE ?");
            }, array_keys($where)))))." ORDER BY $order".((!empty($limit)) ? " LIMIT $limit" : ""), array_values($where)) ?? []);
    }

    public static function read($data=null,$order=null) {
        return (self::list($data,$order,1)[0] ?? []);
    }

    public static function update($data=[]) {
        //connects to table
        if(empty($table = (self::$crudTable ?? null))) return 0;
        //verify permissions
        if(is_callable('self::crudPermissionHandler'))
            if(!self::crudPermissionHandler($data)) return -405.2;
        //organize updating values
        $values = [];
        $jsonset = [];
        $jsonvalues = [];
        if(is_array($data))
            foreach($data as $k => $v)
                if(!(strpos($k,':') !== false)) //do not enter here if there is ":"
                   if(!empty(@preg_replace('/[^a-zA-Z]/','',($k = @preg_replace('/[^0-9a-zA-Z\.\_\|]/','',str_replace('-','.',$k))))))
                       if(!(strpos($k,'.') !== false)) $values[$k] = self::convchars($v);
                       else if(is_array($parse = explode('.',$k)) && !empty($primary = ($parse[0] ?? '')))
                               if(!empty($subset = substr_replace($k, '', ((($p=strpos($k, ($n="$primary.")))===false)?0:$p), strlen($n)))) {
                                    if(!isset($jsonset[$primary])) $jsonset[$primary] = [];
                                    $jsonset[$primary][$subset] = $v; /* no conv needed at this point */ }
        //organize json set to add condition to update
        foreach($jsonset as $primary => $subset) {
            $jsonvalues[$primary] = "json_set($primary";
            foreach($subset as $k => $v) {
                $jsonvalues[$primary] .= ",'\$.$k',?";
                $values["--".preg_replace('/[^0-9a-zA-Z]/','',"$primary$k")] = self::convchars($v); }
            $jsonvalues[$primary] .= ")";
        }
        //organizes the where conditions
        $where = [];
        if(is_array($data))
            foreach($data as $k => $v)
                if(strpos($k,':') !== false) //only enters on where when there is ":"
                    if(!empty(@preg_replace('/[^a-zA-Z]/','',($k = @preg_replace('/[^0-9a-zA-Z\.\_\|]/','',str_replace('-','.',$k))))))
                        if(!(strpos($k,'.') !== false)) $where[$k] = $v;
                        else if(is_array($parse = explode('.',$k)) && !empty($primary = ($parse[0] ?? '')))
                                if(!empty($subset = substr_replace($k, '', ((($p=strpos($k, ($n="$primary.")))===false)?0:$p), strlen($n))))
                                    $where["json_value($primary,'\$.$subset')"] = self::convchars($value);
        //returns
        if(empty($where)) return 0;
        return pdo_query("UPDATE $table SET ".implode(', ',array_merge_recursive(
                array_filter(array_map(function($a){ if(substr($a,0,2) == '--') return null; return " `$a` = ? "; }, array_keys($values))),
                array_map(function($a,$b){ return " `$a` = $b "; }, array_keys($jsonvalues), array_values($jsonvalues)))).
            " WHERE ".preg_replace('/^(OR |AND )|(OR |AND )$/', '', implode(" ", array_map(function($a){
                return (((strpos($a,'|') !== false) ? "OR " : "AND ")."`".str_replace('|','',$a)."` LIKE ?");
            }, array_keys($where)))), array_values(array_merge(array_values($values), array_values($where))));
    }

    public static function delete($data=[]) {
        //connects to table
        if(empty($table = (self::$crudTable ?? null))) return 0;
        //verify permissions
        if(is_callable('self::crudPermissionHandler'))
            if(!self::crudPermissionHandler($data)) return -405.2;
        //organizes the where conditions
        $where = [];
        if(is_array($data))
            foreach($data as $k => $v)
                if(!empty(@preg_replace('/[^a-zA-Z]/','',($k = @preg_replace('/[^0-9a-zA-Z\.\_\|]/','',str_replace('-','.',$k))))))
                    if(!(strpos($k,'.') !== false)) $where[$k] = $v;
                    else if(is_array($parse = explode('.',$k)) && !empty($primary = ($parse[0] ?? '')))
                            if(!empty($subset = substr_replace($k, '', ((($p=strpos($k, ($n="$primary.")))===false)?0:$p), strlen($n))))
                                $where["json_value($primary,'\$.$subset')"] = self::convchars($value);
        //returns
        if(empty($where)) return 0;
        return pdo_query("DELETE FROM $table WHERE ".
            preg_replace('/^(OR |AND )|(OR |AND )$/', '', implode(" ", array_map(function($a){
                return (((strpos($a,'|') !== false) ? "OR " : "AND ")."`".str_replace('|','',$a)."` LIKE ?");
            }, array_keys($where)))), array_values($where));
    }

}
