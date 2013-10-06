<?php
namespace PhpQueue\Interfaces;

use PhpQueue\Task;

interface IWebInterface {

    /**
     * Get tasks list
     * @param $limit
     * @param int $offset
     * @param null $status
     * @param null $task_name
     * @param null $task_group_id
     * @return mixed
     */
    public function get_tasks($limit, $offset = 0, $status = null, $task_name = null, $task_group_id = null);

    /**
     * Get task details
     * @param $unique_id
     * @return mixed
     */
    public function get_task_details($unique_id);

    /**
     * Get status list
     * @return mixed
     */
    public function get_list_statuses();

    /**
     * Get tasks count for pagination
     * @param $status_id
     * @return mixed
     */
    public function get_tasks_count_by_status($status_id);
}