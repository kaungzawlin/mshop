<?php

namespace App\Services\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class RuntimeConfigResolver
{
    public const CACHE_KEY = 'mshop.settings.all';

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        if (! $this->settingsTableReady()) {
            return $this->schemaDefaults();
        }

        return Cache::remember(self::CACHE_KEY, now()->addMinutes(10), function (): array {
            $grouped = $this->schemaDefaults();

            Setting::query()->get()->each(function (Setting $setting) use (&$grouped): void {
                $grouped[$setting->group][$setting->key] = $setting->getDecodedValue();
            });

            return $grouped;
        });
    }

    public function get(string $group, ?string $key = null, mixed $default = null): mixed
    {
        $all = $this->all();

        if ($key === null) {
            return $all[$group] ?? $default;
        }

        return data_get($all, "{$group}.{$key}", $default);
    }

    public function enabled(string $group): bool
    {
        return (bool) $this->get($group, 'enabled', false);
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function apply(): void
    {
        $settings = $this->all();

        $auth = $settings['auth'] ?? [];
        Config::set('mshop.auth.token_ttl_days', (int) ($auth['token_ttl_days'] ?? 30));
        Config::set('mshop.auth.require_email_verification', (bool) ($auth['require_email_verification'] ?? false));
        Config::set('mshop.auth.login_max_attempts', (int) ($auth['login_max_attempts'] ?? 5));
        Config::set('mshop.auth.login_decay_minutes', (int) ($auth['login_decay_minutes'] ?? 15));

        $queue = $settings['queue'] ?? [];
        if (($queue['enabled'] ?? false) === true) {
            Config::set('queue.default', 'redis');
            Config::set('database.redis.default.host', $queue['host'] ?? '127.0.0.1');
            Config::set('database.redis.default.port', (int) ($queue['port'] ?? 6379));
            Config::set('database.redis.default.password', ($queue['password'] ?? '') !== '' ? $queue['password'] : null);
            Config::set('database.redis.default.database', (int) ($queue['database'] ?? 0));
            Config::set('database.redis.options.prefix', $queue['prefix'] ?? 'mshop_');
        } else {
            Config::set('queue.default', 'sync');
        }

        $mail = $settings['mail'] ?? [];
        if (($mail['enabled'] ?? false) === true) {
            $driver = $mail['driver'] ?? 'log';
            Config::set('mail.default', $driver === 'smtp' ? 'smtp' : $driver);
            Config::set('mail.mailers.smtp.host', $mail['host'] ?? '127.0.0.1');
            Config::set('mail.mailers.smtp.port', (int) ($mail['port'] ?? 1025));
            Config::set('mail.mailers.smtp.username', ($mail['username'] ?? '') !== '' ? $mail['username'] : null);
            Config::set('mail.mailers.smtp.password', ($mail['password'] ?? '') !== '' ? $mail['password'] : null);
            Config::set('mail.mailers.smtp.encryption', ($mail['encryption'] ?? '') !== '' ? $mail['encryption'] : null);
            Config::set('mail.from.address', $mail['from_address'] ?? 'noreply@mshop.test');
            Config::set('mail.from.name', $mail['from_name'] ?? 'mshop');
        }

        $storage = $settings['storage'] ?? [];
        $active = $storage['active_driver'] ?? 'local';
        if ($active === 's3' && ($storage['s3_enabled'] ?? false) === true) {
            Config::set('filesystems.disks.s3.key', $storage['s3_key'] ?? '');
            Config::set('filesystems.disks.s3.secret', $storage['s3_secret'] ?? '');
            Config::set('filesystems.disks.s3.region', $storage['s3_region'] ?? 'ap-southeast-1');
            Config::set('filesystems.disks.s3.bucket', $storage['s3_bucket'] ?? '');
            Config::set('filesystems.disks.s3.endpoint', ($storage['s3_endpoint'] ?? '') !== '' ? $storage['s3_endpoint'] : null);
            Config::set('filesystems.disks.s3.use_path_style_endpoint', (bool) ($storage['s3_path_style'] ?? true));
            Config::set('filesystems.default', 's3');
        } else {
            Config::set('filesystems.default', 'public');
        }

        $realtime = $settings['realtime'] ?? [];
        if (($realtime['enabled'] ?? false) === true) {
            Config::set('broadcasting.default', 'reverb');
            Config::set('broadcasting.connections.reverb.key', $realtime['key'] ?? '');
            Config::set('broadcasting.connections.reverb.secret', $realtime['secret'] ?? '');
            Config::set('broadcasting.connections.reverb.app_id', $realtime['app_id'] ?? 'mshop');
            Config::set('broadcasting.connections.reverb.options.host', $realtime['host'] ?? '127.0.0.1');
            Config::set('broadcasting.connections.reverb.options.port', (int) ($realtime['port'] ?? 6001));
            Config::set('broadcasting.connections.reverb.options.scheme', ($realtime['tls'] ?? false) ? 'https' : 'http');
        } else {
            Config::set('broadcasting.default', 'null');
        }
    }

    public function activeStorageDisk(): string
    {
        $storage = $this->get('storage') ?? [];
        $active = $storage['active_driver'] ?? 'local';

        if ($active === 's3' && ($storage['s3_enabled'] ?? false) === true) {
            return 's3';
        }

        return 'public';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function schemaDefaults(): array
    {
        $defaults = [];

        foreach (SettingsSchema::groups() as $group => $schema) {
            $defaults[$group] = SettingsSchema::defaultsFor($group);
        }

        return $defaults;
    }

    private function settingsTableReady(): bool
    {
        try {
            return Schema::hasTable('settings');
        } catch (\Throwable) {
            return false;
        }
    }
}
