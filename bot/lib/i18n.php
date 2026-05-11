<?php
/**
 * Класс локализации для чтения JSON словарей.
 */

class I18n
{
    private static $translations = [];
    private static $currentLang = 'en';

    public static function load(string $lang, string $dir = __DIR__ . '/../lang')
    {
        self::$currentLang = $lang;
        $file = rtrim($dir, '/') . '/' . $lang . '.json';
        
        if (file_exists($file)) {
            $json = file_get_contents($file);
            self::$translations = json_decode($json, true) ?? [];
        } else {
            self::$translations = [];
        }
    }

    public static function get(string $key, array $replacements = []): string
    {
        $keys = explode('.', $key);
        $value = self::$translations;

        foreach ($keys as $k) {
            if (is_array($value) && array_key_exists($k, $value)) {
                $value = $value[$k];
            } else {
                return $key; // Возвращаем ключ, если перевод не найден
            }
        }

        if (is_string($value)) {
            if (!empty($replacements)) {
                return vsprintf($value, $replacements);
            }
            return $value;
        }

        return $key;
    }
}

/**
 * Глобальный хелпер для переводов, как в Laravel
 */
if (!function_exists('__')) {
    function __(string $key, ...$replacements)
    {
        return I18n::get($key, $replacements);
    }
}
