<?php

class Log{
    function writelog($login,$functionName,$object,$methodType,$folder){
        $date = new DateTime("now", new DateTimeZone('Asia/Bangkok'));
        $timezone = new DateTime("now", new DateTimeZone('Asia/Bangkok'));
        $timezone = date("Y-m-d");
        $dateTime=$date->format('Y-m-d H:i:s');
    
        $filename=$folder.'\BIDC-Gateway.log.'.$timezone;
        $myfile = fopen($filename, "a+") or die("Unable to open file!");
    
        fwrite($myfile,sprintf("%-10s",'[ INFO]'));
        fwrite($myfile,$dateTime);
        fwrite($myfile,' - ');
        fwrite($myfile,sprintf("%8s",'user['.$login.']'));
        fwrite($myfile,' - ');
        fwrite($myfile,'['.sprintf("%30s",$functionName).']   ');
        fwrite($myfile,sprintf("%-20s",$methodType));
        fwrite($myfile,$object);
        fwrite($myfile,"\n");
        fwrite($myfile,"\n");
    
        fclose($myfile);
    }

    function bakonglog($login,$functionName,$object,$methodType,$folder){
        $date = new DateTime("now", new DateTimeZone('Asia/Bangkok'));
        $timezone = new DateTime("now", new DateTimeZone('Asia/Bangkok'));
        $timezone = date("Y-m-d");
        $dateTime=$date->format('Y-m-d H:i:s');

        $filename=$folder.'\Bakong-Link-Acc.log.'.$timezone;
        $myfile = fopen($filename, "a+") or die("Unable to open file!");

        fwrite($myfile,sprintf("%-10s",'[ INFO]'));
        fwrite($myfile,$dateTime);
        fwrite($myfile,' - ');
        fwrite($myfile,sprintf("%8s",'user['.$login.']'));
        fwrite($myfile,' - ');
        fwrite($myfile,'['.sprintf("%30s",$functionName).']   ');
        fwrite($myfile,sprintf("%-20s",$methodType));
        fwrite($myfile,$object);
        fwrite($myfile,"\n");
    
        fclose($myfile);
    }

    function transactionlog($login,$sourceAcc,$destinationAcc,$amount,$ccy,$fee,$desc,$status,$FT,$mothed,$folder){
        $date = new DateTime("now", new DateTimeZone('Asia/Bangkok'));
        $timezone = new DateTime("now", new DateTimeZone('Asia/Bangkok'));
        $timezone = date("Y-m-d");
        $dateTime=$date->format('Y-m-d H:i:s');

        $object = <<<DATA
        {
            "sourceAcc":"$sourceAcc",
            "destinationAcc":"$destinationAcc",
            "amount":$amount,
            "currency":"$ccy",
            "fee":$fee,
            "description":"$desc",
            "status":"$status",
            "FT":"$FT";
        }
        DATA;

        $filename=$folder.'\BakongLinkAcc-Transaction.log.'.$timezone;
        $myfile = fopen($filename, "a+") or die("Unable to open file!");

        fwrite($myfile,sprintf("%-10s",'[ INFO]'));
        fwrite($myfile,$dateTime);
        fwrite($myfile,' - ');
        fwrite($myfile,sprintf("%8s",'user['.$login.']'));
        fwrite($myfile,' - ');
        fwrite($myfile,'['.$mothed.']');
        fwrite($myfile,sprintf("%10s",''));
        fwrite($myfile,$object);
        fwrite($myfile,"\n");
    
        fclose($myfile);
    }
}
?>