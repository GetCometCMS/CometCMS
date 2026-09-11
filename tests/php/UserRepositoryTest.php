<?php

declare(strict_types=1);

use CometCMS\Auth\UserRepository;

test('users store a red theme preference', function (): void {
    $users = new UserRepository();
    $user = $users->create('admin', 'secret-password', 'admin');

    $updated = $users->update((string) $user['id'], ['theme' => 'red']);

    assert_same('red', $updated['theme']);
    assert_same('red', $users->find((string) $user['id'])['theme'] ?? null);
});

test('users store an admin language preference', function (): void {
    $users = new UserRepository();
    $user = $users->create('admin', 'secret-password', 'admin');

    assert_same('en', $user['language']);

    $updated = $users->update((string) $user['id'], ['language' => 'pt-BR']);

    assert_same('pt-br', $updated['language']);
    assert_same('pt-br', $users->find((string) $user['id'])['language'] ?? null);
});

test('users reject invalid admin language tags', function (): void {
    $users = new UserRepository();
    $user = $users->create('admin', 'secret-password', 'admin');

    assert_throws(InvalidArgumentException::class, function () use ($users, $user): void {
        $users->update((string) $user['id'], ['language' => '../de']);
    });
});
