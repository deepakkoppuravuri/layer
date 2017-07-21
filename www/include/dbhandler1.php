<?php
class dbhandler1{
    private $conn;
    function __construct()
    {
        include_once dirname(__FILE__).'./dbconnect.php';
        $db=new DbConnect();
        $this->conn=$db->connect();
        if($this->conn==null)
        {
            echo 'error';
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
            return USER_ALREADY_EXISTED;
        }
        else{
            include_once dirname(__FILE__).'./passhashh.php';
            $hashedpass=passhash::hash($password);
            $apikey=passhash::generateapikey();
            $pubid=passhash::genuserid($username);
            $stmt=$this->conn->prepare("INSERT INTO publishers(email_id_p,password,api_key,publisher_id) VALUES(?,?,?,?)");
            $stmt->bind_param("ssss",$username,$hashedpass,$apikey,$pubid);
            $stmt->execute();
            return USER_CREATED_SUCCESSFULLY;
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
    //updates publisher profile and returns boolean
    public function editProfilePub($publisher_name,$contact,$publisher_id)
    {
        $stmt = $this->conn->prepare("UPDATE publishers SET publisher_name = ?, contact = ? WHERE publisher_id = ?");
        $stmt->bind_param("sss",$publisher_name, $contact, $publisher_id);
        $stmt->execute();
        $num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;
    }
    //change publisher password and returns boolean
    public function changePasswordPub($old_password, $new_password, $publisher_id)
    {
        $stmt = $this->conn->prepare("SELECT password FROM publishers WHERE publisher_id = ?");
        $stmt->bind_param("s", $publisher_id);
        $stmt->execute();
        $stmt->bind_result($password);
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->fetch();
            $stmt->close();
            if (passhash::check_password($password, $old_password)) {
                $new_password_hash = passhash::hash($new_password);
                $stmt = $this->conn->prepare("UPDATE publishers SET password=? WHERE publisher_id=?");
                $stmt->bind_param("ss", $new_password_hash, $publisher_id);
                $stmt->execute();
                $num_affected_rows = $stmt->affected_rows;
                $stmt->close();
                return $num_affected_rows > 0;
            }
        }else {
            return 6;
        }
    }

    //creates a row in book table after checking weather duplicate buk is present or not
    function createbook($book_id,$publisher_id,$book_name,$category,$total_sets,$edition)
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
        $stmt=$this->conn->prepare("update books set book_name=?,category=?,total_sets=?,edition=?,status=? where book_id=? and publisher_id=?");
        $stmt->bind_param("sssssss",$book_name,$category,$total_sets,$edition,$status,$book_id,$publisher_id);
        if($stmt->execute())
        {
            return 5;
        }
        return 6;
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

    //returns all the books associated with a particular publisher
    function pubbooks($pubid)
    {
        $stmt=$this->conn->prepare("select book_id,book_name from books where publisher_id=?");
        $stmt->bind_param("s",$pubid);
        $stmt->execute();
        $stmt->bind_result($book_id,$book_name);
        $stmt->store_result();
        if($stmt->num_rows<=0)
        {
            return 8;
        }
        $result=array();
        $i=1;
        while($stmt->fetch())
        {
            $result[]=$book_id;
            $result[]=$book_name;
            $i++;
        }
        return $result;
    }

    //creates a new set
    public function createSet($set_id,$book_id, $set_name, $total_questions, $total_marks, $positive_marks, $negative_marks) {
        $stmt=$this->conn->prepare("select set_name from sets where book_id=? and set_name=? and total_questions=? and total_marks=? and positive_marks=? and negative_marks=?");
        $stmt->bind_param("ssssss",$book_id,$set_name,$total_questions,$total_marks,$positive_marks,$negative_marks);
        $stmt->execute();
        $stmt->bind_result($set_details);
        $stmt->store_result();
        while($stmt->fetch()){
            if($set_details==$set_name){
                return SET_ALREADY_EXISTS;
            }
        }
        $stmt=$this->conn->prepare("INSERT INTO sets(set_id,book_id,set_name,total_questions,total_marks,positive_marks,negative_marks) VALUES(?,?,?,?,?,?,?)");
        $stmt->bind_param("sssssss",$set_id,$book_id,$set_name,$total_questions,$total_marks,$positive_marks,$negative_marks);
        $stmt->execute();
        return SET_CREATED_SUCCESFULLY;
    }

    //generate set id
    function generateSetId(){
        return substr(uniqid(rand(),true),0,6);
    }

    //get sets by book_id
    public function getSets($book_id){
        $stmt=$this->conn->prepare("SELECT set_id,set_name from sets WHERE book_id=?");
        $stmt->bind_param("s",$book_id);
        $stmt->execute();
        $stmt->bind_result($set_id,$set_name);
        $stmt->store_result();
        $result = array();
        while($row=$stmt->fetch())
        {
            $result[]=$set_id;
            $result[]=$set_name;
        }
        return $result;
    }

    //get set details
    public function setDetails($set_id,$book_id){
        $stmt=$this->conn->prepare("select set_id,set_name,total_questions,total_marks,positive_marks,negative_marks from sets where set_id=? and book_id=?");
        $stmt->bind_param("ss",$set_id,$book_id);
        $stmt->execute();
        $res= $stmt->get_result();
        return $res;
    }
    //update set details
    public function updateSet($set_name,$total_questions,$total_marks,$positive_marks,$negative_marks,$set_id,$book_id){
        $stmt=$this->conn->prepare("UPDATE sets SET set_name=?, total_questions=?, total_marks=?, positive_marks=?, negative_marks=? WHERE set_id=? AND book_id=?");
        $stmt->bind_param("sssssss",$set_name,$total_questions,$total_marks,$positive_marks,$negative_marks,$set_id,$book_id);
        $stmt->execute();
        $num_affected_rows=$stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows;

    }
}
?>