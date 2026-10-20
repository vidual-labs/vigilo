<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Tiny key/value store for install-level state (e.g. "demo data seeded"). */
class AppSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $row = static::query()->find($key);

        return $row ? ($row->value['v'] ?? $default) : $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => ['v' => $value]]);
    }
}
