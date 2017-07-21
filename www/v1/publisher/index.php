<?php
require_once '../../include/dbhandler.php';
require_once '../../include/passhashh.php';
require_once '../../libs/Slim/Slim.php';
require_once '../../include/dbhandler1.php';
require_once '../../include/requiedfieldverification.php';
\Slim\Slim::registerAutoloader();
$app=new \Slim\Slim();
//verifies weather the required fields are present in incoming requet
function verifyrequiredfields($requiredfields)
{
    $error=false;
    $receivedparams=$_REQUEST;
    if($_SERVER['REQUEST_METHOD']=="PUT")
    {
        $app=\Slim\Slim::getInstance();
        parse_str($app->request()->getBody(),$receivedparams);

    }
    foreach ($requiredfields as $fields)
    {
        if(!isset($receivedparams[$fields])||strlen(trim($receivedparams[$fields]))<=0)
        {
            $error=true;
        }
    }
    if($error)
    {
        $app=\Slim\Slim::getInstance();
        $response=array();
        $response['error']=true;
        $response['message']="required fields";
        echoresponse(404,$response);
        $app->stop();
    }

}
//sends json response
function echoresponse($statuscode,$response)
{
    $app=\Slim\Slim::getInstance();
    $app->status($statuscode);
    $app->contentType('application/json');
    echo json_encode($response);
}
// checks api_key present in the request header and uses it to authenticate
function authenticate()
{
    $headers=apache_request_headers();
    global $pubid;
    if(isset($headers['authentication'])){
        $api=$headers['authentication'];
        $db=new dbhandler1();
        $res=$db->verifyapikey($api);
        if($res==11) {
            $response=array();
            $response['error']=true;
            $response['messege']='api not valid';
            echoresponse(404,$response);
            $app=\Slim\Slim::getInstance();
            $app->stop();
        }
        else {
        $db=new dbhandler1();
        $user=$db->getpubemail($api);
        $pubid=$user;
        }
    }
    else{
        $response=array();
        $response['error']=true;
        $response['messege']='no api key in header';
        echoresponse(404,$response);
        $app=\Slim\Slim::getInstance();
        $app->stop();
    }
}

//callable fuction for signup request
$app->post('/signup',function () use ($app){

    verifyrequiredfields(array('username','password'));
    $app=\Slim\Slim::getInstance();
    $db=new dbhandler1();
    $name=$app->request()->post('username');
    $pass=$app->request()->post('password');
    $res=$db->signuppub($name,$pass);
    if($res==USER_CREATED_SUCCESSFULLY)
    {
        $response=array();
        $response['error']=false;
        $response['message']="created sucessfully";
        echoresponse(201,$response);
    }
    if($res==USER_ALREADY_EXISTED)
    {
        $response=array();
        $response['error']=true;
        $response['message']="user already exists";
        echoresponse(404,$response);
    }
});

//callable function for login request
$app->post('/login',function () use ($app){
   verifyrequiredfields(array('username','password'));
   $app=\Slim\Slim::getInstance();
   $name=$app->request()->post('username');
   $pass=$app->request()->post('password');
   $db=new dbhandler1();
   $rcvpass=$db->verifypasspub($name);
   if($rcvpass==10) {
        $response=array();
        $response['error']=true;
        $response['message']="no such username";
        echoresponse(404,$response);
   }
   else{
        if(password_verify($pass,$rcvpass)) {
            $re=new dbhandler1();
            $response=array();
            $response['error']=false;
            $response['message']="authenticated";
            $response['apikey']=$re->getpubapi($name);
            $response['userid']=$re->getpubid($name);
            $response['username']=$name;
            echoresponse(201,$response);
        }
        else{
            $response=array();
            $response['error']=true;
            $response['message']="password is not valid";
            echoresponse(404,$response);
        }
   }
});

//edit publisher profile
$app->put('/editprofile/:id' , 'authenticate',   function($pub_id) use ($app) {
    verifyrequiredfields(array('publisher_name','contact'));

    $response = array();
    $publisher_name= $app->request->params('publisher_name');
    $contact = $app->request->params('contact');

    $db= new dbhandler1();

    $result = $db->editProfilePub($publisher_name,$contact,$pub_id);
    if($result)
    {
        $response['error']=false;
        $response['message']="Profile Updated Successfully";
    }
    else{
        $response['error']=true;
        $response['message']="Profile Failed to Update. Please try again";
    }
    echoresponse(200,$response);
});

