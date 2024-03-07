<?php
class TraktTV {
    private $clientId;
    private $user;

    public function __construct($clientId, $user) {
        $this->clientId = $clientId;
        $this->user = $user;
    }

    public function getHistory($limit = 3) {
        $url = "https://api.trakt.tv/users/{$this->user}/history?limit={$limit}";

        $response = wp_remote_get($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'trakt-api-version' => '2',
                'trakt-api-key' => $this->clientId
            ]
        ]);

        if (is_wp_error($response)) {
            return 'Fehler beim Abrufen der Aktivitäten von trakt.tv.';
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return $data ?? [];
    }

    // Weitere Methoden für andere Trakt.tv API-Anfragen...
}
