<?php
namespace PhpQueue\Interfaces;

use PhpQueue\Exceptions\DriverException;
use PhpQueue\Task;

interface IQueueDriver
{
    /**
     * Add task to data provider
     * @param Task $task
     * @return mixed
     */
    public function add_task(Task $task);

    /**
     * Get data from data provider, generate and return task
     * @param array $request Select request
     * @return Task|bool
     */
    public function get_task(array $request);

    /**
     * Modify task in queue
     * @param Task $task
     * @param bool $need_commit Transaction commit required
     * @return bool
     */
    public function modify_task(Task $task, $need_commit = false);

    /**
     * Prepare modify task
     * Lock record in queue
     *
     * Return array of task_data
     * @param $unique_id
     * @return mixed
     */
    public function prepare_modify($unique_id);
}