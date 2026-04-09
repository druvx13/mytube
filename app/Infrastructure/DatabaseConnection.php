<?php

declare(strict_types=1);

namespace MyTube\Infrastructure;

final class DatabaseConnection
{
    /** @param array{host:string,username:string,password:string,database:string} $config */
    public static function create(array $config): \mysqli
    {
        return new \mysqli(
            $config['host'],
            $config['username'],
            $config['password'],
            $config['database']
        );
    }
}
