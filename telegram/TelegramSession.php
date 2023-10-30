<?php
namespace ARUSH\telegram;

trait TelegramSession
{
    private $db;

    public function set($userId, $key, $value)
    {
        if ($this->get($userId, $key) != null) {
            $this->delete($userId, $key);
        }
        $data = [
            'user_id' => $userId,
            'session_key' => $key,
            'session_value' => $value,
        ];

        // Insert or update the session data in the database
        $this->db->upsert('ar_telegram_sessions', $data, ['user_id', 'session_key']);
    }

    public function get($userId, $key, $defaultValue = null)
    {
        // Retrieve the session data from the database
        $data = $this->db->query(
            'SELECT session_value FROM ar_telegram_sessions WHERE user_id = ? AND session_key = ?',
            [$userId, $key]
        );

        if (!empty($data) && isset($data[0]['session_value'])) {
            return $data[0]['session_value'];
        }

        return $defaultValue;
    }

    public function delete($userId, $key)
    {
        // Delete the session data from the database
        $this->db->delete(
            'ar_telegram_sessions',
            'user_id = ? AND session_key = ?',
            [$userId, $key]
        );
    }
}