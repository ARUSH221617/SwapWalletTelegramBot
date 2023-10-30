<?php

namespace ARUSH\telegram;

use ARUSH\DatabaseController;
use Exception;
use ARUSH\payment\zarinpal;

class telegram
{
    use TelegramFileDownloader;
    use TelegramMessageSender;
    use TelegramPayment;
    use TelegramWebhook;
    use TelegramSession;

    public $userId;

    public function __construct($botToken)
    {
        $this->db = new DatabaseController;
        $this->botToken = $botToken;
        $this->apiBaseUrl = 'https://api.telegram.org/bot' . $this->botToken;
        $this->chatId = 0;
        $this->userId = 0;
    }

    public function getUpdates()
    {
        $url = $this->apiBaseUrl . '/getUpdates';
        $response = $this->sendRequest($url);

        try {
            $updates = json_decode($response);

            if (!isset($updates->result)) {
                throw new Exception('Invalid response: ' . $response);
            }

            return $updates->result;
        } catch (Exception $e) {
            error_log($e->getMessage());
            return null;
        }
    }


    public function sendRequest($url, $data = [], $method = 'POST')
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

    public function handleTextMessage($command, $username)
    {
        switch (true) {
            case ($command == "شروع مجدد 🔄"):
            case ($command == "/start"):
                $is_login = $this->get($username, 'is_login');
                if ($is_login) {
                    $mobile = $this->get($username, 'mobile');
                    $this->handlelogin($mobile, $username);
                    break;
                }
                $responseText = "🟡🟡 سلام به AdminGeram خوش آمدی!\n برای ادامه و ورود به اکانت خود شماره موبایل خود را ارسال کنید 🟡🟡";
                $keyboard = [
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true,
                    'keyboard' => [
                        [
                            [
                                'text' => '📱 ارسال شماره موبایل',
                                'request_contact' => true,
                                // Request contact here
                            ],
                        ],
                    ],
                ];
                $this->sendMessage($this->chatId, $responseText, $keyboard);
                break;
            case ($command == "شارژ حساب 💰"):
                $is_login = $this->get($username, 'is_login');
                if ($is_login) {
                    $mobile = $this->get($username, 'mobile');
                    // $this->handleIncreaseWalletCoin();
                    $responseText = "🟡🟡 لطفا تعداد کاربر مورد نیاز را بصورت عدد انگلیسی و به همراه متن /coin بفرستید 🟡🟡\n مثال: <code>/coin 100</code>";
                    $keyboard = [
                        'resize_keyboard' => true,
                        'one_time_keyboard' => true,
                        'keyboard' => [
                            [
                                [
                                    'text' => 'شروع مجدد 🔄'
                                ],
                            ],
                        ],
                    ];
                    $this->sendMessage($this->chatId, $responseText, $keyboard, 'HTML');
                    break;
                }
                $responseText = "🟡🟡 سلام به AdminGeram خوش آمدی!\n برای ادامه و ورود به اکانت خود شماره موبایل خود را ارسال کنید 🟡🟡";
                $keyboard = [
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true,
                    'keyboard' => [
                        [
                            [
                                'text' => '📱 ارسال شماره موبایل',
                                'request_contact' => true,
                                // Request contact here
                            ],
                            [
                                'text' => 'شروع مجدد 🔄'
                            ]
                        ],
                    ],
                ];
                $this->sendMessage($this->chatId, $responseText, $keyboard);
                break;
            case preg_match('/^(.*?)\n(\d+)$/', $command, $matches):
                $name = $matches[1]; // Captured name
                $phoneNumber = $matches[2]; // Captured phone number
                $this->handleAddMember($name, $phoneNumber);
                break;
            case (preg_match('/^\/([a-zA-Z]+)\s(\d+)$/', $command, $matches) && $matches[1] == "coin"):
                // $this->sendMessage($this->chatId, "test");
                $command = $matches[1]; // This will contain the command, e.g., "/coin"
                $number = $matches[2]; // This will contain the number, e.g., "100"
                $this->handleIncreaseWalletCoin($number);
                break;
            default:
                $responseText = "⛔️❌ درخواست شما نامعتبر است ❌⛔️";
                $this->sendMessage($this->chatId, $responseText, [
                    'keyboard' => [
                        [
                            [
                                'text' => 'شروع مجدد 🔄'
                            ],
                        ],
                    ],
                    'resize_keyboard' => true,
                ]);
                break;
        }
    }

