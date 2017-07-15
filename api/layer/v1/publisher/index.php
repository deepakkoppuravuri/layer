<?php
require_once '../../include/dbhandler.php';
require_once '../../include/passhashh.php';
require_once '../../libs/Slim/Slim.php';
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
if(isset($headers['authentication']))
{
    $api=$headers['authentication'];
    $db=new dbhandler();
$res=$db->verifyapikey($api);
if($res==11)
{
    $response=array();
    $response['error']=true;
    $response['messege']='api not valid';
    echoresponse(404,$response);
    $app=\Slim\Slim::getInstance();
    $app->stop();
}
else
{
$db=new dbhandler();
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
    $db=new dbhandler();
    $name=$app->request()->post('username');
    $pass=$app->request()->post('password');
    $res=$db->signuppub($name,$pass);
    if($res==USER_CREATED)
    {
        $response=array();
        $response['error']=false;
        $response['message']="created sucessfully";
        echoresponse(201,$response);
    }
    if($res==USER_ALREADY_EXISTS)
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
   $db=new dbhandler();
$rcvpass=$db->verifypasspub($name);
if($rcvpass==10)
{
    $response=array();
    $response['error']=true;
    $response['message']="no such username";
    echoresponse(404,$response);
}
else
{
if(password_verify($pass,$rcvpass))
{
    $re=new dbhandler();
    $response=array();
    $response['error']=false;
    $response['message']="authenticated";
    $response['apikey']=$re->getpubapi($name);
    $response['userid']=$re->getpubid($name);
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
$db=new dbhandler();
$res=$db->createbook($publisher_id,$book_name,$category,$total_sets,$edition);
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
    echoresponse(201,$response);
}
});
//returns books associated with a particular publisher
$app->get('/pubbooks/:id','authenticate',function ($pubid){
   $db=new dbhandler();
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
   $db=new dbhandler();
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
   $db=new dbhandler();
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
$app->run();
?>