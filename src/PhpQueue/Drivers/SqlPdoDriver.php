<?php
namespace PhpQueue\Drivers;

use PhpQueue\Interfaces\IQueueDriver;
use PhpQueue\Interfaces\IWebInterface;
use PhpQueue\Task;
use PhpQueue\TaskConst;

/**
 * Class SqlPdoDriver
 * Support PDO connection MySql PostgreSql SQLite
 * @package PhpQueue\Drivers
 */
class SqlPdoDriver implements IQueueDriver, IWebInterface
{
    private $table_name = "queue_tasks";
    private $connection = null;

    const DRIVER_PGSQL = "pgsql";
    const DRIVER_SQLITE = "sqlite";
    const DRIVER_MYSQL = "mysql";

    private $approved_pdo_drivers = array(self::DRIVER_MYSQL, self::DRIVER_SQLITE, self::DRIVER_PGSQL);

    const SELECT_TYPE_PARENT = 'parent';
    const SELECT_TYPE_CHILD = 'child';

    private $sql_select_lock_rows = null;
    private $current_driver = null;

    public final function __construct(\PDO $_connection) {
        $this->current_driver = $_connection->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if (!in_array($this->current_driver, $this->approved_pdo_drivers)) {
            throw new \Exception("Incorrect PDO connection");
        }

        if($_connection->getAttribute(\PDO::ATTR_DRIVER_NAME) == self::DRIVER_SQLITE) {
            $this->sql_select_lock_rows = "";
        }

        $this->sql_select_lock_rows = $this->current_driver != self::DRIVER_SQLITE ? " FOR UPDATE" : "";

        $this->connection = $_connection;
    }

    /**
     * Add task to data provider
     * @param Task $task
     * @return int
     */
    public function add_task(Task $task)
    {
        $this->connection->beginTransaction();

        $this->insert_task($task);

        foreach($task->sub_tasks()->get_all() as $sub_task)
        {
            $this->insert_task($sub_task);
        }

        $this->connection->commit();

        return $task->get_id();
    }

    /**
     * Sql Insert
     * @param Task $task
     * @return Task
     */
    private function insert_task(Task $task)
    {
        $task_array = $task->to_array();
        unset($task_array['id']);

        $task_array_keys = array_map(function($entity){
                return ":" . $entity;
            }, array_keys($task_array));

        $query = $this->connection->prepare(
            "INSERT INTO {$this->table_name}(" . implode(',', array_keys($task_array)) . ") values(" . implode(',', $task_array_keys) . ")"
        );
        $query->execute($task_array);
        $task->set_id($this->connection->lastInsertId());

        return $task;
    }

    /**
     * Get data from data provider, generate and return task
     * @param array $request
     * @return mixed
     */
    public function get_task(array $request)
    {
        $request = array_filter($request);

        $this->connection->beginTransaction();

        $task = call_user_func(function() use($request) {
            if(array_key_exists(TaskConst::UNIQID, $request)) {
                $request[TaskConst::NEED_SET_IN_PROCESS_FLAG] = false;
            }

            $head_task = $this->select_task($request);

            if (!$head_task) return false;

            $task = new Task($head_task);

            if ($head_task[TaskConst::SUBTASKS_QUANTITY] > 0) {
                $sub_task_request = array(
                    TaskConst::PARENT_ID => $head_task[TaskConst::UNIQID],
                    TaskConst::NEED_SET_IN_PROCESS_FLAG => $request[TaskConst::NEED_SET_IN_PROCESS_FLAG]
                );

                $sub_tasks = $this->select_sub_task($sub_task_request);

                if (!empty($sub_tasks['sub_tasks'])) {
                    foreach ($sub_tasks['sub_tasks'] as $sub_task) {
                        if ($sub_task[TaskConst::UNIQID] == $sub_tasks['perform_task']) {
                            $task->sub_tasks()->add_perform_task(new Task($sub_task));
                            continue;
                        }
                        $task->sub_tasks()->add(new Task($sub_task));
                    }
                }
            }
            return $task;
        });

        $this->connection->commit();
        return $task;
    }

