<?php
ini_set('display_errors', 1);

define('TASKS_LIST_LIMIT', 30);

define("ROOT_PATH",  __DIR__ . "/");
define("DRIVERS_PATH",  __DIR__ . "/drivers/");
define("CONTROLLER_PATH", ROOT_PATH . "controllers/");
define("MODEL_PATH", ROOT_PATH . "model/");
define("LIBRARY_PATH", ROOT_PATH . "library/");
define("TEMPLATE_PATH", ROOT_PATH . "templates/" );
define("PARTIAL_TEMPLATE_PATH", TEMPLATE_PATH . "task_render/" );
define("PHP_QUEUE_PATH", ROOT_PATH . "../src/PhpQueue");
define("PHP_QUEUE_AUTO_LOADER_PATH", PHP_QUEUE_PATH . "/AutoLoader.php");

define("LIST_PARTIAL_TEMPLATE_NAME", "list.php" );
define("DETAILS_PARTIAL_TEMPLATE_NAME", "details.php" );
define("LOG_PARTIAL_TEMPLATE_NAME", "log.php" );
define("ERROR_PARTIAL_TEMPLATE_NAME", "error.php" );
define("PARTIAL_DEFAULT_TEMPLATE_NAME", "default" );