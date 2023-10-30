<?php

namespace ARUSH\telegram;

trait TelegramMessageSender
{
    private string $botToken;
    private string $apiBaseUrl;

    public function sendMessage($chatId, $text, $reply_markup = [], $parse_mode = '')
    {
        $sendData = [
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => json_encode($reply_markup)
        ];

        if ($parse_mode != '') {
            $sendData['parse_mode'] = $parse_mode;
        }

        return $this->sendRequest($this->apiBaseUrl . '/sendMessage', $sendData);
    }

    public function sendPhoto($chatId, $photoUrl, $caption = '')
    {
        $data = [
            'chat_id' => $chatId,
            'photo' => $photoUrl,
            'caption' => $caption,
        ];

        return $this->sendRequest('sendPhoto', $data);
    }

    public function sendVideo($chatId, $videoUrl, $caption = '')
    {
        $data = [
            'chat_id' => $chatId,
            'video' => $videoUrl,
            'caption' => $caption,
        ];

        return $this->sendRequest('sendVideo', $data);
    }

    public function sendContact($chatId, $phoneNumber, $firstName, $lastName = '', $vCard = '')
    {
        $data = [
            'chat_id' => $chatId,
            'phone_number' => $phoneNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'vcard' => $vCard,
        ];

        return $this->sendRequest('sendContact', $data);
    }

    public function getUserProfilePhoto($userId, $offset = 0, $limit = 1)
    {
        $data = [
            'user_id' => $userId,
            'offset' => $offset,
            'limit' => $limit,
        ];

        $response = $this->sendRequest('getUserProfilePhotos', $data);

        $result = json_decode($response, true);
        if ($result['ok']) {
            // Retrieve user profile photos
            $photos = $result['result']['photos'];
            return $photos;
        } else {
            return false;
        }
    }

    public function sendChatAction($chatId, $action)
    {
        $data = [
            'chat_id' => $chatId,
            'action' => $action,
        ];

        return $this->sendRequest('sendChatAction', $data);
    }

    public function sendToAllActiveChats($message)
    {
        $updates = $this->getUpdates();

        foreach ($updates as $update) {
            $chatId = $update->message->chat->id;
            $this->sendMessage($chatId, $message);
        }
    }
}

// مثال استفاده از کلاس
//$botToken = 'YOUR_BOT_TOKEN';
//$telegramMessageSender = new TelegramMessageSender($botToken);

//$chatId = 'TARGET_CHAT_ID'; // شناسه چت مقصد

// ارسال پیام متنی
//$textMessage = 'این یک پیام متنی است.';
//$response = $telegramMessageSender->sendMessage($chatId, $textMessage);

// ارسال عکس با توضیحات
//$photoUrl = 'URL_OF_YOUR_IMAGE';
//$caption = 'توضیحات عکس';
//$response = $telegramMessageSender->sendPhoto($chatId, $photoUrl, $caption);

// ارسال ویدیو با توضیحات
//$videoUrl = 'URL_OF_YOUR_VIDEO';
//$caption = 'توضیحات ویدیو';
//$response = $telegramMessageSender->sendVideo($chatId, $videoUrl, $caption);

//$botToken = 'YOUR_BOT_TOKEN';
//$telegramMessageSender = new TelegramMessageSender($botToken);

//$chatId = 'TARGET_CHAT_ID'; // شناسه چت مقصد

//$phoneNumber = '1234567890';
//$firstName = 'John';
//$lastName = 'Doe';
//$vCard = ''; // اختیاری، اگر نیازی ندارید می‌توانید رشته خالی بگذارید

//$response = $telegramMessageSender->sendContact($chatId, $phoneNumber, $firstName, $lastName, $vCard);

// مثال استفاده از کلاس
//$botToken = 'YOUR_BOT_TOKEN';
//$telegramMessageSender = new TelegramMessageSender($botToken);
//
//$message = 'این یک پیام تست به تمام چت‌هاست.';
//$telegramMessageSender->sendToAllActiveChats($message);

// مثال استفاده از کلاس
//$botToken = 'YOUR_BOT_TOKEN';
//$telegramUserProfilePhoto = new TelegramUserProfilePhoto($botToken);
//
//$userId = 'USER_ID'; // شناسه کاربر مورد نظر
//$photos = $telegramUserProfilePhoto->getUserProfilePhoto($userId);
//
//if ($photos) {
//    // اطلاعات تصاویر پروفایل کاربر
//    print_r($photos);
//} else {
//    echo 'دریافت تصاویر پروفایل با مشکل مواجه شد.';
//}

// متدهای دیگر برای ارسال مخاطب، مکان، و ... نیز به همین شکل فراخوانی می‌شوند.
