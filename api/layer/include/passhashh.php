<?php
class passhash{
    private static $algo='$2a';
    private static $cost='$10';
    public static function unique_salt()
    {
        return substr(sha1(mt_rand()),0,22);
    }
    public static function hash($password)
    {
        return crypt($password,self::$algo.self::$cost.'$'.self::unique_salt());
    }
    public static function check_password($hash,$password)
    {
        $fullsalt=substr($hash,0,29);
        $new_salt=crypt($password,$fullsalt);
        return ($fullsalt==$new_salt);
    }
    public static function generateapikey()
    {
         return md5(rand(0,1000));
    }
    public static function genuserid($email)
    {
         return substr(md5($email),0,6);
    }
}
?>