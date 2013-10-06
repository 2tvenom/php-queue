<?php
namespace PhpQueue;

use PhpQueue\Exceptions\TaskException;

class SubTasks {
    /**
     * @var $sub_tasks Task[]
     */
    private $sub_tasks = array();

    private $parent_task = null;

    private $perform_task = null;

    /**
     * @param Task $parent_task
     */
    public function __construct(Task $parent_task){
        $this->parent_task = $parent_task;
    }

    /**
     * Set current perform sub task
     * This task already have status "IN_PROCESS" in base
     * @param \PhpQueue\Task $perform_task
     * @return $this
     */
    public function add_perform_task(Task $perform_task)
    {
        $this->perform_task = $perform_task->get_uniqid();
        $this->add($perform_task);
        return $this;
    }

    /**
     * Get sub task for perform
     * @return Task
     * @throws Exceptions\TaskException
     */
    public function get_perform_task()
    {
        if(is_null($this->perform_task)) throw new TaskException('Not found sub task for perform');
        return $this->sub_tasks[$this->perform_task];
    }

    /**
     *
     * @return Task
     * @throws Exceptions\TaskException
     */
    public function get_perform_task_id()
    {
        if (is_null($this->perform_task)) throw new TaskException('Not found sub task for perform');
        return $this->perform_task;
    }

    /**
     * Add sub task to current task
     * @param Task|Task[] $tasks
     * @return $this
     * @throws TaskException
     */
    public function add($tasks)
    {
        if (is_array($tasks)) {
            foreach ($tasks as $task) {
                $this->add($task);
            }
        } elseif ($tasks instanceof Task) {
            if($tasks->have_subtasks()) {
                throw new TaskException("Only two levels of nesting tasks");
            }
            $tasks
                ->set_parent_task($this->parent_task)
                ->set_parent_id($this->parent_task->get_uniqid())
                ->set_type(TaskConst::SUB_TASK)
                ->settings()
                ->set_parent_settings($this->parent_task->settings());
            $this->sub_tasks[$tasks->get_uniqid()] = $tasks;
        } else {
            throw new TaskException("In sub tasks you can add only task or array of tasks");
        }
        return $this;
    }

    /**
     * Return all sub tasks
     * @return Task[]
     */
    public function get_all()
    {
        return $this->sub_tasks;
    }

    /**
     * Count of sub tasks
     * @return int
     */
    public function count()
    {
        return count($this->sub_tasks);
    }


    /**
     * Return subtask by unique id
     * @param string $unique_id Subtask unique id
     * @throws Exceptions\TaskException
     * @return Task
     */
    public function get($unique_id)
    {
        if(!array_key_exists($unique_id, $this->sub_tasks)) throw new TaskException('Not found sub task with unique id ' . $unique_id);

        return $this->sub_tasks[$unique_id];
    }

    /**
     * Delete all sub tasks
     * @return $this
     */
    public function delete_all()
    {
        $this->sub_tasks = array();
        return $this;
    }

    /**
     * Delete subtask by unique id
     * @param string|array $unique_id Subtask unique id
     * @return $this
     */
    public function delete($unique_id)
    {
        if (is_array($unique_id)) {
            foreach ($unique_id as $id) {
                $this->delete($id);
            }
        } elseif (array_key_exists($unique_id, $this->sub_tasks)) {
            unset($this->sub_tasks[$unique_id]);
        }

        return $this;
    }
}