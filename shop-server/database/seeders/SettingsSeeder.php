<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\Settings\SettingsSchema;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SettingsSchema::groups() as $group => $schema) {
            foreach ($schema['fields'] as $key => $field) {
                $setting = Setting::query()->firstOrNew([
                    'group' => $group,
                    'key' => $key,
                ]);

                if ($setting->exists) {
                    continue;
                }

                $setting->is_secret = (bool) ($field['secret'] ?? false);
                $setting->setDecodedValue($field['default']);
                $setting->save();
            }
        }
    }
}
