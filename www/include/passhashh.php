<?php
class passhash{
    private static $algo='$2a';
    private static $cost='$10';
    public static function unique_salt()
    {
        return substr(sha1(mt_rand()),0,22);
    }
    //returns hashed password
    public static function hash($password)
    {
        return crypt($password,self::$algo.
            self::$cost.
            '$'.self::unique_salt());
    }
    //checks weather the hashed password in database and given password are same
    public static function check_password($hash,$password)
    {
        $full_salt=substr($hash,0,29);
        $new_salt=crypt($password,$full_salt);
        return ($hash==$new_salt);
    }
    //returns generated api key
    public static function generateapikey()
    {
         return md5(rand(0,1000));
    }
    //returns generated userid
    public static function genuserid($email)
    {
         return substr(md5($email),0,6);
    }
}
?>