<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function upitranzact_MetaData()
{
    return [
        'DisplayName' => 'UPITranzact',
        'APIVersion' => '2.0',
    ];
}

function upitranzact_config()
{
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'UPITranzact',
        ],
        'merchant_id' => [
            'FriendlyName' => 'Merchant ID',
            'Type' => 'text',
            'Size' => '40',
            'Description' => 'Enter your merchant ID here',
        ],
        'public_key' => [
            'FriendlyName' => 'Public Key',
            'Type' => 'text',
            'Size' => '40',
            'Description' => 'Enter your public key here',
        ],
        'secret_key' => [
            'FriendlyName' => 'Secret Key',
            'Type' => 'password',
            'Size' => '40',
            'Description' => 'Enter your secret key here',
        ],
    ];
}

function upitranzact_link($params)
{
    $orderId = "utz_" . uniqid();
    $invoiceId = $params['invoiceid'];
    $redirect_url = $params['systemurl'] . 'modules/gateways/callback/upitranzact_callback.php?order_id=' . $orderId . '&invoice_id=' . $invoiceId;
    $amount = $params['amount'];
    $currency = $params['currency'];
    $merchantId = $params['merchant_id'];
    $publicKey = $params['public_key'];
    $secretKey = $params['secret_key'];
    $customerName = $params['clientdetails']['firstname'] . ' ' . $params['clientdetails']['lastname'];
    $customerEmail = $params['clientdetails']['email'];
    $customerMobile = $params['clientdetails']['phonenumber'];
    $note = "Payment for Invoice #" . $invoiceId;
    
    $authHeader = base64_encode($publicKey . ":" . $secretKey);
    
    $postData = [
        'mid' => $merchantId,
        'amount' => $amount,
        'order_id' => $orderId,
        'redirect_url' => $redirect_url,
        'note' => $note,
        'customer_name' => $customerName,
        'customer_email' => $customerEmail,
        'customer_mobile' => $customerMobile,
    ];

    $ch = curl_init('https://api.upitranzact.com/v1/payments/createOrderRequest');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . $authHeader,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $responseData = json_decode($response, true);

    if (isset($responseData['status']) && $responseData['status']) {
        return '<form method="get" action="' . htmlspecialchars($responseData['data']['payment_url']) . '">
                    <input type="submit" value="Pay with UPITranzact" />
                </form>';
    } else {
        return '<div>Error: ' . htmlspecialchars($responseData['msg'] ?? 'Unknown error') . '</div>';
    }
}

?>
