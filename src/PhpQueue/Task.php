<?php
namespace PhpQueue;

use PhpQueue\Exceptions\TaskException;
use PhpQueue\Interfaces\IObject;

/**
 * Class Task
 * @package PhpQueue
 * @method mixed get_id() Get task id
 * @method mixed get_uniqid() Get task uniqid
 * @method mixed get_type() Get task type
 * @method mixed get_parent_id() Get task parent_id
 * @method mixed get_subtasks_quantity() Get task subtasks_quantity
 * @method mixed get_subtasks_quantity_not_performed() Get task subtasks_quantity_not_performed
 * @method mixed get_subtasks_error() Get count of error sub tasks
 * @method mixed get_exclusive() Get task exclusive
 * @method mixed get_task_name() Get task task_name
 * @method mixed get_task_group_id() Get task task_group_id
 * @method mixed get_status() Get task status
 * @method mixed get_performer() Get task performer
 * @method mixed get_request_data() Get task request_data
 * @method mixed get_execution_date() Get task execution_time
 * @method mixed get_callback() Get task task_callback
 * @method mixed get_error_callback() Get task error_callback
 * @method mixed get_priority() Get task priority
 * @method mixed get_create_date() Get task create_date
 * @method mixed get_start_date() Get task start_date
 * @method mixed get_done_date() Get task done_date
 * @method inc_subtasks_quantity_not_performed() Increment subtasks_quantity_not_performed
 * @method \PhpQueue\Task set_id($param) Set task id
 * @method \PhpQueue\Task set_uniqid($param) Set task uniqid
 * @method \PhpQueue\Task set_type($param) Set task type
 * @method \PhpQueue\Task set_parent_id($param) Set task parent_id
 * @method \PhpQueue\Task set_subtasks_quantity($param) Set task subtasks_quantity
 * @method \PhpQueue\Task set_subtasks_quantity_not_performed($param) Set task subtasks_quantity_not_performed
 * @method \PhpQueue\Task set_subtasks_error($param) Set subtasks error
 * @method \PhpQueue\Task inc_subtasks_error() Inc subtasks error
 * @method \PhpQueue\Task set_exclusive($param) Set task exclusive
 * @method \PhpQueue\Task set_task_name($param) Set task task_name
 * @method \PhpQueue\Task set_task_group_id($param) Set task task_group_id
 * @method \PhpQueue\Task set_status($param) Set task status
 * @method \PhpQueue\Task set_performer($param) Set task performer
 * @method \PhpQueue\Task null_performer() Set task performer to null
 * @method \PhpQueue\Task set_request_data($param) Set task request_data
 * @method \PhpQueue\Task set_execution_date($param) Set task execution_time
 * @method \PhpQueue\Task set_callback($param) Set task task_callback
 * @method \PhpQueue\Task set_error_callback($param) Set task error_callback
 * @method \PhpQueue\Task set_priority($param) Set task priority
 * @method \PhpQueue\Task set_create_date($param) Set task create_date
 * @method \PhpQueue\Task set_start_date($param) Set task start_date
 * @method \PhpQueue\Task set_done_date($param) Set task done_date
*/
class Task extends IObject
{
    const STATUS_NEW = 1,
        STATUS_IN_PROCESS = 2,
        STATUS_DONE = 3,
        STATUS_ERROR = 4,
        STATUS_CANCEL = 5,
        STATUS_CALLBACK_ERROR = 6,
        STATUS_DONE_WITH_ERROR = 7;

    private $status_name_by_id = array(
        self::STATUS_NEW             => 'NEW',
        self::STATUS_IN_PROCESS      => 'IN_PROCESS',
        self::STATUS_DONE            => 'DONE',
        self::STATUS_ERROR           => 'ERROR',
        self::STATUS_CANCEL          => 'CANCEL',
        self::STATUS_CALLBACK_ERROR  => 'CALLBACK_ERROR',
        self::STATUS_DONE_WITH_ERROR => 'DONE_WITH_ERROR',
    );

    const TASK_PRIORITY_HIGH = 'high_priority',
          TASK_PRIORITY_LOW  = 'low_priority',
          TASK_PRIORITY_NONE = 'none_priority';

    private $update_watch = false;
    private $default_fields = array();

