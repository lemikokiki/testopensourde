<?php
include '../helper/dbconnection.php';
include '../config/database.php';
include '../file/filelog.php';
require '../config/config.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("App-Name: BAKONG");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

$data = json_decode(file_get_contents("php://input"));
$bakonglog=new Log();
$db=new DBConnection();

$appName ='';
$contentType='';
$ALLHeaders = getallheaders();
if (isset($ALLHeaders['App-Name'])){
    $appName = $ALLHeaders['App-Name'];
}
if(isset($ALLHeaders['Content-Type'])){
    $contentType=$ALLHeaders['Content-Type'];
}

if($appName==='BAKONG' and $contentType==='application/json'){
    
if(empty($data->loginType) or empty($data->login) or empty($data->key) or empty($data->bakongAccId) or empty($data->phoneNumber)){
    $JSON='{
        "status":{
            "code":1,
            "errorCode":6,
            "errorMessage":"Missing mandatory element.",
        },
        "data":null
    }';

    $bakonglog->bakonglog('','init-link-account',json_encode($data),'Request log data',$folderLog);
    $bakonglog->bakonglog('','init-link-account',$JSON,'Response log data',$folderLog);
    echo $JSON;
}else{
    $bakonglog->bakonglog($data->login,'init-link-account',json_encode($data),'Request log data',$folderLog);

    if($data->loginType===$loginType){
        $databaseService = new DatabaseService();
        $conn = $databaseService->getConnection();

        $pBIDC=$data->login;
        $pBakong=$data->phoneNumber;

        $phoneBIDC=substr($pBIDC,1);
        $phoneBakong=substr($pBakong,3);
        $phone=$data->phoneNumber;

        if($phoneBIDC==$phoneBakong){

            // verify the valid customer with BIDC MB
            $encrypted_data = dataEncryption($data->login,$data->key,$bankCode,$clientCretKey,$encryptor,$username,$password);
            $authentication = authenticate_user($data->login,$userAuthenticator,$encrypted_data,$ip,$username,$password,$folderLog);

            $authentication=json_decode($authentication);
            $status=$authentication->respCode;

            switch($status){
                case '00':{

                    $cmd_inquiry=$db->initInquiry($data->bakongAccId,$data->login,$tblInit);
                    if($cmd_inquiry->rowCount()>0){
                        $row = $cmd_inquiry->fetch(PDO::FETCH_ASSOC);

                        if($row['bakongAccId']==$data->bakongAccId and $row['loginPhoneNumber']=$data->login){
                            $encrytp_initotp=init_otp_encryption($encryptor,$data->login,$chanel,$bankCode,$smsformat,$expireOTP,$clientCretKey,$username,$password);
                            $anOTP=sendOPT($data->login,$initOTP,$encrytp_initotp,$ip,$username,$password,$folderLog);
                
                            $JSON='{
                                "status":{
                                    "code":0,
                                    "errorCode":null,
                                    "errorMessage":null
                                },
                                "data":{
                                    "accessToken":"'.$row['token'].'",
                                    "requireOtp":true,
                                    "requireChangePhone":false,
                                    "last3DigitPhone":"'.substr($phone,strlen($phone)-3,strlen($phone)-1).'"
                                }
                            }';
                            $bakonglog->bakonglog($data->login,'init-link-account',$JSON,'Response log data',$folderLog);
                            echo $JSON;
                        }else{
                            $JSON='{
                                "status":{
                                    "code":1,
                                    "errorCode":15,
                                    "errorMessage":"This account is already linked to another Bakong account."
                                },
                                "data":null
                            }';

                            $bakonglog->bakonglog($row['loginPhoneNumber'],'finish-link-account',$JSON,'Response log data',$folderLog);
                            echo $JSON;
                        }
                    }
                    else{
                        $header = json_encode([
                            'typ' => 'JWT',
                            'alg' => 'sha256'
                        ]);
                        $timezone = new DateTime("now", new DateTimeZone('Asia/Bangkok'));
                        $timezone = date("Y-m-d H:i:s");
                        $date_6months = strtotime("$timezone ,$expired_date");
                
                        $expire_claim = $date_6months; 
                        $payload = json_encode([
                            'sub' => $data->bakongAccId,
                            'auth' => ['can_get_balance','can_top_up'],
                            'exp' => $expire_claim
                        ]);
                
                        $secret = bin2hex(random_bytes(32)); 
                        $base64UrlHeader = base64_encode($header);
                        $base64UrlPayload = base64_encode($payload);
                        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
                        $base64UrlSignature = base64_encode($signature);
                        $jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;

                        $cif=$authentication->customerInfo->cifCore;
                        $initResult=$db->initLinkAcct($data,$expire_claim,$cif,$jwt,$secret,$tblInit);
                
                        if($initResult->rowCount()){
                            $encrytp_initotp=init_otp_encryption($encryptor,$data->login,$chanel,$bankCode,$smsformat,$expireOTP,$clientCretKey,$username,$password);
                            $anOTP=sendOPT($data->login,$initOTP,$encrytp_initotp,$ip,$username,$password,$folderLog);
                            
                            $JSON='{
                                "status":{
                                    "code":0,
                                    "errorCode":null,
                                    "errorMessage":null
                                },
                                "data":{
                                    "accessToken":"'.$jwt.'",
                                    "requireOtp":true,
                                    "requireChangePhone":false,
                                    "last3DigitPhone":"'.substr($phone,strlen($phone)-3,strlen($phone)-1).'"
                                }
                            }';

                            $bakonglog->bakonglog($data->login,'init-link-account',$JSON,'Response log data',$folderLog);
                            echo $JSON;
                        }
                        else{
                            
                            $JSON='{
                                "status":{
                                    "code":1,
                                    "errorCode":1,
                                    "errorMessage":"Internal server error."
                                },
                                "data":null
                            }';

                            $bakonglog->bakonglog($data->login,'init-link-account',$JSON,'Response log data',$folderLog);
                            echo $JSON;
                        }
                    }
                    break;
                }
                case '01':
                case '02':{
                    $JSON='{
                        "status":{
                            "code":1,
                            "errorCode":4,
                            "errorMessage":"Authentication error. Please try again."
                        },
                        "data":null
                    }';

                    $bakonglog->bakonglog($data->login,'init-link-account',$JSON,'Response log data',$folderLog);
                    echo $JSON;
                    break;
                }
                default:{   
                    $JSON='{
                        "status":{
                            "code":1,
                            "errorCode":1,
                            "errorMessage":"Internal server error."
                        },
                        "data":null
                    }';

                    $bakonglog->bakonglog($data->login,'init-link-account',$JSON,'Response log data',$folderLog); 
                    echo $JSON;
                    break;
                }
            }
        }else{
            $JSON='{
                "status":{
                    "code":1,
                    "errorCode":4,
                    "errorMessage":"Authentication error. Please try again."
                },
                "data":null
            }';

            $bakonglog->bakonglog($data->login,'init-link-account',$JSON,'Response log data',$folderLog);
            echo $JSON;
        }
    }else{
        $JSON='{
            "status":{
                "code":1,
                "errorCode":4,
                "errorMessage":"Authentication error. Please try again."
            },
            "data":null
        }';

        $bakonglog->bakonglog($data->login,'init-link-account',$JSON,'Response log data',$folderLog);
        echo $JSON;
    }
}
}

