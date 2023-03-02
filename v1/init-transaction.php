<?php
require '../config/config.php';
include '../config/database.php';
include '../file/filelog.php';
include '../limit/fee-calculation.php';
include '../helper/dbconnection.php';
include '../T24/soapAPI.php';

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

$db=new DBConnection();
$bakonglog=new Log();
$transactionlog=new Log();
$data=json_decode(file_get_contents("php://input"));

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

if(isset($jwt)){
    $cmd_token=$db->getToken_withLinkacc($jwt,$tblInit,$tblLinkAcc);
    if($cmd_token->rowCount()){
        $row=$cmd_token->fetch(PDO::FETCH_ASSOC);
        $bakonglog->bakonglog($row['loginPhoneNumber'],'init-transaction',json_encode($data),'Request log data',$folderLog);

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

            $bakonglog->bakonglog($row['loginPhoneNumber'],'init-transaction',$JSON,'Response log data',$folderLog);
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

                $bakonglog->bakonglog($row['loginPhoneNumber'],'init-transaction',$JSON,'Response log data',$folderLog);
                echo $JSON;
            }else{
                if(empty($data->type) or empty($data->sourceAcc) or empty($data->destinationAcc) or empty($data->amount) or empty($data->ccy)){
                    $JSON='{
                        "status":{
                            "code":1,
                            "errorCode":6,
                            "errorMessage":"Missing mandatory element."             
                        },
                        "data":null
                    }';
                
                    $bakonglog->bakonglog($row['loginPhoneNumber'],'init-transaction',$JSON,'Response log data',$folderLog);
                    echo $JSON;
                }else{
                    if($data->type===$transactionType){
                        if($row['account'] == $data->sourceAcc and $row['bakongAccId']==$data->destinationAcc){
                            
                            $cif=$row['cif'];
                            $phone=$row['loginPhoneNumber'];
                            $account=$data->sourceAcc;

                            $soap=new SoapAPI();
                            $accDetails=$soap->getAccountDetails($cif,$account,$phone,$folderLog);

                            if($accDetails !== null){
                                $accDetails = simplexml_load_string($accDetails);
                                $status = $accDetails->HEADER->ERRORCODE;

                                if($status == '00'){
                                    $accStatus=$accDetails->DATA->STATUS;
                                    if($accStatus == 'ACTIVE'){
                                        $kycStautus=$accDetails->DATA->KYC;
                                        if($kycStautus =='FULL'){
                                            
                                            $ccy=$accDetails->DATA->CURRENCY;
                                            if($data->ccy == $ccy){
                                                $str_result = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
                                                $refNumber= substr(str_shuffle($str_result),0, 32);
                                    
                                                $fee_computing = new FeeCalculation();
                                                $fee=$fee_computing->calculate($data->amount,$data->ccy);
                                                if($fee!='AMOUNTOVERLOADING'){
    
                                                    $total_amount=$data->amount + $fee;
                                                    $avialable_amount=$accDetails->DATA->BALANCE;
                                                    if($total_amount <= $avialable_amount){
                                        
                                                        $cmd_addtrx=$db->insert_intit_transaction($refNumber,$data->type,$data->sourceAcc,$data->destinationAcc,$data->amount,$data->ccy,$fee,$data->desc,$tblTrx);
                                                        if($cmd_addtrx->rowCount()){
    
                                                            $transactionlog->transactionlog($row['loginPhoneNumber'],$data->sourceAcc,$data->destinationAcc,$data->amount,$data->ccy,$fee,$data->desc,'Pending','','init-transaction',$folderLog);
                                                            $encrytp_initotp=init_otp_encryption($encryptor,$phone,$chanel,$bankCode,$smsformat,$expireOTP,$clientCretKey,$username,$password);
                                                            $anOTP=sendOPT($phone,$initOTP,$encrytp_initotp,$ip,$username,$password,$folderLog);
    
                                                            $JSON='{
                                                                "status":{
                                                                    "code":0,
                                                                    "errorCode":null,
                                                                    "errorMessage":null
                                                                },
                                                                "data":{
                                                                    "initRefNumber":"'.$refNumber.'",
                                                                    "debitAmount":'.$data->amount.',
                                                                    "debitCcy":"'.$data->ccy.'",
                                                                    "fee":'.$fee.',
                                                                    "requireOtp":true
                                                                }
                                                            }';
    
                                                            $bakonglog->bakonglog($row['loginPhoneNumber'],'init-transaction',$JSON,'Response log data',$folderLog);
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
    
                                                            $bakonglog->bakonglog($row['loginPhoneNumber'],'init-transaction',$JSON,'Response log data',$folderLog);
                                                            echo $JSON;
                                                        }
                                                    }else{
                                                        $JSON='{
                                                            "status":{
                                                                "code":1,
                                                                "errorCode":1,
                                                                "errorMessage":"Not enough balance to do a transaction."
                                                            },
                                                            "data":null
                                                        }';
                                                        
                                                        $bakonglog->bakonglog($row['loginPhoneNumber'],'init-transaction',$JSON,'Response log data',$folderLog);
                                                        echo $JSON;
                                                    }
                                                }else{
                                                    $JSON='{
                                                        "status":{
                                                            "code":1,
                                                            "errorCode":11,
                                                            "errorMessage":"Transaction failed as the amount entered exceeds the allowed limit. Please enter a lower amount and try again or reach out to the merchant for further assistance."
                                                        },
                                                        "data":null
                                                    }';
                                                    
                                                    $bakonglog->bakonglog($row['loginPhoneNumber'],'init-transaction',$JSON,'Response log data',$folderLog);
                                                    echo $JSON;
                                                }
                                            }
                                        }else{
                                            $JSON='{
                                                "status":{
                                                    "code":1,
                                                    "errorCode":14,
                                                    "errorMessage":"Cannot link account due to your account not yet verified."
                                                },
                                                "data":null
                                            }';
        
                                            $bakonglog->bakonglog($row['loginPhoneNumber'],'finish-link-account',$JSON,'Response log data',$folderLog);
                                            echo $JSON;
                                        }
                                    }else{
                                        $JSON='{
                                            "status":{
                                                "code":1,
                                                "errorCode":2,
                                                "errorMessage":"Account is deactivated."
                                            },
                                            "data":null
                                        }';
        
                                        $bakonglog->bakonglog($row['loginPhoneNumber'],'finish-link-account',$JSON,'Response log data',$folderLog);
                                        echo $JSON;
                                    }
                                }else{
                                    $JSON='{
                                        "status":{
                                            "code":1,
                                            "errorCode":3,
                                            "errorMessage":"No account found."
                                        },
                                        "data":null
                                    }';
    
                                    $bakonglog->bakonglog($row['loginPhoneNumber'],'init-transaction',$JSON,'Response log data',$folderLog);
                                    echo $JSON;
                                }
                            }else{
                                $JSON='{
                                    "status":{
                                        "code":1,
                                        "errorCode":1,
                                        "errorMessage":"Internal server error."
                                    },
                                    "data":null
                                }';
                                
                                $bakonglog->bakonglog($row['loginPhoneNumber'],'init-transaction',$JSON,'Response log data',$folderLog);
                                echo $JSON;
                            }
                        }else{
                            $JSON='{
                                "status":{
                                    "code":1,
                                    "errorCode":9,
                                    "errorMessage":"Transaction to unavailable account."
                                },
                                "data":null
                            }';

                            $bakonglog->bakonglog($row['loginPhoneNumber'],'init-transaction',$JSON,'Response log data',$folderLog);
                            echo $JSON;
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

        $bakonglog->bakonglog('','init-transaction',json_encode($data),'Request log data',$folderLog);
        $bakonglog->bakonglog('','init-transaction',$JSON,'Response log data',$folderLog);
        echo $JSON;
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

    $bakonglog->bakonglog('','init-transaction',json_encode($data),'Request log data',$folderLog);
    $bakonglog->bakonglog('','init-transaction',$JSON,'Response log data',$folderLog);
    echo $JSON;
} 
}

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

function GUID()
{
    if (function_exists('com_create_guid') === true)
    {
        return strtolower(trim(com_create_guid(), '{}'));
    }
    return strtolower(sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', 
    mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535)));
}

?>