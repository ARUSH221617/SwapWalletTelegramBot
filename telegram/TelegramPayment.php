<?php

namespace ARUSH\telegram;

trait TelegramPayment {
    private string $botToken;
    private string $chatId;

    public function sendInvoice($title, $description, $currency, $price, $payload) {
        $data = [
            'chat_id' => $this->chatId,
            'title' => $title,
            'description' => $description,
            'currency' => $currency,
            'total_amount' => $price,
            'start_parameter' => $payload,
            'payload' => $payload,
            'provider_token' => 'YOUR_PROVIDER_TOKEN', // توکن پروایدر پرداخت
            'provider_data' => json_encode(['key' => 'value']), // اطلاعات پروایدر پرداخت
        ];

        $url = 'https://api.telegram.org/bot' . $this->botToken . '/sendInvoice';
        $response = $this->sendRequest($url, $data);

        return $response;
    }

    public function answerShippingQuery($shippingQueryId, $ok, $shippingOptions = []) {
        $data = [
            'shipping_query_id' => $shippingQueryId,
            'ok' => $ok,
            'shipping_options' => json_encode($shippingOptions),
        ];

        $url = 'https://api.telegram.org/bot' . $this->botToken . '/answerShippingQuery';
        $response = $this->sendRequest($url, $data);

        return $response;
    }

    public function answerPreCheckoutQuery($preCheckoutQueryId, $ok, $errorMessage = '') {
        $data = [
            'pre_checkout_query_id' => $preCheckoutQueryId,
            'ok' => $ok,
            'error_message' => $errorMessage,
        ];

        $url = 'https://api.telegram.org/bot' . $this->botToken . '/answerPreCheckoutQuery';
        $response = $this->sendRequest($url, $data);

        return $response;
    }
}

// مثال استفاده از کلاس
//$botToken = 'YOUR_BOT_TOKEN';
//$chatId = 'YOUR_CHAT_ID';
//$telegramPayment = new TelegramPayment($botToken, $chatId);
//
//$title = 'Product';
//$description = 'Description of the product';
//$currency = 'USD';
//$price = 10.00;
//$payload = 'custom_payload';
//
//$response = $telegramPayment->sendInvoice($title, $description, $currency, $price, $payload);
//
//echo $response;
