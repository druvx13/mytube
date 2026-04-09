<?php

declare(strict_types=1);

namespace MyTube\Config;

final class Environment
{
    public static function load(string $projectRoot): void
    {
        $envFile = $projectRoot . '/.env';

        if (!is_file($envFile)) {
            return;
        }

        if (class_exists(\Dotenv\Dotenv::class)) {
            \Dotenv\Dotenv::createImmutable($projectRoot)->safeLoad();
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $trimmed, 2);
            $name = trim($name);
            $value = trim($value);

            if ($name === '') {
                continue;
            }

            if (!isset($_ENV[$name])) {
                $_ENV[$name] = $value;
            }

            if (getenv($name) === false) {
                putenv($name . '=' . $value);
            }
        }
    }
}
