<?php
require '../config/config.php';
include '../config/database.php';
include '../file/filelog.php';
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
        $bakonglog->bakonglog($row['loginPhoneNumber'],'finish-link-account',json_encode($data),'Request log data',$folderLog);

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

            $bakonglog->bakonglog($row['loginPhoneNumber'],'finish-link-account',$JSON,'Response log data',$folderLog);
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

                $bakonglog->bakonglog($row['loginPhoneNumber'],'finish-link-account',$JSON,'Response log data',$folderLog);
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

                    $bakonglog->bakonglog($row['loginPhoneNumber'],'finish-link-account',$JSON,'Response log data',$folderLog);
                    echo $JSON;
                }else{
                    $cif=$row['cif'];
                    $phone=$row['loginPhoneNumber'];

                    $soap=new SoapAPI();
                    $accDetails=$soap->getAccountDetails($cif,$data->accNumber,$phone,$folderLog);

                    if($accDetails !== null){
                        $accDetails = simplexml_load_string($accDetails);
                        $status = $accDetails->HEADER->ERRORCODE;

                        if($status =='00'){
                            $accStatus=$accDetails->DATA->STATUS;
                            if($accStatus == 'ACTIVE'){
                                $kycStautus=$accDetails->DATA->KYC;
                                if($kycStautus == 'FULL'){
                                    
                                    $cmd_finish=$db->insertfinishlink($data->accNumber,$jwt,$cif,$tblLinkAcc);
                                    if($cmd_finish->rowCount()){
                                        $JSON='{
                                            "status":{
                                                "code":0,
                                                "errorCode":null,
                                                "errorMessage":null
                                            },
                                            "data":{
                                                "requireChangePassword":false
                                                }
                                            }';

                                        $bakonglog->bakonglog($row['loginPhoneNumber'],'finish-link-account',$JSON,'Response log data',$folderLog);
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
                        
                        $bakonglog->bakonglog($row['loginPhoneNumber'],'finish-link-account',$JSON,'Response log data',$folderLog);
                        echo $JSON;  
                    }
                }
            }
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

        $bakonglog->bakonglog('','finish-link-account',json_encode($data),'Request log data',$folderLog);
        $bakonglog->bakonglog('','finish-link-account',$JSON,'Response log data',$folderLog);
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

    $bakonglog->bakonglog('','finish-link-account',json_encode($data),'Request log data',$folderLog);
    $bakonglog->bakonglog('','finish-link-account',$JSON,'Response log data',$folderLog);
    echo $JSON;
}
}

// function getAccountDetails($cif,$account,$phone,$t24,$folder){
//     $postdata = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:fast="http://fast.bidc.com/">
//         <soapenv:Header/>
//         <soapenv:Body>
//         <fast:FastService>
//             <!--Optional:-->
//             <inputXML>
//             <![CDATA[
//                 <INSTR>
//                 <TXNREFCODE>110211</TXNREFCODE>
//                 <TYPE>BAKONG_05</TYPE>
//                 <DESCRIPTION>ACCOUNT DETAILS</DESCRIPTION>
//                 <CIF>'.$cif.'</CIF>
//                 <ACCOUNT>'.$account.'</ACCOUNT>
//                 <PHONE>'.$phone.'</PHONE>
//             </INSTR>
//                 ]]>
//             </inputXML>
//         </fast:FastService>
//         </soapenv:Body>
//     </soapenv:Envelope>';
    
//     $opts = array('http' =>
//         array(
//             'method'  => 'POST',
//             'header'  => 'Content-Type: text/xml',
//             'content' => $postdata
//         )
//     );
    
//     $context  = stream_context_create($opts);
//     $result =file_get_contents($t24,false,$context);

//     $opts = array('http' =>
//         array(
//             'method'  => 'POST',
//             'header'  => 'Content-Type: text/xml',
//             'content' => $postdata
//         )
//     );

//     $doc=new DOMDocument();
//     $doc->loadXML($result);
//     $return = $doc->getElementsByTagName('return')->item(0)->nodeValue;

//     $xml = simplexml_load_string($return);
//     $json = json_encode($xml);

//     $log=new Log();
//     $log->writelog($phone,'Get account detail from T24',$postdata,'Request log data:',$folder);
//     $log->writelog($phone,'Get account detail from T24',$json,'Response log data:',$folder);

//     return $json;
// }

?>