<?php
namespace PhpQueue;

/**
 * Class TaskConst
 * Tasks fields ID
 * @package PhpQueue
 */
class TaskConst {
    const ID = 'id';
    const UNIQID = 'uniqid';
    const TYPE = 'type';
    const PARENT_ID = 'parent_id';
    const SUBTASKS_QUANTITY = 'subtasks_quantity';
    const SUBTASKS_QUANTITY_NOT_PERFORMED = 'subtasks_quantity_not_performed';
    const SUBTASKS_ERROR = 'subtasks_error';
    const EXCLUSIVE = 'exclusive';
    const TASK_NAME = 'task_name';
    const TASK_GROUP_ID = 'task_group_id';
    const STATUS = 'status';
    const PERFORMER = 'performer';
    const REQUEST_DATA = 'request_data';
    const RESPONSE_DATA = 'response_data';
    const EXECUTION_DATE = 'execution_date';
    const CALLBACK = 'callback';
    const ERROR_CALLBACK = 'error_callback';
    const SETTINGS = 'settings';
    const PRIORITY = 'priority';
    const CREATE_DATE = 'create_date';
    const START_DATE = 'start_date';
    const DONE_DATE = 'done_date';

    const JOB = 0;
    const TASK_CALLBACK = 1;
    const TASK_ERROR_CALLBACK = 2;
    const PARENT_CALLBACK = 3;
    const PARENT_ERROR_CALLBACK = 4;
    const GLOBAL_CALLBACK = 5;
    const GLOBAL_ERROR_CALLBACK = 6;

    const NEED_SET_IN_PROCESS_FLAG = 'need_set_in_process';

    const JOB_INTERFACE = 'IJob';
    const CALLBACK_INTERFACE = 'ICallback';
    const ERROR_CALLBACK_INTERFACE = 'IErrorCallback';

    public static $job_interfaces = array(
        TaskConst::JOB_INTERFACE,
        TaskConst::CALLBACK_INTERFACE,
        TaskConst::ERROR_CALLBACK_INTERFACE,
    );

    const PERFORMER_DEFAULT_NAME = 'Default_Performer';

    const TASK = 1;
    const SUB_TASK = 2;

    public static $job_name_by_id = array(
        TaskConst::JOB                     => "Task Job",
        TaskConst::TASK_CALLBACK           => "Task callback",
        TaskConst::TASK_ERROR_CALLBACK     => "Task error callback",
        TaskConst::PARENT_CALLBACK         => "Parent callback",
        TaskConst::PARENT_ERROR_CALLBACK   => "Parent error callback",
        TaskConst::GLOBAL_CALLBACK         => "Global callback",
        TaskConst::GLOBAL_ERROR_CALLBACK   => "Global error callback",
    );
}