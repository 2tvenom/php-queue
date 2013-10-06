<?php
namespace PhpQueue\Drivers;

use PhpQueue\Exceptions\DriverException;
use PhpQueue\Interfaces\IQueueDriver;
use PhpQueue\Interfaces\IWebInterface;
use PhpQueue\Task;
use PhpQueue\TaskConst;

/**
 * Class FileDriver
 * @package PhpQueue\Drivers
 */
class FileDriver implements IQueueDriver, IWebInterface
{
    private $index_file = 'index.xml';
    private $done_index_file = 'done_index.xml';
    private $file_handler = null;
    private $tasks_folder = 'tasks';
    private $id_file = 'id';
    private $connection = null;

    const ROOT_XML = 'queue';
    const TASK_XML = 'task';
    const SUB_TASKS_XML = 'sub_tasks';

    const XML_NAMESPACE_TASK = "p";
    const XML_NAMESPACE_SUB_TASK = "s";

    private $index_row = array(
        TaskConst::ID,
        TaskConst::UNIQID,
        TaskConst::SUBTASKS_QUANTITY,
        TaskConst::SUBTASKS_QUANTITY_NOT_PERFORMED,
        TaskConst::EXCLUSIVE,
        TaskConst::TASK_GROUP_ID,
        TaskConst::TASK_NAME,
        TaskConst::STATUS,
        TaskConst::PERFORMER,
        TaskConst::PRIORITY,
        TaskConst::EXECUTION_DATE,
        TaskConst::CREATE_DATE,
        TaskConst::START_DATE,
        TaskConst::DONE_DATE,
    );

    public final function __construct($folder_name)
    {
        $this->connection = $folder_name;

        if (!file_exists($this->connection) || !is_dir($this->connection)) {
            throw new DriverException('Connection must be folder');
        }

        if (!is_writable($this->connection) || !is_readable($this->connection)) {
            throw new DriverException('Folder must be readable and writable');
        }

        $this->index_file = $this->connection . '/' . $this->index_file;
        $this->id_file = $this->connection . '/' . $this->id_file;
        $this->tasks_folder = $this->connection . '/' . $this->tasks_folder . '/';

        if (!file_exists($this->tasks_folder) || !is_dir($this->tasks_folder)) {
            mkdir($this->tasks_folder);
        }
    }

    /**
     * Lock file
     * @throws \PhpQueue\Exceptions\DriverException
     * @return bool
     */
    private function lock_file()
    {
        $this->file_handler = fopen($this->index_file, "a+");
        if (!flock($this->file_handler, LOCK_EX)) throw new DriverException("Cant lock file " . $this->index_file);

        return true;
    }

    /**
     * Release file
     * @return bool
     */
    private function release_file()
    {
        flock($this->file_handler, LOCK_UN);
        fclose($this->file_handler);
        $this->file_handler = null;
        return true;
    }

    /**
     * Add task to data provider
     * @param Task $task
     * @return int
     */
    public function add_task(Task $task)
    {
        $this->lock_file();

        $task->set_id($this->get_new_id());

        $this->save_task($task);

        $xml_dom = $this->get_xml_dom();

        $task_child_xml = $xml_dom->addChild(self::TASK_XML);

        foreach($task->get($this->index_row) as $item_name => $item_value)
        {
            $task_child_xml->addAttribute($item_name, $item_value);
        }
        $task_child_xml->addAttribute(TaskConst::TYPE, TaskConst::TASK);

        if($task->have_subtasks())
        {
            $sub_tasks_child_xml = $task_child_xml->addChild(self::SUB_TASKS_XML);
            foreach ($task->sub_tasks()->get_all() as $sub_task) {
                $sub_task->set_id($this->get_new_id());
                $sub_task_child_xml = $sub_tasks_child_xml->addChild(self::TASK_XML);
                foreach ($sub_task->get($this->index_row) as $item_name => $item_value) {
                    $sub_task_child_xml->addAttribute($item_name, $item_value);
                }
                $sub_task_child_xml->addAttribute(TaskConst::TYPE, TaskConst::SUB_TASK);

                $this->save_task($sub_task);
            }
        }

        $this->save_xml_dom($xml_dom);
        $this->release_file();

        return $task->get_id();
    }

