<?php

declare(strict_types=1);

namespace MyTube\Config;

final class DatabaseConfig
{
    /** @return array{host:string,username:string,password:string,database:string} */
    public static function fromEnvironment(): array
    {
        return [
            'host' => $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost',
            'username' => $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '',
            'database' => $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: 'mytube',
        ];
    }
}
