<?php
namespace PhpQueue;

use PhpQueue\Exceptions\TaskPerformerException;

class TaskPerformer
{
    private $default_job_priority = array(
        TaskConst::JOB,
        TaskConst::TASK_CALLBACK,
        TaskConst::PARENT_CALLBACK,
        TaskConst::GLOBAL_CALLBACK
    );

    private $default_error_job_priority = array(null,
        TaskConst::TASK_ERROR_CALLBACK,
        TaskConst::PARENT_ERROR_CALLBACK,
        TaskConst::GLOBAL_ERROR_CALLBACK
    );

    /**
     * @var null|string Performer unique name
     */
    private $performer_name = TaskConst::PERFORMER_DEFAULT_NAME;


    /**
     * @var string Global callback
     */
    private $global_callback = null;

    /**
     * @var string Global error callback
     */
    private $global_error_callback = null;

    /**
     * @var array
     */
    private $interface_methods = array();

    /**
     * @param string $performer_name Unique task performer name
     * @throws Exceptions\TaskPerformerException
     */
    public function __construct($performer_name = null)
    {
        if(!is_null($performer_name)) {
            if (!is_string($performer_name) || empty($performer_name)) {
                throw new TaskPerformerException("Unique performer name is required");
            }

            if (strlen($performer_name) > 45) {
                throw new TaskPerformerException("Maximum task performer name is 45 chars");
            }

            $this->performer_name = $performer_name;
        }

        foreach(TaskConst::$job_interfaces as  $job_interface)
        {
            $iFooRef = new \ReflectionClass(__NAMESPACE__ . '\Interfaces\\' . $job_interface);
            $methods = $iFooRef->getMethods();
            $method_name = $methods[0]->name;
            $this->interface_methods[$job_interface] = $method_name;
        }
    }

    /**
     * Execute task
     * @param Task $task
     * @return Task
     */
    public function execute_task($task)
    {
        if(!($task instanceof Task)) return false;

        if(is_null($task->get_start_date())){
            $task->set_start_date(date('Y-m-d H:i:s'));
        }

        $task->set_performer($this->performer_name);

        if($task->have_subtasks()){
            if(!$task->get_exclusive()) {
                $task->null_performer();
            }

            $this->execute_task($task->sub_tasks()->get_perform_task());
            return $task;
        }

        $callback_classes_founded = $task->check_callback_class_names();

        if(!$callback_classes_founded) {
            $task->set_status(Task::STATUS_ERROR);
            return $task;
        }

        $exec_array = $this->default_job_priority;

        for($job_id = 0; $job_id < count($exec_array); $job_id++)
        {
            /**
             * @var \PhpQueue\Interfaces\IJob|\PhpQueue\Interfaces\ICallback|\PhpQueue\Interfaces\IErrorCallback
             */
            $exec_class = null;
            $job_type = $exec_array[$job_id];

            switch($job_type){
                case TaskConst::JOB:
                    $exec_class = $task->get_task_name();
                    break;
                case TaskConst::TASK_CALLBACK:
                    $exec_class = $task->get_callback();
                    break;
                case TaskConst::PARENT_CALLBACK:
                    $exec_class = $task->get_parent_callback();
                    break;
                case TaskConst::GLOBAL_CALLBACK:
                    $exec_class = $this->get_global_callback();
                    break;
                case TaskConst::TASK_ERROR_CALLBACK:
                    $exec_class = $task->get_error_callback();
                    break;
                case TaskConst::PARENT_ERROR_CALLBACK:
                    $exec_class = $task->get_parent_error_callback();
                    break;
                case TaskConst::GLOBAL_ERROR_CALLBACK:
                    $exec_class = $this->get_global_error_callback();
                    break;
            }

            if (is_null($exec_class)) continue;

            $method_name = $this->interface_methods[Helper::interface_job($job_type)];

            try {
                $response = call_user_func_array(array($exec_class, $method_name), array($task));
                if($job_type == TaskConst::JOB)
                {
                    $task->completed_ok()
                         ->response()
                         ->set_response($response);
                }

            } catch (\Exception $exception) {
                $task->response()->set_error($exception->getMessage() . " File: " . $exception->getFile() . " Line: " . $exception->getLine());

                if($job_type != TaskConst::JOB){
                    $task->set_status(Task::STATUS_CALLBACK_ERROR);
                    break;
                }

                $job_id = 0;
                $exec_array = $this->default_error_job_priority;

                if ($task->settings()->get_trial() >= $task->settings()->get_nested_settings('error_max_trial')) {
                    $task->completed_error();
                } else {
                    $task->set_status(Task::STATUS_NEW)->settings()->inc_trial();
                }
            }
        }

        return $task;
    }

    /**
     * @return string
     */
    public function get_global_callback()
    {
        return $this->global_callback;
    }

    /**
     * @param string $global_callback Class name of global callback implemented from ICallback
     * @throws Exceptions\TaskPerformerException
     * @return $this
     */
    public function set_global_callback($global_callback = null)
    {
        if(is_null($global_callback) || !Helper::check_class($global_callback, TaskConst::GLOBAL_CALLBACK)) {
            throw new TaskPerformerException('Global callback class not found');
        }
        $this->global_callback = $global_callback;
        return $this;
    }

    /**
     * @return string
     */
    public function get_global_error_callback()
    {
        return $this->global_error_callback;
    }

    /**
     * @param string $global_error_callback Class name of global error callback implemented from IErrorCallback
     * @throws Exceptions\TaskPerformerException
     * @return $this
     */
    public function set_global_error_callback($global_error_callback = null)
    {
        if (is_null($global_error_callback) || !Helper::check_class($global_error_callback, TaskConst::GLOBAL_ERROR_CALLBACK)) {
            throw new TaskPerformerException('Global callback class not found');
        }
        $this->global_error_callback = $global_error_callback;
        return $this;
    }

}