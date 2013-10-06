<?php
use PhpQueue\Interfaces;
use PhpQueue\Task;

class Job implements Interfaces\IJob
{
    public static function run(Task $task)
    {
        return $task->get_request_data()*2;
    }
}