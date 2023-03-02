<?php
require '../config/config.php';
include '../config/database.php';
include '../file/filelog.php';
include '../helper/dbconnection.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("App-Name: BAKONG");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

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

$headers = null;
if (isset($_SERVER['Authorization'])) {
    $headers = trim($_SERVER["Authorization"]);
}
else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { 
    $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
} elseif (function_exists('apache_request_headers')) {
    $requestHeaders = apache_request_headers();
    $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
    if (isset($requestHeaders['Authorization'])) {
        $headers = trim($requestHeaders['Authorization']);
    }
}
if (!empty($headers)) {
    if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
        $matches[1];
        $jwt = $matches[1];
    }
}

$db=new DBConnection();
$bakonglog=new Log();
$data=json_decode(file_get_contents("php://input"));

if(isset($jwt)){

    $cmd_token=$db->getToken_withoutLinkacc($jwt,$tblInit);
    if($cmd_token->rowCount()){
        $row = $cmd_token->fetch(PDO::FETCH_ASSOC);
        $bakonglog->bakonglog($row['loginPhoneNumber'],'verify-otp',json_encode($data),'Request log data',$folderLog);

        $key_num = $row['key_num'];
        $tokenParts = explode('.', $jwt);
        $header = base64_decode($tokenParts[0]);
        $payload = base64_decode($tokenParts[1]);
        $signatureProvided = $tokenParts[2];

        $expiration = (json_decode($payload)->exp);
        $now = date_create('now')->format('Y-m-d H:i:s');
        $dateTimeNow = new DateTime($now);
        $dateTimeStamp = $dateTimeNow->getTimestamp();

        $secret = $key_num;
        $base64UrlHeader = base64_encode($header);
        $base64UrlPayload = base64_encode($payload);
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
        $base64UrlSignature = base64_encode($signature);
        $signatureValid = ($base64UrlSignature === $signatureProvided);

        if($dateTimeStamp>$expiration){
            
            $JSON='{
                "status":{
                    "code":1,
                    "errorCode":5,
                    "errorMessage":"Your Session has expired"
                },
                "data":null
            }';

            $bakonglog->bakonglog($row['loginPhoneNumber'],'verify-otp',$JSON,'Response log data',$folderLog);
            echo $JSON;
        }else{
            if(empty($signatureValid)){

                $JSON='{
                    "status":{
                        "code":1,
                        "errorCode":7,
                        "errorMessage":"Token is invalid."
                    },
                    "data":null
                }';

                $bakonglog->bakonglog($row['loginPhoneNumber'],'verify-otp',$JSON,'Response log data',$folderLog);
                echo $JSON;
            }else{
                if(empty($data->otpCode)){
                    $JSON='{
                        "status":{
                            "code":1,
                            "errorCode":6,
                            "errorMessage":"Missing mandatory element."             
                        },
                        "data":null
                    }';

                    $bakonglog->bakonglog($row['loginPhoneNumber'],'verify-otp',$JSON,'Response log data',$folderLog);
                    echo $JSON;
                }else{

                    $login=$row['loginPhoneNumber'];
                    $encrypted_data=verify_otp_encryption($encryptor,$login,$data->otpCode,$chanel,$bankCode,$clientCretKey,$username,$password);
                    $result=verifyOTP($login,$verifyOTP,$encrypted_data,$ip,$username,$password,$folderLog);

                    $result=json_decode($result);
                    $status=$result->respCode;

                    switch($status){
                        case '00':{
                            $JSON='{
                                "status":{
                                    "code":0,
                                    "errorCode":null,
                                    "errorMessage":null
                                },
                                "data":{
                                    "isValid":true
                                }
                            }';
                            
                            $bakonglog->bakonglog($row['loginPhoneNumber'],'verify-otp',$JSON,'Response log data',$folderLog);
                            echo $JSON;
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

                            $bakonglog->bakonglog($row['loginPhoneNumber'],'verify-otp',$JSON,'Response log data',$folderLog);
                            echo $JSON;
                            break;
                        }
                        case '15':{
                            $JSON='{
                                "status":{
                                    "code":0,
                                    "errorCode":null,
                                    "errorMessage":null
                                },
                                "data":{
                                    "isValid":false
                                }
                            }';

                            $bakonglog->bakonglog($row['loginPhoneNumber'],'verify-otp',$JSON,'Response log data',$folderLog);
                            echo $JSON;
                            break;
                        }
                        case '18':{
                            $JSON='{
                                "status":{
                                    "code":1,
                                    "errorCode":13,
                                    "errorMessage":"OTP expired. Please try again."
                                },
                                "data":null
                            }';

                            $bakonglog->bakonglog($row['loginPhoneNumber'],'verify-otp',$JSON,'Response log data',$folderLog);
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

                            $bakonglog->bakonglog($row['loginPhoneNumber'],'verify-otp',$JSON,'Response log data',$folderLog);
                            echo $JSON;
                            break;
                        }
                    }
                }
            }
        }
    }else{
        $JSON='{
            "status":{
                "code":1,
                "errorCode":7,
                "errorMessage":"Token is invalid."
            },
            "data":null
        }';

        $bakonglog->bakonglog('','verify-otp',json_encode($data),'Request log data',$folderLog);
        $bakonglog->bakonglog('','verify-otp',$JSON,'Response log data',$folderLog);
        echo $JSON;
    }
}
else{
    $JSON='{
        "status":{
            "code":1,
            "errorCode":7,
            "errorMessage":"Token is invalid."
        },
        "data":null
    }';

    $bakonglog->bakonglog('','verify-otp',json_encode($data),'Request log data',$folderLog);
    $bakonglog->bakonglog('','verify-otp',$JSON,'Response log data',$folderLog);
    echo $JSON;
}
}

function verify_otp_encryption($encryptor,$login,$otp,$chanel,$bankCode,$clientCretKey,$username,$password){
   
    $sign=';'.$login.'='.$bankCode.'='.$otp.'?*';
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
       "otp":"$otp",
       "clientCretKey":"$clientCretKey",
       "sign":"$sign"
    }
    DATA;
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
 
    $resp = curl_exec($curl);
    curl_close($curl);
 
    return $resp;
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
 
function verifyOTP($login,$url,$data_encrypted,$ip,$username,$password,$folder){
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
    $log->writelog($login,'Verify OTP code',$data,"Request log data:",$folder);

    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    $result = curl_exec($curl);
    curl_close($curl);
    $log->writelog($login,'verify OTP code',$result,"Response log data:",$folder);
 
    return $result;
}

?>