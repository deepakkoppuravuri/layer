<?php
class database{
    private $conn=null;
    function __construct()
    {
    }
    function connect()
    {
        include_once dirname(__FILE__).'./config.php';
        $this->conn=new mysqli(DB_HOST,DB_USER,DB_PASS,DB_DATABASE);
        if(mysqli_connect_errno())
        {
            echo "error".mysqli_connect_error();
        }

        return $this->conn;

    }
}
?>