    private function save_xml_dom(\SimpleXMLElement $dom)
    {
        ftruncate($this->file_handler, 0);
        fwrite($this->file_handler, $dom->asXML());
        return $this;
    }

    /**
     * Get XML DOM
     * @return \SimpleXMLElement
     */
    private function get_xml_dom()
    {
        return new \SimpleXMLElement($this->read_full_file());
    }

    /**
     * Get data from data provider, generate and return task
     * @param array $request
     * @return mixed
     */
    public function get_task(array $request)
    {
        $request = array_filter($request);

        if(array_key_exists(TaskConst::UNIQID, $request))
        {
            $request[TaskConst::NEED_SET_IN_PROCESS_FLAG] = false;
        }

        $this->lock_file();
        $xml_dom = $this->get_xml_dom();

        $result = call_user_func(function() use($xml_dom, $request) {
                $head_task = $this->xpath_find($xml_dom, $request);

                if (is_null($head_task)) return false;

                //select task from file
                $head_task_array = $this->get_task_array_from_file((string)$head_task[TaskConst::UNIQID]);

                if($request[TaskConst::NEED_SET_IN_PROCESS_FLAG])
                {
                    //set status for task and index record
                    if ((int)$head_task[TaskConst::STATUS] == Task::STATUS_NEW) {
                        $head_task[TaskConst::STATUS] = Task::STATUS_IN_PROCESS;
                        $head_task_array[TaskConst::STATUS] = Task::STATUS_IN_PROCESS;
                    }

                    if ((int)$head_task[TaskConst::SUBTASKS_QUANTITY_NOT_PERFORMED] > 0) {
                        $head_task[TaskConst::SUBTASKS_QUANTITY_NOT_PERFORMED] = (int)$head_task[TaskConst::SUBTASKS_QUANTITY_NOT_PERFORMED] - 1;
                        $head_task_array[TaskConst::SUBTASKS_QUANTITY_NOT_PERFORMED]--;
                    }
                    $this->save_array($head_task_array);
                }

                $task = new Task($head_task_array);

                // select sub tasks
                if(!property_exists($head_task, self::SUB_TASKS_XML))
                {
                    $this->save_xml_dom($xml_dom);
                    return $task;
                }

                $perform_id = null;

                $sub_tasks = array();
                foreach($head_task->{self::SUB_TASKS_XML}->{self::TASK_XML} as $sub_task)
                {
                    $sub_task = (array)$sub_task;
                    $sub_tasks[] = $sub_task['@attributes'];
                }
                unset($sub_task);

                if ($request[TaskConst::NEED_SET_IN_PROCESS_FLAG]){
                    foreach ($sub_tasks as $sub_task) {
                        if ((int)$sub_task[TaskConst::STATUS] != Task::STATUS_NEW) continue;

                        $sub_task[TaskConst::STATUS] = Task::STATUS_IN_PROCESS;

                        $sub_task_array = $this->get_task_array_from_file($sub_task[TaskConst::UNIQID]);
                        $sub_task_array[TaskConst::STATUS] = Task::STATUS_IN_PROCESS;
                        $this->save_array($sub_task_array);

                        $perform_id = $sub_task[TaskConst::UNIQID];
                        break;
                    }
                }

                foreach ($sub_tasks as $sub_task) {
                    $sub_task_params = $this->get_task_array_from_file($sub_task[TaskConst::UNIQID]);

                    if($perform_id == $sub_task[TaskConst::UNIQID]) {
                        $task->sub_tasks()->add_perform_task(new Task($sub_task_params));
                        continue;
                    }
                    $task->sub_tasks()->add(new Task($sub_task_params));
                }

                $this->save_xml_dom($xml_dom);

                return $task;
        });

        $this->release_file();

        return $result;
    }

