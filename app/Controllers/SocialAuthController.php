<?php
namespace App\Controllers;

use PDO;
use App\Core\Controller;
use App\Core\CsrfProtection;
use App\Models\MemberModel;
use Exception;

class SocialAuthController extends Controller
{
    private MemberModel $memberModel;

    /**
     * Inject the database connection and build the member model.
     */
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->memberModel = new MemberModel($pdo);
    }

    /**
     * Build an absolute redirect URI from the configured APP_URL.
     */
    private function redirectUri(string $path): string
    {
        $base = rtrim((string) \App\Core\Dotenv::get('APP_URL', ''), '/');
        return $base . $path;
    }

    /**
     * Validate the OAuth state parameter and clear it from the session.
     */
    private function verifyOAuthState(): bool
    {
        $providedState = '';
        if (isset($_GET['state'])) {
            $providedState = (string) $_GET['state'];
        }
        $sessionState = '';
        if (isset($_SESSION['oauth_state'])) {
            $sessionState = (string) $_SESSION['oauth_state'];
        }

        unset($_SESSION['oauth_state']);

        if ($sessionState === '' || $providedState === '') {
            return false;
        }

        return hash_equals($sessionState, $providedState);
    }

    /**
     * Perform an HTTP request and return the decoded JSON response as an array.
     */
    private function httpRequest(string $url, array $postFields, array $headers): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        if (!empty($postFields)) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        curl_close($ch);

        if (!is_string($response)) {
            return [];
        }

        $decoded = json_decode($response, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        return [];
    }

    /**
     * Complete social login by linking, creating, or signing in the member.
     */
    private function finishSocialLogin(string $provider, string $providerId, string $email): void
    {
        $member = null;
        $stmt = $this->pdo->prepare('SELECT user_id FROM user_social_accounts WHERE provider = ? AND provider_id = ?');
        $stmt->execute([$provider, $providerId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row && isset($row['user_id'])) {
            $member = $this->memberModel->findById((int) $row['user_id']);
        }

        if (!$member) {
            $member = $this->memberModel->findByEmail($email);
            if ($member) {
                try {
                    $this->memberModel->linkSocialAccount((int) $member['id'], $provider, $providerId);
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Could not link this account.';
                    $this->redirect('/login');
                    return;
                }
            } else {
                try {
                    $memberId = $this->memberModel->register($email, bin2hex(random_bytes(16)), '');
                    $this->memberModel->linkSocialAccount($memberId, $provider, $providerId);
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Could not link this account.';
                    $this->redirect('/login');
                    return;
                }
                $_SESSION['success'] = 'Account created. An administrator must activate it before you can sign in.';
                $this->redirect('/login');
                return;
            }
        }

        $status = '';
        if (isset($member['status'])) {
            $status = (string) $member['status'];
        }

        if ($status === 'banned') {
            $_SESSION['error'] = 'Your account has been banned.';
            $this->redirect('/login');
            return;
        }

        if ($status === 'pending') {
            $_SESSION['error'] = 'Your account is waiting for activation by an administrator.';
            $this->redirect('/login');
            return;
        }

        $_SESSION['user_id'] = $member['id'];
        $_SESSION['role'] = 'visitor';
        $_SESSION['email'] = $email;
        $_SESSION['username'] = $member['email'];
        $memberAvatar = null;
        if (isset($member['avatar'])) {
            $memberAvatar = $member['avatar'];
        }
        $_SESSION['avatar'] = $memberAvatar;
        $_SESSION['phone'] = '';

        session_regenerate_id(true);
        CsrfProtection::invalidateToken();

        $_SESSION['success'] = 'Logged in.';
        $this->redirect('/member');
    }

    /**
     * Build the absolute Google redirect URI, or an empty string when APP_URL is missing.
     */
    private function googleRedirectUri(): string
    {
        $appUrl = (string) \App\Core\Dotenv::get('APP_URL', '');
        if ($appUrl === '') {
            return '';
        }
        return $appUrl . '/auth/google/callback';
    }

    /**
     * Fetch the Google user profile for the given access token.
     */
    private function fetchGoogleUserInfo(string $accessToken): array
    {
        $ch = curl_init();
        if ($ch === false) {
            return [];
        }

        curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/oauth2/v2/userinfo');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
        ]);

        $response = curl_exec($ch);
        $curlError = curl_errno($ch);
        curl_close($ch);

        if ($curlError !== 0 || !is_string($response)) {
            return [];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return [];
        }

        $providerId = '';
        if (isset($decoded['id'])) {
            $providerId = (string) $decoded['id'];
        }
        if ($providerId === '') {
            return [];
        }

        return $decoded;
    }

    /**
     * Complete Google login by linking, activating, creating, or signing in the member.
     */
    private function finishGoogleLogin(string $providerId, string $email): void
    {
        try {
            $member = null;
            $stmt = $this->pdo->prepare('SELECT user_id FROM user_social_accounts WHERE provider = ? AND provider_id = ?');
            $stmt->execute(['google', $providerId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            $needsLink = false;
            if ($row && isset($row['user_id'])) {
                $member = $this->memberModel->findById((int) $row['user_id']);
            }

            if (!$member) {
                $member = $this->memberModel->findByEmail($email);
                if ($member) {
                    $needsLink = true;
                } else {
                    $newMemberId = $this->memberModel->registerFromSocial($email);
                    $this->memberModel->linkSocialAccount($newMemberId, 'google', $providerId);
                    $member = $this->memberModel->findById($newMemberId);
                }
            }

            if (!$member) {
                $_SESSION['error'] = 'Google sign-in failed. Please try again.';
                $this->redirect('/login');
                return;
            }

            $status = '';
            if (isset($member['status'])) {
                $status = (string) $member['status'];
            }

            if ($status === 'banned') {
                $_SESSION['error'] = 'Your account has been banned.';
                $this->redirect('/login');
                return;
            }

            if ($status === 'pending') {
                $this->memberModel->activateUser((int) $member['id']);
                $this->memberModel->updatePassword((int) $member['id'], bin2hex(random_bytes(16)));
            }

            if ($needsLink) {
                $this->memberModel->linkSocialAccount((int) $member['id'], 'google', $providerId);
            }

            $_SESSION['user_id'] = $member['id'];
            $_SESSION['role'] = 'visitor';
            $_SESSION['email'] = $email;
            $_SESSION['username'] = $member['email'];
            $memberAvatar = null;
            if (isset($member['avatar'])) {
                $memberAvatar = $member['avatar'];
            }
            $_SESSION['avatar'] = $memberAvatar;
            $_SESSION['phone'] = '';

            session_regenerate_id(true);
            CsrfProtection::invalidateToken();

            $_SESSION['success'] = 'Logged in.';
            $this->redirect('/member');
        } catch (Exception $e) {
            $_SESSION['error'] = 'Google sign-in failed. Please try again.';
            $this->redirect('/login');
        }
    }

    /**
     * Redirect the user to Google's OAuth consent screen.
     */
    public function redirectToGoogle(): void
    {
        $redirectUri = $this->googleRedirectUri();
        if ($redirectUri === '') {
            $_SESSION['error'] = 'Social login is not configured.';
            $this->redirect('/login');
            return;
        }

        $clientId = (string) \App\Core\Dotenv::get('GOOGLE_CLIENT_ID', '');
        if ($clientId === '') {
            $_SESSION['error'] = 'Social login is not configured.';
            $this->redirect('/login');
            return;
        }

        $state = bin2hex(random_bytes(32));
        $_SESSION['oauth_state'] = $state;

        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
        ];

        $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
        $this->redirect($url);
    }

    /**
     * Handle the OAuth callback from Google.
     */
    public function handleGoogleCallback(): void
    {
        $providedState = '';
        if (isset($_GET['state'])) {
            $providedState = (string) $_GET['state'];
        }
        $sessionState = '';
        if (isset($_SESSION['oauth_state'])) {
            $sessionState = (string) $_SESSION['oauth_state'];
        }
        unset($_SESSION['oauth_state']);

        $stateMatches = false;
        if ($sessionState !== '' && $providedState !== '') {
            $stateMatches = hash_equals($sessionState, $providedState);
        }
        if (!$stateMatches) {
            $_SESSION['error'] = 'Invalid OAuth state parameter.';
            $this->redirect('/login');
            return;
        }

        if (isset($_GET['error'])) {
            $_SESSION['error'] = 'Login was cancelled.';
            $this->redirect('/login');
            return;
        }

        $code = '';
        if (isset($_GET['code'])) {
            $code = (string) $_GET['code'];
        }
        if ($code === '') {
            $_SESSION['error'] = 'Login was cancelled.';
            $this->redirect('/login');
            return;
        }

        $redirectUri = $this->googleRedirectUri();
        if ($redirectUri === '') {
            $_SESSION['error'] = 'Social login is not configured.';
            $this->redirect('/login');
            return;
        }

        $clientId = (string) \App\Core\Dotenv::get('GOOGLE_CLIENT_ID', '');
        $clientSecret = (string) \App\Core\Dotenv::get('GOOGLE_CLIENT_SECRET', '');

        $tokenData = $this->httpRequest('https://oauth2.googleapis.com/token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ], []);

        $accessToken = '';
        if (isset($tokenData['access_token'])) {
            $accessToken = (string) $tokenData['access_token'];
        }
        if ($accessToken === '') {
            $_SESSION['error'] = 'Google sign-in failed. Please try again.';
            $this->redirect('/login');
            return;
        }

        $userInfo = $this->fetchGoogleUserInfo($accessToken);

        $providerId = '';
        if (isset($userInfo['id'])) {
            $providerId = (string) $userInfo['id'];
        }
        $email = '';
        if (isset($userInfo['email'])) {
            $email = (string) $userInfo['email'];
        }
        $verifiedEmail = false;
        if (isset($userInfo['verified_email']) && $userInfo['verified_email'] === true) {
            $verifiedEmail = true;
        }

        if ($providerId === '' || $email === '' || $verifiedEmail !== true) {
            $_SESSION['error'] = 'Google sign-in failed. Please try again.';
            $this->redirect('/login');
            return;
        }

        $this->finishGoogleLogin($providerId, $email);
    }

    /**
     * Redirect the user to Facebook's OAuth consent screen.
     */
    public function redirectToFacebook(): void
    {
        $clientId = (string) \App\Core\Dotenv::get('FACEBOOK_CLIENT_ID', '');
        if ($clientId === '') {
            $_SESSION['error'] = 'Social login is not configured.';
            $this->redirect('/login');
            return;
        }

        $state = bin2hex(random_bytes(32));
        $_SESSION['oauth_state'] = $state;

        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $this->redirectUri('/auth/facebook/callback'),
            'response_type' => 'code',
            'scope' => 'email',
            'state' => $state,
        ];

        $url = 'https://www.facebook.com/v19.0/dialog/oauth?' . http_build_query($params);
        $this->redirect($url);
    }

    /**
     * Handle the OAuth callback from Facebook.
     */
    public function handleFacebookCallback(): void
    {
        if (!$this->verifyOAuthState()) {
            $_SESSION['error'] = 'Invalid OAuth state parameter.';
            $this->redirect('/login');
            return;
        }

        if (isset($_GET['error'])) {
            $_SESSION['error'] = 'Login was cancelled.';
            $this->redirect('/login');
            return;
        }

        $code = '';
        if (isset($_GET['code'])) {
            $code = (string) $_GET['code'];
        }
        if ($code === '') {
            $_SESSION['error'] = 'Login was cancelled.';
            $this->redirect('/login');
            return;
        }

        $clientId = (string) \App\Core\Dotenv::get('FACEBOOK_CLIENT_ID', '');
        $clientSecret = (string) \App\Core\Dotenv::get('FACEBOOK_CLIENT_SECRET', '');

        $tokenParams = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $this->redirectUri('/auth/facebook/callback'),
            'code' => $code,
        ];
        $tokenUrl = 'https://graph.facebook.com/v19.0/oauth/access_token?' . http_build_query($tokenParams);
        $tokenData = $this->httpRequest($tokenUrl, [], []);

        $accessToken = '';
        if (isset($tokenData['access_token'])) {
            $accessToken = (string) $tokenData['access_token'];
        }
        if ($accessToken === '') {
            $_SESSION['error'] = 'Failed to obtain Facebook access token.';
            $this->redirect('/login');
            return;
        }

        $userInfo = $this->httpRequest('https://graph.facebook.com/v19.0/me?fields=id,email,name', [], [
            'Authorization: Bearer ' . $accessToken,
        ]);

        $providerId = '';
        if (isset($userInfo['id'])) {
            $providerId = (string) $userInfo['id'];
        }
        $email = '';
        if (isset($userInfo['email'])) {
            $email = (string) $userInfo['email'];
        }

        if ($providerId === '' || $email === '') {
            $_SESSION['error'] = 'Facebook did not provide an email address.';
            $this->redirect('/login');
            return;
        }

        $this->finishSocialLogin('facebook', $providerId, $email);
    }
}