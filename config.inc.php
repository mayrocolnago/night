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
$allowed_domains = [
    '*'
];
if(in_array(($httporg='*'), $allowed_domains) || in_array(($httporg=($_SERVER['HTTP_ORIGIN'] ?? [])), $allowed_domains))
    header('Access-Control-Allow-Origin: '.$httporg);
