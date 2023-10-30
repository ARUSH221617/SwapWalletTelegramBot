<?php

namespace ARUSH\telegram;

trait TelegramWebhook {
    private string $botToken;
    private string $apiBaseUrl;

    public function setWebhook($webhookUrl) {
        $url = "{$this->apiBaseUrl}/setWebhook";
        $data = [
            'url' => $webhookUrl,
        ];
        return $this->sendRequest($url, $data, 'GET');
    }

    public function removeWebhook() {
        $url = "{$this->apiBaseUrl}/deleteWebhook";
        return $this->sendRequest($url);
    }
}
