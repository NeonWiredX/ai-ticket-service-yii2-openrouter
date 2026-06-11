<?php

return [
    'class' => 'yii\redis\Connection',
    'hostname' => getenv('REDIS_HOST') ?: 'redis',
    'port' => (int)(getenv('REDIS_PORT') ?: 6379),
    'database' => (int)(getenv('REDIS_DB') ?: 0),
];
