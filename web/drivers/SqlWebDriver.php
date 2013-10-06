<?php
/**
 * PhpQueue MySQL Web driver
 */
define("QUEUE_HOST", "localhost");
define("QUEUE_DATABASE", "queue");
define("QUEUE_USER", "root");
define("QUEUE_PASSWORD", "");
define("QUEUE_TABLE", "queue_tasks");

return new \PhpQueue\Drivers\SqlPdoDriver(new PDO("mysql:host=" . QUEUE_HOST . ";dbname=" . QUEUE_DATABASE, QUEUE_USER, QUEUE_PASSWORD, array(
    PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING
)));