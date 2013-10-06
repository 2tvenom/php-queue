<?php
use PhpQueue\Interfaces;
use PhpQueue\Task;

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