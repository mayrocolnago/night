<?php
class api {
    use \openapi;

    public static function apistr($data=[]) { 
        return 'Hello World'; // call to api /api/apistr will return {"result":"Hello World"}
    }

    public static function apiint($data=[]) { 
        return 1; // call to api /api/apiint will return {"result":1}
    }

    public static function apiarray2($data=[]) { 
        return [1,2]; // call to api /api/apiarray2 will return {"result":2,"data":[1,2]}
    }

    public static function apiarray3($data=[]) { 
        return [
            "value1" => 1,
            "value2" => 2,
            "value3" => 3
        ]; // call to api /api/apiarray3 will return {"result":3,"data":{"value1":1,"value2":2,"value3":3}}
    }

    public static function apiarray4($data=[]) { 
        return [
            "result" => 10,
            "value2" => 20,
            "value3" => 03
        ]; // call to api /api/apiarray4 will return {"result":10,"value2":20,"value3":30}
           // with values at the same level of "result" due to its presence on the array
    }

    public static function apiparam($data=[]) { 
        return $data; // call to api /api/apiparam?test=4 will return {"result":1,"data":{"test":4}}
    }
    
    public static function apibool($data=[]) { 
       return false; // call to api /api/apibool will return {"result":false}
    }

    public static function noapi($data=[]) { 
        return null; //or return; //won't show api result
    }
}