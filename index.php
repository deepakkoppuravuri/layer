<?php
//header('Access-Control-Allow-Origin: *');
//header('Access-Control-Allow-Methods: *');
//header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: authentication');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE, PUT');
    }
    exit;
}
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
    $app->response()->header('Access-Control-Allow-Origin','*');
   //$app->response()->header('Access-Control-Allow-Methods','GET, POST, PUT, DELETE, OPTIONS');
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
    $db=new dbhandler1();
$res=$db->verifyapikey($api);
if($res==11)
{
    $response=array();
    $response['error']=true;
    $response['message']='Authentication Failed';
    echoresponse(404,$response);
    $app=\Slim\Slim::getInstance();
    $app->stop();
}
else
{
$db=new dbhandler1();
$user=$db->getpubemail($api);
$pubid=$user;
}
}
else{
    $response=array();
    $response['error']=true;
    $response['message']='no api key in header';
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
        $response['message']="username already exists";
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
    $re=new dbhandler1();
    $response=array();
    $response['error']=false;
    $response['message']="Authenticated";
    $response['apikey']=$re->getpubapi($name);
    $response['userid']=$re->getpubid($name);
    $ress=$re->getmobileno($name);
    $pubname=$re->pubname($name);
    if($pubname==null)
    {
        $response['username']="Not Available";
    }
    else
    {
        $response['username']=$pubname;
    }
    if($ress==null)
    {
        $response['mobile_no']="Not Available";
    }
    else{
        $response['mobile_no']=$ress;
    }
    $response['email_id']=$name;
    $img=$re->imgpath($name);
    if($img==null)
    {
        $response['img_path']="http://localhost/layer/photos/nopic.jpg";
    }
    else
    {
        $response['img_path']="http://localhost/layer/photos/".$img.".jpg";
    }
    echoresponse(201,$response);
}
else{
    $response=array();
    $response['error']=true;
    $response['message']="password is Invalid";
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
    $response['message']="book alredy exists";
    echoresponse(404,$response);
}
if($res==12)
{
    $response=array();
    $response['error']=false;
    $response['message']="book added sucessfully";
    $response['book_id']=$db->bookid($publisher_id,$book_name,$category,$total_sets,$edition);
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
       $response['message']="no books associated with the publisher";
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
       $response['message']="no such book associated with publisher ".$pubid;
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
//update the existing book
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
        $response['message']="no such book associated with publisher ".$pubid;
        echoresponse(404,$response);
        $app=\Slim\Slim::getInstance();
        $app->stop();
    }
$res=$db->updatebook($book_name,$category,$total_sets,$edition,$status,$bookid,$pubid);
    if($res==5)
    {
        $response=array();
        $response['error']=false;
        $response['message']="updated sucessfully";
        echoresponse(201,$response);
    }
    else{
        $response=array();
        $response['error']=true;
        $response['message']="cant update";
        echoresponse(404,$response);
    }
});
//adds keys to set_key table or updates the existing key
$app->post('/setkey','authenticate',function (){
    verifyrequiredfields(array('set_id','set_key'));
    $app=\Slim\Slim::getInstance();
    $set_id=$app->request()->post('set_id');
    $set_key=$app->request()->post('set_key');
    $db=new dbhandler();
    $res=$db->addkey($set_id,$set_key);
    if($res==10)
    {
        $response['error']=false;
        $response['message']="key added sucessfully";
        echoresponse(201,$response);
    }
    elseif ($res==11)
    {
        $response['error']=false;
        $response['message']="key updated sucessfully";
        echoresponse(201,$response);
        $app->stop();
    }
    else
    {
        $response['error']=true;
        $response['message']="key not added sucessfully";
        echoresponse(404,$response);
    }
});
$app->post('/example',function (){
   $app=\Slim\Slim::getInstance();
    $response=array();
    $db=new dbhandler1();
    $email="deepak38";
    $pubid=$db->getpubid($email);
   $targetpath="../../photos/".$pubid.".jpg";
   if(isset($_FILES['img']))
   {
       $response['file present']=true;
       $form=$_FILES['img'];

       if(move_uploaded_file($form['tmp_name'],$targetpath))
       {
           $response['success']=true;
       }
       else{
           $response['success']=false;
       }
   }
   else{
       $response['file present']=false;
   }

echoresponse(201,$response);
});
$app->post('/updateprofile','authenticate',function (){
   verifyrequiredfields(array('publisher_name','contact','email_id'));
   $app=\Slim\Slim::getInstance();
   $db=new dbhandler1();
   $response=array();
   $name=$app->request()->post('publisher_name');
   if($name=="Not Available")
   {
       $name=null;
   }
   $contact=$app->request()->post('contact');
    if($contact=="Not Available")
    {
        $contact=null;
    }
   $email=$app->request()->post('email_id');
if(isset($_FILES['img'])){
$pic=$_FILES['img'];
$pubid=$db->getpubid($email);
$targetpath="../../photos/".$pubid.".jpg";
if(move_uploaded_file($pic['tmp_name'],$targetpath)){
    $response['pic_received']=true;
$res=$db->setimgpath($pubid);
if($res==6)
{
    $response['pic_updated']=true;
}
else{
    $response['pic_updated']=false;
}
}
else
{
    $response['pic_uploaded']=false;
}
}
$ress=$db->updatepro($name,$contact,$email);
if($ress==5)
{
    $response['profile_updated']=true;
    $response['message']="updated profile successfully";
}
else{
    $response['profie_updated']=false;
    $response['message']="cant update your profile";
}
echoresponse(200,$response);
});
$app->post('/updatepic','authenticate',function (){
    verifyrequiredfields(array('email_id'));
    $app=\Slim\Slim::getInstance();
    $db=new dbhandler1();
    $response=array();
    $email=$app->request()->post('email_id');
    if(isset($_FILES['img'])){
        $pic=$_FILES['img'];
        $pubid=$db->getpubid($email);
        $targetpath="../../photos/".$pubid.".jpg";
        if(move_uploaded_file($pic['tmp_name'],$targetpath)){
            $response['pic_received']=true;
            $res=$db->setimgpath($pubid);
            if($res==6)
            {
                $response['pub_id']=$pubid;
                $response['pic_updated']=true;
                echoresponse(201,$response);
            }
            else{
                $response['pic_updated']=false;
                echoresponse(404,$response);
            }
        }
        else
        {
            $response['pic_uploaded']=false;
            echoresponse(404,$response);
        }
    }
    else{
        $response['pic_received']=false;
        echoresponse(404,$response);
    }
    //echoresponse(201,$response);
});
$app->options('/{routes:.+}', function ($request, $response, $args) {

    return $response;
});




$app->run();
?>