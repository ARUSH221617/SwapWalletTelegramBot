<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);
require_once "config.php";
require_once "Autoloader.php";

use ARUSH\DatabaseController;
use ARUSH\payment\zarinpal;
use ARUSH\Router;
use ARUSH\telegram\telegram as Bot;

function sendRequest($url, $data = [], $method = 'POST')
{
    // Define the HTTP method
    $method = strtoupper($method);

    // Create a stream context with the HTTP request headers
    $contextOptions = [
        'http' => [
            'method' => $method,
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($data),
            'timeout' => 30,
            // Maximum execution time for the request
        ],
    ];

    $context = stream_context_create($contextOptions);

    // Make the HTTP request using file_get_contents
    $response = @file_get_contents($url, false, $context);

    // Check if file_get_contents encountered an error
    if ($response === false) {
        // Handle the error gracefully
        echo 'HTTP Error: ' . error_get_last()['message'];
        // You may want to log the error or return an error response instead of echoing
    }

    return $response;
}

$bot = new Bot(BOT_TOKEN);
$router = new Router();

$handleWebhook = function ($state) use ($bot) {
    switch ($state) {
        case 'set':
            $bot_on = $bot->setWebhook(BOT_TELEGRAM_ENDPOINT);
            echo $bot_on;
            break;
        default:
            $bot_off = $bot->removeWebhook();
            echo $bot_off;
            break;
    }
};

$handleUpdatesCallback = function () use ($bot) {
    $bot->handleRequest();
};

$getAllData = function () use ($bot) {
    // Set the appropriate headers to allow cross-origin requests
    header("Access-Control-Allow-Origin: *"); // Allow requests from any origin (not recommended for production)

    // Set other CORS headers if needed
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token");
    header("Content-Type: application/json");

    $db = new DatabaseController;
    $tb_user = $db->query("SELECT `id`, `fullname`, `username`, `mobile`, `site`, `registerdate` FROM `ar_user`", []);

    $users = [];

    foreach ($tb_user as $user) {
        $site = $db->query("SELECT `siteurl` FROM `ar_site` WHERE `id`=?", [$user["site"]])[0];
        if ($db->exists("ar_exchange_currency", "`user`=? AND `site`=?", [$user["id"], $user["site"]])) {
            $exchangeCurrencyPrice = $db->query("SELECT `price` FROM `ar_exchange_currency` WHERE `user`=? AND `site`=?", [$user["id"], $user["site"]])[0];
        } else {
            $exchangeCurrencyPrice = $db->query("SELECT `price` FROM `ar_exchange_currency` WHERE `user`=? AND `site`=?", [0, 0])[0];
        }
        if ($db->exists("ar_wallet", "`user`=?", [$user["id"]])) {
            $wallet = $db->query("SELECT `price`, `coin` FROM `ar_wallet` WHERE `user`=?", [$user["id"]])[0];
        } else {
            $chatId = $db->query("SELECT `user_id` FROM `ar_telegram_sessions` WHERE `session_key`=? AND `session_value`=?", ['mobile', $user['mobile']])[0]["user_id"];
            $wallet = $bot->checkWallet($chatId);
        }
        $user["site"] = $site;
        $user["exchangeCurrencyPrice"] = $exchangeCurrencyPrice;
        $user["wallet"] = $wallet;
        $users[] = $user;
    }

    $tb_memberadded = $db->query("SELECT `fullname`, `username`, `mobile`, `site`, `date`, `product` FROM `ar_memberadded`", []);

    $memberadded = [];

    foreach ($tb_memberadded as $member) {
        $site = $db->query("SELECT `siteurl` FROM `ar_site` WHERE `id`=?", [$member["site"]])[0];
        $member["site"] = $site;
        $memberadded[] = $member;
    }

    $tb_site = $db->query("SELECT `siteurl`, `registerdate`, `updatedate` FROM `ar_site`", []);

    $licensePrice = $db->query("SELECT SUM(`coin`) as `price` FROM `ar_wallet`", [])[0]["price"];

    echo json_encode([
        'ok' => true,
        'licensePrice' => $licensePrice,
        'users' => $users,
        'memberadded' => $memberadded,
        'sites' => $tb_site
    ]);
};

