<?php
/**
 * Authentication Controller
 * Handles user registration, login, logout, and rate limiting
 */
namespace App\Controllers;

use PDO;
use App\Core\Controller;
use App\Models\UserModel;
use App\Models\MemberModel;
use App\Core\CsrfProtection;
use App\Core\RateLimiter;
use App\Core\RateLimitException;
use App\Core\Validator;
use App\Core\SecurityLogger;
use Exception;

class AuthController extends Controller
{
    private UserModel $model;
    private MemberModel $memberModel;
    private RateLimiter $rateLimiter;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->model       = new UserModel($pdo);
        $this->memberModel = new MemberModel($pdo);
        $this->rateLimiter = new RateLimiter($pdo);
    }

    public function showLogin(): void
    {
        $this->view('auth/login', ['pageTitle' => 'Sign In']);
    }

    /**
     * Render the public member registration form.
     */
    public function showRegister(): void
    {
        $this->view('auth/register', ['pageTitle' => 'Register']);
    }

    /**
     * Register a new public member from the submitted form.
     */
    public function register(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/register');
            return;
        }

        CsrfProtection::requireValidToken();

        $remoteAddress = '';
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $remoteAddress = (string) $_SERVER['REMOTE_ADDR'];
        }

        try {
            $this->rateLimiter->consume($remoteAddress, 'register');
        } catch (RateLimitException $e) {
            $_SESSION['error'] = 'Too many registration attempts. Please try again later.';
            $this->redirect('/register');
            return;
        }

        $rawEmail = '';
        if (isset($_POST['email'])) {
            $rawEmail = $_POST['email'];
        }
        $email = Validator::sanitize($rawEmail, 255);

        $password = '';
        if (isset($_POST['password'])) {
            $password = $_POST['password'];
        }

        $confirmPassword = '';
        if (isset($_POST['confirm_password'])) {
            $confirmPassword = $_POST['confirm_password'];
        }

        if ($password !== $confirmPassword) {
            $_SESSION['error'] = 'Passwords do not match.';
            $this->redirect('/register');
            return;
        }

        if (strlen($password) < 8) {
            $_SESSION['error'] = 'Password must be at least 8 characters.';
            $this->redirect('/register');
            return;
        }

        $rawWhatsapp = '';
        if (isset($_POST['whatsapp'])) {
            $rawWhatsapp = $_POST['whatsapp'];
        }
        $whatsapp = Validator::sanitize($rawWhatsapp, 30);
        $whatsapp = preg_replace('/[\s\-]/', '', $whatsapp);
        // Validate whatsapp number: must start with + or 0 and contain digits only.
        if (preg_match('/^(\+|0)[0-9]{8,18}$/', $whatsapp) !== 1) {
            $_SESSION['error'] = 'WhatsApp number must start with + or 0 and contain digits only.';
            $this->redirect('/register');
            return;
        }

        try {
            $this->memberModel->register($email, $password, $whatsapp);
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/register');
            return;
        }

        $_SESSION['success'] = 'Registration successful. An administrator must activate your account before you can sign in.';
        $this->redirect('/login');
    }

    /**
     * Verify a member account from an emailed token.
     */
    public function verifyEmail(string $token): void
    {
        $token = Validator::sanitize($token, 64);
        $member = $this->memberModel->findByToken($token);

        if (!$member) {
            $_SESSION['error'] = 'Invalid or expired verification token.';
            $this->redirect('/login');
            return;
        }

        $this->memberModel->activateUser((int) $member['id']);
        $_SESSION['success'] = 'Your email has been verified. You can now sign in.';
        $this->redirect('/login');
    }

    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/login');
            return;
        }

        // 1. Read the identifier (email for members, phone for staff)
        $rawIdentifier = '';
        if (isset($_POST['phone'])) {
            $rawIdentifier = $_POST['phone'];
        }
        $identifier = Validator::sanitize($rawIdentifier, 255);
        $password = '';
        if (isset($_POST['password'])) {
            $password = $_POST['password'];
        }

        if (empty($identifier)) {
            $_SESSION['error'] = 'Email or phone number is required.';
            $this->redirect('/login');
            return;
        }

        // Member login (identifier contains '@')
        if (strpos($identifier, '@') !== false) {
            CsrfProtection::requireValidToken();

            try {
                $this->rateLimiter->consume($identifier, 'login');
            } catch (RateLimitException $e) {
                $wait = RateLimiter::formatRetryAfter($e->getRetryAfter());
                $_SESSION['error'] = "Too many login attempts for this account. Please try again in {$wait}.";
                $this->redirect('/login');
                return;
            }

            try {
                $member = $this->memberModel->login($identifier, $password);
            } catch (Exception $e) {
                $_SESSION['error'] = $e->getMessage();
                $this->redirect('/login');
                return;
            }

            if ($member) {
                $this->rateLimiter->clear($identifier, 'login');

                $_SESSION['user_id'] = $member['id'];
                $_SESSION['role'] = 'visitor';
                $_SESSION['email'] = $identifier;
                $_SESSION['username'] = $member['email'];
                $memberAvatar = null;
                if (isset($member['avatar'])) {
                    $memberAvatar = $member['avatar'];
                }
                $_SESSION['avatar'] = $memberAvatar;
                $_SESSION['phone'] = '';

                session_regenerate_id(true);
                CsrfProtection::invalidateToken();

                $this->redirect('/member');
                return;
            }

            $_SESSION['error'] = 'Invalid email or password.';
            $this->redirect('/login');
            return;
        }

        // Staff login (phone number)
        $phone = Validator::sanitize($rawIdentifier, 50);

        if (!Validator::phone($phone)) {
            $_SESSION['error'] = 'Invalid phone number format.';
            $this->redirect('/login');
            return;
        }

        // 1. CSRF protection
        CsrfProtection::requireValidToken();

        // 2. Rate limiting: 5 attempts per 5 minutes PER PHONE NUMBER.
        // By passing $phone instead of 'login', we prevent attackers from 
        // bypassing the limit by simply rotating their IP address.
        try {
            $this->rateLimiter->consume($phone, 'login');
        } catch (RateLimitException $e) {
            $wait = RateLimiter::formatRetryAfter($e->getRetryAfter());
            $_SESSION['error'] = "Too many login attempts for this account. Please try again in {$wait}.";
            $this->redirect('/login');
            return;
        }

        // 3. Attempt login
        $user = $this->model->login($phone, $password);
        
        if ($user) {
            // Clear rate limit on successful login so legitimate typos don't haunt the user
            $this->rateLimiter->clear($phone, 'login');

            if (class_exists(SecurityLogger::class)) {
                $logger = new SecurityLogger();
                $logger->logAuth('login', true, ['user_id' => $user['id'], 'phone' => $phone]);
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $sessionUsername = null;
            if (isset($user['username'])) {
                $sessionUsername = $user['username'];
            }
            $_SESSION['username'] = $sessionUsername;
            $sessionAvatar = null;
            if (isset($user['avatar'])) {
                $sessionAvatar = $user['avatar'];
            }
            $_SESSION['avatar'] = $sessionAvatar;
            $sessionPhone = null;
            if (isset($user['phone'])) {
                $sessionPhone = $user['phone'];
            }
            $_SESSION['phone'] = $sessionPhone;
            
            // Regenerate session ID for security (prevents session fixation)
            session_regenerate_id(true); 
            
            // Regenerate CSRF token after privilege elevation
            CsrfProtection::invalidateToken();
            
            $this->redirect('/dashboard');
        } else {
            if (class_exists(SecurityLogger::class)) {
                $logger = new SecurityLogger();
                $logger->logAuth('login', false, ['phone' => $phone, 'reason' => 'invalid_credentials']);
            }
            
            $_SESSION['error'] = 'Invalid phone number or password.';
            $this->redirect('/login');
        }
    }

    public function logout(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard');
            return;
        }
        
        \App\Core\CsrfProtection::requireValidToken();

        // 1. Unset all session variables immediately
        $_SESSION = [];

        // 2. Delete the session cookie securely using ARRAY syntax
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            
            // ✅ CORRECT: Use the array options syntax for setcookie()
            $sameSite = 'Lax';
            if (isset($params["samesite"])) {
                $sameSite = $params["samesite"];
            }
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params["path"],
                'domain'   => $params["domain"],
                'secure'   => $params["secure"],
                'httponly' => $params["httponly"],
                'samesite' => $sameSite
            ]);
        }

        // 3. Destroy the session data on the server
        session_destroy();

        // 4. Start a fresh session solely to hold the success flash message
        session_start();
        session_regenerate_id(true); // Ensure the new session gets a pristine, unpredictable ID
        
        \App\Core\CsrfProtection::invalidateToken();
        $_SESSION['success'] = 'You have been signed out.';
        
        $this->redirect('/login');
    }
}