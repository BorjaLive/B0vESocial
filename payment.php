<?php
    include "func/conn.php";
    use PayPal\Api\Amount;
    use PayPal\Api\Details;
    use PayPal\Api\Payment;
    use PayPal\Api\PaymentExecution;
    use PayPal\Api\Transaction;
    use \PayPal\Rest\ApiContext;
    use \PayPal\Auth\OAuthTokenCredential;

    $status = "";
    $msg = "";
    try{
        if (isset($_GET['success']) && $_GET['success'] == 'true') {
            $apiContext = getPaypalContext();
    
            $paymentId = $_GET['paymentId'];
            $payment = Payment::get($paymentId, $apiContext);
    
            $execution = new PaymentExecution();
            $execution->setPayerId($_GET['PayerID']);
    
            $result = $payment->execute($execution, $apiContext);
            $payment = Payment::get($paymentId, $apiContext);

            if($payment->state != "approved") throw new Exception("Pago no aprobado");
            foreach($payment->transactions[0]->item_list->items as $item){
                switch($item->sku){
                    case PRODUCTO_100MONEDAS:
                        ejecutarComprarModenas($payment->transactions[0]->custom, $item->quantity, $payment->id);
                        $msg = $item->quantity;
                    break;
                    default:
                        throw new Exception("Codigo de producto no admitido");
                }
            }

            $status = "success";
        }else{
            throw new Exception("Operación no completada.");
        }
    }catch(Exception $e){
        $status = "error";
        $msg = $e;
    }

    header("Location: ".EXTERNAL_URL."store.html?status=$status&msg=$msg");
    exit;
?>
<html>
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>B0vE Social</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
            integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous" />
    </head>
    <body>
        <div id="globalWaiting" style="position: fixed;top:0;left:0;width: 100%;height: 100%;background-color: #64B5F680;z-index: 1000000;" class="d-flex flex-column justify-content-center align-items-center visually-hidden">
            <h1></h1>
            <div class="spinner-border text-primary" style="width: 5rem;height: 5rem;" role="status"></div>
        </div>
    </body>
</html>