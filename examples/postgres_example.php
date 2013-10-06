<?php
require_once __DIR__ . "/../src/PhpQueue/AutoLoader.php";

use PhpQueue\Queue;
use PhpQueue\AutoLoader;
use PhpQueue\Drivers\SqlPdoDriver;
use PhpQueue\TaskPerformer;

AutoLoader::RegisterDirectory(array('Callbacks', 'Tasks/Example'));
AutoLoader::RegisterNamespaces(array('PhpQueue' => '../src/PhpQueue'));
AutoLoader::RegisterAutoLoader();

$pdo = new \PDO("pgsql:dbname=queue", "postgres", "", array(
    PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING
));

$pdo->query("delete from queue_tasks");

$driver = new SqlPdoDriver($pdo);
$queue = new Queue($driver);
$task_performer = new TaskPerformer();

$simple_task = new \PhpQueue\Task("Job1");
$simple_task->set_execution_date(date('Y-m-d H:i:s', strtotime('now') + 3));
$queue->add_task($simple_task);

$task = $queue->get_task();
var_dump($task);

sleep(4);

$task = $queue->get_task();
var_dump($task);

exit;

//one task
$task = new \PhpQueue\Task("Job");
$task
    ->set_exclusive(true)
    ->sub_tasks()
    ->add(new \PhpQueue\Task("Job1", 5))
    ->add(new \PhpQueue\Task("Job1", 10))
    ->add(new \PhpQueue\Task("Job1", 15))
    ->add(new \PhpQueue\Task("Job1", 15))
    ->add(new \PhpQueue\Task("Job1", 15))
    ->add(new \PhpQueue\Task("Job1", 15))
    ->add(new \PhpQueue\Task("Job1", 15));

$queue->add_task($task);

$task = new \PhpQueue\Task("Job");
//$task->set_priority(2);
$queue->add_task($task);

$task = new \PhpQueue\Task("Job");
//$task->set_priority(1);
$queue->add_task($task);

$task = new \PhpQueue\Task("Job");
//$task->set_priority(3);
$queue->add_task($task);


for($i=0; $i<10; $i++)
{
    $new_task = $queue->get_task();

    if(!$new_task) break;

    $new_task = $task_performer->execute_task($new_task);

    $queue->modify_task($new_task);
}