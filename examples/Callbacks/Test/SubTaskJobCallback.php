<?php
use PhpQueue\Interfaces;
use PhpQueue\Task;

class SubTaskJobCallback implements Interfaces\ICallback, Interfaces\IErrorCallback
{
    public static function callback_run(Task $task)
    {
//        echo 'Callback', PHP_EOL;
    }

    public static function callback_error_run(Task $task)
    {
//        echo 'Error callback', PHP_EOL;
    }
}
