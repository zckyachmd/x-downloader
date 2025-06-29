<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Config extends Model
{
    protected $table = 'configs';

    protected $fillable = [
        'name',
        'key',
        'description',
        'value',
        'type',
    ];

    public function getParsedValueAttribute()
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'int'     => (int) $this->value,
            'json'    => json_decode($this->value, true),
            default   => $this->value,
        };
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        return cache()->remember("config:$key", now()->addMinutes(10), function () use ($key) {
            return static::where('key', $key)->first()?->parsed_value;
        }) ?? $default;
    }
}
