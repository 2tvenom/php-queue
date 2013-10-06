<?php
namespace PhpQueue\Interfaces;

use PhpQueue\Task;

interface IJob {
    public static function run(Task $task);
}