<?php

declare(strict_types=1);

namespace App\Persistence;

use PDO;

final class Database
{
    /**
     * @param array{host: string, port: string, database: string, username: string, password: string} $config
     */
    public static function connect(array $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['database']
        );

        $pdo = new PDO(
            $dsn,
            $config['username'],
            $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        // Usa UTC em todas as datas desta conexão.
        $pdo->exec("SET time_zone = '+00:00'");

        return $pdo;
    }
}