    private function select_task(array $request)
    {
        $need_set_in_process = $request[TaskConst::NEED_SET_IN_PROCESS_FLAG];
        unset($request[TaskConst::NEED_SET_IN_PROCESS_FLAG]);
        $excluded_where = array(TaskConst::PERFORMER, TaskConst::NEED_SET_IN_PROCESS_FLAG, TaskConst::EXECUTION_DATE);

        $order_by = "";
        $mysql_order = array(
            Task::TASK_PRIORITY_HIGH => 'desc',
            Task::TASK_PRIORITY_LOW => 'asc',
        );

        if (array_key_exists(TaskConst::PRIORITY, $request) && array_key_exists($request[TaskConst::PRIORITY], $mysql_order)) {
            $order_by = "order by " . TaskConst::PRIORITY . " " . $mysql_order[$request[TaskConst::PRIORITY]] . ", " . TaskConst::ID . " asc";
        }

        unset($request['priority']);

        $where = "(
                    (" . TaskConst::STATUS . " = " . Task::STATUS_NEW . ") OR
                    (" . TaskConst::EXCLUSIVE . " = 0 AND " . TaskConst::STATUS . " = " . Task::STATUS_IN_PROCESS . " AND " . TaskConst::SUBTASKS_QUANTITY_NOT_PERFORMED . " > 0) OR
                    (" . TaskConst::EXCLUSIVE . " = 1 AND " . TaskConst::STATUS . " = " . Task::STATUS_IN_PROCESS . " AND " . TaskConst::SUBTASKS_QUANTITY_NOT_PERFORMED . " > 0 AND " . TaskConst::PERFORMER . " = :" . TaskConst::PERFORMER . ")
                  ) AND " . TaskConst::TYPE . " = " . TaskConst::TASK . " AND (" . TaskConst::EXECUTION_DATE . " IS NULL OR " . TaskConst::EXECUTION_DATE . " <= :" . TaskConst::EXECUTION_DATE. ")";

        $additional_where = implode(
            ' AND ',
            array_filter(
                array_map(
                    function ($entity) use ($request, $excluded_where) {
                        if (in_array($entity, $excluded_where)) return null;
                        if (is_null($request[$entity])) return $entity . " IS :" . $entity;

                        return $entity . "=:" . $entity;
                    },
                    array_keys($request)
                )
            )
        );

        if(!empty($additional_where)){
            $where .= " AND " . $additional_where;
        }
        if(array_key_exists(TaskConst::UNIQID, $request))
        {
            $where = TaskConst::UNIQID . "=:" . TaskConst::UNIQID;
            $request = array(TaskConst::UNIQID => $request[TaskConst::UNIQID]);
        }

        $task_param = $this->sql_request(self::SELECT_TYPE_PARENT, $request, $where, $order_by, 1);
        if(!$task_param) return false;

        if(!$need_set_in_process)
        {
            return $task_param;
        }

        $update_params = array();

        $additional_update_compiled = array();

        if ($task_param[TaskConst::STATUS] == Task::STATUS_NEW)
        {
            $update_params[TaskConst::STATUS] = Task::STATUS_IN_PROCESS;
            $task_param[TaskConst::STATUS] = Task::STATUS_IN_PROCESS;
            $additional_update_compiled[] = TaskConst::STATUS . "=:" . TaskConst::STATUS;
        }

        if ($task_param[TaskConst::SUBTASKS_QUANTITY_NOT_PERFORMED] > 0) {
            $task_param[TaskConst::SUBTASKS_QUANTITY_NOT_PERFORMED]--;
            $additional_update_compiled[] = TaskConst::SUBTASKS_QUANTITY_NOT_PERFORMED . "=" . TaskConst::SUBTASKS_QUANTITY_NOT_PERFORMED . "-1";
        }

        $update_params[TaskConst::ID] = $task_param[TaskConst::ID];

        $query = $this->connection->prepare(
            "UPDATE {$this->table_name} SET " . implode(',', $additional_update_compiled) . " where " . TaskConst::ID . "=:" . TaskConst::ID
        );