    protected $fields = array(
        TaskConst::ID                              => null,
        TaskConst::UNIQID                          => null,
        TaskConst::TYPE                            => TaskConst::TASK,
        TaskConst::PARENT_ID                       => null,
        TaskConst::SUBTASKS_QUANTITY               => 0,
        TaskConst::SUBTASKS_QUANTITY_NOT_PERFORMED => 0,
        TaskConst::SUBTASKS_ERROR                  => 0,
        TaskConst::EXCLUSIVE                       => 0,
        TaskConst::TASK_NAME                       => null,
        TaskConst::TASK_GROUP_ID                   => 1,
        TaskConst::STATUS                          => self::STATUS_NEW,
        TaskConst::PERFORMER                       => null,
        TaskConst::REQUEST_DATA                    => null,
        TaskConst::RESPONSE_DATA                   => null,
        TaskConst::EXECUTION_DATE                  => null,
        TaskConst::CALLBACK                        => null,
        TaskConst::ERROR_CALLBACK                  => null,
        TaskConst::SETTINGS                        => null,
        TaskConst::PRIORITY                        => null,
        TaskConst::CREATE_DATE                     => null,
        TaskConst::START_DATE                      => null,
        TaskConst::DONE_DATE                       => null,
    );

    protected $exclude_fields = array(
        TaskConst::SETTINGS,
        TaskConst::RESPONSE_DATA,
    );

    /**
     * @var SubTasks
     */
    private $sub_tasks = null;

    /**
     * @var TaskSettings
     */
    private $task_settings = null;

    /**
     * @var TaskResponse
     */
    private $task_response = null;

    /**
     * @var Task
     */
    private $parent_task = null;

    /**
     * @param string|array $task_config Task name|Task array config
     * @param mixed $request_params [optional] Request task param
     * @throws Exceptions\TaskException
     */
    public final function __construct($task_config, $request_params = null)
    {
        $this->fields[TaskConst::UNIQID] = $this->generate_unique_id();
        $this->fields[TaskConst::CREATE_DATE] = date('Y-m-d H:i:s');
        $this->sub_tasks = new SubTasks($this);
        $this->task_settings = new TaskSettings();
        $this->task_response = new TaskResponse();

        if(is_null($request_params)){
            $func_name = gettype($task_config) . "__construct";
            if(!method_exists($this, $func_name))
            {
                throw new TaskException("Constructor not found");
            }
            call_user_func_array(array($this, $func_name), array($task_config));
        } else {
            $this->string_object__construct($task_config, $request_params);
        }

        if (method_exists($this, 'initialize')) {
            call_user_func(array($this, 'initialize'));
        }
    }

    private function string__construct($task_name) {
        $this->set_task_name($task_name);
    }

    private function array__construct($task_config)
    {
        $this->set_array($task_config);
    }

    private function string_object__construct($task_name, $request){
        $this->set_task_name($task_name)->set_request_data($request);
    }

    /**
     * Return sub tasks
     * @return SubTasks
     */
    public function sub_tasks()
    {
        return $this->sub_tasks;
    }

    /**
     * Return settings
     * @return TaskSettings
     */
    public function settings()
    {
        return $this->task_settings;
    }

    /**
     * Task response
     * @return TaskResponse
     */
    public function response()
    {
        return $this->task_response;
    }

    /**
     * Task have sub tasks
     * @return bool
     */
    public function have_subtasks()
    {
        return $this->sub_tasks()->count() > 0;
    }

    public function check_callback_class_names()
    {
        $check_classes_list = array(
            TaskConst::JOB => $this->get_task_name(),
            TaskConst::TASK_CALLBACK => $this->get_callback(),
            TaskConst::TASK_ERROR_CALLBACK => $this->get_error_callback(),
            TaskConst::PARENT_CALLBACK => $this->get_parent_callback(),
            TaskConst::PARENT_ERROR_CALLBACK => $this->get_parent_error_callback(),
        );

        $classes_found = true;
        foreach($check_classes_list as $job_id => $class_name)
        {
            if(is_null($class_name)) continue;
            if(!Helper::check_class($class_name, $job_id))
            {
                $classes_found = false;
                $this->response()->set_error("Not found class " . $class_name . " for " . TaskConst::$job_name_by_id[$job_id]);
            }
        }

        return $classes_found;
    }

    /**
     * Convert task object to array
     * @param bool $only_updated Return only updated fields if was started update watch
     * @return array
     */
    public function to_array($only_updated = false)
    {
        $this->fields[TaskConst::SETTINGS] = base64_encode($this->task_settings->to_serialized());
        $this->fields[TaskConst::RESPONSE_DATA] = base64_encode($this->task_response->to_serialized());

        if(!$only_updated || !$this->update_watch) return $this->fields;

        $updated_array = array();
        foreach($this->fields as $field_key => $field_value)
        {
            if(!array_key_exists($field_key, $this->default_fields) || $this->default_fields[$field_key] == $field_value) continue;
            $updated_array[$field_key] = $field_value;
        }

        $updated_array[TaskConst::ID] = $this->fields[TaskConst::ID];
        return $updated_array;
    }

