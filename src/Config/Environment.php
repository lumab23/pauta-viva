<?php

declare(strict_types=1);

namespace App\Config;

use RuntimeException;

final class Environment
{
    public static function load(string $file): void
    {
        if (!is_file($file)) {
            return;
        }

        $values = parse_ini_file($file, false, INI_SCANNER_RAW);

        if ($values === false) {
            throw new RuntimeException('Não foi possível ler o arquivo de ambiente.');
        }

        foreach ($values as $name => $value) {
            if (getenv((string) $name) !== false) {
                continue;
            }

            $stringValue = (string) $value;
            putenv(sprintf('%s=%s', $name, $stringValue));
            $_ENV[(string) $name] = $stringValue;
        }
    }

    public static function get(string $name, string $default = ''): string
    {
        $value = getenv($name);

        return $value === false ? $default : $value;
    }

    public static function required(string $name): string
    {
        $value = trim(self::get($name));

        if ($value === '') {
            throw new RuntimeException(sprintf('A variável de ambiente %s é obrigatória.', $name));
        }

        return $value;
    }
}

