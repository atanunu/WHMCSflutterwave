<?php

/**
 * Flutterwave WHMCS Payment Gateway Module v1.0
 *
 * This Payment Gateway module allows you to integrate Flutterwave payment solutions with the
 * WHMCS platform.
 *
 * For more information, please refer to the online documentation: 
 * https://developer.flutterwave.com/docs
 * 
 * @author Atanunu Igbunuroghene <atanunuigbunu@hmjp.com>
 * @copyright Copyright (c) HMJP Limited 2025
 */

if (!defined("WHMCS")) {
  die("This file cannot be accessed directly");
}

function flutterwave_MetaData()
{
  return array(
    'DisplayName' => 'Flutterwave',
    'APIVersion' => '3.0',
    'DisableLocalCredtCardInput' => true,
    'TokenisedStorage' => false,
  );
}

function flutterwave_config()
{
  return array(
    'FriendlyName' => array(
      'Type' => 'System',
      'Value' => 'Flutterwave',
    ),
    'publicKey' => array(
      'FriendlyName' => 'Public Key',
      'Type' => 'text',
      'Size' => '50',
      'Default' => '',
      'Description' => 'Enter your Flutterwave public key',
    ),
    'secretKey' => array(
      'FriendlyName' => 'Secret Key',
      'Type' => 'text',
      'Size' => '50',
      'Default' => '',
      'Description' => 'Enter your Flutterwave secret key',
    ),
    'cBname' => array(
      'FriendlyName' => 'Business Name',
      'Type' => 'text',
      'Size' => '50',
      'Default' => '',
      'Description' => 'Enter your business name',
    ),
    'cBdescription' => array(
      'FriendlyName' => 'Business Description',
      'Type' => 'text',
      'Size' => '50',
      'Default' => '',
      'Description' => 'Enter your business description',
    ),
    'whmcsLogo' => array(
      'FriendlyName' => 'Logo URL',
      'Type' => 'text',
      'Size' => '80',
      'Default' => '',
      'Description' => 'Enter the URL to your logo (square image recommended)',
    ),
    'payButtonText' => array(
      'FriendlyName' => 'Pay Button Text',
      'Type' => 'text',
      'Size' => '25',
      'Default' => 'Pay Now',
      'Description' => 'Text to display on the payment button',
    ),
    'gatewayLogs' => array(
      'FriendlyName' => 'Enable Gateway Logs',
      'Type' => 'yesno',
      'Description' => 'Enable logging for gateway requests and responses',
      'Default' => '0'
    ),
    'paymentFlow' => array(
      'FriendlyName' => 'Payment Flow',
      'Type' => 'dropdown',
      'Options' => array(
        'inline' => 'Inline (JavaScript)',
        'redirect' => 'Redirect (Server-to-Server)'
      ),
      'Default' => 'inline',
      'Description' => 'Choose the payment flow: Inline (JavaScript) or Redirect (server-to-server)'
    ),
    'paymentMethods' => array(
      'FriendlyName' => 'Supported Payment Methods',
      'Type' => 'text',
      'Size' => '80',
      'Description' => 'Enter a comma-separated list of payment methods to support (e.g. card,account,ussd,banktransfer,qr,mpesa,googlepay,applepay,opay,barter,wechat).',
      'Default' => 'card,account,ussd,banktransfer,qr,mpesa,googlepay,applepay,opay,barter,wechat',
    ),
  );
}

