<?php

declare(strict_types=1);

function app_environment(): string
{
    return strtolower(trim((string) ($_ENV['APP_ENV'] ?? 'local')));
}

function app_is_production(): bool
{
    return in_array(app_environment(), ['production', 'producao'], true);
}

function env_bool(string $key, bool $default = false): bool
{
    if (!array_key_exists($key, $_ENV)) return $default;
    return filter_var($_ENV[$key], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
}