    private function xpath_find(\SimpleXMLElement $xml_dom, $request)
    {
        if(array_key_exists(TaskConst::UNIQID, $request))
        {
            $tasks = $xml_dom->xpath("//" . self::TASK_XML . "[@" . TaskConst::UNIQID . "='" . $request[TaskConst::UNIQID] . "']");
            return empty($tasks) ? null : $tasks[0];
        }

        $tasks = $xml_dom->xpath("//" . self::TASK_XML . "[@" . TaskConst::TYPE . "='" . TaskConst::TASK . "']");
        //sorting
        if ($request[TaskConst::PRIORITY] != Task::TASK_PRIORITY_NONE) {
            usort(
                $tasks,
                function ($a, $b) use ($request) {
                    $a_priority = (int)$a[TaskConst::PRIORITY];
                    $b_priority = (int)$b[TaskConst::PRIORITY];

                    $a_id = (int)$a[TaskConst::ID];
                    $b_id = (int)$b[TaskConst::ID];

                    if ($a_priority == $b_priority) {
                        return $a_id < $b_id ? -1 : 1;
                    }

                    if ($request[TaskConst::PRIORITY] == Task::TASK_PRIORITY_HIGH) {
                        return $a_priority > $b_priority ? -1 : 1;
                    }

                    return $a_priority < $b_priority ? -1 : 1;
                }
            );
        }

        $head_task = null;

        //select task
        foreach ($tasks as $select_task) {
            if (!in_array(
                (int)$select_task[TaskConst::STATUS],
                array(Task::STATUS_NEW, Task::STATUS_IN_PROCESS)
            )
            ) continue;

            if (array_key_exists(TaskConst::TASK_GROUP_ID, $request) && (int)$select_task[TaskConst::TASK_GROUP_ID] != (int)$request[TaskConst::TASK_GROUP_ID]) continue;

            $execution_date = (string)$select_task[TaskConst::EXECUTION_DATE];
            if (!empty($execution_date) && strtotime('now') < strtotime($execution_date)) continue;

            if (array_key_exists(TaskConst::TASK_NAME, $request) && (string)$select_task[TaskConst::TASK_NAME] != $request[TaskConst::TASK_NAME]) continue;

            if ((int)$select_task[TaskConst::STATUS] == Task::STATUS_NEW) {
                $head_task = $select_task;
                break;
            }

            if ((int)$select_task[TaskConst::EXCLUSIVE] == 0 && (int)$select_task[TaskConst::STATUS] == Task::STATUS_IN_PROCESS && (int)$select_task[TaskConst::SUBTASKS_QUANTITY_NOT_PERFORMED] > 0) {
                $head_task = $select_task;
                break;
            }

            if ((int)$select_task[TaskConst::EXCLUSIVE] == 1 && (int)$select_task[TaskConst::STATUS] == Task::STATUS_IN_PROCESS && (int)$select_task[TaskConst::SUBTASKS_QUANTITY_NOT_PERFORMED] > 0 && (string)$select_task[TaskConst::PERFORMER] == $request[TaskConst::PERFORMER]) {
                $head_task = $select_task;
                break;
            }
        }
        return $head_task;
    }

    /**
     * Save task in file
     * @param Task $task
     * @return $this
     */
    private function save_task(Task $task)
    {
        return $this->save_array($task->to_array());
    }

    /**
     * Save task array in file
     * @param array $task_params
     * @return $this
     */
    private function save_array(array $task_params)
    {
        $unique_id = $task_params[TaskConst::UNIQID];

        if (!file_exists($this->tasks_folder)) {
            mkdir($this->tasks_folder);
        }

        list($first_folder, $second_folder, $full_path) = $this->get_file_path_by_uniqid($unique_id);

        if (!file_exists($first_folder)) {
            mkdir($first_folder);
        }

        if (!file_exists($second_folder)) {
            mkdir($second_folder);
        }

        file_put_contents($full_path, serialize($task_params));

        return $this;
    }

