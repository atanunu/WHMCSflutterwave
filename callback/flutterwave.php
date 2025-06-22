<?php
/**
 * Flutterwave WHMCS Payment Gateway Module - Callback v2.0
 *
 * This Payment Gateway module allows you to integrate Flutterwave payment solutions with the WHMCS platform.
 * For more information, see: https://developer.flutterwave.com/docs
 * @author Atanunu Igbunuroghene <atanunuigbunu@hmjp.com>
 * @copyright Copyright (c) HMJP Limited 2025
 */

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

// Get module name and configuration
$gatewayModuleName = basename(__FILE__, '.php');
$gatewayParams = getGatewayVariables($gatewayModuleName);
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

// Sanitize and validate input from GET parameters
$invoiceId = isset($_GET["tx_ref"]) ? intval($_GET["tx_ref"]) : 0;
$trxStatus = isset($_GET["status"]) ? preg_replace('/[^a-zA-Z]/', '', $_GET["status"]) : '';
$transactionId = isset($_GET["transaction_id"]) ? preg_replace('/[^A-Za-z0-9\-]/', '', $_GET["transaction_id"]) : '';
$secretKey = $gatewayParams['secretKey'];
$systemUrl = filter_var($gatewayParams['systemurl'], FILTER_SANITIZE_URL);

// Ensure $systemUrl always uses HTTPS for security
if (strpos($systemUrl, 'http://') === 0) {
    $systemUrl = 'https://' . substr($systemUrl, 7);
}

// Only validate invoice ID for GET/callback/verification, not for POST/cURL flow
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    try {
        $invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['invoiceid']);
    } catch (Exception $e) {
        if ($gatewayParams['gatewayLogs'] == 'on') {
            logTransaction($gatewayModuleName, 'Invoice check failed: ' . $e->getMessage(), 'Callback Error');
        }
        redirectWithMessage($systemUrl . 'clientarea.php?action=invoices', 'Payment error: Invalid or missing invoice. Please contact support.');
    }
}

