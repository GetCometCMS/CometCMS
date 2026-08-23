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

        if ($user === null || !password_verify($password, (string) ($user['password_hash'] ?? ''))) {
            (new Logger())->warning('failed login', ['username' => $username]);
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
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

        return is_string($id) ? $this->users->find($id) : null;
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