//change publisher password
$app->put('/changePassword/:id', 'authenticate', function($publisher_id) use($app) {
    // check for required params
    verifyrequiredfields(array('old_password','new_password'));

    //reading put params
    $old_password = $app->request->params('old_password');
    $new_password = $app->request->params('new_password');
    $response = array();

    $db = new dbhandler1();
    // updating password
    $result = $db->changePasswordPub($old_password, $new_password, $publisher_id);
    if ($result==5) {
        // password updated successfully
        $response["error"] = false;
        $response["message"] = "Password Changed Successfully";
        echoresponse(200, $response);
    } else {
        // password failed to update
        $response["error"] = true;
        $response["message"] = "Password Update Failed. Please Try Again";
        echoresponse(404, $response);
    }
});

//creates new book
$app->post('/book','authenticate',function () use ($app){
    verifyrequiredfields(array('publisher_id','book_name','category','total_sets','edition'));
global $pubid;
$app=\Slim\Slim::getInstance();
$publisher_id=$app->request()->post('publisher_id');
$book_name=$app->request()->post('book_name');
$category=$app->request()->post('category');
$total_sets=$app->request()->post('total_sets');
$edition=$app->request()->post('edition');
$db=new dbhandler1();
$book_id=$db->genbookid();
$res=$db->createbook($book_id,$publisher_id,$book_name,$category,$total_sets,$edition);
if($res==9)
{
    $response=array();
    $response['error']=true;
    $response['messege']="book alredy exists";
    echoresponse(404,$response);
}
if($res==12)
{
    $response=array();
    $response['error']=false;
    $response['messege']="book added sucessfully";
    $response['bookid']=$book_id;
    echoresponse(201,$response);
}
});

//returns books associated with a particular publisher
$app->get('/pubbooks/:id','authenticate',function ($pubid){
   $db=new dbhandler1();
   $result=$db->pubbooks($pubid);
   if($result==8)
   {
       $response=array();
       $response['error']=true;
       $response['messege']="no books associated with the publisher";
       echoresponse(404,$response);
   }
   else{
       $response=array();
       $response['error']=false;
       $response['books']=array();
       array_push($response['books'],$result);
       echoresponse(201,$response);
   }
});

//returns details of book of publisher
$app->get('/bookdetails/:pubid/:bookid','authenticate',function ($pubid,$bookid){
   $db=new dbhandler1();
   $res1=$db->verifybooknpub($bookid,$pubid);
   if($res1==5)
   {
       $response=array();
       $response['error']=true;
       $response['messege']="no such book associated with publisher ".$pubid;
       echoresponse(404,$response);
       $app=\Slim\Slim::getInstance();
       $app->stop();
   }
   $res=$db->bookdetails($bookid,$pubid);
   $response=array();
   $response['error']=false;
   $response['message']="details of book selected";
   $response['details']=array();
   while($re=$res->fetch_assoc())
   {
       $ar=array();
       $ar['book_id']=$re['book_id'];
       $ar['book_name']=$re['book_name'];
       $ar['category']=$re['category'];
       $ar['total_sets']=$re['total_sets'];
       $ar['edition']=$re['edition'];
       $ar['status']=$re['status'];
       array_push($response['details'],$ar);
   }
   echoresponse(201,$response);
});

