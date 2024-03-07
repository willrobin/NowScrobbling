<?php
class LastFM {
    private $apiKey;
    private $user;

    public function __construct($apiKey, $user) {
        $this->apiKey = $apiKey;
        $this->user = $user;
    }

    public function getRecentTracks($limit = 3) {
        $url = "http://ws.audioscrobbler.com/2.0/?method=user.getrecenttracks&user={$this->user}&api_key={$this->apiKey}&limit={$limit}&format=json";

        $response = wp_remote_get($url);
        if (is_wp_error($response)) {
            return 'Fehler beim Abrufen der Scrobbles.';
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return $data['recenttracks']['track'] ?? [];
    }
}
