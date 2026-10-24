<?php

declare(strict_types=1);

use CometCMS\Auth\ApiTokenRepository;
use CometCMS\Auth\UserRepository;

test('api tokens are stored independently from users with descriptions', function (): void {
    $users = new UserRepository();
    $user = $users->create('admin', 'secret-password', 'admin');
    $tokens = new ApiTokenRepository();

    $plain = $tokens->create('Deploy', 'Deploy workflow', [
        ['actions' => ['content.read'], 'resources' => ['content:*']],
    ]);

    assert_matches('/^ctcms_[a-f0-9]{10}_[a-f0-9]{48}$/', $plain);
    assert_same([], $users->find((string) $user['id'])['api_tokens'] ?? []);

    $stored = $tokens->all()[0] ?? null;
    assert_same('Deploy', $stored['name'] ?? null);
    assert_same('Deploy workflow', $stored['description'] ?? null);

    $principal = $tokens->findByToken($plain);
    assert_same('token', $principal['_principal_type'] ?? null);
    assert_same('Deploy', $principal['username'] ?? null);
});

test('embedded user tokens do not authenticate or migrate', function (): void {
    $users = new UserRepository();
    $user = $users->create('admin', 'secret-password', 'admin');
    $plain = 'ctcms_1234567890_' . str_repeat('a', 48);
    $user['api_tokens'] = [[
        'id' => 'tok_1234567890',
        'name' => 'Legacy',
        'hash' => password_hash($plain, PASSWORD_DEFAULT),
        'permissions' => [
            ['actions' => ['content.read'], 'resources' => ['content:*']],
        ],
        'created_at' => '2026-05-06T00:00:00+00:00',
        'last_used_at' => null,
        'revoked_at' => null,
    ]];
    $users->save($user);

    $tokens = new ApiTokenRepository();
    $principal = $tokens->findByToken($plain);

    assert_null($principal);
    assert_null($tokens->find('tok_1234567890'));
    assert_same($user['api_tokens'], $users->find((string) $user['id'])['api_tokens']);
});

test('revoked api tokens can be deleted permanently', function (): void {
    $tokens = new ApiTokenRepository();
    $plain = $tokens->create('Deploy');
    $stored = $tokens->all()[0] ?? null;

    $tokens->revoke((string) $stored['id']);
    assert_null($tokens->findByToken($plain));

    $tokens->delete((string) $stored['id']);
    assert_null($tokens->find((string) $stored['id']));
});
