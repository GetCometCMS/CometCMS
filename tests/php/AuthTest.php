<?php

declare(strict_types=1);

use CometCMS\Auth\Auth;
use CometCMS\Auth\UserRepository;

function comet_auth_test_start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
}

test('auth attempt succeeds with valid credentials and check/user reflect session', function (): void {
    comet_auth_test_start_session();

    $users = new UserRepository();
    $created = $users->create('admin', 'secret-password', 'admin');
    $auth = new Auth($users);

    assert_true($auth->attempt('admin', 'secret-password'));
    assert_same((string) $created['id'], $_SESSION['user_id'] ?? null);
    assert_true($auth->check());
    assert_same((string) $created['id'], $auth->user()['id'] ?? null);
});

test('auth persistent login records an expiry and expired sessions are rejected', function (): void {
    comet_auth_test_start_session();

    $users = new UserRepository();
    $users->create('admin', 'secret-password', 'admin');
    $auth = new Auth($users);

    assert_true($auth->attempt('admin', 'secret-password', true));
    assert_true(is_int($_SESSION['persistent_until'] ?? null));
    assert_true(($_SESSION['persistent_until'] ?? 0) > time());

    $_SESSION['persistent_until'] = time() - 1;
    assert_null($auth->user());
    assert_same([], $_SESSION);
});

test('auth attempt fails with invalid credentials', function (): void {
    comet_auth_test_start_session();

    $users = new UserRepository();
    $users->create('admin', 'secret-password', 'admin');
    $auth = new Auth($users);

    assert_false($auth->attempt('admin', 'wrong-password'));
    assert_false($auth->attempt('missing-user', 'secret-password'));
    assert_null($_SESSION['user_id'] ?? null);
    assert_false($auth->check());
});

test('auth logout clears session and user/check become null/false', function (): void {
    comet_auth_test_start_session();

    $users = new UserRepository();
    $users->create('admin', 'secret-password', 'admin');
    $auth = new Auth($users);

    assert_true($auth->attempt('admin', 'secret-password'));
    assert_true($auth->check());

    $auth->logout();

    assert_same([], $_SESSION);
    assert_null($auth->user());
    assert_false($auth->check());
});

test('auth user returns null when no user is logged in', function (): void {
    comet_auth_test_start_session();

    $users = new UserRepository();
    $auth = new Auth($users);

    assert_null($auth->user());
    assert_false($auth->check());
});

test('auth allows enforces viewer-editor-admin role hierarchy', function (): void {
    $viewer = ['role' => 'viewer'];
    $editor = ['role' => 'editor'];
    $admin = ['role' => 'admin'];

    assert_true(Auth::allows($viewer, 'viewer'));
    assert_false(Auth::allows($viewer, 'editor'));
    assert_false(Auth::allows($viewer, 'admin'));

    assert_true(Auth::allows($editor, 'viewer'));
    assert_true(Auth::allows($editor, 'editor'));
    assert_false(Auth::allows($editor, 'admin'));

    assert_true(Auth::allows($admin, 'viewer'));
    assert_true(Auth::allows($admin, 'editor'));
    assert_true(Auth::allows($admin, 'admin'));
});

test('auth allows handles unknown role edge cases safely', function (): void {
    assert_false(Auth::allows(['role' => 'owner'], 'viewer'));
    assert_false(Auth::allows(['role' => 'admin'], 'owner'));
    assert_false(Auth::allows([], 'admin'));
});
