<?php

namespace App\Services\Settings;

class SettingsSchema
{
    /**
     * @return array<string, array{label: string, fields: array<string, array<string, mixed>>}>
     */
    public static function groups(): array
    {
        return [
            'queue' => [
                'label' => 'Queue (Redis)',
                'fields' => [
                    'enabled' => ['type' => 'boolean', 'default' => false, 'secret' => false],
                    'connection' => ['type' => 'string', 'default' => 'redis', 'secret' => false],
                    'host' => ['type' => 'string', 'default' => '127.0.0.1', 'secret' => false],
                    'port' => ['type' => 'integer', 'default' => 6379, 'secret' => false],
                    'password' => ['type' => 'string', 'default' => '', 'secret' => true],
                    'database' => ['type' => 'integer', 'default' => 0, 'secret' => false],
                    'prefix' => ['type' => 'string', 'default' => 'mshop_', 'secret' => false],
                ],
            ],
            'storage' => [
                'label' => 'Storage',
                'fields' => [
                    'enabled' => ['type' => 'boolean', 'default' => true, 'secret' => false],
                    'active_driver' => ['type' => 'string', 'default' => 'local', 'secret' => false],
                    's3_enabled' => ['type' => 'boolean', 'default' => false, 'secret' => false],
                    's3_endpoint' => ['type' => 'string', 'default' => '', 'secret' => false],
                    's3_region' => ['type' => 'string', 'default' => 'ap-southeast-1', 'secret' => false],
                    's3_bucket' => ['type' => 'string', 'default' => '', 'secret' => false],
                    's3_key' => ['type' => 'string', 'default' => '', 'secret' => true],
                    's3_secret' => ['type' => 'string', 'default' => '', 'secret' => true],
                    's3_path_style' => ['type' => 'boolean', 'default' => true, 'secret' => false],
                    'gdrive_enabled' => ['type' => 'boolean', 'default' => false, 'secret' => false],
                    'gdrive_client_id' => ['type' => 'string', 'default' => '', 'secret' => false],
                    'gdrive_client_secret' => ['type' => 'string', 'default' => '', 'secret' => true],
                    'gdrive_refresh_token' => ['type' => 'string', 'default' => '', 'secret' => true],
                    'gdrive_folder' => ['type' => 'string', 'default' => '', 'secret' => false],
                ],
            ],
            'realtime' => [
                'label' => 'Realtime (Soketi/Echo)',
                'fields' => [
                    'enabled' => ['type' => 'boolean', 'default' => false, 'secret' => false],
                    'host' => ['type' => 'string', 'default' => '127.0.0.1', 'secret' => false],
                    'port' => ['type' => 'integer', 'default' => 6001, 'secret' => false],
                    'app_id' => ['type' => 'string', 'default' => 'mshop', 'secret' => false],
                    'key' => ['type' => 'string', 'default' => '', 'secret' => false],
                    'secret' => ['type' => 'string', 'default' => '', 'secret' => true],
                    'tls' => ['type' => 'boolean', 'default' => false, 'secret' => false],
                ],
            ],
            'mail' => [
                'label' => 'Mail',
                'fields' => [
                    'enabled' => ['type' => 'boolean', 'default' => true, 'secret' => false],
                    'driver' => ['type' => 'string', 'default' => 'log', 'secret' => false],
                    'host' => ['type' => 'string', 'default' => '127.0.0.1', 'secret' => false],
                    'port' => ['type' => 'integer', 'default' => 1025, 'secret' => false],
                    'username' => ['type' => 'string', 'default' => '', 'secret' => false],
                    'password' => ['type' => 'string', 'default' => '', 'secret' => true],
                    'encryption' => ['type' => 'string', 'default' => '', 'secret' => false],
                    'from_address' => ['type' => 'string', 'default' => 'noreply@mshop.test', 'secret' => false],
                    'from_name' => ['type' => 'string', 'default' => 'mshop', 'secret' => false],
                ],
            ],
            'sms' => [
                'label' => 'SMS',
                'fields' => [
                    'enabled' => ['type' => 'boolean', 'default' => false, 'secret' => false],
                    'provider' => ['type' => 'string', 'default' => 'log', 'secret' => false],
                    'api_key' => ['type' => 'string', 'default' => '', 'secret' => true],
                    'api_secret' => ['type' => 'string', 'default' => '', 'secret' => true],
                    'sender' => ['type' => 'string', 'default' => 'MSHOP', 'secret' => false],
                    'endpoint' => ['type' => 'string', 'default' => '', 'secret' => false],
                ],
            ],
            'payments' => [
                'label' => 'Payments',
                'fields' => [
                    'enabled' => ['type' => 'boolean', 'default' => true, 'secret' => false],
                    'cod_enabled' => ['type' => 'boolean', 'default' => true, 'secret' => false],
                    'cod_max_amount' => ['type' => 'integer', 'default' => 500000, 'secret' => false],
                    'manual_transfer_enabled' => ['type' => 'boolean', 'default' => true, 'secret' => false],
                    'manual_transfer_account' => ['type' => 'string', 'default' => '', 'secret' => false],
                    'wallet_enabled' => ['type' => 'boolean', 'default' => true, 'secret' => false],
                    'kpay_enabled' => ['type' => 'boolean', 'default' => false, 'secret' => false],
                    'wave_enabled' => ['type' => 'boolean', 'default' => false, 'secret' => false],
                ],
            ],
            'deposit' => [
                'label' => 'First deposit',
                'fields' => [
                    'enabled' => ['type' => 'boolean', 'default' => false, 'secret' => false],
                    'amount_mmk' => ['type' => 'integer', 'default' => 10000, 'secret' => false],
                ],
            ],
            'points' => [
                'label' => 'Points & tiers',
                'fields' => [
                    'enabled' => ['type' => 'boolean', 'default' => true, 'secret' => false],
                    'earn_per_mmk' => ['type' => 'integer', 'default' => 100, 'secret' => false],
                    'redeem_percent_cap' => ['type' => 'integer', 'default' => 20, 'secret' => false],
                    'point_value_mmk' => ['type' => 'integer', 'default' => 1, 'secret' => false],
                    'expiry_days' => ['type' => 'integer', 'default' => 365, 'secret' => false],
                    'return_window_days' => ['type' => 'integer', 'default' => 7, 'secret' => false],
                ],
            ],
            'delivery' => [
                'label' => 'Delivery',
                'fields' => [
                    'enabled' => ['type' => 'boolean', 'default' => true, 'secret' => false],
                    'gtg_fee_mmk' => ['type' => 'integer', 'default' => 2000, 'secret' => false],
                    'max_cod_attempts' => ['type' => 'integer', 'default' => 3, 'secret' => false],
                    'yangon_only_gtg' => ['type' => 'boolean', 'default' => true, 'secret' => false],
                ],
            ],
            'checkout' => [
                'label' => 'Checkout',
                'fields' => [
                    'enabled' => ['type' => 'boolean', 'default' => true, 'secret' => false],
                    'guest_checkout' => ['type' => 'boolean', 'default' => false, 'secret' => false],
                ],
            ],
            'auth' => [
                'label' => 'Auth',
                'fields' => [
                    'enabled' => ['type' => 'boolean', 'default' => true, 'secret' => false],
                    'token_ttl_days' => ['type' => 'integer', 'default' => 30, 'secret' => false],
                    'require_email_verification' => ['type' => 'boolean', 'default' => false, 'secret' => false],
                    'login_max_attempts' => ['type' => 'integer', 'default' => 5, 'secret' => false],
                    'login_decay_minutes' => ['type' => 'integer', 'default' => 15, 'secret' => false],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultsFor(string $group): array
    {
        $schema = self::groups()[$group] ?? null;

        if ($schema === null) {
            return [];
        }

        $defaults = [];

        foreach ($schema['fields'] as $key => $field) {
            $defaults[$key] = $field['default'];
        }

        return $defaults;
    }

    public static function isSecret(string $group, string $key): bool
    {
        return (bool) (self::groups()[$group]['fields'][$key]['secret'] ?? false);
    }
}
