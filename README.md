#PhpQueue
--------
The PhpQueue library provides queue for execution php scripts. This code has been developed and maintained by Ven from August 2013 to October 2013.

You can send comments, patches, questions here on github or to <2tvenom@gmail.com>

-----
1. [Queue](#queue)
    *   [Simple queue](#simple-queue)
    *   [Adding task to queue](#adding-task-to-queue)
    *   [Get task from queue and execute](#get-task-from-queue-and-execute)
2. [Task](#task)
    *   [Create task](#create-task)
    *   [Request data](#request-data)
    *   [Select task from queue](#select-task-from-queue)
    *   [Priority](#priority)
    *   [Task group id](#task-group-id)
    *   [Execution date](#execution-date)
    *   [Unique id](#unique-id)
3. [Task and sub tasks](#task-and-sub-tasks)
    *   [Sub tasks](#sub-tasks)
    *   [Exclusive tasks](#exclusive-tasks)
        *   [Non exclusive](#non-exclusive)
        *   [Exclusive](#exclusive)
    *   [Task response, log, parent](#task-response-log-parent)
    *   [Response](#response)
    *   [Log](#log)
    *   [Access to parent task](#access-to-parent-task)
4. [Error in task and sub tasks](#error-in-task-and-sub-tasks)
    *   [Error in simple task](#error-in-simple-task)
    *   [Error in task with sub tasks](#error-in-task-with-sub-tasks)
    *   [Nested settings](#nested-settings)
    *   [Error log](#error-log)
5. [Callbacks and error callback](#callbacks-and-error-callback)
    *   [Callback](#callback)
    *   [Error callback](#error-callback)
    *   [Callbacks of Parent task](#callbacks-of-parent-task)
    *   [Global callback](#global-callback)
    *   [Errors in callback](#errors-in-callback)
    *   [Callback execution priority](#callback-execution-priority)
6. [Queue drivers](#queue-drivers)
    *   [SqlPdoDriver](#sqlpdodriver)
    *   [FileDriver](#filedriver)
7. [Autoloader](#autoloader)
8. [Web interface](#web-interface)
    *   [Installation](#installation)
    *   [Driver connection](#driver-connection)
    *   [Driver connection property](#driver-connection-property)
    *   [Customisation](#customisation)
-----

##Queue
###Simple queue
--------
###Adding task to queue

PhpQueue have components: Queue, QueueDriver, TaskPerformer and Task.

#####*Example*
```php
<?
use PhpQueue\Queue;
use PhpQueue\Drivers\SqlPdoDriver;
use PhpQueue\TaskPerformer;
use PhpQueue\Task;

$pdo = new \PDO("mysql:host=localhost;dbname=queue", "root", "");
$driver = new SqlPdoDriver($pdo);
$queue = new Queue($driver);

$task = new Task("Job");
$queue->add_task($task);
?>
```
--------
###Get task from queue and execute
Steps of getting task from the queue and execute. Queue return `false` If queue not have task.
1. Connect to queue
2. Get task from queue
3. **Queue set task status In Process**
4. Execute task
5. Modify task status in queue (**Warning!** This step is required)

#####*Example*
```php
<?
$pdo = new \PDO("mysql:host=localhost;dbname=queue", "root", "");

$driver = new SqlPdoDriver($pdo);
$queue = new Queue($driver);
$task_performer = new TaskPerformer();

$task = $queue->get_task();
//in queue this task have status "In process"
$task = $task_performer->execute_task($task);
$queue->modify_task($task);
?>
```

--------
## Task

### Create task
Task name this is name of class implemented in `PhpQueue\Interfaces\IJob`

#####*Example*
```php
<?
class Job implements PhpQueue\Interfaces\IJob
{
    public static function run(Task $task)
    {
        return 2*2;
    }
}

$task = new PhpQueue\Task("Job");
?>
```
--------
### Request data

Task can receive input data. This is one object, array or scalar value.

#####*Example*
```php
<?
class Job implements PhpQueue\Interfaces\IJob
{
    public static function run(Task $task)
    {
        return $task->get_request_data() * 2;
    }
}
?>
```

You can set data in constructor `$task = new PhpQueue\Task("Job", 100);` or by method `$task->set_request_data(100);`

-------
###Select task from queue

-------
###Priority
Tasks in queue sorted by priority. All tasks by default have zero priority. If the tasks have the same priority, then to order by id desc.

Set priority: `set_priority($priority)`. Priority must be integer.

Get priority: `get_priority()`

####*Example*
```php
<?
$task = new PhpQueue\Task("Job");
$queue->add_task($task);

$super_task = new PhpQueue\Task("SuperJob");
$super_task->set_priority(10);
$queue->add_task($super_task);
```

####*Result*
First performed task will `SuperJob`, next `Job`

-------
###Task group id

It is possible divide the queue to several queues by `task_group_id`.
By default all tasks have group id is `0`. 

Set task group id: `set_task_group_id($group_id)`. Group id must be integer.

Get priority: `get_task_group_id()`

####*Example*
Adding tasks
```php
<?
$task1 = new PhpQueue\Task("Job1");
$task1->set_task_group_id(1);
$queue->add_task($task1);

$task2 = new PhpQueue\Task("Job2");
$task2->set_task_group_id(2);
$queue->add_task($task2);
```
Get task
```php
<?
$task1 = $queue->get_task(array(
    TaskConst::TASK_GROUP_ID => 1
));

$task2 = $queue->get_task(array(
    TaskConst::TASK_GROUP_ID => 2
));

$task3 = $queue->get_task();
```

####*Result*
task1 is `Job1`

task2 is `Job2`

task3 is `false`

-------
### Execution date

Task will execute `after` this date.

Set execution date: `set_execution_date($date)`. Date format is `Y-m-d H:i:s`

Get date: `get_execution_date()`

####*Example*
```php
<?
$task = new Task("JobByDate");
$task->set_execution_date(date('Y-m-d H:i:s', strtotime('now') + 10));
$queue->add_task($task);

$task1 = $queue->get_task();
sleep(15);
$task2 = $queue->get_task();
```
####*Result*
task1 is `false`

task2 is `JobByDate`

-------
### Unique id

All tasks have unique id. Generated when the an object is created. Unique id is string of 32 characters (md5).

Select from queue by unique id **not set** task status to `In process`

Get unique id of task: `get_uniqid()`.

####*Example*
Add to queue
```php
<?
$task = new Task("Job");
$queue->add_task($task);
$uniqid = $task->get_uniqid();
```
####*Result*
String: `a8a042ffabf5230dfdfa0a2cf9d47110`

Select by unique id
```php
<?
$task = $queue->get_task(array(
    TaskConst::UNIQID => "a8a042ffabf5230dfdfa0a2cf9d47110"
));

```
####*Result*
$task is Task `Job` with unique id `a8a042ffabf5230dfdfa0a2cf9d47110`.
Task still have status `New` in queue

-------
##Task and sub tasks
-------
###Sub tasks 
-------
PhpQueue have two type of tasks:
* Simple task
* Task with sub tasks

Task can have sub tasks with one nested level.

####*Example*
Adding sub tasks
```php
<?
$task = new Task("JobWithSubTasks");
$task
    ->sub_tasks()
    ->add(new \PhpQueue\Task("Job1", 5))
    ->add(new \PhpQueue\Task("Job2", 10))
    ->add(new \PhpQueue\Task("Job3", 15));
    
$queue->add_task($task);
```

Performer will execute only sub tasks.

####*Example*
Execute sub tasks
```php    
for($i=0; $i<3; $i++){
    $task = $queue->get_task();
    $task = $task_performer->execute_task($task);
    $queue->modify_task($task);
}
```
####*Result*
Execute steps:
1. Execute `Job1`. Status done
2. Execute `Job2`. Status done
3. Execute `Job3`. Status done
4. `JobWithSubTasks` status done. _Parent task not perform._

-------
### Exclusive tasks

By default task have exclusive status `false`.

-------

#### Non exclusive
Subtasks of task can execute all Task Performers.

#####*Example*
Add task
```php    
<?
$task = new Task("NonExclusiveTask");
$task
    ->sub_tasks()
    ->add(new \PhpQueue\Task("Job1", 5))
    ->add(new \PhpQueue\Task("Job2", 10))
    ->add(new \PhpQueue\Task("Job3", 15));
    
$queue->add_task($task);
```

On three servers _(server1, server2, server3)_ started TaskPerformers
```php
<?
$performer_name = php_uname('n');
$task_performer = new TaskPerformer($performer_name);
$task = $queue->get_task();
$task = $task_performer->execute_task($task);
$queue->modify_task($task);
```
####*Result*
NonExclusiveTask task status is `DONE`

- `Job1` performed by `server1`. Status `DONE`
- `Job2` performed by `server2`. Status `DONE` 
- `Job3` performed by `server3`. Status `DONE`

Get performer name: `get_performer()`. By default performer name is `Default_Performer`

----------

#### Exclusive
Subtasks of task can execute only one Task Performer.

Set exclusive: `set_exclusive(true)`

#####*Example*
Add task
```php
<?
$task = new Task("ExclusiveTask");
$task
    ->set_exclusive(true)
    ->sub_tasks()
    ->add(new \PhpQueue\Task("Job1", 5))
    ->add(new \PhpQueue\Task("Job2", 10))
    ->add(new \PhpQueue\Task("Job3", 15));
    
$queue->add_task($task);
```
On three servers _(server1, server2, server3)_ started TaskPerformers
```php
<?
$performer_name = php_uname('n');
$task_performer = new TaskPerformer($performer_name);
$task = $queue->get_task(
    array(TaskConst::PERFORMER => $performer_name)
);
$task = $task_performer->execute_task($task);
$queue->modify_task($task);
```
####*Result*
ExclusiveTask task status is `DONE`

- `Job1` performed by `server1`. Status `DONE`
- `Job2` performed by `server1`. Status `DONE` 
- `Job3` performed by `server1`. Status `DONE`
- `server2` receive `false` from queue
- `server3` receive `false` from queue

####Warning
####All TaskPerformers must have unique name when you use exclusive tasks.
If you not made it you can break logic of queue. Queue and performer not receive second sub task after first performed if not add in queue request `get_task()` TaskConst::PERFORMER unique name.

----------
### Task response, log, parent
----------
#### Response
----------
Task response is return data of `run` method

####*Example*
```php
<?
$task = new Task("ParentJob");
$task
    ->set_exclusive(true)
    ->sub_tasks()
    ->add(new \PhpQueue\Task("Job1", 5))
    ->add(new \PhpQueue\Task("Job2", 10))
    ->add(new \PhpQueue\Task("Job3", 15));
    
class Job1 implements PhpQueue\Interfaces\IJob
{
    public static function run(Task $task)
    {
        $sub_task_request_data = $task->get_request_data();
        
        return $sub_task_request_data + 5;
    }
}
```

####*Result*
Get response: `$task->response()->get_response()`

- `Job1` response: 10
- `Job2` response: 15
- `Job3` response: 20

----------
#### Log
----------
Logging in task
####*Example*
```php
<?
class Job1 implements PhpQueue\Interfaces\IJob
{
    public static function run(Task $task)
    {
        $arg1 = 1;
        $task->response()->set_log('Step 1');
        
        $arg2 = 2;
        $task->response()->set_log('Step 2');
        
        return $arg1+$arg2;
    }
}
```

####*Result*
```php
<?
$logs = $task->response()->get_log();
```
Return: array `array('Step 1', 'Step2');`

----------
#### Access to parent task
----------
Method `$task->parent_task()`

####*Example*
```php
<?
$task = new Task("ParentTask", 5);
$task
    ->set_exclusive(true)
    ->sub_tasks()
    ->add(new \PhpQueue\Task("Job1", 5))
    ->add(new \PhpQueue\Task("Job2", 10))
    ->add(new \PhpQueue\Task("Job3", 15));
    
class Job1 implements PhpQueue\Interfaces\IJob
{
    public static function run(Task $task)
    {
        $sub_task_request_data = $task->get_request_data();
        $parent_request_data = $task->parent_task()->get_request_data();
        
        return $sub_task_request_data * $parent_request_data;
    }
}
class Job2 extends Job1 {}
class Job3 extends Job1 {}
```

####*Result*
Response

- `Job1` response: 25
- `Job2` response: 50
- `Job3` response: 75

---------
## Error in task and sub tasks
---------
### Error in simple task

After first exception task got status: `ERROR`. You can set maximum error trials for task.

Set maximum error trials `$task->settings()->set_error_max_trial($trials)`. Where `$trials` is maximum trials count (int).

Get current trial number `$task->settings()->get_trial()`. Return: int, number of current trial.

####*Example*
```php
<?
$task = new Task("ExceptionTask");
$task->settings()->set_error_max_trial(5);

class ExceptionTask implements PhpQueue\Interfaces\IJob
{
    public static function run(Task $task)
    {
        if($task->settings()->get_trial() < 3)
        {
            throw new Exception("Test exception");
        }
        return 100;
    }
}
```

####*Result*
Response

1. Error. Status "New"
2. Error. Status "New"
3. Done.

---------
### Error in task with sub tasks

By default after last error trial parent task got status `ERROR`.

If need continue execute sub tasks need set `error break` to `false`

Set error break flag `$task->settings()->set_error_break($flag)`. Where `$flag` is bool.

####*Example*
```php
<?
$task = new Task("TaskWithOneExceptionSubTask");
$task->
    subtasks()
    ->add(new \PhpQueue\Task("Job1"))
    ->add(new \PhpQueue\Task("ExceptionTask"))
    ->add(new \PhpQueue\Task("Job2"));

class ExceptionTask implements PhpQueue\Interfaces\IJob
{
    public static function run(Task $task)
    {
        throw new Exception("Test exception");
    }
}

class Job1 implements PhpQueue\Interfaces\IJob
{
    public static function run(Task $task)
    {
        return true;
    }
}

class Job2 extends Job1 {}
```

####*Result with error break flag = false*
- `Job1` status `Done`
- `ExceptionTask` status `Error`
- `TaskWithOneExceptionSubTask` status `Error`

####*Result with error break flag = true*
- `Job1` status `Done`
- `ExceptionTask` status `Error`
- `Job2` status `Done`
- `TaskWithOneExceptionSubTask` status `Done with Error`

### Nested settings

Settitng in parent task cover settings in sub tasks. Settings in sub task override settings of parent task.

####*Example*
```php
<?
$task = new Task("ParentJob");
$task->settings()->set_error_max_trial(5);

$job1 = new \PhpQueue\Task("Job1");
$job2 = new \PhpQueue\Task("Job2");
$job2->settings()->set_error_max_trial(2);
$job3 = new \PhpQueue\Task("Job3");

$task->subtasks()->add(array($job1, $job2, $job3,));
```

####*Result*
- `Job1` max trials 5
- `Job1` max trials 2
- `Job1` max trials 5

---------
### Error log

Get exception log from task: `$task->response()->get_error()` Return: array

---------
## Callbacks and error callback

### Callback
Callback execute after task. 

PhpQueue have two types of callbacks:
- Callback `PhpQueue\Interfaces\ICallback`
- Error callback `PhpQueue\Interfaces\IErrorCallback`

#### *Example*
```php
<?
$task = new Task("TaskWithCallback");
$task->set_callback("SimpleCallback");

class SimpleCallback implements PhpQueue\Interfaces\ICallback
{
    public static function callback_run(Task $task)
    {
        echo "Callback";
    }
}
```
#### *Result*
TaskWithCallback executed and "Callback" displayed on the screen

--------
### Error callback
Error callback executed after error in task

```php
<?
$task = new Task("TaskWithError");
$task
    ->set_callback("SimpleCallback")
    ->set_error_callback("ErrorCallback");

class TaskWithError implements PhpQueue\Interfaces\IJob
{
    public static function run(Task $task)
    {
        throw new Exception("Test exception");
    }
}

class ErrorCallback implements PhpQueue\Interfaces\IErrorCallback
{
    public static function callback_error_run(Task $task)
    {
        echo "ERROR!";
    }
}
```
#### *Result*
TaskWithCallback executed and "ERROR!" displayed on the screen

-------
### Callbacks of Parent task

Sub tasks and parent tasks can have callbacks. First executed subtasks callbacks, after parent callback.

#### *Example*
```php
<?
$task = new Task("TaskWithSubTasks");
$task->set_callback("ParentCallback")

$job1 =new \PhpQueue\Task("Job");
$job1->set_callback("Callback");
$job2 =new \PhpQueue\Task("Job");
$job3 =new \PhpQueue\Task("Job");
$job4 =new \PhpQueue\Task("Job");
$job4->set_callback("Callback")

$task->subtasks()->add(array($job1, $job2, $job3, $job4));

class Job implements PhpQueue\Interfaces\IJob
{
    public static function run(Task $task)
    {
        echo "Job";
    }
}

class Callback implements PhpQueue\Interfaces\ICallback
{
    public static function callback_run(Task $task)
    {
        echo "Callback";
    }
}
```

#### *Result*
Displayed on screen:

```
Job
Callback
ParentCallback
Job
ParentCallback
Job
ParentCallback
Job
Callback
ParentCallback
```
-----------
### Global callback
Task performer cah Callback and Error callback

#### *Example*
```php
<?
$task = new Task("TaskWithException");

class TaskWithException implements PhpQueue\Interfaces\IJob
{
    public static function run(Task $task)
    {
        throw new Exception("Test exception");
    }
}

class ErrorCallback implements PhpQueue\Interfaces\IErrorCallback
{
    public static function callback_error_run(Task $task)
    {
        echo "GLOBAL ERROR!";
    }
}

$task_performer = new TaskPerformer();
$task_performer->set_global_error_callback("ErrorCallback");
$task_performer->execute_task($task);
```

#### *Result*
Displayed on screen "GLOBAL ERROR!"

### Errors in callback

After arror in callback task got status: `CALLBACK ERROR` without execution other callbacks.

### Callback execution priority

*Callback:*
1. Sub task callback/error callback
2. Parent callback/error callback
3. Global callback/error callback

-------
## Queue drivers

PhpQueue support:
- MySql
- PostgreSQL
- SQLite
- File

*In future: Mongo, Redis*

### SqlPdoDriver
*Requirement*: PDO

#### *Example*
Create MySQL based queue
```php
<?
$pdo = new \PDO("mysql:host=localhost;dbname=queue", "root", "");
$driver = new SqlPdoDriver($pdo);
$queue = new Queue($driver);
```

### FileDriver
*Requirement*: SimpleXMLElement

#### *Example*
Create file based queue
```php
<?
$driver = new SqlPdoDriver('queue_folder');
$queue = new Queue($driver);
```

## Autoloader
#### *Example*
```php
<?
require_once __DIR__ . "/../src/PhpQueue/AutoLoader.php";

use PhpQueue\AutoLoader;
AutoLoader::RegisterDirectory(array('Callbacks', 'Tasks/Example'));
AutoLoader::RegisterNamespaces(array('PhpQueue' => '../src/PhpQueue'));
AutoLoader::RegisterAutoLoader();
```

------
## Web interface

![Alt text](https://raw.github.com/2tvenom/PhpQueue/master/web/screenshots/interface_default.jpg "Queue Web interface")

### Installation
Just copy queue files from folder `web` to web server folder.

### Driver connection

In file `web/index.php` find this line:

```php
<?
$web_driver = include(DRIVERS_PATH . "SqlWebDriver.php");
```
and change path to file with driver (if need it).

###Driver connection property

Connection property in driver file in folder `drivers`.

####*Example*
Connection property of SqlWebDriver

```php
<?
define("QUEUE_HOST", "localhost");
define("QUEUE_DATABASE", "queue");
define("QUEUE_USER", "root");
define("QUEUE_PASSWORD", "");
define("QUEUE_TABLE", "queue_tasks");
```

### Customisation
![Alt text](https://raw.github.com/2tvenom/PhpQueue/master/web/screenshots/interface.jpg "Queue Web interface")

You can customise web interface 

PhpQueue have 4 types of web interface:
- List
- Details
- Log
- Error log

For customisation of task need create a folder with the name of the task.

####*Example*
```php
<?
//Task class
class JobLog implements Interfaces\IJob
{
    public static function run(Task $task)
    {
        for($i=0; $i<6; $i++)
        {
            $task->response()->set_log(ceil(rand(0, 100) / 10));
        }
        return $task->get_request_data()*2;
    }
}

// /web/templates/task_render/JobLog/log.php

foreach($list as $_id => $log_string){
    $performed = (int)$log_string;
    $not_performed = 10 - $performed;
    echo "Fork {$_id} <span style='color:#009944'>" . str_repeat("#", $performed) . "</span><span style='color:#ff0000'>" . str_repeat("#",$not_performed) . "</span><br>";
}

// /web/templates/task_render/JobLog/list.php

/**
 * @var array $task
 */
use PhpQueue\Task;
use PhpQueue\TaskConst;
?>
<div class="row">
    <div class="col-md-12">
        <span class="pull-right label label-<?= TaskModel::$class_by_status[$task[TaskConst::STATUS]] ?>">
            <?= TaskModel::$status_text[$task[TaskConst::STATUS]] ?>
            <? if ($task[TaskConst::STATUS] == Task::STATUS_IN_PROCESS && $task[TaskConst::SUBTASKS_QUANTITY] > 0) { ?>
                <?= round(100 - (int)$task[TaskConst::SUBTASKS_QUANTITY_NOT_PERFORMED] / ((int)$task[TaskConst::SUBTASKS_QUANTITY] / 100)); ?>%
            <? } ?>
        </span>
        <h1 class="list-group-item-heading" style="text-align: center; color: #3a87ad">
            <?= $task[TaskConst::TASK_NAME] ?>
        </h1>
    </div>
</div>
```

#### *Result*
Custom list

![Custom list](https://raw.github.com/2tvenom/PhpQueue/master/web/screenshots/custom_list.jpg "Custom list")

Custom log 

![Custom log](https://raw.github.com/2tvenom/PhpQueue/master/web/screenshots/custom_log.jpg "Custom list")
