<?php
namespace App\Models;

use App\Core\Model;
use PDO;
use Exception;

class MemberModel extends Model
{
    protected string $table = 'members';

    /**
     * Return the preferred Argon2id algorithm, falling back to the default.
     */
    private function passwordAlgorithm(): string
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return PASSWORD_ARGON2ID;
        }
        return PASSWORD_DEFAULT;
    }

    /**
     * Register a new member and return the new ID.
     */
    public function register(string $email, string $plainPassword, string $whatsapp = ''): int
    {
        if (!\App\Core\Validator::email($email)) {
            throw new Exception('Invalid email address');
        }

        if ($this->findByEmail($email)) {
            throw new Exception('Email address already registered');
        }

        $hash = password_hash($plainPassword, $this->passwordAlgorithm());
        $verificationToken = bin2hex(random_bytes(16));

        return $this->insert([
            'email' => $email,
            'password' => $hash,
            'status' => 'pending',
            'verification_token' => $verificationToken,
            'whatsapp' => $whatsapp,
        ]);
    }

    /**
     * Register an active member from a verified social sign-in and return the new ID.
     */
    public function registerFromSocial(string $email): int
    {
        $hash = password_hash(bin2hex(random_bytes(16)), $this->passwordAlgorithm());

        return $this->insert([
            'email' => $email,
            'password' => $hash,
            'status' => 'active',
            'verification_token' => null,
            'whatsapp' => '',
        ]);
    }

    /**
     * Authenticate a member with timing-attack protection.
     */
    public function login(string $email, string $plainPassword): ?array
    {
        $user = $this->findByEmail($email);

        // SECURITY: Prevent Timing Attacks (User Enumeration)
        // If the user doesn't exist, we still run password_verify against a dummy hash.
        $hashToVerify = '$argon2id$v=19$m=65536,t=4,p=1$ZmFrZQ$ZmFrZQ';
        if ($user) {
            $hashToVerify = $user['password'];
        }

        // Execute password_verify unconditionally so timing does not reveal existence.
        $isPasswordCorrect = password_verify($plainPassword, $hashToVerify);

        if ($user && $isPasswordCorrect) {
            if ($user['status'] === 'banned') {
                throw new Exception('Your account has been banned.');
            }
            if ($user['status'] === 'pending') {
                throw new Exception('Your account is waiting for activation by an administrator.');
            }

            unset($user['password']);
            return $user;
        }

        return null;
    }

    /**
     * Find a member by email address.
     */
    public function findByEmail(string $email): ?array
    {
        return $this->queryOne("SELECT * FROM {$this->table} WHERE email = ?", [$email]);
    }

    /**
     * Find a member by verification token.
     */
    public function findByToken(string $token): ?array
    {
        return $this->queryOne("SELECT * FROM {$this->table} WHERE verification_token = ?", [$token]);
    }

    /**
     * Return members for the dashboard list without secrets.
     */
    public function findAllMembersForList(): array
    {
        $sql = "SELECT id, email, username, status, created_at FROM {$this->table} ORDER BY id ASC";
        return $this->query($sql);
    }

    /**
     * Activate a member and clear the verification token.
     */
    public function activateUser(int $id): bool
    {
        return $this->update($id, [
            'status' => 'active',
            'verification_token' => null,
        ]);
    }

    /**
     * Update a member's status after validating the whitelist.
     */
    public function updateStatus(int $id, string $status): bool
    {
        $allowedStatuses = ['active', 'banned'];
        if (!in_array($status, $allowedStatuses, true)) {
            throw new Exception('Invalid status');
        }

        return $this->update($id, ['status' => $status]);
    }

    /**
     * Hash and update a member's password.
     */
    public function updatePassword(int $id, string $newPassword): bool
    {
        $hash = password_hash($newPassword, $this->passwordAlgorithm());
        return $this->update($id, ['password' => $hash]);
    }

    /**
     * Update a member's avatar path.
     */
    public function updateAvatar(int $id, string $avatarPath): bool
    {
        return $this->update($id, ['avatar' => $avatarPath]);
    }

    /**
     * Link a social account to an existing member.
     */
    public function linkSocialAccount(int $memberId, string $provider, string $providerId): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO user_social_accounts (user_id, provider, provider_id) VALUES (?, ?, ?)"
        );
        return $stmt->execute([$memberId, $provider, $providerId]);
    }

    /**
     * Set or clear a member's unique username.
     */
    public function updateUsername(int $id, string $username): bool
    {
        $username = trim($username);

        if ($username === '') {
            return $this->update($id, ['username' => null]);
        }

        if (preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username) !== 1) {
            throw new Exception('Username must be 3-50 characters and contain only letters, numbers, and underscores');
        }

        $existing = $this->queryOne(
            "SELECT id FROM {$this->table} WHERE username = ? AND id != ?",
            [$username, $id]
        );
        if ($existing) {
            throw new Exception('Username is already taken');
        }

        return $this->update($id, ['username' => $username]);
    }

    /**
     * Count members that have the given status.
     */
    public function countByStatus(string $status): int
    {
        $sql = "SELECT COUNT(*) AS c FROM {$this->table} WHERE status = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$status]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int) $row['c'];
    }
}
