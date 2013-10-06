<?php
namespace PhpQueue;

class Helper {
    /**
     * @api
     * @static
     * Check Job/Callback class
     * @param $job_class_name
     * @param $job_type
     * @internal param $job_class
     * @internal param string $interface
     * @return bool
     */
    public static function check_class($job_class_name, $job_type)
    {
        $interface = self::interface_job($job_type);

        if ($interface === false || !class_exists($job_class_name)) return false;

        $implements = class_implements($job_class_name);

        if (empty($implements) || !array_key_exists(
                __NAMESPACE__ . '\Interfaces\\' . $interface,
                $implements
            )
        ) return false;

        return true;
    }

    /**
     * @api
     * Get interface by job id
     * @param $job_type
     * @return bool
     */
    public static function interface_job($job_type)
    {
        switch ($job_type) {
            case TaskConst::TASK_ERROR_CALLBACK:
            case TaskConst::PARENT_ERROR_CALLBACK:
            case TaskConst::GLOBAL_ERROR_CALLBACK:
                return TaskConst::ERROR_CALLBACK_INTERFACE;
                break;
            case TaskConst::JOB:
                return TaskConst::JOB_INTERFACE;
                break;
            case TaskConst::TASK_CALLBACK:
            case TaskConst::PARENT_CALLBACK:
            case TaskConst::GLOBAL_CALLBACK:
                return TaskConst::CALLBACK_INTERFACE;
                break;
        }
        return false;
    }
}