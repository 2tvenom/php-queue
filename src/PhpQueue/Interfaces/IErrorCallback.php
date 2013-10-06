<?php
namespace PhpQueue\Interfaces;

use PhpQueue\Task;

interface IErrorCallback {
    public static function callback_error_run(Task $task);
}