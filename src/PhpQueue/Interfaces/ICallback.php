<?php
namespace PhpQueue\Interfaces;

use PhpQueue\Task;

interface ICallback {
    public static function callback_run(Task $task);
}