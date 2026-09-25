<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
http_response_code(501);

echo json_encode(
    [
        'error' => [
            'code' => 'not_implemented',
            'message' => 'A API será implementada na etapa de back-end.',
        ],
    ],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

