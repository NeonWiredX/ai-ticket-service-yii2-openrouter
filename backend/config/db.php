<?php

$host = getenv('DB_HOST') ?: 'postgres';
$port = getenv('DB_PORT') ?: '5432';
$name = getenv('DB_NAME') ?: 'ticket';

return [
    'class' => 'yii\db\Connection',
    'dsn' => "pgsql:host={$host};port={$port};dbname={$name}",
    'username' => getenv('DB_USER') ?: 'ticket',
    'password' => getenv('DB_PASSWORD') ?: 'ticket',
    'charset' => 'utf8',

    // Schema cache options (for production environment)
    //'enableSchemaCache' => true,
    //'schemaCacheDuration' => 60,
    //'schemaCache' => 'cache',
];
