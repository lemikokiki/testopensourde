<?php

class DBConnection{
    
    function initLinkAcct($data,$expire_claim,$cif,$jwt,$secret,$tblInit){
        $databaseService = new DatabaseService();
        $conn = $databaseService->getConnection();

        $date = new DateTime("now", new DateTimeZone('Asia/Bangkok'));
        $created_Date=$date->format('Y-m-d H:i:s');    
        $query_init="INSERT INTO `$tblInit` (`token`, `key_num`, `loginType`, `loginPhoneNumber`, `key`, `bakongAccId`, `phoneNumber`, `created_Date`, `expired_Date`,`cif`) 
                    VALUE ('$jwt','$secret','$data->loginType','$data->login','$data->key','$data->bakongAccId','$data->phoneNumber','$created_Date','$expire_claim','$cif')";

        $cmd_init=$conn->prepare($query_init);
        $cmd_init->execute();

        return $cmd_init;
    }

    function initInquiry($bakongAccId,$login,$tblInit){
        $databaseService = new DatabaseService();
        $conn = $databaseService->getConnection();

        $query_inquiry="SELECT * FROM `$tblInit` WHERE `bakongAccId`='$bakongAccId' OR `loginPhoneNumber`='$login' ";
        $cmd_inquiry=$conn->prepare($query_inquiry);
        $cmd_inquiry->execute();

        return $cmd_inquiry;
    }

    function insertfinishlink($accNumber,$jwt,$cif,$tblLinkAcc){
        $databaseService = new DatabaseService();
        $conn = $databaseService->getConnection();

        $query_finish="INSERT INTO `$tblLinkAcc` (`account`, `token`,`cif`) VALUES ('$accNumber','$jwt','$cif' ) ";
        $cmd_finish=$conn->prepare($query_finish);
        $cmd_finish->execute();

        return $cmd_finish;
    }

    function authentication_inquiry($loginType,$login,$key,$tblInit){
        $databaseService = new DatabaseService();
        $conn = $databaseService->getConnection();

        $query_inquiry="SELECT * FROM `$tblInit` WHERE `loginType`='$loginType' AND `loginPhoneNumber`='$login' AND `key`='$key'";
        $cmd_inquiry=$conn->prepare($query_inquiry);
        $cmd_inquiry->execute();

        return $cmd_inquiry;
    }

    function authentication_update($jwt,$secret,$expire_claim,$loginType,$login,$key,$tblInit){
        $databaseService = new DatabaseService();
        $conn = $databaseService->getConnection();

        $query_update="UPDATE `$tblInit` SET `token`='$jwt', `key_num`='$secret' , `expired_Date`='$expire_claim' 
                    WHERE `loginType`='$loginType' AND `loginPhoneNumber`='$login' AND `key`='$key' " ;
        $cmd_update=$conn->prepare($query_update);
        $cmd_update->execute();

        return $cmd_update;
    }

    function insert_intit_transaction($refNumber,$type,$sourceAcc,$destinationAcc,$amount,$ccy,$fee,$desc,$tblTrx){
        $databaseService = new DatabaseService();
        $conn = $databaseService->getConnection();

        $date = new DateTime("now", new DateTimeZone('Asia/Bangkok'));
        $transDate=$date->format('Y-m-d H:i:s');
        $transDateStamp=strtotime($transDate,0);

        $query_trx="INSERT INTO `$tblTrx` (`refNumber` , `transType`, `fromAcc`, `toAcc`, `amount`, `currency`, `fee` , `dbtCdt`, `desc`, `status`, `transDate`) 
                    VALUES ('$refNumber','$type','$sourceAcc','$destinationAcc','$amount','$ccy','$fee','Debit','$desc','PENDING','$transDateStamp')";
        $cmd_trx=$conn->prepare($query_trx);
        $cmd_trx->execute();

        return $cmd_trx;
    }

    function inquiry_transaction($initRefNumber,$tblTrx){
        $databaseService = new DatabaseService();
        $conn = $databaseService->getConnection();

        $query_acc="SELECT * FROM `$tblTrx` WHERE `refNumber`='$initRefNumber'";
        $cmd_acc=$conn->prepare($query_acc);
        $cmd_acc->execute();

        return $cmd_acc;
    }

    function update_finish_transaction($initRefNumber,$transId,$transHash,$tblTrx){
        $databaseService = new DatabaseService();
        $conn = $databaseService->getConnection();

        $query_finTrx="UPDATE `$tblTrx` SET `transId`= '$transId' , `transHash`= '$transHash' , `status`='SUCCESS' WHERE `refNumber`='$initRefNumber' ";
        $cmd_finTrx=$conn->prepare($query_finTrx);
        $cmd_finTrx->execute();
    }

    function getToken_withLinkacc($jwt,$tblInit,$tblLinkAcc){
        $databaseService = new DatabaseService();
        $conn = $databaseService->getConnection();

        $query_token="SELECT * FROM `$tblInit` INNER JOIN `$tblLinkAcc` ON `$tblInit`.`token`=`$tblLinkAcc`.`token` AND `$tblLinkAcc`.`token`='$jwt'";
        $cmd_token=$conn->prepare($query_token);
        $cmd_token->execute();

        return $cmd_token;
    }

    function getToken_withoutLinkacc($jwt,$tblInit){
        $databaseService = new DatabaseService();
        $conn = $databaseService->getConnection();
    
        $query_token="SELECT * FROM `$tblInit` WHERE `token`='$jwt'";
        $cmd_token=$conn->prepare($query_token);
        $cmd_token->execute();

        return $cmd_token;
    }
}
?>