$usersAdd = function () {
    try {
        // Set the appropriate headers to allow cross-origin requests
        header("Access-Control-Allow-Origin: *"); // Allow requests from any origin (not recommended for production)
        // Set other CORS headers if needed
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        header("Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token");
        header("Content-Type: application/json");
        http_response_code(200);

        $data = $_POST;

        // Check if required data fields are present
        if (!isset($data['siteurl'], $data['fullname'], $data['tmusername'], $data['mobile'], $data['coin'])) {
            $response = ['ok' => false, 'message' => 'لطفا همه فیلد ها را پر کنید'];
        }

        $db = new DatabaseController;

        // Insert site data
        $site = $db->insert("ar_site", [
            'user' => 0,
            'siteurl' => $data['siteurl']
        ]);

        $wallet = $db->insert("ar_wallet", [
            'user' => 0,
            'coin' => $data['coin'],
            'price' => '0TOM',
            'status' => 1,
            'type' => 1
        ]);

        // Insert user data
        $user = $db->insert("ar_user", [
            'fullname' => $data['fullname'],
            'username' => $data['tmusername'],
            'mobile' => $data['mobile'],
            'site' => $site,
            'pass' => '0',
            'wallet' => $wallet
        ]);

        $exchangeCurrency = $this->db->insert("ar_exchange_currency", [
            "user" => $user,
            "site" => $site,
            "price" => 10000,
            "currency" => "TOM",
            "coin" => 1
        ]);

        $db->update("ar_site", [
            'user' => $user
        ], 'id=?', [$site]);

        $db->update("ar_wallet", [
            'user' => $user
        ], 'id=?', [$wallet]);

        if (!$site || !$user || !$wallet || !$exchangeCurrency) {
            $response = ['ok' => false, 'message' => 'یه مشکلی در دیتابیس به وجود اومده!'];
        }

        $response = ['ok' => true, 'message' => 'کاربر با موفقیت در ربات ثبت شد'];
        echo json_encode($response);
    } catch (Exception $e) {
        http_response_code(400); // Set the appropriate HTTP error code
        $errorResponse = ['ok' => false, 'message' => $e->getMessage()];
        echo json_encode($errorResponse);
    }

};

$usersDelete = function () {
    try {
        // Set the appropriate headers to allow cross-origin requests
        header("Access-Control-Allow-Origin: *"); // Allow requests from any origin (not recommended for production)
        // Set other CORS headers if needed
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        header("Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token");
        header("Content-Type: application/json");
        http_response_code(200);

        $data = $_POST;

        // Check if required data fields are present
        if (!isset($data['mobile'])) {
            $response = ['ok' => false, 'message' => 'لطفا همه فیلد ها را پر کنید'];
        }

        $db = new DatabaseController;

        $user = $db->query("SELECT `id` FROM `ar_user` WHERE `mobile`=?", [$data["mobile"]])[0]["id"];
        $userD = $db->delete("ar_user", "id=?", [$user]);
        $siteD = $db->delete("ar_site", "user=?", [$user]);

        if (!$userD || !$siteD) {
            $response = ['ok' => false, 'message' => 'یه مشکلی در دیتابیس به وجود اومده!'];
        }

        $response = ['ok' => true, 'message' => 'کاربر با موفقیت از ربات حذف شد'];
        echo json_encode($response);
    } catch (Exception $e) {
        http_response_code(400); // Set the appropriate HTTP error code
        $errorResponse = ['ok' => false, 'message' => $e->getMessage()];
        echo json_encode($errorResponse);
    }

};

