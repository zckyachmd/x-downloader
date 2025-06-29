<?php

namespace App\Utils;

use Illuminate\Support\Facades\Log;

class Interpolator
{
    /**
     * Interpolasi string template dengan data kunci.
     *
     * Example:
     *  - Template: "Hello, {{name}}!"
     *  - Data: ['name' => 'Zacky']
     *  - Output: "Hello, Zacky!"
     */
    public static function render(string $template, array $data = []): string
    {
        return preg_replace_callback('/{{(\w+)}}/', function ($matches) use ($data) {
            $key   = $matches[1];
            $value = $data[$key] ?? '';

            if ($value === '' || $value === null) {
                Log::warning("[Interpolator] Empty or missing value for placeholder: {{$key}}");

                return '';
            }

            return $value;
        }, $template);
    }
}
