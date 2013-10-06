<?php
require_once "../src/PhpQueue/AutoLoader.php";

use PhpQueue\AutoLoader;

AutoLoader::RegisterDirectory(array('.', '../examples/Callbacks', '../examples/Tasks/Test'));
AutoLoader::RegisterNamespaces(array('PhpQueue' => '../src/PhpQueue'));
AutoLoader::RegisterAutoLoader();
