<?php

declare(strict_types=1);

namespace App;

class LdapAuth
{
    private array $cfg;

    public function __construct(array $ldapConfig)
    {
        $this->cfg = $ldapConfig;
    }

    /**
     * Authenticate a user against Active Directory / LDAP.
     * Returns user info array on success, null on failure.
     */
    public function authenticate(string $username, string $password): ?array
    {
        if (!$this->cfg['enabled'] || empty($this->cfg['host'])) {
            return null;
        }

        $protocol = $this->cfg['use_ssl'] ? 'ldaps://' : 'ldap://';
        $uri      = $protocol . $this->cfg['host'] . ':' . $this->cfg['port'];

        $conn = @ldap_connect($uri);
        if (!$conn) {
            error_log("LDAP: impossible de se connecter à {$uri}");
            return null;
        }

        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, 10);

        $domain = trim((string)($this->cfg['domain'] ?? ''));
        $baseDn = trim((string)($this->cfg['base_dn'] ?? ''));

        // 1) First bind: service account if configured, otherwise bind directly as user.
        $serviceBindDn = trim((string)($this->cfg['bind_dn'] ?? ''));
        $serviceBindPw = (string)($this->cfg['bind_password'] ?? '');

        if ($serviceBindDn !== '') {
            if (!@ldap_bind($conn, $serviceBindDn, $serviceBindPw)) {
                error_log('LDAP: echec du bind service (LDAP_BIND_DN).');
                @ldap_close($conn);
                return null;
            }
        } else {
            $identityCandidates = $this->buildIdentityCandidates($username, $domain);
            $directBindOk = false;
            foreach ($identityCandidates as $candidate) {
                if (@ldap_bind($conn, $candidate, $password)) {
                    $directBindOk = true;
                    break;
                }
            }
            if (!$directBindOk) {
                @ldap_close($conn);
                return null;
            }
        }

        // 2) Search user entry to retrieve DN and profile attrs.
        $escapedInput = ldap_escape($username, '', LDAP_ESCAPE_FILTER);
        $filter = (string)($this->cfg['search_filter'] ?? '(sAMAccountName={username})');
        $filter = str_replace(['{username}', '{input}'], $escapedInput, $filter);

        $search = @ldap_search(
            $conn,
            $baseDn,
            $filter,
            ['dn', 'uid', 'mail', 'displayName', 'sAMAccountName', 'userPrincipalName', 'cn'],
        );

        if (!$search) {
            @ldap_close($conn);
            return null;
        }

        $entries = ldap_get_entries($conn, $search);
        if (($entries['count'] ?? 0) < 1) {
            @ldap_close($conn);
            return null;
        }

        $entry  = $entries[0];
        $userDn = $entry['dn'] ?? '';

        // 3) Verify user password by binding as the found user DN.
        if ($userDn === '' || !@ldap_bind($conn, $userDn, $password)) {
            @ldap_close($conn);
            return null;
        }

        @ldap_close($conn);

        return [
            'username' => $entry['uid'][0]
                          ?? $entry['samaccountname'][0]
                          ?? $entry['userprincipalname'][0]
                          ?? $username,
            'email'    => $entry['mail'][0]
                          ?? $entry['userprincipalname'][0]
                          ?? ($domain !== '' ? "{$username}@{$domain}" : $username),
            'name'     => $entry['displayname'][0] ?? $entry['cn'][0] ?? $username,
        ];
    }

    private function buildIdentityCandidates(string $username, string $domain): array
    {
        $candidates = [];

        if ($username !== '') {
            $candidates[] = $username;
        }

        if ($domain !== '') {
            if (!str_contains($username, '@') && str_contains($domain, '.')) {
                $candidates[] = "{$username}@{$domain}";
            }
            if (!str_contains($username, '\\') && !str_contains($domain, '.')) {
                $candidates[] = "{$domain}\\{$username}";
            }
        }

        return array_values(array_unique($candidates));
    }
}
