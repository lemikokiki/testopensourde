<?php
include '../T24/soapAPI.php';
require '../config/config.php';
include '../file/filelog.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("App-Name: BAKONG");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");


$soap=new SoapAPI();
echo $soap->getAccountDetails('137281','703700283907','0969647064',$folderLog);

// $timezone = new DateTime("now", new DateTimeZone('Asia/Bangkok'));
// $datetime=$timezone -> format("YmdHis");
// echo $datetime;