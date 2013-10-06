<?php
use PhpQueue\Task;

class TaskModel {
    public static $class_by_status = array(
        Task::STATUS_NEW => "info",
        Task::STATUS_IN_PROCESS => "warning",
        Task::STATUS_DONE => "success",
        Task::STATUS_ERROR => "danger",
        Task::STATUS_CANCEL => "default",
        Task::STATUS_CALLBACK_ERROR => "danger",
        Task::STATUS_DONE_WITH_ERROR => "danger",
    );

    public static $status_text = array(
        Task::STATUS_NEW => "New",
        Task::STATUS_IN_PROCESS => "In process",
        Task::STATUS_DONE => "Success",
        Task::STATUS_ERROR => "Error",
        Task::STATUS_CANCEL => "Cancel",
        Task::STATUS_CALLBACK_ERROR => "Callback error",
        Task::STATUS_DONE_WITH_ERROR => "Done with error",
    );
}