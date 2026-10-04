<?php

declare(strict_types=1);

namespace CometCMS\Auth;

use CometCMS\Logging\Logger;

final class Auth
{
    private const ORDER = [
        'viewer' => 1,
        'editor' => 2,
        'admin' => 3,
    ];

    public function __construct(private readonly UserRepository $users)
    {
    }

    public function attempt(string $username, string $password, bool $remember = false): bool
    {
        $user = $this->users->findByUsername($username);
        // Verify against a dummy hash for unknown users so response timing does
        // not reveal which usernames exist.
        $hash = (string) ($user['password_hash'] ?? self::dummyHash());

        if (!password_verify($password, $hash) || $user === null) {
            (new Logger())->warning('failed login', ['username' => $username]);
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['session_epoch'] = self::epoch($user);
        $this->configureSessionCookie($remember);
        (new Logger())->info('login', ['user_id' => $user['id']]);

        return true;
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
        }

        session_destroy();
    }

    public function user(): ?array
    {
        $persistentUntil = $_SESSION['persistent_until'] ?? null;

        if (is_int($persistentUntil) && $persistentUntil <= time()) {
            $_SESSION = [];
            return null;
        }

        $id = $_SESSION['user_id'] ?? null;
        $user = is_string($id) ? $this->users->find($id) : null;

        // A password change bumps the user's epoch, which ends every session
        // that was started with the old password.
        if ($user !== null && ($_SESSION['session_epoch'] ?? 0) !== self::epoch($user)) {
            $_SESSION = [];
            return null;
        }

        return $user;
    }

    /** Keep the current session valid after its own user changed their password. */
    public function refresh(array $user): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && ($_SESSION['user_id'] ?? null) === $user['id']) {
            session_regenerate_id(true);
            $_SESSION['session_epoch'] = self::epoch($user);
        }
    }

    private static function epoch(array $user): int
    {
        return (int) ($user['session_epoch'] ?? 0);
    }

    /** Hash of a discarded random secret, at the same cost as real password hashes. */
    private static function dummyHash(): string
    {
        return '$2y$10$0.sKqCbS8YuRB4r4qYqoUekYs65G5mpb2QF7EXhxvnSKyTEyNIqUa';
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public static function allows(array $user, string $minimumRole): bool
    {
        $role = (string) ($user['role'] ?? 'viewer');

        return (self::ORDER[$role] ?? 0) >= (self::ORDER[$minimumRole] ?? 99);
    }

    private function configureSessionCookie(bool $remember): void
    {
        if ($remember) {
            $ttl = max(86400, (int) comet_config('security.persistent_session_seconds', 2592000));
            $expires = time() + $ttl;
            $_SESSION['persistent_until'] = $expires;
        } else {
            $expires = 0;
            unset($_SESSION['persistent_until']);
        }

        if (!ini_get('session.use_cookies') || headers_sent()) {
            return;
        }

        $params = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'expires' => $expires,
            'path' => $params['path'],
            'domain' => $params['domain'] ?? '',
            'secure' => (bool) $params['secure'],
            'httponly' => (bool) $params['httponly'],
            'samesite' => (string) ($params['samesite'] ?? 'Lax'),
        ]);
    }
}
