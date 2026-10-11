<?php

declare(strict_types=1);

$base = getenv('SMOKE_API_BASE') ?: 'http://127.0.0.1:8000/api';
$fail = [];

function req(string $method, string $url, ?array $body = null, ?string $token = null): array
{
    $ch = curl_init($url);
    $headers = ['Accept: application/json', 'Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer '.$token;
    }

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_POSTFIELDS => $body ? json_encode($body) : null,
    ]);

    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['status' => 0, 'json' => null, 'error' => $err];
    }

    return ['status' => $status, 'json' => json_decode($raw, true), 'error' => null];
}

function check(string $name, bool $ok, string $detail = ''): void
{
    global $fail;
    if ($ok) {
        echo "PASS {$name}\n";
    } else {
        echo "FAIL {$name} {$detail}\n";
        $fail[] = $name;
    }
}

$me = req('GET', "{$base}/auth/me");
check('A1-guest-401', $me['status'] === 401, "status={$me['status']} err={$me['error']}");

$email = 'smoke_'.bin2hex(random_bytes(4)).'@example.test';
$reg = req('POST', "{$base}/auth/register", [
    'name' => 'Smoke User',
    'email' => $email,
    'password' => 'secret-pass-1',
    'password_confirmation' => 'secret-pass-1',
]);
check(
    'A2-register',
    ($reg['json']['user']['role'] ?? null) === 'customer'
        && ($reg['json']['user']['status'] ?? null) === 'active'
        && ($reg['json']['user']['locale'] ?? null) === 'my'
        && ! empty($reg['json']['token']),
    json_encode($reg['json'])
);
$custToken = $reg['json']['token'] ?? '';

$dup = req('POST', "{$base}/auth/register", [
    'name' => 'Smoke User',
    'email' => $email,
    'password' => 'secret-pass-1',
    'password_confirmation' => 'secret-pass-1',
]);
check('A2-duplicate', $dup['status'] === 422 && ($dup['json']['code'] ?? null) === 'email_taken', "status={$dup['status']}");

$admin = req('POST', "{$base}/auth/login", [
    'email' => 'admin@example.test',
    'password' => 'password',
]);
check('A4-admin-login', ($admin['json']['user']['role'] ?? null) === 'admin' && ! empty($admin['json']['token']));
$adminToken = $admin['json']['token'] ?? '';

foreach (['admin/customers', 'reseller/ping', 'rider/ping', 'ops/ping', 'finance/ping'] as $path) {
    $r = req('GET', "{$base}/{$path}", null, $custToken);
    check("A5-{$path}", $r['status'] === 403 && ($r['json']['code'] ?? null) === 'forbidden', "status={$r['status']}");
}

foreach ([
    ['ops@example.test', 'ops'],
    ['finance@example.test', 'finance'],
    ['reseller@example.test', 'reseller'],
    ['rider@example.test', 'rider'],
] as [$fixtureEmail, $role]) {
    $login = req('POST', "{$base}/auth/login", ['email' => $fixtureEmail, 'password' => 'password']);
    $token = $login['json']['token'] ?? '';
    $ping = req('GET', "{$base}/{$role}/ping", null, $token);
    check("A4-ping-{$role}", ($ping['json']['ok'] ?? false) === true && ($ping['json']['role'] ?? null) === $role, json_encode($ping['json']));
}

$opsAsAdmin = req('GET', "{$base}/ops/ping", null, $adminToken);
check('A4-admin-ops-403', $opsAsAdmin['status'] === 403, "status={$opsAsAdmin['status']}");

$targetId = $reg['json']['user']['id'] ?? 0;
$block = req('POST', "{$base}/admin/customers/{$targetId}/block", ['reason' => 'QA smoke block'], $adminToken);
check('A6-block', ($block['json']['user']['status'] ?? null) === 'blocked');

$me1 = req('GET', "{$base}/auth/me", null, $custToken);
check('A6-me-account-blocked', $me1['status'] === 401 && ($me1['json']['code'] ?? null) === 'account_blocked', "status={$me1['status']}");

$me2 = req('GET', "{$base}/auth/me", null, $custToken);
check('A6-me-unauthenticated', $me2['status'] === 401 && ($me2['json']['code'] ?? null) === 'unauthenticated', "status={$me2['status']}");

$blockedLogin = req('POST', "{$base}/auth/login", ['email' => $email, 'password' => 'secret-pass-1']);
check('A6-login-blocked-403', $blockedLogin['status'] === 403 && ($blockedLogin['json']['code'] ?? null) === 'account_blocked');

$unblock = req('POST', "{$base}/admin/customers/{$targetId}/unblock", ['reason' => 'QA smoke unblock'], $adminToken);
check('A6-unblock', ($unblock['json']['user']['status'] ?? null) === 'active');

$opsLogin = req('POST', "{$base}/auth/login", ['email' => 'ops@example.test', 'password' => 'password']);
$opsList = req('GET', "{$base}/admin/customers", null, $opsLogin['json']['token'] ?? '');
check('A6-ops-list-403', $opsList['status'] === 403, "status={$opsList['status']}");

echo $fail ? ('SMOKE FAILED: '.implode(', ', $fail)."\n") : "SMOKE OK\n";
exit($fail ? 1 : 0);
