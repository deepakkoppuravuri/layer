<?php
class dbhandler{
    private $conn;
    //uses dbconnect.php and assigns db connection to $conn
    function __construct()
    {
        include_once dirname(__FILE__).'./dbconnect.php';
        $db=new database();
        $this->conn=$db->connect();
        if($this->conn==null)
        {
            echo 'error';
        }
    }
    //used for user registration
    function signup($username,$password)
    {
        $flag1=0;
        $stmt=$this->conn->prepare("SELECT email_id_u FROM users");
        $stmt->execute();
        $stmt->bind_result($usr);
        $stmt->store_result();
        while($stmt->fetch())
        {
            if($usr==$username)
            {
                $flag1=1;
                break;
            }
        }
        if($flag1==1)
        {
            //echo 'username already exists';
            return USER_ALREADY_EXISTS;
        }
        else{
            include_once dirname(__FILE__).'./passhashh.php';
            $hashedpass=passhash::hash($password);
            $apikey=passhash::generateapikey();
            $userid=passhash::genuserid($username);
            $stmt=$this->conn->prepare("INSERT INTO users(email_id_u,password,api_key,user_id) VALUES(?,?,?,?)");
            $stmt->bind_param("ssss",$username,$hashedpass,$apikey,$userid);
            $stmt->execute();
            return USER_CREATED;
        }
    }
    //used for publisher registration
    function signuppub($username,$password)
    {
        $flag1=0;
        $stmt=$this->conn->prepare("SELECT email_id_p FROM publishers");
        $stmt->execute();
        $stmt->bind_result($usr);
        $stmt->store_result();
        while($stmt->fetch())
        {
            if($usr==$username)
            {
                $flag1=1;
                break;
            }
        }
        if($flag1==1)
        {
            //echo 'username already exists';
            return USER_ALREADY_EXISTS;
        }
        else{
            include_once dirname(__FILE__).'./passhashh.php';
            $hashedpass=passhash::hash($password);
            $apikey=passhash::generateapikey();
            $pubid=passhash::genuserid($username);
            $stmt=$this->conn->prepare("INSERT INTO publishers(email_id_p,password,api_key,publisher_id) VALUES(?,?,?,?)");
            $stmt->bind_param("ssss",$username,$hashedpass,$apikey,$pubid);
            $stmt->execute();
            return USER_CREATED;
        }
    }
    //user password verification
    function verifypass($username)
    {

        $stmt=$this->conn->prepare("select password from users where email_id_u=?");
        $stmt->bind_param("s",$username);
        $stmt->execute();
        $stmt->bind_result($pass);
        $stmt->store_result();
        if($stmt->num_rows>0)
        {
            $stmt->fetch();
            return $pass;
        }
        else
        {
            return 10;
        }
    }
    //publisher password verification
    function verifypasspub($username)
    {

        $stmt=$this->conn->prepare("select password from publishers where email_id_p=?");
        $stmt->bind_param("s",$username);
        $stmt->execute();
        $stmt->bind_result($pass);
        $stmt->store_result();
        if($stmt->num_rows>0)
        {
            $stmt->fetch();
            return $pass;
        }
        else
        {
            return 10;
        }
    }
    //returns userid associted with email of user
    function getuserid($email)
    {
        $stmt=$this->conn->prepare("select user_id from users where email_id_u=?");
        $stmt->bind_param("s",$email);
        $stmt->execute();
        $stmt->bind_result($res);
        $stmt->store_result();
        $stmt->fetch();
        return $res;
    }
    //returns publishers id assocaiated with publisher email
    function getpubid($email)
    {
        $stmt=$this->conn->prepare("select publisher_id from publishers where email_id_p=?");
        $stmt->bind_param("s",$email);
        $stmt->execute();
        $stmt->bind_result($res);
        $stmt->store_result();
        $stmt->fetch();
        return $res;
    }
    //returns api_key of user
    function getapi($email)
    {
        $stmt=$this->conn->prepare("select api_key from users where email_id_u=?");
        $stmt->bind_param("s",$email);
        $stmt->execute();
        $stmt->bind_result($res);
        $stmt->store_result();
        $stmt->fetch();
        return $res;
    }
    //returns api_key of publisher
    function getpubapi($email)
    {
        $stmt=$this->conn->prepare("select api_key from publishers where email_id_p=?");
        $stmt->bind_param("s",$email);
        $stmt->execute();
        $stmt->bind_result($res);
        $stmt->store_result();
        $stmt->fetch();
        return $res;
    }
    //used to verify api_key present in header of request
    function verifyapikey($api)
    {
        $stmt=$this->conn->prepare("select api_key from publishers");
        $stmt->execute();
        $stmt->bind_result($res);
        $stmt->store_result();
        while ($stmt->fetch())
        {
            if($res==$api)
            {
                $stmt1=$this->conn->prepare("select publisher_id from publishers where api_key=?");
                $stmt1->bind_param("s",$api);
                $stmt1->execute();
                $stmt1->bind_result($res1);
                $stmt1->store_result();
                $stmt1->fetch();
                return $res1;
            }
        }
        return 11;
    }
    //returns email of publisher using api_key
    function getpubemail($api)
    {
        $stmt=$this->conn->prepare("select email_id_p from publishers where api_key=?");
        $stmt->bind_param("s",$api);
        $stmt->execute();
        $stmt->bind_result($res);
        $stmt->store_result();
        $stmt->fetch();
        return $res;
    }
    //creates a row in book table after checking weather duplicate buk is present or not
    function createbook($publisher_id,$book_name,$category,$total_sets,$edition)
    {
        $stmt=$this->conn->prepare("select book_name from books where publisher_id=? and book_name=? and category=? and total_sets=? and edition=?");
        $stmt->bind_param("sssss",$publisher_id,$book_name,$category,$total_sets,$edition);
        $stmt->execute();
        $stmt->bind_result($bk);
        $stmt->store_result();
        while($stmt->fetch())
        {
            if($bk==$book_name)
            {
                return 9;
            }
        }
        $book_id=$this->genbookid();
        $stmt=$this->conn->prepare("insert into books(book_id,publisher_id,book_name,category,total_sets,edition) values(?,?,?,?,?,?)");
        $stmt->bind_param("ssssss",$book_id,$publisher_id,$book_name,$category,$total_sets,$edition);
        $stmt->execute();
        return 12;
    }
    //generated bookid
    function genbookid()
    {
        return substr(uniqid(rand(),true),0,6);
    }
    //returns all the books associated with a particualr publisher
    function pubbooks($pubid)
    {
        $stmt=$this->conn->prepare("select book_id from books where publisher_id=?");
        $stmt->bind_param("s",$pubid);
        $stmt->execute();
        $stmt->bind_result($res);
        $stmt->store_result();
        if($stmt->num_rows<=0)
        {
            return 8;
        }
        $result=array();
        $i=0;
        while($stmt->fetch())
        {
            $result[$i]=$res;
            $i++;
        }
        return $result;
    }
    //returns bookdetails using bookid
    function bookdetails($bookid,$pubid)
    {
        $stmt=$this->conn->prepare("select book_id,book_name,category,total_sets,edition,status from books where book_id=? and publisher_id=?");
        $stmt->bind_param("ss",$bookid,$pubid);
        $stmt->execute();
        $res= $stmt->get_result();
        return $res;
    }
    //checks weather particular book is assioted with publisher or not
    function verifybooknpub($bookid,$pubid)
    {
        $stmt=$this->conn->prepare("select book_name from books where book_id=? and publisher_id=?");
        $stmt->bind_param("ss",$bookid,$pubid);
        $stmt->execute();
        $stmt->bind_result($res);
        $stmt->store_result();
        if($stmt->num_rows<=0)
        {
            return 5;
        }
        return 6;
    }
    //updates the already present book or else returns book not assoiated with the publisher
    function updatebook($book_name,$category,$total_sets,$edition,$status,$book_id,$publisher_id)
    {
        $stmt=$this->conn->prepare("update  books set book_name=?,category=?,total_sets=?,edition=?,status=? where book_id=? and publisher_id=?");
        $stmt->bind_param("sssssss",$book_name,$category,$total_sets,$edition,$status,$book_id,$publisher_id);
        if($stmt->execute())
        {
            return 5;
        }
        return 6;
    }
}
?>