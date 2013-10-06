<?php
namespace PhpQueue;

use PhpQueue\Exceptions\QueueException;
use PhpQueue\Interfaces\IQueueDriver;

class Queue
{
    private $driver = null;
    private $performer_name = null;
    private $not_modifiable_parameters = array(
        TaskConst::ID,
        TaskConst::UNIQID,
        TaskConst::PARENT_ID,
        TaskConst::SUBTASKS_QUANTITY,
        TaskConst::TASK_GROUP_ID,
        TaskConst::CREATE_DATE,
    );

    public function __construct(IQueueDriver $driver)
    {
        $this->driver = $driver;
    }

    /**
     * Add task to queue
     * @param Task $task
     * @return int Task id
     */
    public function add_task(Task $task)
    {
        if ($task->have_subtasks()) {
            if($task->get_subtasks_quantity() == null){
                $task->set_subtasks_quantity($task->sub_tasks()->count());
            }

            if ($task->get_subtasks_quantity_not_performed() == null) {
                $task->set_subtasks_quantity_not_performed($task->sub_tasks()->count());
            }
        }

        return $this->driver->add_task($task);
    }

    /**
     * Get task from queue
     * @param array $cfg Select request config
     * @return Task|bool
     */
    public function get_task(array $cfg = array())
    {
        $fields = array(
            TaskConst::PRIORITY                 => Task::TASK_PRIORITY_HIGH,
            TaskConst::TASK_GROUP_ID            => null,
            TaskConst::UNIQID                   => null,
            TaskConst::TASK_NAME                => null,
            TaskConst::PERFORMER                => TaskConst::PERFORMER_DEFAULT_NAME,
            TaskConst::NEED_SET_IN_PROCESS_FLAG => true,
            TaskConst::EXECUTION_DATE           => date('Y-m-d H:i:s'),
        );

        if(!empty($cfg))
        {
            foreach ($fields as $field_name => $_) {
                if (array_key_exists($field_name, $cfg)) {
                    $fields[$field_name] = $cfg[$field_name];
                }
            }
        }

        $returned_task = $this->driver->get_task($fields);
        if($returned_task instanceof Task) {
            foreach($returned_task->sub_tasks()->get_all() as $sub_task){
                $sub_task->start_update_watch();
            }
            $returned_task->start_update_watch();
        }
        return $returned_task;
    }

    /**
     * Modify task in queue
     * @param Task $task
     * @throws Exceptions\QueueException
     * @return Task
     */
    public function modify_task($task)
    {
        if(!($task instanceof Task)) return false;

        $need_commit = false;

        if($task->have_subtasks()) {
            $need_commit = true;
            $database_data = $this->driver->prepare_modify($task->get_uniqid());

            if(is_null($database_data))
            {
                throw new QueueException('Task for modify not found');
            }

            foreach($database_data as $id => $task_database_data)
            {
                /**
                 * @var Task $modify_task
                 */
                $current_task = $id == 0 ? $task : $task->sub_tasks()->get($task_database_data[TaskConst::UNIQID]);
                $current_task_data = $current_task->to_array();

                //check not modified parameters
                foreach($this->not_modifiable_parameters as $not_modifiable_parameter)
                {
                    if($current_task_data[$not_modifiable_parameter] != $task_database_data[$not_modifiable_parameter])
                    {
                        throw new QueueException("Parameter {$not_modifiable_parameter} was changed");
                    }
                }
                $diff = array_diff($current_task_data, $task_database_data);
                $current_task->set_array($diff);
            }

            $task
                ->set_subtasks_quantity_not_performed(0)
                ->set_subtasks_error(0);

            $have_in_process = false;

            foreach($task->sub_tasks()->get_all() as $sub_task)
            {
                if($sub_task->get_status() == Task::STATUS_IN_PROCESS) $have_in_process = true;

                switch($sub_task->get_status())
                {
                    case Task::STATUS_CALLBACK_ERROR:
                        $task->completed_error(Task::STATUS_CALLBACK_ERROR);
                    case Task::STATUS_ERROR:
                        $task->inc_subtasks_error();
                        if($sub_task->settings()->get_nested_settings('error_break')) {
                            $task->completed_error();
                        }
                    break;
                    case Task::STATUS_NEW:
                        $task->inc_subtasks_quantity_not_performed();
                        break;
                }
            }

            if (!$have_in_process && $task->get_subtasks_quantity_not_performed() == 0 && !in_array($task->get_status(), array(Task::STATUS_ERROR, Task::STATUS_CALLBACK_ERROR))) {
                if ($task->get_subtasks_error() > 0) {
                    $task->completed_error(Task::STATUS_DONE_WITH_ERROR);
                } else {
                    $task->completed_ok();
                }
            }

            $this->driver->modify_task($task->sub_tasks()->get_perform_task());
        }

        $this->driver->modify_task($task, $need_commit);

        return $task;
    }


    /**
     * Set performer name
     * @param $performer_name
     * @return $this
     */
    public function set_performer_name($performer_name)
    {
        $this->performer_name = $performer_name;

        return $this;
    }
}