<?php

namespace App\Console\Commands;

use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('drive:authorize')]
#[Description('One-time OAuth flow to mint a Google Drive refresh token')]
class AuthorizeGoogleDrive extends Command
{
    private const REDIRECT_URI = 'http://localhost';

    public function handle(): int
    {
        $clientId = config('services.google_drive.client_id');
        $clientSecret = config('services.google_drive.client_secret');

        if (! $clientId || ! $clientSecret) {
            $this->error('Set GOOGLE_DRIVE_CLIENT_ID and GOOGLE_DRIVE_CLIENT_SECRET in .env first.');
            $this->line('Create an OAuth 2.0 "Desktop app" client at https://console.cloud.google.com/apis/credentials');

            return self::FAILURE;
        }

        $client = new GoogleClient;
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri(self::REDIRECT_URI);
        $client->addScope(GoogleDrive::DRIVE_FILE);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        $authUrl = $client->createAuthUrl();

        $this->newLine();
        $this->line('1. Open this URL in your browser:');
        $this->line('');
        $this->line('   '.$authUrl);
        $this->line('');
        $this->line('2. Authorize access. Your browser will redirect to a "site can\'t be reached" page at');
        $this->line('   '.self::REDIRECT_URI.'/?code=… — that\'s expected.');
        $this->line('');
        $this->line('3. Copy the full redirect URL from the browser address bar and paste it below.');
        $this->newLine();

        $pasted = trim((string) $this->ask('Paste the redirect URL'));
        $code = $this->extractCode($pasted);

        if (! $code) {
            $this->error('Could not find `code=` in the URL you pasted.');

            return self::FAILURE;
        }

        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            $this->error('Google rejected the code: '.($token['error_description'] ?? $token['error']));

            return self::FAILURE;
        }

        if (empty($token['refresh_token'])) {
            $this->error('Google did not return a refresh_token. Revoke access at https://myaccount.google.com/permissions and try again.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Refresh token minted. Add this line to your .env (local and production):');
        $this->newLine();
        $this->line('GOOGLE_DRIVE_REFRESH_TOKEN='.$token['refresh_token']);
        $this->newLine();

        return self::SUCCESS;
    }

    private function extractCode(string $input): ?string
    {
        if (str_starts_with($input, 'http')) {
            parse_str((string) parse_url($input, PHP_URL_QUERY), $params);

            return $params['code'] ?? null;
        }

        return $input !== '' ? $input : null;
    }
}
