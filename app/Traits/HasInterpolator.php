<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait HasInterpolator
{
    /**
     * Interpolasi string template.
     *
     * Example:
     *  - Template: "Hello, {{name}}!"
     *  - Data: ['name' => 'Zacky']
     *  - Output: "Hello, Zacky!"
     *
     * @param string $template
     * @param array $data
     * @return string
     */
    public function interpolate(string $template, array $data = []): string
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
