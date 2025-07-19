<?php

namespace App\Contracts;

interface ShlinkClientContract
{
    /**
     * Shorten a long URL using Shlink.
     *
     * @param string $longUrl
     * @return string The shortened URL or the original URL on failure.
     */
    public function shorten(string $longUrl): string;
}
