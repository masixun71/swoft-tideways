<?php
use ExtraSwoft\Tideways\Middleware\TidewaysMiddleware;

return [
    'ExtraSwoft\\Tideways\\Middleware\\TidewaysMiddleware' => [
        'class' => TidewaysMiddleware::class,
        'root' => '${config.tideways.root}',
        'start' => '${config.tideways.start}',
        'host' => '${config.tideways.host}',
        'db' => '${config.tideways.db}',
    ]
];