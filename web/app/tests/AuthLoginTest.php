<?php

declare(strict_types=1);

use App\Auth;
use App\PocketBaseClient;

class FakePocketBaseClientForAuth extends PocketBaseClient
{
    public array $users = [];

    public function __construct()
    {
        parent::__construct('http://test.local');
    }

    public function authUser(string $identity, string $password): array
    {
        if ($identity === 'local@example.com' && $password === 'ok') {
            return [
                'record' => [
                    'id' => 'u_local',
                    'email' => 'local@example.com',
                    'name' => 'Local User',
                    'is_app_admin' => false,
                    'allow_personal_otp' => false,
                ],
            ];
        }
        throw new RuntimeException('invalid');
    }

    public function authAdmin(string $email, string $password): array
    {
        return ['token' => 'admin-token'];
    }

    public function listRecords(string $collection, array $params = []): array
    {
        if ($collection === 'users') {
            $filter = (string)($params['filter'] ?? '');
            if (str_contains($filter, "ad_username='jdoe'")) {
                foreach ($this->users as $u) {
                    if (($u['ad_username'] ?? '') === 'jdoe') {
                        return ['items' => [$u]];
                    }
                }
            }
            return ['items' => []];
        }
        return ['items' => []];
    }

    public function createRecord(string $collection, array $data): array
    {
        if ($collection === 'users') {
            $data['id'] = 'u_ldap';
            $this->users[] = $data;
            return $data;
        }
        return $data;
    }

    public function updateRecord(string $collection, string $id, array $data): array
    {
        return $data;
    }
}

return [
    [
        'name' => 'local login sets session',
        'test' => static function (): void {
            $_SESSION = [];
            $pb = new FakePocketBaseClientForAuth();
            $auth = new Auth($pb, ['pocketbase' => ['admin_email' => 'a', 'admin_password' => 'b']]);

            $ok = $auth->login('local@example.com', 'ok');
            assertTrue($ok, 'local login should succeed');
            assertSame('u_local', $_SESSION['user']['id'] ?? '', 'session user id should be set');
            assertSame('local', $_SESSION['user']['auth_type'] ?? '', 'auth_type should be local');
        },
    ],
    [
        'name' => 'ldap login provisions user',
        'test' => static function (): void {
            $_SESSION = [];
            $pb = new FakePocketBaseClientForAuth();
            $auth = new Auth($pb, ['pocketbase' => ['admin_email' => 'a', 'admin_password' => 'b']]);

            $ok = $auth->loginWithLdap([
                'username' => 'jdoe',
                'name' => 'John Doe',
                'email' => 'jdoe@example.com',
            ]);

            assertTrue($ok, 'ldap login should succeed');
            assertSame('ldap', $_SESSION['user']['auth_type'] ?? '', 'auth_type should be ldap');
            assertSame('jdoe@example.com', $_SESSION['user']['email'] ?? '', 'session email should come from ldap');
        },
    ],
];
