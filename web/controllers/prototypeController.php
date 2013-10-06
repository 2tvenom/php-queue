<?php
use \PhpQueue\Task;

class prototypeController
{
    private $assigned = array();
    /**
     * @var PDO
     */
    protected $db = null;

    protected $_no_layout = false;
    protected $_view_name = null;
    protected $_driver = null;

    public function __construct(\PhpQueue\Interfaces\IWebInterface $driver)
    {
        $this->_driver = $driver;

        $this->assign('status_list', $this->_driver->get_list_statuses());
    }

    public function get($name, $default = null)
    {
        if(array_key_exists($name, $_GET)) return $_GET[$name];

        return $default;
    }

    /**
     * @param null $bool
     * @return boolean
     */
    public function no_layout($bool = null)
    {
        if(is_bool($bool)){
            $this->_no_layout = $bool;
        }

        return $this->_no_layout;
    }

    /**
     * @param null $name
     * @return boolean
     */
    public function view_name($name = null)
    {
        if (is_string($name)) {
            $this->_view_name = $name;
        }

        return $this->_view_name;
    }

    protected function assign($name, $value)
    {
        $this->assigned[$name] = $value;
    }

    public function get_assigned()
    {
        return $this->assigned;
    }

    public function json_response(array $data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
}