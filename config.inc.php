<?php
//auto select environment
if(!isset($_SERVER['DEVELOPMENT']))
  if(!($_SERVER['DEVELOPMENT'] = empty($env = ($_SERVER['SERVER_NAME'] ?? ''))))
    if(strpos($env,'localhost') !== false
    ||(strpos($env,'.night') !== false)
    ||(strpos($env,'.code') !== false)
    ||(strpos($env,'.dev') !== false)
    ) $_SERVER['DEVELOPMENT'] = true;

//CORS permissions
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

//set timezone
@date_default_timezone_set('America/Sao_Paulo');
@session_start();