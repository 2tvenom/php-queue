<?php
require_once "config.php";
require_once PHP_QUEUE_AUTO_LOADER_PATH;

use PhpQueue\AutoLoader;

AutoLoader::RegisterNamespaces(array('PhpQueue' => PHP_QUEUE_PATH));
AutoLoader::RegisterAutoLoader();

spl_autoload_register(function($class_name){
    $file_name = $class_name . ".php";

    if(strpos($class_name, "Controller") !== false) {
        require_once CONTROLLER_PATH . $file_name;
        return;
    }

    if (strpos($class_name, "Model") !== false) {
        require_once MODEL_PATH . $file_name;
    }

    if (file_exists(LIBRARY_PATH . $file_name)) {
        require_once LIBRARY_PATH . $file_name;
    }
});

/**
 * Web Interface Driver
 */
$web_driver = include(DRIVERS_PATH . "SqlWebDriver.php");

$controller_name = "index";
$action_name = "index";
$view_name = __DIR__ . "/templates/";

if(!empty($_GET))
{
    if(array_key_exists('action', $_GET)) {
        $controller_name = $_GET['action'];
    }

    if (array_key_exists('method', $_GET)) {
        $action_name = $_GET['method'];
    }
}
$view_name .= $controller_name . "/";
$controller_name .= "Controller";

if(!class_exists($controller_name)) {
    header("HTTP/1.0 404 Not Found");
    exit;
}

/**
 * @var prototypeController $controller
 */
$controller = new $controller_name($web_driver);
$view_name .= $action_name . ".php";
$action_name .= "Action";

if(!method_exists($controller, $action_name)) {
    header("HTTP/1.0 404 Not Found");
    exit;
}

$controller->$action_name();

if(!is_null($controller->view_name()))
{
    $view_name = $controller->view_name();
}

if(!file_exists($view_name)) exit;

extract($controller->get_assigned());
ob_start();
require_once $view_name;
$action = ob_get_contents();
ob_end_clean();

if($controller->no_layout())
{
    echo $action;
    exit;
}

require_once "templates/layout.php";