if (!function_exists('redirectWithMessage')) {
    function redirectWithMessage($url, $message)
    {
        echo '<script type="text/javascript">alert("' . addslashes($message) . '");window.location.href = "' . $url . '";</script>';
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // If cancelled, only require tx_ref and status
    if ($trxStatus === 'cancelled') {
        if (!$invoiceId || empty($trxStatus)) {
            if ($gatewayParams['gatewayLogs'] == 'on') {
                $output = "Missing or invalid callback parameters (cancelled)."
                    . "\r\nInvoice ID: $invoiceId"
                    . "\r\nStatus: $trxStatus";
                logTransaction($gatewayModuleName, $output, "Callback Error");
            }
            redirectWithMessage($systemUrl . 'clientarea.php?action=invoices', 'Payment error: Missing or invalid callback parameters. Please contact support.');
        } else {
            if ($gatewayParams['gatewayLogs'] == 'on') {
                $output = "Transaction cancelled.\r\nInvoice ID: $invoiceId\r\nStatus: $trxStatus";
                logTransaction($gatewayModuleName, $output, "Payment Cancelled");
            }
            // Redirect to invoice page with paymentmsg=cancelled
            $invoice_url = $systemUrl . 'viewinvoice.php?id=' . $invoiceId . '&paymentmsg=cancelled';
            header('Location: ' . $invoice_url);
            exit;
        }
    }
    // For all other statuses, require all params
    if (!$invoiceId || !$transactionId || !$trxStatus) {
        if ($gatewayParams['gatewayLogs'] == 'on') {
            $output = "Missing or invalid callback parameters."
                . "\r\nInvoice ID: $invoiceId"
                . "\r\nTransaction ID: $transactionId"
                . "\r\nStatus: $trxStatus";
            logTransaction($gatewayModuleName, $output, "Callback Error");
        }
        redirectWithMessage($systemUrl . 'clientarea.php?action=invoices', 'Payment error: Missing or invalid callback parameters. Please contact support.');
    }
    if ($invoiceId <= 0 || empty($transactionId) || empty($trxStatus)) {
        if ($gatewayParams['gatewayLogs'] == 'on') {
            $output = "Empty or invalid parameter values."
                . "\r\nInvoice ID: $invoiceId"
                . "\r\nTransaction ID: $transactionId"
                . "\r\nStatus: $trxStatus";
            logTransaction($gatewayModuleName, $output, "Callback Error");
        }
        redirectWithMessage($systemUrl . 'clientarea.php?action=invoices', 'Payment error: Invalid or empty callback parameters. Please contact support.');
    }
}

// --- Handle server-to-server (cURL) payment initiation for redirect flow ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invoiceid'], $_POST['amount'], $_POST['currency'], $_POST['email'])) {
    try {
        $invoiceId = intval($_POST['invoiceid']);
        $amount = floatval($_POST['amount']);
        $currency = preg_replace('/[^A-Z]/', '', $_POST['currency']);
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $phone = isset($_POST['phone']) ? htmlspecialchars($_POST['phone'], ENT_QUOTES, 'UTF-8') : '';
        $firstname = isset($_POST['firstname']) ? htmlspecialchars($_POST['firstname'], ENT_QUOTES, 'UTF-8') : '';
        $lastname = isset($_POST['lastname']) ? htmlspecialchars($_POST['lastname'], ENT_QUOTES, 'UTF-8') : '';
        $description = isset($_POST['description']) ? htmlspecialchars($_POST['description'], ENT_QUOTES, 'UTF-8') : '';
        $tx_ref = (string) $invoiceId;
        $callback_url = $systemUrl . 'modules/gateways/callback/' . $gatewayModuleName . '.php';
        // Get admin-selected payment methods from gateway config (now a comma-separated string)
        $selectedMethods = [];
        if (!empty($gatewayParams['paymentMethods'])) {
            if (is_array($gatewayParams['paymentMethods'])) {
                $selectedMethods = $gatewayParams['paymentMethods'];
            } elseif (is_string($gatewayParams['paymentMethods'])) {
                $selectedMethods = explode(',', $gatewayParams['paymentMethods']);
            }
        }
        $selectedMethods = array_map('trim', $selectedMethods);
        $selectedMethods = array_filter($selectedMethods); // Remove empty
        $paymentOptions = $selectedMethods ? implode(',', $selectedMethods) : 'card';
        $payload = [
            'tx_ref' => $tx_ref,
            'amount' => $amount,
            'currency' => $currency,
            'redirect_url' => $callback_url,
            'payment_options' => $paymentOptions,
            'customer' => [
                'email' => $email,
                'phonenumber' => $phone,
                'name' => trim($firstname . ' ' . $lastname),
            ],
            'customizations' => [
                'title' => $gatewayParams['cBname'],
                'description' => $description,
                'logo' => $gatewayParams['whmcsLogo'],
            ],
            'meta' => [
                'consumer_id' => $invoiceId,
                'consumer_mac' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
            ],
        ];
        $ch = curl_init('https://api.flutterwave.com/v3/payments');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $secretKey,
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        $result = json_decode($response);
        $redirectLink = isset($result->data->link) ? $result->data->link : '';
        $isValidLink = $redirectLink && filter_var($redirectLink, FILTER_VALIDATE_URL) && strpos($redirectLink, 'https://checkout.flutterwave.com/') === 0;
        if ($err || !$result || $result->status !== 'success' || !$isValidLink) {
            $apiMsg = isset($result->message) ? $result->message : 'Unknown error';
            if ($gatewayParams['gatewayLogs'] == 'on') {
                $output = "Flutterwave payment init failed.\r\nInvoice ID: $invoiceId\r\nError: $err\r\nResponse: $response";
                logTransaction($gatewayModuleName, $output, 'Payment Init Error');
            }
            echo '<div style="color:red;font-weight:bold;">Payment error: Unable to initiate payment. ' . htmlspecialchars($apiMsg) . ' Please try again or contact support.</div>';
            exit;
        }
        header('Location: ' . $redirectLink);
        exit;
    } catch (Throwable $e) {
        echo '<div style="color:red;font-weight:bold;">Fatal error in payment handler. Please contact support.</div>';
        exit;
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo '<div style="color:red;font-weight:bold;">Payment error: Required POST data missing. Please contact support.</div>';
    exit;
}

// Verify transaction with Flutterwave API (server-to-server verification)
$curl = curl_init();
$api = "https://api.flutterwave.com/v3/transactions/$transactionId/verify";
curl_setopt($curl, CURLOPT_URL, $api);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_HTTPGET, true);
$headers = [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $secretKey,
];
curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
$response = curl_exec($curl);
if (curl_errno($curl)) {
    $errorMsg = 'cURL error: ' . curl_error($curl);
    if ($gatewayParams['gatewayLogs'] == 'on') {
        logTransaction($gatewayModuleName, $errorMsg, 'cURL Error');
    }
    curl_close($curl);
    redirectWithMessage($systemUrl . 'viewinvoice.php?id=' . $invoiceId, 'Payment verification failed. Please try again or contact support.');
}
$result = json_decode($response);
curl_close($curl);
if ($result && $result->status == 'success') {
    $paymentAmount = $result->data->amount;
    if (!is_numeric($paymentAmount) || floatval($paymentAmount) <= 0) {
        if ($gatewayParams['gatewayLogs'] == 'on') {
            $output = "Invalid payment amount received."
                . "\r\nTransaction ref: $transactionId"
                . "\r\nInvoice ID: $invoiceId"
                . "\r\nAmount: $paymentAmount";
            logTransaction($gatewayModuleName, $output, "Callback Error");
        }
        redirectWithMessage($systemUrl . 'viewinvoice.php?id=' . $invoiceId, 'Payment error: Invalid payment amount. Please contact support.');
    }
    $amount = number_format(floatval($paymentAmount), 2, '.', '');
    if ($gatewayParams['convertto']) {
        $res = select_query(
            "tblclients",
            "tblinvoices.invoicenum,tblclients.currency,tblcurrencies.code",
            array("tblinvoices.id" => $invoiceId),
            "",
            "",
            "",
            "tblinvoices ON tblinvoices.userid=tblclients.id INNER JOIN tblcurrencies ON tblcurrencies.id=tblclients.currency"
        );
        $data = mysql_fetch_array($res);
        $invoice_currency_id = $data['currency'];
        if ($gatewayParams['convertto'] != $invoice_currency_id) {
            $converto_amount = convertCurrency($paymentAmount, $gatewayParams['convertto'], $invoice_currency_id);
            $amount = number_format(floatval($converto_amount), 2, '.', '');
        }
    }
    if ($gatewayParams['gatewayLogs'] == 'on') {
        $output = "Transaction ref: $transactionId\r\nInvoice ID: $invoiceId\r\nStatus: succeeded\r\nAPI Response: " . json_encode($result);
        logTransaction($gatewayModuleName, $output, "Success");
    }
    addInvoicePayment(
        $invoiceId,
        $transactionId,
        $amount,
        0,
        $gatewayModuleName
    );
    $invoice_url = $systemUrl . 'viewinvoice.php?id=' . $invoiceId . '&paymentmsg=success';
    header('Location: ' . $invoice_url);
    exit;
} else {
    $apiError = $result && isset($result->message) ? $result->message : 'Unknown API error';
    if ($gatewayParams['gatewayLogs'] == 'on') {
        $output = "Transaction ref: $transactionId\r\nInvoice ID: $invoiceId\r\nStatus: failed\r\nAPI Response: " . json_encode($result);
        logTransaction($gatewayModuleName, $output, "Failed: $apiError");
    }
    redirectWithMessage($systemUrl . 'viewinvoice.php?id=' . $invoiceId, 'Payment could not be verified or was declined.');
}