//updated the existing book
$app->put('/bookupdate/:pubid/:bookid','authenticate',function ($pubid,$bookid){
    verifyrequiredfields(array('book_name','category','total_sets','edition','status'));

   $app=\Slim\Slim::getInstance();
   $book_name=$app->request()->put('book_name');
   $category=$app->request()->put('category');
   $total_sets=$app->request()->put('total_sets');
   $edition=$app->request()->put('edition');
   $status=$app->request()->put('status');
   $db=new dbhandler1();
    $res1=$db->verifybooknpub($bookid,$pubid);

    if($res1==5)
    {
        $response=array();
        $response['error']=true;
        $response['messege']="no such book associated with publisher ".$pubid;
        echoresponse(404,$response);
        $app=\Slim\Slim::getInstance();
        $app->stop();
    }

$res=$db->updatebook($book_name,$category,$total_sets,$edition,$status,$bookid,$pubid);
    if($res==5)
    {
        $response=array();
        $response['error']=false;
        $response['messege']="updated sucessfully";
        echoresponse(201,$response);
    }
    else{
        $response=array();
        $response['error']=true;
        $response['messege']="cant update";
        echoresponse(404,$response);
    }
});

//create a new set
$app->post('/set','authenticate', function() use ($app) {
    // check for required params
    verifyrequiredfields(array('book_id','set_name','total_questions','total_marks','positive_marks','negative_marks'));

    $response = array();
    $book_id = $app->request->post('book_id');
    $set_name = $app->request->post('set_name');
    $total_questions = $app->request->post('total_questions');
    $total_marks = $app->request->post('total_marks');
    $positive_marks = $app->request->post('positive_marks');
    $negative_marks = $app->request->post('negative_marks');

    $db = new dbhandler1();
    $set_id=$db->generateSetId();
    // creating new task
    $result = $db->createSet($set_id,$book_id, $set_name, $total_questions, $total_marks, $positive_marks, $negative_marks);

    if ($result==SET_CREATED_SUCCESFULLY){
        $response['error']=false;
        $response['messasge']="Set Created Successfully";
        $response['set_id']=$set_id;
        echoresponse(200, $response);
    }
    else if($result==SET_ALREADY_EXISTS){
        $response['error']=true;
        $response['message']="Set Already Exists. Try Again";
        echoresponse(404, $response);
    }
    else{
        $response['error']=true;
        $response['message']="Failed to create Set. Try Again";
        echoresponse(404, $response);
    }

});

//get sets of a particular book
$app->get('/getSets/:bookid','authenticate',function($book_id) use ($app){
    $db = new dbhandler1();
    $response = array();
    $result=$db->getSets($book_id);
    if($result==23){
        $response['error']=true;
        $response['message']="No sets in this book";
        echoresponse(404,$response);
    }
    else{
        $response = array();
        $response['error']=false;
        $response['sets']=array();
        array_push($response['sets'],$result);
        echoresponse(201,$response);
    }
});

//get details of a set
$app->get('/getSetDetails/:setid/:bookid','authenticate',function($set_id,$book_id) use ($app){
    $db = new dbhandler1();

    $response = array();
    $set_details=$db->setDetails($set_id,$book_id);

    $response['error']=false;
    $response['message']="details of book selected";
    $response['set_details']=array();
    while($result=$set_details->fetch_assoc())
    {
        $field=array();
        $field['set_id']=$result['set_id'];
        $field['set_name']=$result['set_name'];
        $field['total_questions']=$result['total_questions'];
        $field['total_marks']=$result['total_marks'];
        $field['positive_marks']=$result['positive_marks'];
        $field['negative_marks']=$result['negative_marks'];
        array_push($response['set_details'],$field);
    }
    echoresponse(201,$response);
});
//update a set
$app->put('/updateSet/:set_id/:book_id', 'authenticate', function($set_id,$book_id) {
    verifyrequiredfields(array('set_name','total_questions','total_marks','positive_marks','negative_marks'));

    $app=\Slim\Slim::getInstance();
    $set_name=$app->request->put('set_name');
    $total_questions=$app->request->put('total_questions');
    $total_marks=$app->request->put('total_marks');
    $positive_marks=$app->request->put('positive_marks');
    $negative_marks=$app->request->put('negative_marks');
    $response=array();

    $db=new dbhandler1();
    $result=$db->updateSet($set_name,$total_questions,$total_marks,$positive_marks,$negative_marks,$set_id,$book_id);

    if($result==1){
        $response['error']=false;
        $response['message']="Set Updated Succesfully";
        echoresponse(200, $response);
    }
    else {
        $response['error']=true;
        $response['message']="Failed to Update Set Details. Try Again";
        echoresponse(404, $response);
    }
});

$app->run();
?>