<?php

namespace App\Services;

use Google_Client;
use Google_Service_Sheets;
use Illuminate\Support\Facades\Log;

class GoogleSheetsClientFactory
{
    public function make(): Google_Service_Sheets
    {
        $client = new Google_Client;
        $client->setClientId((string) config('services.google.client_id'));
        $client->setClientSecret((string) config('services.google.client_secret'));
        $client->setRedirectUri((string) config('services.google.redirect_uri'));
        $client->addScope(Google_Service_Sheets::SPREADSHEETS);
        $client->setAccessType('offline');

        $tokenPath = storage_path('app/google-token.json');

        if (! file_exists($tokenPath)) {
            Log::critical('Google token file missing: '.$tokenPath);
            throw new \Exception('Google token file not found.');
        }

        $token = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($token);

        if ($client->isAccessTokenExpired()) {
            $refreshToken = $client->getRefreshToken();
            if (! $refreshToken) {
                throw new \Exception('Google refresh token missing.');
            }

            $client->fetchAccessTokenWithRefreshToken($refreshToken);
            file_put_contents($tokenPath, json_encode($client->getAccessToken()));
        }

        return new Google_Service_Sheets($client);
    }
}