    public function handleIncreaseWalletCoin($coin)
    {
        $exc = $this->getExchangeCurrency();
        $this->sendMessage($this->chatId, "💰قیمت اضافه کردن هر 👤 کاربر {$exc['price']} " . ($exc['currency'] == 'TOM' ? 'تومان' : ($exc['currency'] == "USD" ? 'دلار' : '')) . " است\nمبلغ قابل پرداخت: 💰" . $coin * $exc['price'] . ($exc['currency'] == 'TOM' ? 'تومان' : ($exc['currency'] == "USD" ? 'دلار' : '')) . "\n👇 نوع پرداخت را انتخاب کنید 👇", [
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
            'inline_keyboard' => [
                [
                    [
                        'text' => 'خرید با زرینپال',
                        'callback_data' => 'selected_payment_method_zarinpal_coin_' . $coin
                    ],
                    // [
                    //     'text' => 'خرید با تلگرام',
                    //     'callback_data' => 'selected_payment_method_telegram_coin_' . $coin
                    // ]
                ],
            ],
        ]);

        return;
    }

    public function handleCallbackData($update): void
    {
        // Handle callback data here based on your application's logic
        // You can use a switch statement or any other method to process the data
        // Example:
        $callbackData = $update->callback_query->data;

        switch (true) {
            case preg_match('/^product_selected_(\w+)_(\d+)$/', $callbackData, $matches):
                // Extracted values from the callbackData
                $phone = $matches[1];
                $productId = $matches[2];

                // Handle the product selection with $productId and $userId
                $this->handleProductSelection($phone, $productId);
                break;
            case preg_match('/^selected_payment_method_(\w+)_coin_(\d+)$/', $callbackData, $matches):
                $paymentMethod = $matches[1];
                $coin = $matches[2];
                $this->handlePaymentMethod($paymentMethod, $coin);
                break;
            default:
                error_log($callbackData);
                // Handle unknown callback data
                break;
        }
    }

    public function handlePaymentMethod($paymentMethod, $coin)
    {
        switch ($paymentMethod) {
            // case 'telegram':
            //     $this->handlePaymentByTelegram($coin);
            //     break;
            case 'zarinpal':
                $this->handlePaymentByZarinpal($coin);
                break;
            default:
                break;
        }
    }

    // public function handlePaymentByTelegram($coin)
    // {
    //     $exc = $this->getExchangeCurrency();
    //     $this->sendInvoice("خرید {$coin} کاربر", "test", $exc['currency'], $exc['price'], "");
    // }

    public function handlePaymentByZarinpal($coin)
    {
        $is_login = $this->get($this->chatId, 'is_login');
        if ($is_login) {
            $phone = $this->get($this->chatId, "mobile");
            $exc = $this->getExchangeCurrency();
            $MerchantID = ZARINPAL_MERCHANT_ID;
            $Amount = (intval($coin) * intval($exc['price'])) / 10;
            $transaction = $this->createTransaction($Amount . $exc["currency"], $coin, 1);
            $Description = "خرید اشتراک اضافه کردن 👤کاربر به تعداد {$coin}";
            $Email = "";
            $Mobile = $phone;
            $CallbackURL = BOT_ENDPOINT . "/payment/verify/{$transaction['id']}";
            $ZarinGate = false;
            $SandBox = ZARINPAL_SandBox;
            // $exc['currency'], $exc['price']

            $zp = new zarinpal();
            $result = $zp->request($MerchantID, $Amount, $Description, $Email, $Mobile, $CallbackURL, $SandBox, $ZarinGate);

            if (isset($result["Status"]) && $result["Status"] == 100) {
                // Success and redirect to pay
                $this->sendMessage($this->chatId, "👇 برای پرداخت روی لینک زیر کلیک کنید 👇\n" . $result["StartPay"], ['remove_keyboard' => true]);
            } else {
                // error
                $this->sendMessage($this->chatId, "❌ خطا در ایجاد تراکنش ❌\nکد خطا : " . $result["Status"] . "\nتفسیر و علت خطا : " . $result["Message"], [
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true,
                    'inline_keyboard' => [
                        [
                            [
                                'text' => 'خرید با زرینپال',
                                'callback_data' => 'selected_payment_method_zarinpal_coin_' . $coin
                            ],
                            [
                                'text' => 'خرید با تلگرام',
                                'callback_data' => 'selected_payment_method_telegram_coin_' . $coin
                            ]
                        ],
                    ],
                ]);
                error_log("خطا در ایجاد تراکنش" . "<br />کد خطا : " . $result["Status"] . "<br />تفسیر و علت خطا : " . $result["Message"]);
            }
        } else {
            return false;
        }
    }