    /**
     * Set task properties from assoc array
     * @param array $properties
     * @throws Exceptions\TaskException
     * @return $this
     */
    public function set_array(array $properties)
    {
        foreach($properties as $property_key => $property_value)
        {
            if(!array_key_exists($property_key, $this->fields))
            {
                throw new Exceptions\TaskException("Property field {$property_key} not found");
            }

            switch($property_key)
            {
                case TaskConst::SETTINGS:
                    $this->task_settings = unserialize(base64_decode($property_value));
                    break;
                case TaskConst::RESPONSE_DATA:
                    $this->task_response = unserialize(base64_decode($property_value));
                    break;
                default:
                    $this->fields[$property_key] = $property_value;
                    break;
            }
        }
        return $this;
    }

    /**
     * @api
     * Start update watcher
     */
    public function start_update_watch()
    {
        $this->default_fields = $this->fields;
        $this->default_fields[TaskConst::SETTINGS] = $this->task_settings->to_serialized();
        $this->default_fields[TaskConst::RESPONSE_DATA] = $this->task_response->to_serialized();

        $this->update_watch = true;
        return $this;
    }

    /**
     * @api
     * Input data set mutator
     * @param $value
     * @return int
     * @throws Exceptions\TaskException
     */
    protected function setter_request_data($value)
    {
        if(is_null($value)) return null;
        return base64_encode(serialize($value));
    }

    /**
     * @api
     * Input data getter mutator
     * @param $value
     * @return int
     * @throws Exceptions\TaskException
     */
    protected function getter_request_data($value)
    {
        if (is_null($value)) return null;
        return unserialize(base64_decode($value));
    }

    /**
     * @api
     * Exclusive getter mutator
     * @param $value
     * @return int
     * @throws Exceptions\TaskException
     */
    protected function getter_exclusive($value)
    {
        return $value == "1";
    }

    /**
     * @api
     * Priority set validator
     * @param $value
     * @return int
     * @throws Exceptions\TaskException
     */
    protected function setter_priority($value)
    {
        if(!is_int($value) || $value < 0) throw new TaskException("Priority must be int and not be negative");
        return $value;
    }

    /**
     * @api
     * Status set validator
     * @param $value
     * @return $this
     * @throws Exceptions\TaskException
     */
    protected function setter_status($value)
    {
        if (!array_key_exists($value, $this->status_name_by_id)) {
            throw new TaskException('Incorrect task status');
        }

        return $value;
    }

    /**
     * @api
     * Exclusive field set validator
     * @param $value
     * @return bool
     * @throws Exceptions\TaskException
     */
    protected function setter_exclusive($value)
    {
        if (!is_bool($value)) throw new TaskException('Input data must be boolean');

        return $value;
    }

    /**
     * @api
     * Performer setter validator
     * @param $value
     * @return mixed
     * @throws Exceptions\TaskException
     */
    protected function setter_performer($value)
    {
        if (strlen($value) > 45) {
            throw new TaskException("Maximum task performer name is 45 chars");
        }
        return $value;
    }

    /**
     * @api
     *
     * Generate unique id for task
     * @return string
     */
    private function generate_unique_id()
    {
        return md5(uniqid(rand(0, 9999) . ' ' . microtime(), true));
    }


    /**
     * Alias for set status DONE
     * @return $this
     */
    public function completed_ok()
    {
        $this->set_done_date(date('Y-m-d H:i:s'));
        return $this->set_status(self::STATUS_DONE);
    }

    /**
     * Alias for set status ERROR
     * @param int $status
     * @throws Exceptions\TaskException
     * @return $this
     */
    public function completed_error($status = self::STATUS_ERROR)
    {
        if(!array_key_exists($status, $this->status_name_by_id)){
            throw new TaskException("Incorrect status");
        }
        $this->set_done_date(date('Y-m-d H:i:s'));
        return $this->set_status($status);
    }

    /**
     * Get parent callback
     * @return null
     */
    public function get_parent_callback()
    {
        if(is_null($this->parent_task)) return null;
        return $this->parent_task->get_callback();
    }

    /**
     * Get parent error callback
     * @return null
     */
    public function get_parent_error_callback()
    {
        if (is_null($this->parent_task)) return null;
        return $this->parent_task->get_error_callback();
    }

    /**
     * Get parent request data
     * @return null
     */
    public function get_parent_request_data()
    {
        if (is_null($this->parent_task)) return null;
        return $this->parent_task->get_request_data();
    }

    /**
     * @return \PhpQueue\Task
     */
    public function parent_task()
    {
        return $this->parent_task;
    }

    /**
     * @param \PhpQueue\Task $parent_task
     * @return $this
     */
    public function set_parent_task($parent_task)
    {
        $this->parent_task = $parent_task;
        return $this;
    }
}