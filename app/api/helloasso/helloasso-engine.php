<?php
// Database connection and common variables
require_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.php";

// extract auth token
$input = json_decode(file_get_contents("php://input"), true);
$csrf = $input['csrf'] ?? null;
$action = $action ?? null;

// Restrict to admins
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo "<h1>Unauthorized</h1>The requested ressource is not accessible.";
	exit;
}

if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "<h1>Forbidden</h1>You don't have permission to access this resource.";
	exit;
}

if ($_SESSION['CSRF'] !== $csrf || !$csrf) {
    http_response_code(401);
    echo "<h1>Unauthorized</h1>The requested ressource is not accessible.";
	exit;
}


// bypass if ordersStatus edition
if ($action === 'edit') {
    $data = $input['data'];
    file_put_contents(__DIR__ . "/data/goodies_status.json", json_encode($data, JSON_PRETTY_PRINT));

    // get goodies delivery status
    $deliveredGoodies = array_map(fn ($item) => $item['delivered'] ? $item['id']:null, $data ?? []);
    $registeredGoodies = array_map(fn ($item) => $item['registered'] ? $item['id']:null, $data ?? []);

	// prepare goodies output
    $goodies = json_decode(file_get_contents(__DIR__ . "/data/goodies.json") ?? '[]', true);
    $goodiesOut = [];
    foreach ($goodies as $item) {
        $item['delivered'] = in_array($item['id'], $deliveredGoodies);
        $item['registered'] = in_array($item['id'], $registeredGoodies);
        $goodiesOut[] = $item;
    }
    file_put_contents(__DIR__ . "/data/goodies.json", json_encode($goodiesOut, JSON_PRETTY_PRINT));

    logger('/log/helloasso.log', "Status of goodies updated and saved on server", 'Goodies status update');
    http_response_code(200);
    echo json_encode(['message' => 'Status of goodies updated.']);
    exit;
}

require_once __DIR__ . 	'/auth/helloasso-api-token-manager.php';

$HAManager = new HATokenManager(
    $ha_client_id,
    $ha_client_secret
);
$accessToken = $HAManager->getToken();

if (!$accessToken) {
    http_response_code(500); // access authorized so success but failed to ouput sql
	echo json_encode(['error' => 'Unable to get access token for HelloAsso.']);
}

// Configure OAuth2 access token for authorization: OAuth2
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken($accessToken);

// Orders API
$ordersApiInstance = new OpenAPI\Client\Api\CommandesApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);

// Forms API
$formsApiInstance = new OpenAPI\Client\Api\FormulairesApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);

