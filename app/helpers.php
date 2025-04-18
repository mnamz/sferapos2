<?php

use App\Models\ShopSettings;

if (!function_exists('settings')) {
    function settings($key, $default = null)
    {
        static $settings = null;

        if ($settings === null) {
            $settings = ShopSettings::first();
        }

        return $settings?->{$key} ?? $default;
    }
} 