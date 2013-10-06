<?php
use PhpQueue\TaskConst;

class indexController extends prototypeController
{
    public function indexAction()
    {

    }

    public function tasksListAction()
    {
        $status = null;
        $offset = 0;

        if (array_key_exists('offset', $_GET) && (int)$_GET['offset'] > 0) {
            $offset = (int)$_GET['offset'];
        }

        if(array_key_exists(TaskConst::STATUS, $_GET) && (int)$_GET[TaskConst::STATUS] > 0) {
            $status = (int)$_GET[TaskConst::STATUS];
        }

        $this->no_layout(true);
        $tasks = $this->_driver->get_tasks(TASKS_LIST_LIMIT, $offset, $status);
        $templates = array();

        $tasks_count = $this->_driver->get_tasks_count_by_status($status);

        foreach($tasks as $task)
        {
            $task_name = $task['task_name'];
            if(array_key_exists($task_name, $templates)) continue;

            $templates[$task_name] = Template::list_partial_template_exist($task_name) ? $task_name : PARTIAL_DEFAULT_TEMPLATE_NAME;
        }

        $this->assign('tasks', $tasks);
        $this->assign('templates', $templates);
        $this->assign('tasks_offset', $offset);
        $this->assign('tasks_count', $tasks_count);
    }

    public function taskDetailsAction()
    {
        $this->no_layout(true);

        $id = $this->get('id');
        if (is_null($id)) throw new Exception("Not found id");

        $task_details = $this->_driver->get_task_details($id);
        $this->assign('head_task_details', $task_details[0]);

        $template_name = Template::details_partial_template_exist(
            $task_details[0]['task_name']
        ) ? $task_details[0]['task_name'] : PARTIAL_DEFAULT_TEMPLATE_NAME;

        unset($task_details[0]);
        $this->assign('sub_task_details', $task_details);
        $this->view_name(PARTIAL_TEMPLATE_PATH . $template_name . '/' . DETAILS_PARTIAL_TEMPLATE_NAME);
    }

    public function taskActionAction()
    {
        $approved_task_action = array(
            'cancel'
        );
        if(!array_key_exists('task_action', $_GET) || !in_array($_GET['task_action'], $approved_task_action)) $this->json_response(array('error' => 'Unknown task action'));

        if (!array_key_exists('uniqid', $_GET)) $this->json_response(array('error' => 'Not found uniqid'));

        $uniqid = $_GET['uniqid'];

        $result = array();

        switch($_GET['task_action']){
            case "cancel":

                if(!($this->_driver instanceof PhpQueue\Interfaces\IQueueDriver))
                {
                    $result = array('error' => 'Queue driver not implement IQueueDriver');
                    break;
                }

                /**
                 * @property \PhpQueue\Interfaces\IQueueDriver $_driver
                 * @var \PhpQueue\Task $task
                 */
                $task = $this->_driver->get_task(array(TaskConst::UNIQID => $uniqid));
                $task->set_status(\PhpQueue\Task::STATUS_CANCEL);
                $this->_driver->modify_task($task);

                $result = array("success" => "ok");
                break;
        }

        $this->json_response($result);
    }

    public function menuUpdateAction()
    {
        extract($this->get_assigned());
        include(TEMPLATE_PATH . 'menu.php');
        exit;
    }
}