$botEdit = function () {
    // Set the appropriate headers to allow cross-origin requests
    header("Access-Control-Allow-Origin: *"); // Allow requests from any origin (not recommended for production)
    // Set other CORS headers if needed
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token");
    header("Content-Type: application/json");

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); // Method Not Allowed
        echo json_encode(['ok' => false, 'message' => 'درخواست شما معتبر نیست']);
        return;
    }

    $data = $_POST;
    $files = $_FILES;

    $botToken = BOT_TOKEN; // Replace with your actual bot token
    $apiUrl = "https://api.telegram.org/bot{$botToken}/setBotProfile";

    // Prepare the data to send
    $postData = [
        'bot_username' => 'ARUSHAdminGeramBot',
        'first_name' => $data["name"],
        'description' => $data["description"],
        'about' => $data["about"],
    ];

    if (!empty($files["profile-photo"]["tmp_name"])) {
        // Include profile photo if provided
        $postData['photo'] = new CURLFile($files["profile-photo"]["tmp_name"], $files["profile-photo"]["type"]);
    }

    // Use the sendRequest function to make the API request
    $response = sendRequest($apiUrl, $postData, 'POST');

    if ($response === false) {
        // Handle the error gracefully
        http_response_code(500); // Internal Server Error
        echo json_encode(['ok' => false, 'message' => 'خطای سرور']);
        return;
    }

    // Decode the JSON response
    $responseData = json_decode($response, true);

    if ($responseData['ok']) {
        // Bot profile edited successfully
        http_response_code(200);
        echo json_encode(['ok' => true, 'message' => 'نمایه ربات با موفقیت بروزرسانی شد']);
    } else {
        // Handle the error
        http_response_code(400); // Bad Request
        echo json_encode(['ok' => false, 'message' => $responseData['description']]);
    }
};

$userEdit = function () use ($bot) {
    try {
        // Set the appropriate headers to allow cross-origin requests
        header("Access-Control-Allow-Origin: *"); // Allow requests from any origin (not recommended for production)
        // Set other CORS headers if needed
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        header("Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token");
        header("Content-Type: application/json");
        http_response_code(200);

        $data = $_POST;

        $mobile = $data["cmobile"];
        unset($data["cmobile"]);

        // Check if required data fields are present
        if (empty(intval($mobile))) {
            $response = ['ok' => false, 'message' => 'لطفا همه فیلد ها را پر کنید'];
        }

        $db = new DatabaseController;

        if (isset($data["coin"])) {
            $user = $db->query("SELECT `id` FROM `ar_user` WHERE `mobile`=?", [$mobile])[0];
            $edit = $db->update("ar_wallet", ["coin" => $data["coin"]], "`user`=?", [$user["id"]]);
        } else if (isset($data["exchangeCurrencyPrice"])) {
            $user = $db->query("SELECT `id`, `site` FROM `ar_user` WHERE `mobile`=?", [$mobile])[0];
            $edit = $db->update("ar_exchange_currency", ["price" => $data["exchangeCurrencyPrice"]], "`user`=? AND `site`=?", [$user["id"], $user["site"]]);
        } else if (isset($data["mobile"])) {
            $edit = $db->update("ar_user", ["mobile" => $data["mobile"]], "mobile=?", [$mobile]);
            $mobile = $data["mobile"];
        } else if (!isset($data["siteurl"])) {
            $edit = $db->update("ar_user", [array_keys($data)[0] => array_values($data)[0]], "mobile=?", [$mobile]);
        } else {
            $user = $db->query("SELECT `id` FROM `ar_user` WHERE `mobile`=?", [$mobile])[0]["id"];
            $edit = $db->update("ar_site", ["siteurl" => $data["siteurl"]], "user=?", [$user]);
        }

        if (!$edit) {
            $response = ['ok' => false, 'message' => 'یه مشکلی در دیتابیس به وجود اومده!'];
        }

        $user = $db->query("SELECT * FROM `ar_user` WHERE `mobile`=?", [$mobile])[0];
        $site = $db->query("SELECT `siteurl` FROM `ar_site` WHERE `user`=?", [$user["id"]])[0];
        $wallet = $db->query("SELECT `coin` FROM `ar_wallet` WHERE `user`=?", [$user["id"]])[0];
        if ($db->exists("ar_exchange_currency", "`user`=? AND `site`=?", [$user["id"], $user["site"]])) {
            $exchangeCurrencyPrice = $db->query("SELECT `price` FROM `ar_exchange_currency` WHERE `user`=? AND `site`=?", [$user["id"], $user["site"]])[0];
        } else {
            $exchangeCurrencyPrice = $db->query("SELECT `price` FROM `ar_exchange_currency` WHERE `user`=? AND `site`=?", [0, 0])[0];
        }
        $response = ['ok' => true, 'message' => 'اطلاعات کاربر با موفقیت بروزرسانی شد', 'data' => array_merge($user, $site, $exchangeCurrencyPrice, $wallet)];
        echo json_encode($response);
    } catch (Exception $e) {
        http_response_code(400); // Set the appropriate HTTP error code
        $errorResponse = ['ok' => false, 'message' => $e->getMessage()];
        echo json_encode($errorResponse);
    }
};

