<?php
namespace app;

class user extends \auth {

    public $table = 'users';

    public $CSRFprotection = true;
    public $allow2FAsecret = false;

    public $allowAPIsignup = false;
    public $allowAPIexists = false;
    public $allowAPIsuggest = false;
    public $allowAPIrecovery = false;
}