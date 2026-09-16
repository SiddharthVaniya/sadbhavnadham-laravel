<?php

namespace App\Http\Controllers;

use Google_Client;
use Google_Service_Sheets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleOAuthController extends Controller
{
    public function redirectToGoogle(Request $request): RedirectResponse
    {
        $client = $this->makeGoogleClient();
        $client->setPrompt('consent');
        $client->setState($state = Str::random(40));

        $request->session()->put('google_oauth_state', $state);

        $authUrl = $client->createAuthUrl();

        return redirect($authUrl);
    }

    public function handleGoogleCallback(Request $request): Response
    {
        $expectedState = (string) $request->session()->pull('google_oauth_state', '');
        $providedState = (string) $request->query('state', '');

        if ($expectedState === '' || ! hash_equals($expectedState, $providedState)) {
            return response('Invalid Google OAuth state. Please retry from /google-auth.', 422);
        }

        if ($request->filled('error')) {
            return response(
                'Google authorization failed: '.(string) $request->query('error'),
                422
            );
        }

        if (! $request->filled('code')) {
            return response('Authorization code is missing. Please retry from /google-auth.', 422);
        }

        $client = $this->makeGoogleClient();

        try {
            $accessToken = $client->fetchAccessTokenWithAuthCode((string) $request->query('code'));
        } catch (\Throwable $exception) {
            Log::warning('Google OAuth callback failed', [
                'message' => $exception->getMessage(),
            ]);

            return response('Invalid or expired authorization code. Please retry from /google-auth.', 422);
        }

        if (isset($accessToken['error'])) {
            return response(
                'Error retrieving token: '.($accessToken['error_description'] ?? $accessToken['error']),
                422
            );
        }

        file_put_contents(storage_path('app/google-token.json'), json_encode($accessToken));

        return response('Google Sheets token saved successfully!');
    }

    private function makeGoogleClient(): Google_Client
    {
        $client = new Google_Client;
        $client->setClientId((string) config('services.google.client_id'));
        $client->setClientSecret((string) config('services.google.client_secret'));
        $client->setRedirectUri((string) config('services.google.redirect_uri', url('/oauth2callback')));
        $client->addScope(Google_Service_Sheets::SPREADSHEETS);
        $client->setAccessType('offline');

        return $client;
    }
}
