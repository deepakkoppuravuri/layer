<?php
require_once '../../include/dbhandler.php';
require_once '../../include/passhashh.php';
require_once '../../libs/Slim/Slim.php';
require_once '../../include/requiedfieldverification.php';
\Slim\Slim::registerAutoloader();
$app=new \Slim\Slim();
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
function echoresponse($statuscode,$response)
{
    $app=\Slim\Slim::getInstance();
    $app->status($statuscode);
    $app->contentType('application/json');
    echo json_encode($response);
}
$app->post('/signup',function () use ($app){

    verifyrequiredfields(array('username','password'));
    $app=\Slim\Slim::getInstance();
    $db=new dbhandler();
    $name=$app->request()->post('username');
    $pass=$app->request()->post('password');
    $res=$db->signup($name,$pass);
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
$app->post('/login',function () use ($app){
   verifyrequiredfields(array('username','password'));
   $app=\Slim\Slim::getInstance();
   $name=$app->request()->post('username');
   $pass=$app->request()->post('password');
   $db=new dbhandler();
$rcvpass=$db->verifypass($name);
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
    $response['apikey']=$re->getapi($name);
    $response['userid']=$re->getuserid($name);
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
$app->run();
?>