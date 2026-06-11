<?php

$db = require __DIR__ . '/db.php';

// test database! Important not to run tests on production or development databases
$host = getenv('DB_HOST') ?: 'postgres';
$port = getenv('DB_PORT') ?: '5432';
$name = getenv('TEST_DB_NAME') ?: 'ticket_test';
$db['dsn'] = "pgsql:host={$host};port={$port};dbname={$name}";

return $db;
