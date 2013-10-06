<?php
namespace PhpQueue;

use PhpQueue\Interfaces\IObject;

/**
 * Class TaskResponse
 * @package PhpQueue
 * @method get_response
 * @method get_error
 * @method get_log
 * @method clear_error
 * @method \PhpQueue\TaskResponse set_response($param) Set response
 * @method \PhpQueue\TaskResponse set_error($param) Set error
 * @method \PhpQueue\TaskResponse set_log($param) Set log
 */
class TaskResponse extends IObject{
    /**
     * @var array
     */
    protected $fields = array(
        'response' => null,
        'error'    => array(),
        'log'    => array(),
    );

    protected function setter_error($error)
    {
        $this->fields['error'][] = $error;
        return $this->fields['error'];
    }

    protected function setter_log($error)
    {
        $this->fields['log'][] = $error;

        return $this->fields['log'];
    }
}