$handlePaymentVerify = function ($tid, $payid, $statusText) use ($bot) {
    // Set the appropriate headers to allow cross-origin requests
    header("Access-Control-Allow-Origin: *"); // Allow requests from any origin (not recommended for production)
    // Set other CORS headers if needed
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token");
    // header("Content-Type: application/json");
    http_response_code(200);
    $db = new DatabaseController;
    $transaction = $db->query("SELECT * FROM `ar_transaction` WHERE `id`=?", [$tid])[0];
    $MerchantID = ZARINPAL_MERCHANT_ID;
    $ZarinGate = false;
    $SandBox = ZARINPAL_SandBox;

    $zp = new zarinpal();
    $result = $zp->verify($MerchantID, $transaction["price"], $SandBox, $ZarinGate);

    if (isset($result["Status"]) && $result["Status"] == 100) {
        // Success
        $status = true;
        $amount = $result["Amount"];
        $refID = $result["RefID"];
        $Authority = $result["Authority"];
        $db->update("ar_transaction", ['status' => 3], '`id`=?', [$tid]);
        $transaction = $db->query("SELECT * FROM `ar_transaction` WHERE `id`=?", [$tid])[0];
        $user = $db->query("SELECT * FROM `ar_user` WHERE `id`=?", [$transaction["user"]])[0];
        $chatId = $db->query("SELECT `user_id` FROM `ar_telegram_sessions` WHERE `session_key`=? AND `session_value`=?", ['mobile', $user['mobile']])[0]["user_id"];
        $bot->increaseWallet($transaction["coin"], $chatId);
        $wallet = $db->query("SELECT * FROM `ar_wallet` WHERE `id`=?", [$user["wallet"]])[0];
        $bot->sendMessage($chatId, "✅✅حساب شما با موفقیت شارژ شد ✅✅\n💰 موجودی: {$wallet['coin']} نفر", ['remove_keyboard' => true]);
    } else {
        // error
        $status = false;
        $statusCode = $result["Status"];
        $message = $result["Message"];
        $db->update("ar_transaction", ['status' => 2], '`id`=?', [$tid]);
        $transaction = $db->query("SELECT * FROM `ar_transaction` WHERE `id`=?", [$tid])[0];
        $user = $db->query("SELECT * FROM `ar_user` WHERE `id`=?", [$transaction["user"]])[0];
        $chatId = $db->query("SELECT `user_id` FROM `ar_telegram_sessions` WHERE `session_key`=? AND `session_value`=?", ['mobile', $user['mobile']])[0]["user_id"];
        $bot->sendMessage($chatId, "⛔️ خطا در پرداخت: " . $result["Message"], ['remove_keyboard' => true]);
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>شارژ حساب</title>
        <!-- <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700&display=swap"> -->
        <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
        <link rel="stylesheet"
            href="https://cdnjs.cloudflare.com/ajax/libs/material-components-web/4.0.0/material-components-web.min.css">
        <style>
            body {
                /* font-family: 'Roboto', sans-serif; */
                margin: 0;
                direction: rtl;
                text-align: center;
            }

            .mdc-layout-grid {
                width: 100%;
                max-width: 1200px;
                margin: 0 auto;
            }

            .mdc-card {
                margin: 16px;
                padding: 20px;
            }

            .mdc-card__title {
                padding: 0 10px;
                font-size: 24px;
                font-weight: 500;
            }

            .mdc-card__subtitle {
                padding: 0 5px;
                font-size: 16px;
                font-weight: 400;
            }

            .mdc-card__body {
                padding: 16px;
            }

            .mdc-button {
                margin: 16px;
            }
        </style>
    </head>

    <body>
        <div class="mdc-layout-grid">
            <div class="mdc-card">
                <div class="mdc-card__title">
                    <?= ($status) ? '<svg xmlns="http://www.w3.org/2000/svg" enable-background="new 0 0 24 24" height="24px" viewBox="0 0 24 24" width="24px" fill="#4CAF50"><rect fill="none" height="24" width="24"/><path d="M22,5.18L10.59,16.6l-4.24-4.24l1.41-1.41l2.83,2.83l10-10L22,5.18z M19.79,10.22C19.92,10.79,20,11.39,20,12 c0,4.42-3.58,8-8,8s-8-3.58-8-8c0-4.42,3.58-8,8-8c1.58,0,3.04,0.46,4.28,1.25l1.44-1.44C16.1,2.67,14.13,2,12,2C6.48,2,2,6.48,2,12 c0,5.52,4.48,10,10,10s10-4.48,10-10c0-1.19-0.22-2.33-0.6-3.39L19.79,10.22z"/></svg> پرداخت با موفقیت انجام شد' : '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#F44336"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M14.59 8L12 10.59 9.41 8 8 9.41 10.59 12 8 14.59 9.41 16 12 13.41 14.59 16 16 14.59 13.41 12 16 9.41 14.59 8zM12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/></svg> مشکلی در پرداخت پیش آمده است'; ?>
                </div>
                <?= ($status) ? "" : "<div class='mdc-card__subtitle'>مشکلی در پرداخت پیش آمده است</div>"; ?>
                <div class="mdc-card__body">
                    <p>
                        <?= ($status) ? "" : "خطا: " . $message; ?>
                    </p>
                    <p>
                        <?= ($status) ? "" : "کد خطا: " . $statusCode; ?>
                    </p>
                    <p>
                        <?= ($status) ? "مبلغ: " . $amount : ""; ?>
                    </p>
                    <p>
                        <?= ($status) ? "کد پیگیری: " . $refID : ""; ?>
                    </p>
                    <p>
                        <?= ($status) ? "Authority: " . $Authority : ""; ?>
                    </p>
                </div>
                <!-- <div class="mdc-card__actions">
                    <a class="mdc-button mdc-button--raised"
                        href="https://material.io/design/material-fantastic-design/">Learn more</a>
                </div> -->
            </div>
        </div>
    </body>

    </html>
    <?php
};

$router->addRoute('GET', '/\/bot\/([a-zA-Z]+)/', $handleWebhook);

$router->addRoute('GET', '/\/payment\/verify\/(\d+)\?Authority=(\d+)&Status=(\w+)/', $handlePaymentVerify);

$router->addRoute('GET', '/\/get\/all/', $getAllData);

$router->addRoute('POST', '/\/users\/delete/', $usersDelete);

$router->addRoute('POST', '/\/users\/add/', $usersAdd);

$router->addRoute('POST', '/\/users\/edit/', $userEdit);

$router->addRoute('POST', '/\/bot/', $handleUpdatesCallback);

// Handle the request
$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'];
$router->handleRequest($method, $path);