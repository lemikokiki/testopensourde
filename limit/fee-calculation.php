<?php
// include '../config/database.php';
// require '../config/config.php';

class FeeCalculation{
    function calculate($amount,$ccy){
        
        $databaseService = new DatabaseService();
        $conn = $databaseService->getConnection();

        $query="SELECT * FROM `tbllimitation` WHERE `minimum`<$amount AND `maximum`>=$amount  AND `currency`='$ccy'";
        $cmd=$conn->prepare($query);
        $cmd->execute();

        if($cmd->rowCount()){
            $row=$cmd->fetch(PDO::FETCH_ASSOC);
            $fee=$row['fee'];

            return $fee;
        }

        return 'AMOUNTOVERLOADING';
    }
}

?>