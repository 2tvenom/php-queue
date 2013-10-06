<?php
namespace PhpQueue;

use phpDocumentor\Descriptor\ProjectDescriptor\Settings;
use PhpQueue\Exceptions\TaskSettingsException;
use PhpQueue\Interfaces\IObject;

/**
 * Class TaskSettings
 * @package PhpQueue
 * @method int get_error_break
 * @method int get_trial
 * @method int inc_trial
 * @method int get_error_max_trial
 * @method \PhpQueue\TaskSettings set_error_break($param) Set error break
 * @method \PhpQueue\TaskSettings set_trial($param) Set trial
 * @method \PhpQueue\TaskSettings set_error_max_trial($param) Set error max trial
 */
class TaskSettings extends IObject{
    /**
     * @var array
     */
    protected $fields = array(
        'error_break'     => null,
        'trial'           => 1,
        'error_max_trial' => null,
    );

    private $default = array(
        'error_break'     => true,
        'error_max_trial' => 0,
    );

    /**
     * @var null|TaskSettings
     */
    private $parent_settings = null;

    /**
     * Get settings values
     * @param $key_name
     * @return mixed
     */
    public function get_nested_settings($key_name)
    {
        $value = $this->get($key_name);
        if (!is_null($value)) return $value;

        if (!is_null($this->get_parent_settings())) {
            $value = $this->get_parent_settings()->get($key_name);
            if (!is_null($value)) return $value;
        }

        return $this->get_default($key_name);
    }

    /**
     * Get default settings
     * @param $name
     * @throws Exceptions\TaskSettingsException
     * @return
     */
    public function get_default($name)
    {
        if(!array_key_exists($name, $this->default)){
            throw new TaskSettingsException('Not found default value of ' . $name);
        }
        return $this->default[$name];
    }

    /**
     * Get parent settings
     * @return TaskSettings|null
     */
    public function get_parent_settings()
    {
        return $this->parent_settings;
    }

    /**
     * Set parent settings
     * @param TaskSettings $parent_settings
     * @return $this
     */
    public function set_parent_settings(TaskSettings $parent_settings)
    {
        $this->parent_settings = $parent_settings;
        return $this;
    }

    /**
     * Set null parent settings
     * @return $this
     */
    private function null_parent_settings()
    {
        $this->parent_settings = null;
        return $this;
    }

    /**
     * @api
     *
     * Error max trial setting validation
     * @param $value
     * @throws Exceptions\TaskSettingsException
     * @return bool
     */
    protected function setter_error_max_trial($value)
    {
        if (!is_int($value) || $value < 0) throw new TaskSettingsException("Error max trial setting must be int and not be negative");

        return $value;
    }

    /**
     * @api
     *
     * Trial setting validation
     * @param $value
     * @throws Exceptions\TaskSettingsException
     * @return bool
     */
    protected function setter_trial($value)
    {
        if (!is_int($value) || $value < 0) throw new TaskSettingsException("Trial setting must be int and not be negative");

        return $value;
    }

    /**
     * @api
     *
     * Error break setting validation
     * @param $value
     * @throws Exceptions\TaskSettingsException
     * @return bool
     */
    protected function setter_error_break($value)
    {
        if(!is_bool($value)) throw new TaskSettingsException("Error break setting must be bool");
        return $value;
    }
}