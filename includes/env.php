<?php
// includes/env.php
// Robust environment loader for .env file

if (!function_exists('load_env')) {
    function load_env($filePath = null) {
        if ($filePath === null) {
            $filePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
        }

        if (!file_exists($filePath) || !is_readable($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            if (strpos($line, '=') !== false) {
                list($key, $val) = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val);

                // Strip outer quotes if present
                if ((strpos($val, '"') === 0 && substr($val, -1) === '"') ||
                    (strpos($val, "'") === 0 && substr($val, -1) === "'")) {
                    $val = substr($val, 1, -1);
                }

                // Handle boolean / null strings
                $lower = strtolower($val);
                if ($lower === 'true') {
                    $val = true;
                } elseif ($lower === 'false') {
                    $val = false;
                } elseif ($lower === 'null') {
                    $val = null;
                }

                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
                if (is_scalar($val)) {
                    putenv("$key=$val");
                }
            }
        }
    }
}

if (!function_exists('env')) {
    function env($key, $default = null) {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }
        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }
        $val = getenv($key);
        if ($val !== false) {
            $lower = strtolower($val);
            if ($lower === 'true') return true;
            if ($lower === 'false') return false;
            if ($lower === 'null') return null;
            return $val;
        }
        return $default;
    }
}

// Auto-load on include
load_env();