function GUID()
{
    if (function_exists('com_create_guid') === true)
    {
        return strtolower(trim(com_create_guid(), '{}'));
    }
    return strtolower(sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', 
    mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535)));
}

function authenticate_user($login,$url,$data_encrypted,$ip,$username,$password,$folder){

    $log=new Log();
    $uuid=GUID();

    $timezone = new DateTime("now", new DateTimeZone('Asia/Bangkok'));
    $timezone = date("Y-m-d H:i:s");
    $timestamp = strtotime("$timezone");

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Basic '.base64_encode("$username:$password"),
        );
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

    $data = <<<DATA
    {
    "thread":"$uuid",
    "data":"$data_encrypted",
    "t":$timestamp,
    "ip":"$ip"
    }
    DATA;
    $log->writelog($login,'Authenticate User Password Login from MB',$data,"Request log data:",$folder);

    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    $result = curl_exec($curl);
    curl_close($curl);
    $log->writelog($login,'Authenticate User Password Login from MB',$result,"Response log data:",$folder);

    return $result;
}

function dataEncryption($login,$pass,$bankCode,$clientCretKey,$encryptor,$username,$password){

    $sign=';'.$login.'='.$bankCode.'='.$pass.'?*';
    $sign=hash('sha256',$sign);

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $encryptor);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    $headers = array(
    'Accept: application/json',
    'Content-Type: application/json',
    'Authorization: Basic '.base64_encode("$username:$password"),
    );
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

    $data = <<<DATA
    {
    "userName":"$login",
    "pass":"$pass",
    "bankCode":"$bankCode",
    "clientCretKey":"$clientCretKey",
    "sign":"$sign"
    }
    DATA;

    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

    $resp = curl_exec($curl);
    curl_close($curl);

    return $resp;
}

// function init send otp to customer
function init_otp_encryption($encryptor,$login,$chanel,$bankCode,$smsformat,$expireOTP,$clientCretKey,$username,$password){
   
    $sign=';'.$login.'='.$bankCode.'='.$smsformat.'='.$expireOTP.'?*';
    $sign=hash('sha256',$sign);
 
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $encryptor);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
 
    $headers = array(
       'Accept: application/json',
       'Content-Type: application/json',
       'Authorization: Basic '.base64_encode("$username:$password"),
    );
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
 
    $data = <<<DATA
    {
       "userName":"$login",
       "channel":"$chanel",
       "bankCode":"$bankCode",
       "messageFormatSendSMS":"$smsformat",
       "expireMinuteOTP":$expireOTP,
       "clientCretKey":"$clientCretKey",
       "sign":"$sign"
    }
    DATA;
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
 
    $resp = curl_exec($curl);
    curl_close($curl);
 
    return $resp;
}
 
function sendOPT($login,$url,$data_encrypted,$ip,$username,$password,$folder){
 
    $log=new Log();
    $uuid=GUID();
 
    $timezone = new DateTime("now", new DateTimeZone('Asia/Bangkok'));
    $timezone = date("Y-m-d H:i:s");
    $timestamp = strtotime("$timezone");
 
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
 
    $headers = array(
       'Accept: application/json',
       'Content-Type: application/json',
       'Authorization: Basic '.base64_encode("$username:$password"),
    );
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
 
    $data = <<<DATA
    {
       "thread":"$uuid",
       "data":"$data_encrypted",
       "t":$timestamp,
       "ip":"$ip"
    }
    DATA;
    $log->writelog($login,'Send OTP code to customer',$data,"Request log data:",$folder);

    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    $result = curl_exec($curl);
    curl_close($curl);

    $log->writelog($login,'Send OTP code to customer',$result,"Response log data:",$folder);
}
?>