$organization_slug = 'arcensiel-association-queer-de-l-ens-lyon'; // string | The organization slug
$form_slug = 'goodies-arcensiel'; // string | The form slug
$form_type = 'shop'; // \OpenAPI\Client\Model\HelloAssoApiV5ModelsEnumsFormType | The form type CrowdFunding, Membership, Event, Donation, PaymentForm, Checkout, Shop
$from = '1900-01-01T00:00:00+00:00'; // \DateTime | First Date Filter
$to = '2100-12-31T23:59:59+00:00'; // \DateTime | End Date Filter (exclusive)
$user_search_key = null; // string | Filter results on user or payer first name, last name or email
$page_index = null; // int | The page of results to retrieve
$page_size = 100; // int | The number of items per page
$continuation_token = null; // string | Continuation Token from which we wish to retrieve results
$states = null; // \OpenAPI\Client\Model\HelloAssoApiV5ModelsEnumsPaymentState[] | Filter results by states of payments  Available values: * `Pending` - A payment scheduled at a later date, not yet processed. * `Authorized` - The payment has been authorized, validated, processed. * `Refused` - The payment has been refused by the bank. * `Unknown` * `Registered` - Represents a payment made offline.              Probably for an item of type * `Refunded` - The payment has been refunded. * `Refunding` - The payment is being refunded. * `Contested` - Payment has been contested by the contributor
$tier_types = null; // \OpenAPI\Client\Model\HelloAssoApiV5ModelsEnumsTierType[] | The type of tiers Donation, Payment, Registration, Membership, MonthlyDonation, MonthlyPayment, OfflineDonation, Contribution, Bonus
$form_types = null; // \OpenAPI\Client\Model\HelloAssoApiV5ModelsEnumsFormType[] | The type of the form CrowdFunding, Membership, Event, Donation, PaymentForm, Checkout, Shop
$item_states = null; // \OpenAPI\Client\Model\HelloAssoApiV5ModelsEnumsItemState[] | The item states  Available values: * `Processed` - The item is paid and is valid * `Registered` - The item has been registered manually by the organization and is valid * `Unknown` * `Canceled` - The item has been canceled, and is no longer valid
$tier_name = null; // string | The name of a tier
$with_details = 'true'; // bool | Set to true to return CustomFields and Options
$sort_order = null; // \OpenAPI\Client\Model\HelloAssoApiV5ModelsEnumsSortOrder | Sort payments by ascending or descending order. Default is descending
$sort_field = null; // \OpenAPI\Client\Model\HelloAssoApiV5ModelsEnumsSortField | Sort payments by a specific field (Date or UpdateDate). Default is date
$with_count = 'false'; // bool | Whether the pagination should include totalCount and totalPages.
$with_failed_refund_operation = 'true'; // bool | True to retrieve the refund operation in the states 'ABORTED', 'CANCELED', 'ERROR', 'REFUSED'.