        $query->execute($update_params);
        return $task_param;
    }

    /**
     * Select request and set status
     * @param array $request
     * @return array|bool
     */
    private function select_sub_task(array $request)
    {
        $need_set_in_process = $request[TaskConst::NEED_SET_IN_PROCESS_FLAG];
        unset($request[TaskConst::NEED_SET_IN_PROCESS_FLAG]);

        $where = TaskConst::PARENT_ID . " = :" . TaskConst::PARENT_ID;
        unset($request[TaskConst::PERFORMER]);
        $sub_tasks = $this->sql_request(self::SELECT_TYPE_CHILD, $request, $where, "order by " . TaskConst::ID . " asc");

        $perform_id = "";

        if($need_set_in_process)
        {
            foreach ($sub_tasks as &$sub_task) {
                if ($sub_task[TaskConst::STATUS] != Task::STATUS_NEW) continue;

                $sub_task[TaskConst::STATUS] = Task::STATUS_IN_PROCESS;
                $perform_id = $sub_task[TaskConst::UNIQID];

                $update_params = array(
                    TaskConst::STATUS => Task::STATUS_IN_PROCESS,
                    TaskConst::ID => $sub_task[TaskConst::ID]
                );

                $query = $this->connection->prepare(
                    "UPDATE {$this->table_name} SET " . TaskConst::STATUS . "=:" . TaskConst::STATUS . " where " . TaskConst::ID . "=:" . TaskConst::ID
                );
                $query->execute($update_params);

                break;
            }
            unset($sub_task);
        }
        return array('perform_task' => $perform_id, 'sub_tasks' => $sub_tasks);
    }

    /**
     * Sql request task and set status
     * @param $type
     * @param $request
     * @param $where
     * @param null $order_by
     * @param int|null $limit
     * @return array|bool|mixed
     */
    private function sql_request($type, $request, $where, $order_by = null, $limit = null)
    {
        $limit_str = "";
        if(!is_null($limit)) {
            $limit_str = "LIMIT $limit OFFSET 0";
        }

        $sql = "SELECT * FROM {$this->table_name} WHERE {$where} {$order_by} {$limit_str} " . $this->sql_select_lock_rows;

        $query = $this->connection->prepare($sql);
        $query->execute($request);
        $task_params = $query->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($task_params)) return false;

        return $type == self::SELECT_TYPE_CHILD ? $task_params : $task_params[0];
    }

    /**
     * Prepare modify task
     * Lock record in queue
     *
     * Return array of task_data
     * @param $unique_id
     * @return mixed
     */
    public function prepare_modify($unique_id)
    {
        $this->connection->beginTransaction();

        $where_entity = array(
            TaskConst::UNIQID,
            TaskConst::PARENT_ID,
        );

        $prepared_tasks = array();

        foreach($where_entity as $entity)
        {
            $query = $this->connection->prepare("SELECT * FROM {$this->table_name} WHERE " . $entity . " = :" . TaskConst::UNIQID . $this->sql_select_lock_rows);
            $query->execute(array(TaskConst::UNIQID => $unique_id));
            $prepared_tasks = array_merge($prepared_tasks, $query->fetchAll(\PDO::FETCH_ASSOC));
        }

        if(empty($prepared_tasks))
        {
            $this->connection->commit();
            return null;
        }

        return $prepared_tasks;
    }

    /**
     * Modify task in queue
     * @param Task $task
     * @param bool $need_commit Transaction commit required
     * @return bool
     */
    public function modify_task(Task $task, $need_commit = false)
    {
        $task_array = $task->to_array(true);

        if (count($task_array) > 1) {
            $update_statement = implode(",", array_filter(array_map(function($entity){
                    if($entity == TaskConst::ID) return null;
                    return $entity . "=:" . $entity;
                }, array_keys($task_array))));

            $query = $this->connection->prepare("UPDATE {$this->table_name} SET {$update_statement} where id = :id");
            $query->execute($task_array);
        }

        if($need_commit) $this->connection->commit();

        return $this;
    }

    /**
     * Get tasks list
     * @param $limit
     * @param int $offset
     * @param null $status
     * @param null $task_name
     * @param null $task_group_id
     * @return array|mixed
     */
    public function get_tasks($limit, $offset = 0, $status = null, $task_name = null, $task_group_id = null)
    {
        $limit = "LIMIT " . $limit . " OFFSET " . $offset;

        $prepared_attr = array(
            TaskConst::TYPE => TaskConst::TASK
        );

        if (!is_null($status) && $status > 0) {
            $prepared_attr[TaskConst::STATUS] = $status;
        }

        $where_sql = array();
        foreach ($prepared_attr as $entity_where => $_) {
            $where_sql[] = $entity_where . "=:" . $entity_where;
        }

        $tasks = $this->connection->prepare("SELECT * FROM {$this->table_name} WHERE " . implode(' and ', $where_sql) . " ORDER BY " . TaskConst::ID . " DESC " . $limit);
        $tasks->execute($prepared_attr);
        return $tasks->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get task details
     * @param $unique_id
     * @return mixed
     */
    public function get_task_details($unique_id)
    {
        $task = $this->connection->prepare(
            "SELECT * FROM {$this->table_name} WHERE " . TaskConst::UNIQID . "=:" . TaskConst::UNIQID . " UNION SELECT * FROM {$this->table_name} WHERE " . TaskConst::PARENT_ID . " = :" . TaskConst::UNIQID . " ORDER BY " . TaskConst::ID
        );
        $task->execute(array(TaskConst::UNIQID => $unique_id));

        $tasks_data = $task->fetchAll(\PDO::FETCH_ASSOC);

        $base64_encoded_fields = array(
            TaskConst::SETTINGS,
            TaskConst::REQUEST_DATA,
            TaskConst::RESPONSE_DATA,
        );

        foreach($base64_encoded_fields as $field_name)
        {
            foreach($tasks_data as &$task)
            {
                $task[$field_name] = base64_decode($task[$field_name]);
            }
        }
        unset($task);

        return $tasks_data;
    }

    /**
     * Get status list
     * @return mixed
     */
    public function get_list_statuses()
    {
        $statuses = $this->connection->prepare(
            "select " . TaskConst::STATUS . ", count(*) cnt from {$this->table_name} where " . TaskConst::CREATE_DATE . " >= DATE_ADD(CURDATE(), INTERVAL -1 DAY) and " . TaskConst::TYPE . "=:" . TaskConst::TYPE ." group by " . TaskConst::STATUS
        );

        $statuses->execute(array(TaskConst::TYPE => TaskConst::TASK));

        $required_statuses = array(
            Task::STATUS_NEW,
            Task::STATUS_DONE,
            Task::STATUS_IN_PROCESS,
            Task::STATUS_ERROR,
            Task::STATUS_CANCEL,
        );

        $status_list = $statuses->fetchAll(\PDO::FETCH_KEY_PAIR);

        foreach ($required_statuses as $status_id) {
            if (array_key_exists($status_id, $status_list)) continue;
            $status_list[$status_id] = 0;
        }

        $same_as_error = array(
            Task::STATUS_DONE_WITH_ERROR,
            Task::STATUS_CALLBACK_ERROR,
        );

        foreach($same_as_error as $status_id)
        {
            if(!array_key_exists($status_id, $status_list)) continue;
            $status_list[Task::STATUS_ERROR] += $status_list[$status_id];
            unset($status_list[$status_id]);
        }

        return $status_list;
    }

    /**
     * Get tasks count for pagination
     * @param $status_id
     * @return mixed
     */
    public function get_tasks_count_by_status($status_id = null)
    {
        $prepared_attr = array(
            TaskConst::TYPE => TaskConst::TASK,
        );

        if(!is_null($status_id))
        {
            $prepared_attr[TaskConst::STATUS] = $status_id;
        }

        $where_sql = array();
        foreach ($prepared_attr as $entity_where => $_) {
            $where_sql[] = $entity_where . "=:" . $entity_where;
        }

        $tasks = $this->connection->prepare("SELECT count(*) cnt FROM {$this->table_name} WHERE " . implode(' and ',$where_sql));
        $tasks->execute($prepared_attr);
        return (int)$tasks->fetch(\PDO::FETCH_COLUMN);
    }
}