    /**
     * Get full path unique id
     * @param $unique_id
     * @return array
     */
    private function get_file_path_by_uniqid($unique_id)
    {
        $first_folder = $this->tasks_folder . substr($unique_id, 0, 2);
        $second_folder = $first_folder . '/' . substr($unique_id, 2, 2);

        return array($first_folder, $second_folder, $second_folder . '/' . $unique_id);
    }

    /**
     * Read full file
     * @return string
     */
    private function read_full_file()
    {
        $full_file = "";
        fseek($this->file_handler, 0);
        while (!feof($this->file_handler)) {
            $line = fgets($this->file_handler);
            if (empty($line)) break;
            $full_file .= $line;
        }
        if (empty($full_file)) {
            return "<?xml version=\"1.0\"?><" . self::ROOT_XML . "></" . self::ROOT_XML . ">";
        }

        return $full_file;
    }

    /**
     * Generate new id
     * @return int id
     */
    private function get_new_id()
    {
        $id = 1;
        if(!file_exists($this->id_file))
        {
            file_put_contents($this->id_file, $id);
            return $id;
        }

        $id = (int)file_get_contents($this->id_file) + 1;

        file_put_contents($this->id_file, $id);
        return $id;
    }

    /**
     * Return task array from task file
     * @param $unique_id
     * @return array
     */
    private function get_task_array_from_file($unique_id)
    {
        list(, , $full_path) = $this->get_file_path_by_uniqid($unique_id);

        if(!file_exists($full_path))
        {
            return null;
        }

        return unserialize(file_get_contents($full_path));
    }

    /**
     * Prepare modify task
     * Lock record in queue
     *
     * Return array of task_data
     * @param $unique_id
     * @return mixed
     */
    public function prepare_modify($unique_id)
    {
        $this->lock_file();

        $xml_dom = $this->get_xml_dom();

        $prepared_tasks = array();

        $head_task_array = $this->get_task_array_from_file($unique_id);

        if(is_null($head_task_array))
        {
            return null;
        }

        $prepared_tasks[] = $head_task_array;

        $sub_tasks = $xml_dom->xpath(
            "//" . self::TASK_XML . "[@" . TaskConst::UNIQID . "='" . $unique_id . "']/" . self::SUB_TASKS_XML . '/' . self::TASK_XML . '/@' . TaskConst::UNIQID
        );

        foreach($sub_tasks as $sub_task_unique_id)
        {
            $sub_task_array = $this->get_task_array_from_file((string)$sub_task_unique_id);
            if(!is_null($sub_task_array))
            {
                $prepared_tasks[] = $sub_task_array;
            }
        }

        if(empty($prepared_tasks))
        {
            $this->release_file();
            return null;
        }

        return $prepared_tasks;
    }

    /**
     * Modify task in queue
     * @param Task $task
     * @param bool $need_commit Transaction commit required
     * @return bool
     */
    public function modify_task(Task $task, $need_commit = false)
    {
        if(is_null($this->file_handler))
        {
            $this->lock_file();
        }

        $xml_dom = $this->get_xml_dom();
        $task_xml = $xml_dom->xpath(
            "//" . self::TASK_XML . "[@" . TaskConst::UNIQID . "='" . $task->get_uniqid() . "']"
        );
        $task_xml = $task_xml[0];

        foreach($task->get($this->index_row) as $item_name => $item_value)
        {
            $task_xml[$item_name] = $item_value;
        }

        $this->save_task($task);

        $this->save_xml_dom($xml_dom);

        $this->release_file();

        return $this;
    }

