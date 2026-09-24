<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        $setting = static::where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    public static function enabled(string $key, bool $default = true): bool
    {
        $value = static::get($key, $default ? '1' : '0');

        return in_array($value, ['1', 'true', 'on', 'yes'], true);
    }

    public static function set(string $key, ?string $value): static
    {
        return tap(static::firstOrNew(['key' => $key]), function ($setting) use ($value) {
            $setting->value = $value;
            $setting->save();
        });
    }
}
