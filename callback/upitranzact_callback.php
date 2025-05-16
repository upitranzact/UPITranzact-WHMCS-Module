<?php

require("../../../init.php");
include("../../../includes/functions.php");
include("../../../includes/gatewayfunctions.php");
include("../../../includes/invoicefunctions.php");
use WHMCS\Database\Capsule;

$gatewayModuleName = 'upitranzact';
$gatewayParams = getGatewayVariables($gatewayModuleName);

if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

$orderId = filter_input(INPUT_GET, 'order_id', FILTER_SANITIZE_STRING);
$invoiceId = filter_input(INPUT_GET, 'invoice_id', FILTER_VALIDATE_INT);

if (!$orderId || !$invoiceId) {
    die("Invalid Order ID or Invoice ID.");
}

$publicKey = $gatewayParams['public_key'];
$secretKey = $gatewayParams['secret_key'];
$merchantId = $gatewayParams['merchant_id'];
$apiUrl = $gatewayParams['api_url'] ?? 'https://api.upitranzact.com/v1/payments/checkPaymentStatus';

if (!$publicKey || !$secretKey || !$merchantId) {
    die("Missing API credentials.");
}

$headers = [
    'Authorization: Basic ' . base64_encode($publicKey . ':' . $secretKey),
    'Content-Type: application/json'
];

$postData = json_encode([
    'mid' => $merchantId,
    'order_id' => $orderId,
]);

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

$response = curl_exec($ch);
curl_close($ch);

$responseData = json_decode($response, true);

if (!isset($responseData['status']) || !$responseData['status']) {
    die("Unknown response!");
}

if ((string)$orderId === (string)$responseData['data']['order_id'] && $responseData['txnStatus'] === "SUCCESS") {
    
    $amount = $responseData['data']['amount'];
    $txnId = $responseData['data']['order_id'];
    
    $invoice = Capsule::table('tblinvoices')->find($invoiceId);
    
    if ($invoice->status === 'Paid') {
        logTransaction("UPITranzact", ['orderId' => $orderId], "Invoice already marked as paid");
        die("Invoice already marked as paid.");
    }
        
    if ($invoice) {
        addInvoicePayment($invoice->id, $txnId, $amount, 0, 'upitranzact');
        logTransaction("UPITranzact", $responseData, "Successful");
    } else {
        logTransaction("UPITranzact", ['orderId' => $orderId], "Invoice not found");
    }
} else {
    logTransaction("UPITranzact", $responseData, "Failed");
}

header("Location: " . $gatewayParams['systemurl'] . "viewinvoice.php?id=" . $invoiceId);
exit;

?>
