<?php

namespace ARUSH\telegram;

trait TelegramFileDownloader {
    private string $botToken;

    public function downloadFileFromMessage($fileId, $downloadPath) {
        // Step 1: Get the file path using getFile method
        $fileInfo = $this->getFileInfo($fileId);

        if ($fileInfo) {
            $fileUrl = 'https://api.telegram.org/file/bot' . $this->botToken . '/' . $fileInfo['file_path'];

            // Step 2: Download the file using file_get_contents
            $fileContents = file_get_contents($fileUrl);

            if ($fileContents) {
                // Step 3: Save the file to the desired location
                file_put_contents($downloadPath, $fileContents);
                return true; // Download successful
            }
        }

        return false; // Download failed
    }

    private function getFileInfo($fileId) {
        $url = 'https://api.telegram.org/bot' . $this->botToken . '/getFile';
        $data = ['file_id' => $fileId];

        $response = $this->sendRequest($url, $data);
        $result = json_decode($response, true);

        if ($result['ok']) {
            return $result['result'];
        } else {
            return false;
        }
    }
}


// Example usage:
//$botToken = 'YOUR_BOT_TOKEN';
//$telegramFileDownloader = new TelegramFileDownloader($botToken);
//
//$fileId = 'FILE_ID_FROM_USER_MESSAGE'; // Replace with the actual file ID
//$downloadPath = 'path/to/save/downloaded/file.jpg'; // Specify the local file path to save the downloaded file
//
//if ($telegramFileDownloader->downloadFileFromMessage($fileId, $downloadPath)) {
//    echo 'File downloaded successfully.';
//} else {
//    echo 'Failed to download the file.';
//}

//// Construct the full URL for downloading the file
//$fileUrl = 'https://api.telegram.org/file/bot' . $botToken . '/' . $filePath;
//
//// Download the file
//$fileContents = file_get_contents($fileUrl);
//
//// Save the downloaded file to a local path
//file_put_contents('local_path/file_name.extension', $fileContents);

