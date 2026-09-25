<?php

declare(strict_types=1);

use App\Config\Environment;

return [
    'host' => Environment::get('DB_HOST', '127.0.0.1'),
    'port' => Environment::get('DB_PORT', '3306'),
    'database' => Environment::required('DB_DATABASE'),
    'username' => Environment::required('DB_USERNAME'),
    'password' => Environment::get('DB_PASSWORD', ''),
];

