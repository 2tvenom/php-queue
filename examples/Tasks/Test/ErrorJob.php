<?php
use PhpQueue\Interfaces;
use PhpQueue\Task;

class ErrorJob implements Interfaces\IJob
{
    public static function run(Task $task)
    {
        throw new \Exception("Test Exception");
    }
}