function flutterwave_link($params)
{
  // Gateway Configuration Parameters
  $publicKey = isset($params['publicKey']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', $params['publicKey']) : '';
  $companyName = isset($params['cBname']) ? htmlspecialchars($params['cBname'], ENT_QUOTES, 'UTF-8') : '';
  $companyDescription = isset($params['cBdescription']) ? htmlspecialchars($params['cBdescription'], ENT_QUOTES, 'UTF-8') : '';
  $logo = isset($params['whmcsLogo']) ? filter_var($params['whmcsLogo'], FILTER_SANITIZE_URL) : '';
  $payButtonText = isset($params['payButtonText']) ? htmlspecialchars($params['payButtonText'], ENT_QUOTES, 'UTF-8') : '';

  // Invoice Parameters
  $invoiceId = isset($params['invoiceid']) ? intval($params['invoiceid']) : 0;
  $description = isset($params['description']) ? htmlspecialchars($params['description'], ENT_QUOTES, 'UTF-8') : '';
  $amount = isset($params['amount']) ? floatval($params['amount']) : 0.0;
  $currencyCode = isset($params['currency']) ? htmlspecialchars($params['currency'], ENT_QUOTES, 'UTF-8') : '';

  // Client Parameters
  $firstname = isset($params['clientdetails']['firstname']) ? htmlspecialchars($params['clientdetails']['firstname'], ENT_QUOTES, 'UTF-8') : '';
  $lastname = isset($params['clientdetails']['lastname']) ? htmlspecialchars($params['clientdetails']['lastname'], ENT_QUOTES, 'UTF-8') : '';
  $email = isset($params['clientdetails']['email']) ? filter_var($params['clientdetails']['email'], FILTER_SANITIZE_EMAIL) : '';
  $phone = isset($params['clientdetails']['phonenumber']) ? htmlspecialchars($params['clientdetails']['phonenumber'], ENT_QUOTES, 'UTF-8') : '';

  // System Parameters
  $systemUrl = isset($params['systemurl']) ? filter_var($params['systemurl'], FILTER_SANITIZE_URL) : '';
  $langPayNow = isset($params['langpaynow']) ? htmlspecialchars($params['langpaynow'], ENT_QUOTES, 'UTF-8') : 'Pay Now';
  $moduleName = isset($params['paymentmethod']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', $params['paymentmethod']) : '';
  $redirectUrl = $systemUrl . 'modules/gateways/callback/' . $moduleName . '.php';
  $paymentFlow = isset($params['paymentFlow']) ? $params['paymentFlow'] : 'inline';

  // Error logging example: check for missing required config
  if (empty($publicKey) || empty($companyName)) {
    logModuleCall(
      'flutterwave',
      'ConfigError',
      array('publicKey' => '[hidden]', 'companyName' => $companyName),
      '',
      'Missing required configuration: publicKey or companyName',
      array()
    );
    return '<div style="color:red;">Payment gateway misconfiguration. Please contact support.</div>';
  }

  // Example: Log API/callback errors if present in GET/POST (for callback handler, you would use a separate function)
  if (isset($_GET['flutterwave_error']) && !empty($_GET['flutterwave_error'])) {
    logModuleCall(
      'flutterwave',
      'APIError',
      array_merge($_GET, ['publicKey' => '[hidden]']),
      '',
      'Flutterwave API error: ' . htmlspecialchars($_GET['flutterwave_error'], ENT_QUOTES, 'UTF-8'),
      array()
    );
    return '<div style="color:red;">Payment failed: ' . htmlspecialchars($_GET['flutterwave_error'], ENT_QUOTES, 'UTF-8') . '</div>';
  }

  // Determine payment options from admin config
  $paymentMethods = array();
  if (isset($params['paymentMethods'])) {
    if (is_array($params['paymentMethods'])) {
      $paymentMethods = $params['paymentMethods'];
    } elseif (is_string($params['paymentMethods'])) {
      $paymentMethods = explode(',', $params['paymentMethods']);
    }
  }
  $paymentMethods = array_map('trim', $paymentMethods);
  $paymentMethods = array_filter($paymentMethods);
  $paymentOptions = $paymentMethods ? implode(',', $paymentMethods) : 'card';

  // Payment Flow Selection
  if ($paymentFlow === 'redirect') {
    // Server-to-server (cURL) redirect: POST to callback handler, which will handle cURL and redirect
    $htmlOutput = '<noscript><div style="color:red;">JavaScript is required for payment processing. Please enable JavaScript in your browser.</div></noscript>';
    $htmlOutput .= '<form method="post" action="' . $redirectUrl . '" id="fw-redirect-form">';
    $htmlOutput .= '<input type="hidden" name="invoiceid" value="' . $invoiceId . '" />';
    $htmlOutput .= '<input type="hidden" name="amount" value="' . $amount . '" />';
    $htmlOutput .= '<input type="hidden" name="currency" value="' . $currencyCode . '" />';
    $htmlOutput .= '<input type="hidden" name="email" value="' . $email . '" />';
    $htmlOutput .= '<input type="hidden" name="phone" value="' . $phone . '" />';
    $htmlOutput .= '<input type="hidden" name="firstname" value="' . $firstname . '" />';
    $htmlOutput .= '<input type="hidden" name="lastname" value="' . $lastname . '" />';
    $htmlOutput .= '<input type="hidden" name="description" value="' . $description . '" />';
    $htmlOutput .= '<input type="hidden" name="payment_options" value="' . htmlspecialchars($paymentOptions) . '" />';
    $htmlOutput .= '<button type="submit" id="fw-redirect-btn" aria-busy="false" aria-live="polite" style="cursor:pointer;background-color:#ff9b00;color:#12122c;padding:7.5px 16px;font-weight:500;font-size:14px;border-radius:4px;border:none;display:inline-flex;align-items:center;gap:8px;">' . $payButtonText . ' <span id="fw-redirect-spinner" style="display:none;"><svg width="18" height="18" viewBox="0 0 38 38" xmlns="http://www.w3.org/2000/svg" stroke="#12122c"><g fill="none" fill-rule="evenodd"><g transform="translate(1 1)" stroke-width="2"><circle stroke-opacity=".3" cx="18" cy="18" r="18"/><path d="M36 18c0-9.94-8.06-18-18-18"><animateTransform attributeName="transform" type="rotate" from="0 18 18" to="360 18 18" dur="1s" repeatCount="indefinite"/></path></g></g></svg></span></button>';
    $htmlOutput .= '</form>';
    $htmlOutput .= '<div id="fw-redirect-msg" style="margin-top:10px;color:#555;font-size:15px;display:none;">Redirecting you to secure payment...</div>';
    $htmlOutput .= '<script>document.getElementById("fw-redirect-form").addEventListener("submit",function(){document.getElementById("fw-redirect-btn").disabled=true;document.getElementById("fw-redirect-spinner").style.display="inline-block";document.getElementById("fw-redirect-msg").style.display="block";});</script>';
    return $htmlOutput;
  }

  // Inline (JavaScript) payment flow (default)
  $htmlOutput = '<noscript><div style="color:red;">JavaScript is required for payment processing. Please enable JavaScript in your browser.</div></noscript>';
  $htmlOutput .= '<script src="https://checkout.flutterwave.com/v3.js"></script>';
  $htmlOutput .= '<form onsubmit="return false;">';
  $htmlOutput .= '<button type="button" id="start-payment-button" aria-busy="false" aria-live="polite" style="cursor: pointer;position: relative;background-color: #ff9b00;color: #12122c;max-width: 100%;padding: 7.5px 16px;font-weight: 500;font-size: 14px;border-radius: 4px;border: none;transition: all .1s ease-in;vertical-align: middle;display:inline-flex;align-items:center;gap:8px;">'
    . $langPayNow . ' <span id="fw-inline-spinner" style="display:none;"><svg width="18" height="18" viewBox="0 0 38 38" xmlns="http://www.w3.org/2000/svg" stroke="#12122c"><g fill="none" fill-rule="evenodd"><g transform="translate(1 1)" stroke-width="2"><circle stroke-opacity=".3" cx="18" cy="18" r="18"/><path d="M36 18c0-9.94-8.06-18-18-18"><animateTransform attributeName="transform" type="rotate" from="0 18 18" to="360 18 18" dur="1s" repeatCount="indefinite"/></path></g></g></svg></span></button>';
  $htmlOutput .= '</form>';
  $htmlOutput .= '<div id="fw-inline-msg" style="margin-top:10px;color:#555;font-size:15px;display:none;">Launching secure payment...</div>';
  $htmlOutput .= '<script>
        function makePayment() {
          var btn = document.getElementById("start-payment-button");
          btn.disabled = true;
          btn.setAttribute("aria-busy","true");
          document.getElementById("fw-inline-spinner").style.display = "inline-block";
          document.getElementById("fw-inline-msg").style.display = "block";
          btn.innerText = "Processing...";
          try {
            FlutterwaveCheckout({
              public_key: "' . $publicKey . '",
              tx_ref: "' . $invoiceId . '",
              amount: ' . $amount . ',
              currency: "' . $currencyCode . '",
              payment_options: "' . $paymentOptions . '",
              redirect_url: "' . $redirectUrl . '",
              meta: {
                consumer_id: "' . $invoiceId . '",
                consumer_mac: "' . htmlspecialchars($_SERVER['REMOTE_ADDR'], ENT_QUOTES, 'UTF-8') . '",
              },
              customer: {
                email: "' . $email . '",
                phone_number: "' . $phone . '",
                name: "' . $firstname . ' ' . $lastname . '",
              },
              customizations: {
                title: "' . $companyName . '",
                description: "Online Payment: ' . $description . '",
                logo: "' . $logo . '",
              },
              onclose: function() {
                window.location.href = "' . $systemUrl . 'viewinvoice.php?id=' . $invoiceId . '";
              }
            });
          } catch (e) {
            document.getElementById("fw-inline-msg").innerText = "An error occurred while launching payment. Please try again.";
            btn.disabled = false;
            btn.setAttribute("aria-busy","false");
            document.getElementById("fw-inline-spinner").style.display = "none";
            btn.innerText = "' . $langPayNow . '";
          }
        }
        document.getElementById("start-payment-button").addEventListener("click", makePayment);
      </script>';
  return $htmlOutput;
}
