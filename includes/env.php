<?php
function highlander_env($key, $default = null) {
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];
    $value = getenv($key);
    if ($value !== false && $value !== '') return $value;

    $paths = [
        dirname(__DIR__) . '/.env',
        dirname(__DIR__, 2) . '/.env',
    ];

    foreach ($paths as $envPath) {
        if (!file_exists($envPath)) continue;
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) continue;

            list($name, $envValue) = explode('=', $line, 2);
            if (trim($name) !== $key) continue;

            $envValue = trim($envValue);
            if (preg_match('/^["\'](.*)["\']$/', $envValue, $matches)) {
                $envValue = $matches[1];
            }
            return $envValue;
        }
    }

    return $default;
}
?>