    public function handleProductSelection($phone, $productId): void
    {
        $wallet = $this->checkWallet();
        if ($wallet["coin"] > 0) {
            $responseText = "کاربر های خود را ارسال کنید 🤓🧐";
            $this->sendMessage($this->chatId, $responseText, ['remove_keyboard' => true]);
            $this->set($this->chatId, 'product_selected', "$productId");
        } else {
            $responseText = "❌❌ اعتبار شما به اتمام رسیده است ❌❌";
            $this->sendMessage($this->chatId, $responseText, [
                'keyboard' => [
                    [
                        [
                            'text' => 'شارژ حساب 💰'
                        ],
                        [
                            'text' => 'شروع مجدد 🔄'
                        ],
                    ],
                ],
                'resize_keyboard' => true,
            ]);
            $this->delete($this->chatId, 'product_selected');
        }
        return;
    }

    public function handleAddMember($fullname, $mobile)
    {
        try {
            $product_id = $this->get($this->chatId, 'product_selected');
            $admin_mobile = $this->get($this->chatId, 'mobile');
            $user = $this->db->query("SELECT * FROM `ar_user` WHERE `mobile`=?", [$admin_mobile])[0];
            $site = $this->db->query("SELECT * FROM `ar_site` WHERE `user`=?", [$user["id"]])[0];
            $response = json_decode($this->sendRequest("https://" . $site["siteurl"] . AR_MODULE_URL . "/add_user", [
                'mobile' => $mobile,
                'fullname' => $fullname,
                'product' => $product_id
            ], 'POST'), false);
            if (is_object($response) && $response->ok) {
                $wallet = $this->db->query("SELECT * FROM `ar_wallet` WHERE `id`=?", [$user["wallet"]])[0];
                $this->db->update("ar_wallet", ["coin" => intval($wallet["coin"]) - 1], "`id`=?", [$wallet["id"]]);
                $responseText = "✅✅ کاربر با موفقیت ثبت نام شد ✅✅\nپیغام سایت: " . $response->message;
                $this->sendMessage($this->chatId, $responseText, [
                    'keyboard' => [
                        [
                            [
                                'text' => 'شروع مجدد 🔄'
                            ],
                        ],
                    ],
                    'resize_keyboard' => true,
                ], 'HTML');
                $this->db->insert('ar_memberadded', [
                    'fullname' => $fullname,
                    'username' => $mobile,
                    'mobile' => $mobile,
                    'site' => $site["id"],
                    'product' => $product_id,
                ]);
            } else if (is_object($response)) {
                $this->sendMessage($this->chatId, $response->message, [
                    'keyboard' => [
                        [
                            [
                                'text' => 'شروع مجدد 🔄'
                            ],
                        ],
                    ],
                    'resize_keyboard' => true,
                ]);
            } else {
                $this->sendMessage($this->chatId, "پاسخی از ماژول دریافت نشد!", [
                    'keyboard' => [
                        [
                            [
                                'text' => 'شروع مجدد 🔄'
                            ],
                        ],
                    ],
                    'resize_keyboard' => true,
                ]);
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            $this->sendMessage($this->chatId, "An error occurred: " . $e->getMessage());
        }
        return;
    }



    public function handlelogin($phone, $userId)
    {
        $user = $this->db->query("SELECT * FROM `ar_user` WHERE `mobile`=?", [$phone])[0];
        $site = $this->db->query("SELECT * FROM `ar_site` WHERE `user`=?", [$user["id"]])[0];

        $is_login = $this->get($userId, 'is_login');
        if ($is_login == null) {
            $this->set($userId, 'is_login', 'true');
            $this->set($userId, 'mobile', $phone);
        }

        // Send a request to demo.arush.ir to get product data
        $products = json_decode($this->sendRequest("https://" . $site["siteurl"] . WP_MODULE_URL . "/product", [], 'GET'), false);

        $inline_keyboard = [];

        foreach ($products as $product) {
            $inline_keyboard[] = [
                'text' => $product->title->rendered,
                'callback_data' => 'product_selected_' . md5($phone) . '_' . $product->id // Replace (userid) and (id) with actual values
            ];
        }

        $responseText = "محصول مورد نظر را انتخاب کنید 👇";
        $this->sendMessage($this->chatId, $responseText, [
            'inline_keyboard' => [$inline_keyboard]
        ]);
        return;
    }

    public function handleContactMessage($contact)
    {
        if (!isset($contact)) {
            $responseText = "❌ لطفا با استفاده از 'ارسال شماره موبایل' از پایین صفحه اقدام به ارسال شماره موبایل نمایید";
            $this->sendMessage($this->chatId, $responseText, ['remove_keyboard' => true]);
            return;
        }

        $phoneNumber = $contact->phone_number;
        $userId = $contact->user_id;

        $pattern = '/^\+(\d{2})(\d+)/'; // Match the country code (2 digits) and the rest of the number
        $replacement = '$2'; // Replace with the second captured group (the rest of the number)
        $cleanedPhoneNumber = preg_replace($pattern, $replacement, $phoneNumber);

        try {

            if ($this->db->exists('ar_user', 'mobile=?', ["$cleanedPhoneNumber"])) {

                $responseText = "شما با موفقیت به ربات وارد شدید ";
                $this->sendMessage($this->chatId, $responseText);
                $this->handlelogin($cleanedPhoneNumber, $userId);
            } else {
                $responseText = "این شماره موبایل در ربات ثبت نام نکرده است 🥲";
                $this->sendMessage($this->chatId, $responseText);
            }
        } catch (Exception $e) {
            $responseText = "یه مشکلی پیش اومده لطفا مجددا تلاش کنید و اگر دوباره این پیام را دیدید به پشتیبانی اطلاع دهید. 🥲";
            $this->sendMessage($this->chatId, $responseText, [
                'keyboard' => [
                    [
                        [
                            'text' => 'شروع مجدد 🔄'
                        ],
                    ],
                ],
                'resize_keyboard' => true,
            ]);
            error_log($e->getMessage());
        }
    }

    public function checkWallet($chatId = null)
    {
        if ($chatId == null) {
            $chatId = $this->chatId;
        }
        $is_login = $this->get($chatId, 'is_login');
        if ($is_login) {
            $phone = $this->get($chatId, "mobile");
            $user = $this->db->query("SELECT * FROM `ar_user` WHERE `mobile`=?", [$phone])[0];

            if (!$this->db->exists("ar_wallet", "`user`=?", [$user["id"]])) {
                $wallet = $this->db->insert("ar_wallet", [
                    "user" => $user["id"],
                    "price" => "0TOM",
                    "coin" => $user["coin"],
                    "status" => 1,
                    "type" => 1
                ]);
                $wallet = $this->db->query("SELECT * FROM `ar_wallet` WHERE `id`=?", [$wallet]);
            } else {
                $wallet = $this->db->query("SELECT * FROM `ar_wallet` WHERE `user`=?", [$user["id"]]);
            }
            return $wallet[0];
        } else {
            return false;
        }
    }

    public function getExchangeCurrency($chatId = null)
    {
        if ($chatId == null) {
            $chatId = $this->chatId;
        }
        $is_login = $this->get($chatId, 'is_login');
        if ($is_login) {
            $phone = $this->get($chatId, "mobile");
            $user = $this->db->query("SELECT * FROM `ar_user` WHERE `mobile`=?", [$phone])[0];
            $site = $this->db->query("SELECT * FROM `ar_site` WHERE `user`=?", [$user["id"]])[0];

            if (!$this->db->exists("ar_exchange_currency", "`user`=? AND `site`=?", [$user["id"], $site["id"]])) {
                $exchangeCurrency = $this->db->insert("ar_exchange_currency", [
                    "user" => $user["id"],
                    "site" => $site["id"],
                    "price" => 10000,
                    "currency" => "TOM",
                    "coin" => 1
                ]);
                $exchangeCurrency = $this->db->query("SELECT * FROM `ar_exchange_currency` WHERE `id`=?", [$exchangeCurrency]);
            } else {
                $exchangeCurrency = $this->db->query("SELECT * FROM `ar_exchange_currency` WHERE `user`=? AND `site`=?", [$user["id"], $site["id"]]);
            }
            return $exchangeCurrency[0];
        } else {
            return false;
        }
    }

    public function createTransaction(string $price, int $coin, int $type = 1)
    {
        $is_login = $this->get($this->chatId, 'is_login');
        if ($is_login) {
            $phone = $this->get($this->chatId, "mobile");
            $user = $this->db->query("SELECT * FROM `ar_user` WHERE `mobile`=?", [$phone])[0];
            $site = $this->db->query("SELECT * FROM `ar_site` WHERE `user`=?", [$user["id"]])[0];

            $matches = [];
            preg_match("/^(\d+)([A-Za-z]+)$/", $price, $matches);
            $price = $matches[1];
            $priceCurrency = $matches[2];

            $transactionId = $this->db->insert("ar_transaction", [
                "user" => $user["id"],
                "site" => $site["id"],
                "price" => $price,
                "currency" => $priceCurrency,
                "coin" => $coin,
                "type" => $type,
                "status" => 1
            ]);
            $transaction = $this->db->query("SELECT * FROM `ar_transaction` WHERE `id`=?", [$transactionId]);
            return $transaction[0];
        } else {
            return false;
        }
    }

    public function increaseWallet($coin, $chatId = null)
    {
        if ($chatId == null) {
            $chatId = $this->chatId;
        }
        $is_login = $this->get($chatId, 'is_login');
        if ($is_login) {
            $wallet = $this->checkWallet($chatId);
            if (!$wallet) {
                return false;
            }
            $phone = $this->get($chatId, "mobile");
            $user = $this->db->query("SELECT * FROM `ar_user` WHERE `mobile`=?", [$phone])[0];
            $wallet = $this->db->query("SELECT * FROM `ar_wallet` WHERE `user`=?", [$user["id"]])[0];
            $uwallet = $this->db->update("ar_wallet", ["coin" => intval($wallet["coin"]) + intval($coin)], "`id`=?", [$wallet["id"]]);
            return $uwallet;
        } else {
            return false;
        }
    }

    public function handleRequest(): void
    {
        try {
            $update = json_decode(file_get_contents('php://input'), false);
            if (!empty($update->callback_query)) {
                $chatId = $update->callback_query->message->chat->id;
                $this->chatId = $chatId;
                $this->userId = $update->callback_query->message->from->id;
                // Handle callback queries if available
                $this->handleCallbackData($update);
                return;
            } else if (!empty($update->message)) {
                $chatId = $update->message->chat->id;
                $this->chatId = $chatId;
                $this->userId = $update->message->from->id;
                $is_login = $this->get($chatId, 'is_login');
                // Handle callback queries if available
                switch (true) {
                    case isset($update->message->text):
                        $this->handleTextMessage($update->message->text, $update->message->from->id);
                        break;
                    case (isset($update->message->contact) && $is_login == null):
                        $this->handleContactMessage($update->message->contact);
                        break;
                    default:
                        $responseText = "درخواست ما معتبر نیست 🙁";
                        $this->sendMessage($this->chatId, $responseText, [
                            'keyboard' => [
                                [
                                    [
                                        'text' => 'شروع مجدد 🔄'
                                    ],
                                ],
                            ],
                            'resize_keyboard' => true,
                        ]);
                        break;
                }
            }
        } catch (Exception $e) {
            // Log the error
            error_log("An error occurred: " . $e->getMessage());

            // Handle the error gracefully, for example, send an error message to the user
            $this->sendMessage($this->chatId, "An error occurred while processing your request. Please try again later.");
        }
    }
}