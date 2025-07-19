<?php

namespace App\Traits;

use App\Models\Config;

trait HasConfig
{
    /**
     * Get config from CLI option (if available), else fallback to DB config.
     * If $optionKey is null, skip CLI check.
     */
    public function getConfig(string $configKey, mixed $default = null, ?string $optionKey = null): mixed
    {
        if ($optionKey && method_exists($this, 'option')) {
            return $this->option($optionKey) ?? Config::getValue($configKey, $default);
        }

        return Config::getValue($configKey, $default);
    }
}
