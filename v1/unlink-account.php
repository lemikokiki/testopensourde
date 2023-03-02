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

$data=json_decode(file_get_contents("php://input"));
$db=new DBConnection();
$bakonglog=new Log();

if(isset($jwt)){
    $cmd_token=$db->getToken_withoutLinkacc($jwt,$tblInit);
    if($cmd_token->rowCount()){
        $row = $cmd_token->fetch(PDO::FETCH_ASSOC);

        $bakonglog->bakonglog($row['loginPhoneNumber'],'unlink-account',json_encode($data),'Request log data',$folderLog);

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

            $bakonglog->bakonglog($row['loginPhoneNumber'],'unlink-account',$JSON,'Response log data',$folderLog);
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

                $bakonglog->bakonglog($row['loginPhoneNumber'],'unlink-account',$JSON,'Response log data',$folderLog);
                echo $JSON;
            }else{
                if(empty($data->accNumber)){
                    $JSON='{
                        "status":{
                            "code":1,
                            "errorCode":6,
                            "errorMessage":"Missing mandatory element."             
                        },
                        "data":null
                    }';

                    $bakonglog->bakonglog($row['loginPhoneNumber'],'unlink-account',$JSON,'Response log data',$folderLog);
                    echo $JSON;
                }else{
                    $databaseService = new DatabaseService();
                    $conn = $databaseService->getConnection();

                    $date = new DateTime("now", new DateTimeZone('Asia/Bangkok'));
                    $unlink_Date=$date->format('Y-m-d H:i:s');
                    $query_transfer="INSERT INTO `$tblUnlink` (`loginPhoneNumber`, `key`, `bakongAccId`, `phoneNumber`, `accNumber`, `created_Date`, `unlink_Date`,`cif`) 
                                    (SELECT `$tblInit`.`loginPhoneNumber`, `$tblInit`.`key`, `$tblInit`.`bakongAccId`, `$tblInit`.`phoneNumber`, `$tblLinkAcc`.`account`,
                                    `$tblInit`.`created_Date`,'$unlink_Date', `$tblInit`.`cif`
                                    FROM `$tblInit` INNER JOIN `$tblLinkAcc` ON `$tblInit`.`token`=`$tblLinkAcc`.`token` AND `$tblLinkAcc`.`account`='$data->accNumber')";

                    $cmd_transfer=$conn->prepare($query_transfer);
                    $cmd_transfer->execute();

                    if($cmd_transfer->rowCount()){
                        
                        $query_unlink="DELETE FROM `$tblLinkAcc` WHERE `token`='$jwt' AND `account`='$data->accNumber'";
                        $cmd_unlink=$conn->prepare($query_unlink);
                        $cmd_unlink->execute();

                        if($cmd_unlink->rowCount()){

                            $query_countlink="SELECT * FROM `$tblLinkAcc` WHERE `token`='$jwt'";
                            $cmd_countlink=$conn->prepare($query_countlink);
                            $cmd_countlink->execute();

                            if($cmd_countlink->rowCount()>1){
                            }else{
                                $query_uninit="DELETE FROM `$tblInit` WHERE `token`='$jwt'";
                                $cmd_uninit=$conn->prepare($query_uninit);
                                $cmd_uninit->execute();
                            }

                            $JSON='{
                                "status":{
                                    "code":0,
                                    "errorCode":null,
                                    "errorMessage":null
                                },
                                "data":null
                            }';

                            $bakonglog->bakonglog($row['loginPhoneNumber'],'unlink-account',$JSON,'Response log data',$folderLog);
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

                            $bakonglog->bakonglog($row['loginPhoneNumber'],'unlink-account',$JSON,'Response log data',$folderLog);
                            echo $JSON;
                        }
                    }else {
                        $JSON='{
                            "status":{
                                "code":1,
                                "errorCode":3,
                                "errorMessage":"No account found."
                            },
                            "data":null
                        }';

                        $bakonglog->bakonglog($row['loginPhoneNumber'],'unlink-account',$JSON,'Response log data',$folderLog);
                        echo $JSON;
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

        $bakonglog->bakonglog('','unlink-account',json_encode($data),'Request log data',$folderLog);
        $bakonglog->bakonglog('','unlink-account',$JSON,'Response log data',$folderLog);
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

    $bakonglog->bakonglog('','unlink-account',json_encode($data),'Request log data',$folderLog);
    $bakonglog->bakonglog('','unlink-account',$JSON,'Response log data',$folderLog);
    echo $JSON;
}
}

?>