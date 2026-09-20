<?php

$envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
$env = is_file($envFile) ? parse_ini_file($envFile, false, INI_SCANNER_RAW) : false;

if ($env === false) {
    throw new RuntimeException('The .env file is missing or cannot be read.');
}

function env(string $key, ?string $default = null): ?string
{
    global $env;

    if (array_key_exists($key, $env)) {
        return (string) $env[$key];
    }

    return $default;
}

function required_env(string $key): string
{
    $value = env($key);

    if ($value === null || $value === '') {
        throw new RuntimeException("Missing required environment variable: {$key}");
    }

    return $value;
}