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

$data = json_decode(file_get_contents("php://input"));
$db=new DBConnection();
$bakonglog=new Log();

if(empty($data->loginType) or empty($data->login) or empty($data->key)){
    $JSON='{
        "status":{
            "code":1,
            "errorCode":6,
            "errorMessage":"Missing mandatory element."
        },
        "data":null
    }';

    $bakonglog->bakonglog('','authenticate',json_encode($data),'Request log data',$folderLog);
    $bakonglog->bakonglog('','authenticate',$JSON,'Response log data',$folderLog);
    echo $JSON;
}else{
    $bakonglog->bakonglog($data->login,'authenticate',json_encode($data),'Request log data',$folderLog);

    if($data->loginType===$loginType){
        // check the valid customer on MB
        $data_encryption=dataEncryption($data->login,$data->key,$bankCode,$clientCretKey,$encryptor,$username,$password);
        $authenticate=authenticate_user($data->login,$userAuthenticator,$data_encryption,$ip,$username,$password,$folderLog);

        $authenticate=json_decode($authenticate);
        $status=$authenticate->respCode;

        switch($status){
            case '00':{

                $cmd_inquiry=$db->authentication_inquiry($data->loginType,$data->login,$data->key,$tblInit);
                if($cmd_inquiry->rowCount()){

                    $row = $cmd_inquiry->fetch(PDO::FETCH_ASSOC);
                    $bakongAccId=$row['bakongAccId'];

                    $header = json_encode([
                        'typ' => 'JWT',
                        'alg' => 'sha256'
                    ]);
                    $timezone = new DateTime("now", new DateTimeZone('Asia/Bangkok'));
                    $timezone = date("Y-m-d H:i:s");
                    $date_6months = strtotime("$timezone ,$expired_date");
            
                    $expire_claim = $date_6months; 
                    $payload = json_encode([
                        'sub' => $bakongAccId,
                        'auth' => ['can_get_balance','can_top_up'],
                        'exp' => $expire_claim
                    ]);
            
                    $secret = bin2hex(random_bytes(32)); 
                    $base64UrlHeader = base64_encode($header);
                    $base64UrlPayload = base64_encode($payload);
                    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
                    $base64UrlSignature = base64_encode($signature);
                    $jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
            
                    $date = new DateTime("now", new DateTimeZone('Asia/Bangkok'));
                    $created_Date=$date->format('Y-m-d H:i:s');
                    
                    $cmd_update=$db->authentication_update($jwt,$secret,$expire_claim,$data->loginType,$data->login,$data->key,$tblInit);
                    if($cmd_update->rowCount()){
                        $JSON='{
                            "status":{
                                "code":0,
                                "errorCode":null,
                                "errorMessage":null
                            },
                            "data":{
                                "accessToken":"'.$jwt.'",
                                "requireChangePassword":false
                            }
                        }';

                        $bakonglog->bakonglog($data->login,'authenticate',$JSON,'Response log data',$folderLog);
                        echo $JSON;
                    }else{
                        $JSON='{
                            "status":{
                                "code":1,
                                "errorCode":1,
                                "errorMessage":"Internal server error."
                            },
                            "data":null
                        }';
                        
                        $bakonglog->bakonglog($data->login,'authenticate',$JSON,'Response log data',$folderLog);
                        echo $JSON;
                    }
                }else{
                    $JSON='{
                        "status":{
                            "code":1,
                            "errorCode":42,
                            "errorMessage":"Authentication error.Please try again."
                        },
                        "data":null
                    }';

                    $bakonglog->bakonglog($data->login,'authenticate',$JSON,'Response log data',$folderLog);
                    echo $JSON;
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

                $bakonglog->bakonglog($data->login,'authenticate',$JSON,'Response log data',$folderLog);
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
                
                $bakonglog->bakonglog($data->login,'authenticate',$JSON,'Response log data',$folderLog);
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

        $bakonglog->bakonglog($data->login,'authenticate',$JSON,'Response log data',$folderLog);
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

?>