    /**
     * Get tasks list
     * @param $limit
     * @param int $offset
     * @param null $status
     * @param null $task_name
     * @param null $task_group_id
     * @return mixed
     */
    public function get_tasks($limit, $offset = 0, $status = null, $task_name = null, $task_group_id = null)
    {
        $this->lock_file();
        $xml_dom = $this->get_xml_dom();

        $xpath_request = array(
            TaskConst::TYPE => TaskConst::TASK
        );

        if(!is_null($status))
        {
            $xpath_request[TaskConst::STATUS] = $status;
        }

        $xpath_find = array_map(function($entity) use($xpath_request) {
            return "@" . $entity . "='" . $xpath_request[$entity] . "'";
            }, array_keys($xpath_request));

        $tasks = $xml_dom->xpath("//" . self::TASK_XML . "[" . implode(" and ", $xpath_find) . "]");

        //sorting
        usort(
            $tasks,
            function ($a, $b){
                return (int)$a[TaskConst::ID] > (int)$b[TaskConst::ID] ? -1 : 1;
            }
        );

        $tasks = array_slice($tasks, $offset, $limit);

        foreach($tasks as &$task)
        {
            $task = (array)$task;
            $task = $task['@attributes'];
        }
        unset($task);
        $this->release_file();
        return $tasks;
    }

    /**
     * Get task details
     * @param $unique_id
     * @return mixed
     */
    public function get_task_details($unique_id)
    {
        $this->lock_file();
        $xml_dom = $this->get_xml_dom();

        $tasks = $xml_dom->xpath("//" . self::TASK_XML . "[@" . TaskConst::UNIQID . "='" . $unique_id . "']/" . self::SUB_TASKS_XML . "/" . self::TASK_XML);

        $tasks_data = array($this->get_task_array_from_file($unique_id));

        foreach($tasks as $task)
        {
            $tasks_data[] = $this->get_task_array_from_file((string)$task[TaskConst::UNIQID]);
        }

        $base64_encoded_fields = array(
            TaskConst::SETTINGS,
            TaskConst::REQUEST_DATA,
            TaskConst::RESPONSE_DATA,
        );

        foreach ($base64_encoded_fields as $field_name) {
            foreach ($tasks_data as &$task) {
                $task[$field_name] = base64_decode($task[$field_name]);
            }
        }
        unset($task);
        $this->release_file();

        return $tasks_data;
    }

    /**
     * Get status list
     * @return mixed
     */
    public function get_list_statuses()
    {
        $this->lock_file();

        $xml_dom = $this->get_xml_dom();

        $tasks = $xml_dom->xpath("//" . self::TASK_XML . "[@" . TaskConst::TYPE . "=" . TaskConst::TASK . "]/@" . TaskConst::STATUS);

        $status_list = array();

        foreach($tasks as $task)
        {
            $status_id = (int)$task['status'];
            if(!array_key_exists($status_id, $status_list)) $status_list[$status_id] = 0;

            $status_list[$status_id]++;
        }

        $required_statuses = array(
            Task::STATUS_NEW,
            Task::STATUS_DONE,
            Task::STATUS_IN_PROCESS,
            Task::STATUS_ERROR,
            Task::STATUS_CANCEL,
        );

        foreach ($required_statuses as $status_id) {
            if (array_key_exists($status_id, $status_list)) continue;
            $status_list[$status_id] = 0;
        }

        $same_as_error = array(
            Task::STATUS_DONE_WITH_ERROR,
            Task::STATUS_CALLBACK_ERROR,
        );

        foreach ($same_as_error as $status_id) {
            if (!array_key_exists($status_id, $status_list)) continue;
            $status_list[Task::STATUS_ERROR] += $status_list[$status_id];
            unset($status_list[$status_id]);
        }

        $this->release_file();
        return $status_list;
    }

    /**
     * Get tasks count for pagination
     * @param $status_id
     * @return mixed
     */
    public function get_tasks_count_by_status($status_id)
    {
        $this->lock_file();

        $xml_dom = $this->get_xml_dom();

        $xpath_request = array(
            TaskConst::TYPE => TaskConst::TASK
        );

        if (!is_null($status_id)) {
            $xpath_request[TaskConst::STATUS] = $status_id;
        }

        $xpath_find = array_map(
            function ($entity) use ($xpath_request) {
                return "@" . $entity . "='" . $xpath_request[$entity] . "'";
            },
            array_keys($xpath_request)
        );

        $tasks = $xml_dom->xpath("//" . self::TASK_XML . "[" . implode(" and ", $xpath_find) . "]");

        $this->release_file();

        return count($tasks);
    }
}