$ordersList = [];
$formsList = [];
try {
	$continuation_token = null;
    do {
		// get all sold items of organisaztion in any form (shop, donation, lotery)
		$order_raw = $ordersApiInstance->organizationsOrganizationSlugOrdersGet($organization_slug, $from, $to, $user_search_key, $page_index, $page_size, $continuation_token, $form_types, $with_details, $sort_order, $with_count);
		// $continuation_token = $pay_raw->getPagination()->getContinuationToken();
        
        if (!$order_raw) {
			http_response_code(500); // access authorized so success but failed to ouput sql
			echo json_encode( ['error' => 'Exception when calling HelloAsso Orders API : null output']);
			logger('/log/helloasso.log', "Null output fetching orders at page $page_index", '[ERROR] Failed: fetch orders entries');
			exit;
		}

        logger('/log/helloasso.log', json_encode([count($order_raw->getData())." data entries",$order_raw->getPagination()]), 'Fetch Orders entries');
        $ordersList = array_merge($ordersList, $order_raw->getData());

        if ($continuation_token === $order_raw->getPagination()->getContinuationToken()) {
			logger('/log/helloasso.log', "[Orders API] Continuation token not updated : last page reached", 'End of output');
			break;
		} else {
            $continuation_token = $order_raw->getPagination()->getContinuationToken();
        }
	} while ($continuation_token !== null);
    
    $continuation_token = null;
    do {
		// get forms list
		$form_raw = $formsApiInstance->organizationsOrganizationSlugFormsGet($organization_slug, $states, $form_types, $page_index, $page_size, $continuation_token);
        // $continuation_token = $pay_raw->getPagination()->getContinuationToken();

        if (!$form_raw) {
			http_response_code(500); // access authorized so success but failed to ouput sql
			echo json_encode( ['error' => 'Exception when calling HelloAsso Forms API : null output']);
			logger('/log/helloasso.log', "Null output fetching forms at page $page_index", '[ERROR] Failed: fetch forms entries');
			exit;
		}

        logger('/log/helloasso.log', json_encode([count($form_raw->getData())." data entries",$form_raw->getPagination()]), 'Fetch Forms entries');
		$formsList = array_merge($formsList, $form_raw->getData());
        
        if ($continuation_token === $form_raw->getPagination()->getContinuationToken()) {
			logger('/log/helloasso.log', "[Forms API] Continuation token not updated : last page reached", 'End of output');
			break;
		} else {
            $continuation_token = $form_raw->getPagination()->getContinuationToken();
        }
	} while ($continuation_token !== null);

	$output = ['ordersList' => $ordersList, 'formsList' => $formsList];
    $compatibleOutput = json_decode(json_encode($output), true); // HelloAsso outputs is a special data type which doesn't allow direct acces with associative arrays
	file_put_contents(__DIR__ . "/data/all.json", json_encode($output));
	
    logger('/log/helloasso.log', "Data synchronized from HelloAsso API.", 'Fetch HelloAsso data');

    // get goodies delivery status
    $goodiesOrdersStatus = json_decode(file_get_contents(__DIR__ . "/data/goodies_status.json") ?? '[]', true);
    $deliveredGoodies = array_map(fn ($item) => $item['delivered'] ? $item['id']:null, $goodiesOrdersStatus ?? []);
    $registeredGoodies = array_map(fn ($item) => $item['registered'] ? $item['id']:null, $goodiesOrdersStatus ?? []);

    // prepare shop oreders output
    $Orders = [];
    foreach ($compatibleOutput['ordersList'] as $order) {
        $Orders[] = [
            'payer' => ['email' => $order['payer']['email'], 'firstName' => $order['payer']['firstName'], 'lastName' => $order['payer']['lastName']],
            'items' => array_map(fn ($item) => ['id' => $item['id'], 'name' => $item['name'], 'amount' => $item['amount'], 'state' => $item['state'], 'type' => $item['type'], 'ticketUrl' => $item['ticketUrl']], $order['items'] ?? []) ?: null,
            'payments' => array_map(fn ($item) => ['id' => $item['id'], 'amount' => $item['amount'], 'state' => $item['state'], 'paymentReceiptUrl' => $item['paymentReceiptUrl']], $order['payments'] ?? []) ?: null,
            'amount' => $order['amount'],
            'id' => $order['id'],
            'date' => $order['date'],
            'formSlug' => $order['formSlug'],
            'formType' => $order['formType']
        ];
    }
    file_put_contents(__DIR__ . "/data/orders.json", json_encode($Orders, JSON_PRETTY_PRINT));

	// prepare shop oreders output
    $Shop = [];
    foreach ($compatibleOutput['ordersList'] as $order) {
        if ($order['formType'] === "Shop") {
            foreach ($order['items'] as $item) {
                $Shop[] = [
                    'identity' => $order['payer']['firstName'] . ' ' . strtoupper($order['payer']['lastName']),
                    'mail' => $order['payer']['email'],
                    'paymentsID' => $order['payments'] !== null ? reset($order['payments'])['id'] : null,
                    'id' => $item['id'],
                    'date' => $order['date'],
                    'receipts' => $order['payments'] !== null ? reset($order['payments'])['paymentReceiptUrl'] : null,
                    'amount' => $item['amount'],
                    'item' => $item['name'] ?? ($item['type'] === 'Donation' ? 'Don' : null),
                    'delivered' => in_array($item['id'], $deliveredGoodies),
                    'registered' => in_array($item['id'], $registeredGoodies),
                    'campaign' => $order['formSlug']
                ];
            }
        }
    }
    file_put_contents(__DIR__ . "/data/goodies.json", json_encode($Shop, JSON_PRETTY_PRINT));

    // prepare events output
    $Event = [];
    foreach ($compatibleOutput['ordersList'] as $order) {
        if ($order['formType'] === "Event") {
            foreach ($order['items'] as $item) {
                $Event[] = [
                    'identity' => $item['user']['firstName'] . ' ' . strtoupper($item['user']['lastName']),
                    'paymentsID' => $order['payments'] !== null ? reset($order['payments'])['id'] : null,
                    'id' => $item['id'],
                    'date' => $order['date'],
                    'receipts' => $order['payments'] !== null ? reset($order['payments'])['paymentReceiptUrl'] : null,
                    'ticket' => $item['ticketUrl'],
                    'amount' => $item['amount'],
                    'item' => $item['name'] ?? ($item['type'] === 'Donation' ? 'Don' : null),
                    'student' => $item['customFields'][0]['answer'] ?? null,
                    'campaign' => $order['formSlug']
                ];
            }
        }
    }
    file_put_contents(__DIR__ . "/data/events.json", json_encode($Event, JSON_PRETTY_PRINT));

    // prepare events output
    $Membership = [];
    foreach ($compatibleOutput['ordersList'] as $order) {
        if ($order['formType'] === "Membership") {
            foreach ($order['items'] as $item) {
                $Membership[] = [
                    'identity' => $item['user']['firstName'] . ' ' . strtoupper($item['user']['lastName']),
                    'nom' => strtoupper($item['user']['lastName']),
                    'paymentsID' => $order['payments'] !== null ? reset($order['payments'])['id'] : null,
                    'id' => $item['id'],
                    'date' => $order['date'],
                    'receipts' => $order['payments'] !== null ? reset($order['payments'])['paymentReceiptUrl'] : null,
                    'amount' => $item['amount'],
                    'item' => $item['name'] ?? ($item['type'] === 'Donation' ? 'Don' : null),
                    'mail' => $item['customFields'][0]['answer'] ?? null,
                    'campaign' => $order['formSlug']
                ];
            }
        }
    }
    file_put_contents(__DIR__ . "/data/memberships.json", json_encode($Membership, JSON_PRETTY_PRINT));
	
    // prepare donations output
    $Donation = [];
    foreach ($compatibleOutput['ordersList'] as $order) {
        if ($order['formType'] === "Donation") {
            foreach ($order['items'] as $item) {
                $Donation[] = [
                    'identity' => $order['payer']['firstName'] . ' ' . strtoupper($order['payer']['lastName']),
                    'paymentsID' => reset($order['payments'])['id'],
                    'id' => $item['id'],
                    'date' => $order['date'],
                    'receipts' => reset($order['payments'])['paymentReceiptUrl'],
                    'amount' => $item['amount'],
                    'campaign' => $order['formSlug']
                ];
            }
        }
    }
    file_put_contents(__DIR__ . "/data/donations.json", json_encode($Donation, JSON_PRETTY_PRINT));

    // prepare forms output
    $formsOut = array_reduce(['Shop', 'Event', 'Membership', 'Donation', 'Checkout', 'CrowdFunding'], fn ($carry, $item) => [...$carry, $item => ['forms' => [], 'items' => []]], []);
    $campaignsOut = [];
    foreach ($compatibleOutput['formsList'] as $form) {
        $item = [
                'name' => $form['title'],
                'id' => $form['formSlug'],
                'description' => $form['description'],
                'start' => $form['startDate'],
                'end' => $form['endDate'],
                'status' => $form['state'],
                'type' => $form['formType'],
                'url' => $form['url']
                ];
        $formsOut[$form['formType']]['forms'][] = $item;
        $formsOut[$form['formType']]['items'][$form['formSlug']] = array_values(array_filter(array_unique(array_map(fn ($item) => $item['campaign'] === $form['formSlug'] ? $item['item']:null, ${$form['formType']} ?? []), SORT_STRING)));
        $campaignsOut[] = $item;
    }
    file_put_contents(__DIR__ . "/data/campaigns.json", json_encode($campaignsOut, JSON_PRETTY_PRINT));
    file_put_contents(__DIR__ . "/data/forms.json", json_encode($formsOut, JSON_PRETTY_PRINT));

    http_response_code(200);
	echo json_encode( ['message' => 'Data synchronized']);
	exit;
} catch (Exception $e) {
    http_response_code(500); // access authorized so success but failed to ouput sql
	echo json_encode( ['error' => 'Exception when calling HelloAsso API ' . $e->getMessage()]);
    logger('/log/helloasso.log', $e->getMessage(), '[ERROR] Failed: Fetching data from HelloAsso API');
	exit;
}