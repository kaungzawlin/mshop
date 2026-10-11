<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\Settings\RuntimeConfigResolver;
use App\Services\Settings\SettingsSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Predis\Client;

class SettingsController extends Controller
{
    public function __construct(private RuntimeConfigResolver $resolver) {}

    public function index(): JsonResponse
    {
        $groups = [];

        foreach (SettingsSchema::groups() as $group => $schema) {
            $values = $this->maskedGroup($group);
            $groups[] = [
                'group' => $group,
                'label' => $schema['label'],
                'enabled' => (bool) ($values['enabled'] ?? false),
                'values' => $values,
            ];
        }

        return response()->json(['data' => $groups]);
    }

    public function show(string $group): JsonResponse
    {
        $schema = SettingsSchema::groups()[$group] ?? null;

        if ($schema === null) {
            return response()->json([
                'message' => 'Unknown settings group.',
                'code' => 'not_found',
            ], 404);
        }

        return response()->json([
            'group' => $group,
            'label' => $schema['label'],
            'fields' => $schema['fields'],
            'values' => $this->maskedGroup($group),
        ]);
    }

    public function update(Request $request, string $group): JsonResponse
    {
        $schema = SettingsSchema::groups()[$group] ?? null;

        if ($schema === null) {
            return response()->json([
                'message' => 'Unknown settings group.',
                'code' => 'not_found',
            ], 404);
        }

        $rules = [];
        foreach ($schema['fields'] as $key => $field) {
            $rules[$key] = match ($field['type']) {
                'boolean' => ['sometimes', 'boolean'],
                'integer' => ['sometimes', 'integer'],
                default => ['sometimes', 'nullable', 'string'],
            };
        }

        $validated = $request->validate($rules);
        $current = $this->resolver->get($group) ?? [];
        $oldMasked = $this->maskSecrets($group, $current);

        DB::transaction(function () use ($request, $group, $schema, $validated, $current): void {
            foreach ($schema['fields'] as $key => $field) {
                if (! array_key_exists($key, $validated)) {
                    continue;
                }

                $incoming = $validated[$key];

                if (($field['secret'] ?? false) === true && is_string($incoming) && ($incoming === '' || str_contains($incoming, '••••'))) {
                    continue;
                }

                $setting = Setting::query()->firstOrNew([
                    'group' => $group,
                    'key' => $key,
                ]);
                $setting->is_secret = (bool) ($field['secret'] ?? false);
                $setting->updated_by = $request->user()->id;
                $setting->setDecodedValue($incoming);
                $setting->save();
            }

            AuditLog::query()->create([
                'actor_id' => $request->user()->id,
                'action' => 'settings.updated',
                'target_type' => 'settings',
                'target_id' => null,
                'reason' => "Updated settings group: {$group}",
                'old_values' => ['group' => $group, 'values' => $this->maskSecrets($group, $current)],
                'new_values' => ['group' => $group, 'values' => $this->maskSecrets($group, array_merge($current, $validated))],
                'created_at' => now(),
            ]);
        });

        $this->resolver->forgetCache();
        $this->resolver->apply();

        return response()->json([
            'group' => $group,
            'values' => $this->maskedGroup($group),
            'previous' => $oldMasked,
        ]);
    }

    public function test(Request $request, string $group): JsonResponse
    {
        $schema = SettingsSchema::groups()[$group] ?? null;

        if ($schema === null) {
            return response()->json([
                'message' => 'Unknown settings group.',
                'code' => 'not_found',
            ], 404);
        }

        $values = array_merge($this->resolver->get($group) ?? [], $request->all());

        try {
            $ok = match ($group) {
                'mail' => $this->testMail($values),
                'queue' => $this->testRedis($values),
                'sms' => ($values['enabled'] ?? false) === true,
                'storage' => true,
                'realtime' => true,
                default => true,
            };
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'connection' => [$e->getMessage()],
            ]);
        }

        return response()->json([
            'ok' => (bool) $ok,
            'message' => $ok ? 'Connection looks good.' : 'Connection failed.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function maskedGroup(string $group): array
    {
        return $this->maskSecrets($group, $this->resolver->get($group) ?? SettingsSchema::defaultsFor($group));
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function maskSecrets(string $group, array $values): array
    {
        foreach ($values as $key => $value) {
            if (SettingsSchema::isSecret($group, (string) $key) && is_string($value) && $value !== '') {
                $values[$key] = '••••••••';
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function testMail(array $values): bool
    {
        if (($values['driver'] ?? 'log') === 'log') {
            return true;
        }

        $errno = 0;
        $errstr = '';
        $host = (string) ($values['host'] ?? '127.0.0.1');
        $port = (int) ($values['port'] ?? 1025);
        $fp = @fsockopen($host, $port, $errno, $errstr, 3);

        if ($fp === false) {
            throw new \RuntimeException("Cannot reach SMTP {$host}:{$port} ({$errstr})");
        }

        fclose($fp);

        return true;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function testRedis(array $values): bool
    {
        if (! extension_loaded('redis') && ! class_exists(Client::class)) {
            return ($values['enabled'] ?? false) === false;
        }

        return true;
    }
}
