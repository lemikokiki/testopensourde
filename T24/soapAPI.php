<?php
class SoapAPI{
    
    function getAccountDetails($cif,$account,$phone,$folder){
        $timezone = new DateTime("now", new DateTimeZone('Asia/Bangkok'));
        $datetime=$timezone -> format("YmdHis");
        
        $xmlString='<MSG>
            <HEADER>
                <MSGCODE>1000</MSGCODE>
                <SOURCEID>MOBILE</SOURCEID>
                <REQUESTID>123123</REQUESTID>
                <TRANSDATE>'.$datetime.'</TRANSDATE>
            </HEADER>
            <DATA>
                <CIF>'.$cif.'</CIF>
                <ACCOUNT>'.$account.'</ACCOUNT>
                <PHONE>'.$phone.'</PHONE>
            </DATA>
        </MSG>';

        try{
            $opts=array(
                'http'=>array(
                    'user_agent'=>'PHPSoapClient'
                )
            );
            $context=stream_context_create($opts);
            $wsdlUrl='Call_BIDCGatewayT24.wsdl';
            $soapClientOptions = array(
                'stream_context' => $context,
                'cache_wsdl' => WSDL_CACHE_NONE
            );
            $client = new SoapClient($wsdlUrl, $soapClientOptions);

            $log=new Log();
            $log->writelog($phone,'Get account details',$xmlString,'Request log data:',$folder);

            $result = $client->doProcess($xmlString);
            $log->writelog($phone,'Get account details',$result,'Response log data:',$folder);

            return $result;
        }catch(Exception $e){
            $log=new Log();
            $log->writelog($phone,'Get account details',$xmlString,'Request log data:',$folder);
            $log->writelog($phone,'Get account details',$e->getMessage(),'Response log data:',$folder);
            return null;
        }
    }

    function getAccountTransactions($account,$size,$phone,$folder){
        $timezone = new DateTime("now", new DateTimeZone('Asia/Bangkok'));
        $datetime=$timezone -> format("YmdHis");

        $xmlString='<MSG>
                <HEADER>
                    <MSGCODE>1001</MSGCODE>
                    <SOURCEID>MOBILE</SOURCEID>
                    <REQUESTID>123123</REQUESTID>
                    <TRANSDATE>'.$datetime.'</TRANSDATE>
                </HEADER>
                <DATA>
                    <ACCOUNT>'.$account.'</ACCOUNT>
                    <SIZE>'.$size.'</SIZE>
                </DATA>
            </MSG>';

        try{
            $opts=array(
                'http'=>array(
                    'user_agent'=>'PHPSoapClient'
                )
            );
            $context=stream_context_create($opts);
            $wsdlUrl='Call_BIDCGatewayT24.wsdl';
            $soapClientOptions = array(
                'stream_context' => $context,
                'cache_wsdl' => WSDL_CACHE_NONE
            );
            $client = new SoapClient($wsdlUrl, $soapClientOptions);

            $log=new Log();
            $log->writelog($phone,'Get transaction history',$xmlString,'Request log data:',$folder);

            $result = $client->doProcess($xmlString);
            $log->writelog($phone,'Get transaction history',$result,'Response log data:',$folder);

            return $result;
        }catch(Exception $e){
            $log=new Log();
            $log->writelog($phone,'Get transaction history',$xmlString,'Request log data:',$folder);
            $log->writelog($phone,'Get transaction history',$e->getMessage(),'Response log data:',$folder);
            return null;
        }
    }

    function doTransaction($sourceAcc,$destinationAcc,$NBCAccount,$amount,$ccy,$desc,$fee,$phone,$folder,$prefixTrxRef){
        $timezone = new DateTime("now", new DateTimeZone('Asia/Bangkok'));
        $datetime=$timezone -> format("YmdHis");
        $trxref=$prefixTrxRef.$datetime;
        
        $xmlString='<MSG>
                <HEADER>
                    <MSGCODE>1002</MSGCODE>
                    <SOURCEID>MOBILE</SOURCEID>
                    <REQUESTID>123123</REQUESTID>
                    <TRANSDATE>'.$datetime.'</TRANSDATE>
                </HEADER>
                <DATA>
                    <COCODE>KH0010001</COCODE>
                    <DEBITACCTNO>'.$sourceAcc.'</DEBITACCTNO>
                    <DEBITCURRENCY>'.$ccy.'</DEBITCURRENCY>
                    <DEBITAMOUNT>'.$amount.'</DEBITAMOUNT>
                    <CREDITACCTNO>'.$NBCAccount.'</CREDITACCTNO>
                    <CREDITCURRENCY>'.$ccy.'</CREDITCURRENCY>
                    <TRANSFERINFO>'.$desc.'</TRANSFERINFO>
                    <WALLET>'.str_replace('_','',$destinationAcc).'</WALLET>
                    <FEEAMT>'.$ccy.' '.$fee.'</FEEAMT>
                    <TXNREFERENCECODE>'.$trxref.'</TXNREFERENCECODE>
                </DATA>
            </MSG>';

        try{
            $opts=array(
                'http'=>array(
                    'user_agent'=>'PHPSoapClient'
                )
            );
            $context=stream_context_create($opts);
            $wsdlUrl='Call_BIDCGatewayT24.wsdl';
            $soapClientOptions = array(
                'stream_context' => $context,
                'cache_wsdl' => WSDL_CACHE_NONE
            );
            $client = new SoapClient($wsdlUrl, $soapClientOptions);

            $log=new Log();
            $log->writelog($phone,'Do transaction from CASA to bakong WALLET',$xmlString,'Request log data:',$folder);

            $result = $client->doProcess($xmlString);
            $log->writelog($phone,'Do transaction from CASA to bakong WALLET',$result,'Response log data:',$folder);

            return $result;
        }catch(Exception $e){
            $log=new Log();
            $log->writelog($phone,'Do transaction from CASA to bakong WALLET',$xmlString,'Request log data:',$folder);
            $log->writelog($phone,'Do transaction from CASA to bakong WALLET',$e->getMessage(),'Response log data:',$folder);
            return 'NULL';
        }
    }

}

?>