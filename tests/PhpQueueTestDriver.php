<?php
use PhpQueue\Task;
use PhpQueue\TaskConst;

abstract class PhpQueueTestDriver extends \PHPUnit_Framework_TestCase
{
    const SUB_TASK_JOB_CALLBACK = "SubTaskJobCallback";
    const TASK_JOB_CALLBACK = "TaskJobCallback";
    const JOB = "Job";
    const ERROR_JOB = "ErrorJob";
    const JOB_1 = "Job1";
    const JOB_2 = "Job2";
    const TASK_ARGS = 100;
    const EXCLUSIVE_PERFORMER = "Exclusive_Performer";
    const TASKS_COUNT_5 = 5;
    const TASKS_COUNT_3 = 3;

    /**
     * @var \PhpQueue\Queue
     */
    protected static $queue = null;

    /**
     * @var \PhpQueue\TaskPerformer
     */
    protected static $task_performer1 = null;

    /**
     * @var \PhpQueue\TaskPerformer
     */
    protected static $task_performer2 = null;
    /**
     * @return \PhpQueue\Interfaces\IQueueDriver
     */
    public static function getQueueDriver(){}

    public static function prepareTestExecution(){}

    /**
     * SetUp tasks in queue
     */
    public static function setUpBeforeClass()
    {
        static::prepareTestExecution();

        self::$queue = new \PhpQueue\Queue(static::getQueueDriver());

        self::$task_performer1 = new \PhpQueue\TaskPerformer();

        self::$task_performer2 = new \PhpQueue\TaskPerformer(self::EXCLUSIVE_PERFORMER);

        //one task
        $task = new Task(self::JOB, self::TASK_ARGS);
        $task->set_callback(self::TASK_JOB_CALLBACK)->set_error_callback(self::TASK_JOB_CALLBACK);
        self::$queue->add_task($task);

        //task with sub tasks
        $task = new Task(self::JOB, self::TASK_ARGS);
        $task
            ->set_callback(self::TASK_JOB_CALLBACK)
            ->set_error_callback(self::TASK_JOB_CALLBACK);
        for ($i = 0; $i < self::TASKS_COUNT_5; $i++) {
            $sub_task = new Task(self::JOB_1, $i);
            $sub_task
                ->set_callback(self::SUB_TASK_JOB_CALLBACK)
                ->set_error_callback(self::SUB_TASK_JOB_CALLBACK);
            $task->sub_tasks()->add($sub_task);
        }
        self::$queue->add_task($task);

        //task with sub tasks and error break false and exclusive
        $task = new Task(self::JOB_2);
        $task
            ->set_callback(self::TASK_JOB_CALLBACK)
            ->set_error_callback(self::TASK_JOB_CALLBACK)
            ->set_exclusive(true);

        for ($i = 0; $i < self::TASKS_COUNT_3; $i++) {
            $sub_task = new Task(self::JOB_2, $i);
            $task->sub_tasks()->add($sub_task);
        }
        self::$queue->add_task($task);

        //task with error
        $task = new Task(self::JOB);
        $task
            ->set_callback(self::TASK_JOB_CALLBACK)
            ->set_error_callback(self::TASK_JOB_CALLBACK)
            ->settings()
            ->set_error_break(false)
            ->set_error_max_trial(self::TASKS_COUNT_3);

        for ($i = 0; $i < self::TASKS_COUNT_5; $i++) {
            if($i == 2){
                $sub_task = new Task(self::ERROR_JOB, $i);
            } else {
                $sub_task = new Task(self::JOB_2, $i);
            }

            $task->sub_tasks()->add($sub_task);
        }
        self::$queue->add_task($task);


        //task with subtasks and error
        $task = new Task(self::JOB);
        $task
            ->set_task_group_id(2)
            ->settings()
            ->set_error_break(true)
            ->set_error_max_trial(2);

        $sub_task = new Task(self::ERROR_JOB);
        $task->sub_tasks()->add($sub_task);

        for ($i = 0; $i < 3; $i++) {
            $sub_task = new Task(self::JOB_2, $i);
            $task->sub_tasks()->add($sub_task);
        }
        self::$queue->add_task($task);

        $task = new Task(self::ERROR_JOB);
        $task->set_task_group_id(3);
        self::$queue->add_task($task);
    }

    public function testPerformerOne_First_Task()
    {
        $task = self::$queue->get_task();
        $this->check_asserts($task, array(
                'have_subtasks' => false,
                'exclusive' => false,
                'status' => Task::STATUS_IN_PROCESS,
                'request_data' => self::TASK_ARGS,
                'task_name' => self::JOB,
            ));

        $task = self::$task_performer1->execute_task($task);

        $this->check_asserts(
            $task,
            array(
                'status' => Task::STATUS_DONE,
                'response' => self::TASK_ARGS * 2,
                'performer_name' => TaskConst::PERFORMER_DEFAULT_NAME,
            )
        );
        self::$queue->modify_task($task);
    }

    /**
     * @depends testPerformerOne_First_Task
     */
    public function testPerformerOne_Second_Task()
    {
        for($i=0; $i< self::TASKS_COUNT_5-3; $i++)
        {
            $task = self::$queue->get_task();

            $this->check_asserts(
                $task,
                array(
                    'have_subtasks' => true,
                    'callback' => self::TASK_JOB_CALLBACK,
                    'error_callback' => self::TASK_JOB_CALLBACK,
                    'exclusive' => false,
                    'status' => Task::STATUS_IN_PROCESS,
                    'request_data' => self::TASK_ARGS,
                    'not_performed' => self::TASKS_COUNT_5 - ($i+1),
                )
            );

            $sub_task = $task->sub_tasks()->get_perform_task();

            $this->check_asserts(
                $sub_task,
                array(
                    'have_subtasks' => false,
                    'callback' => self::SUB_TASK_JOB_CALLBACK,
                    'error_callback' => self::SUB_TASK_JOB_CALLBACK,
                    'exclusive' => false,
                    'task_name' => self::JOB_1,
                    'status' => Task::STATUS_IN_PROCESS,
                    'request_data' => $i,
                )
            );

            $task = self::$task_performer1->execute_task($task);

            $this->check_asserts(
                $task,
                array(
                    'performer_name' => null,
                    'status' => Task::STATUS_IN_PROCESS,
                )
            );

            $this->check_asserts(
                $sub_task,
                array(
                    'performer_name' => TaskConst::PERFORMER_DEFAULT_NAME,
                    'status' => Task::STATUS_DONE,
                    'response' => $i * 2,
                )
            );

            self::$queue->modify_task($task);
        }
    }

    /**
     * @depends testPerformerOne_Second_Task
     */
    public function testPerformerTwo_Second_Task()
    {
        for ($i = 2; $i < self::TASKS_COUNT_5-1; $i++) {
            $task = self::$queue->get_task();

            $this->check_asserts(
                $task,
                array(
                    'have_subtasks' => true,
                    'callback' => self::TASK_JOB_CALLBACK,
                    'error_callback' => self::TASK_JOB_CALLBACK,
                    'exclusive' => false,
                    'status' => Task::STATUS_IN_PROCESS,
                    'request_data' => self::TASK_ARGS,
                    'not_performed' => self::TASKS_COUNT_5 - ($i + 1),
                    'task_name' => self::JOB,
                )
            );

            $sub_task = $task->sub_tasks()->get_perform_task();

            $this->check_asserts(
                $sub_task,
                array(
                    'have_subtasks' => false,
                    'callback' => self::SUB_TASK_JOB_CALLBACK,
                    'error_callback' => self::SUB_TASK_JOB_CALLBACK,
                    'request_data' => $i,
                    'task_name' => self::JOB_1,
                )
            );

            self::$task_performer2->execute_task($task);

            $this->check_asserts(
                $task,
                array(
                    'performer_name' => null,
                    'status' => Task::STATUS_IN_PROCESS,
                )
            );

            $this->check_asserts(
                $sub_task,
                array(
                    'performer_name' => self::EXCLUSIVE_PERFORMER,
                    'status' => Task::STATUS_DONE,
                    'response' => $i * 2,
                )
            );

            self::$queue->modify_task($task);
        }
    }

    /**
     * @depends testPerformerTwo_Second_Task
     */
    public function testPerformerOne_Second_Task_AGAIN()
    {
        $task = self::$queue->get_task();

        $this->check_asserts(
            $task,
            array(
                'have_subtasks' => true,
                'callback' => self::TASK_JOB_CALLBACK,
                'error_callback' => self::TASK_JOB_CALLBACK,
                'exclusive' => false,
                'status' => Task::STATUS_IN_PROCESS,
                'request_data' => self::TASK_ARGS,
                'not_performed' => 0,
            )
        );

        $sub_task = $task->sub_tasks()->get_perform_task();

        $this->check_asserts(
            $sub_task,
            array(
                'have_subtasks' => false,
                'callback' => self::SUB_TASK_JOB_CALLBACK,
                'error_callback' => self::SUB_TASK_JOB_CALLBACK,
                'task_name' => self::JOB_1,
                'request_data' => 4,
            )
        );

        $task = self::$task_performer1->execute_task($task);

        $this->check_asserts(
            $sub_task,
            array(
                'response' => 8,
                'status' => Task::STATUS_DONE,
                'performer_name' => TaskConst::PERFORMER_DEFAULT_NAME,
            )
        );

        $task = self::$queue->modify_task($task);

        $this->check_asserts(
            $task,
            array(
                'performer_name' => null,
                'status' => Task::STATUS_DONE,
            )
        );
    }

    /**
     * @depends testPerformerOne_Second_Task_AGAIN
     */
    public function testPerformerTwo_Exclusive_FirstTwoSubTasks()
    {
        for ($i = 0; $i < self::TASKS_COUNT_5 - 3; $i++) {
            $task = self::$queue->get_task(array(
                    TaskConst::PERFORMER => self::EXCLUSIVE_PERFORMER
            ));

            $this->check_asserts(
                $task,
                array(
                    'have_subtasks' => true,
                    'callback' => self::TASK_JOB_CALLBACK,
                    'error_callback' => self::TASK_JOB_CALLBACK,
                    'exclusive' => true,
                    'status' => Task::STATUS_IN_PROCESS,
                    'request_data' => null,
                    'not_performed' => self::TASKS_COUNT_3 - ($i + 1),
                )
            );

            $sub_task = $task->sub_tasks()->get_perform_task();

            $this->check_asserts(
                $sub_task,
                array(
                    'task_name' => self::JOB_2,
                    'request_data' => $i,
                )
            );

            self::$task_performer2->execute_task($task);

            $this->check_asserts(
                $task,
                array(
                    'performer_name' => self::EXCLUSIVE_PERFORMER,
                    'status' => Task::STATUS_IN_PROCESS,
                )
            );

            $this->check_asserts(
                $sub_task,
                array(
                    'performer_name' => self::EXCLUSIVE_PERFORMER,
                    'status' => Task::STATUS_DONE,
                    'response' => $i * 2,
                )
            );

            self::$queue->modify_task($task);
        }
    }

    /**
     * @depends testPerformerTwo_Exclusive_FirstTwoSubTasks
     */
    public function testPerformerOne_FirstTwoSubTasks()
    {
        for ($i = 0; $i < self::TASKS_COUNT_3 - 1; $i++) {
            $task = self::$queue->get_task();

            $this->check_asserts(
                $task,
                array(
                    'have_subtasks' => true,
                    'callback' => self::TASK_JOB_CALLBACK,
                    'error_callback' => self::TASK_JOB_CALLBACK,
                    'exclusive' => false,
                    'status' => Task::STATUS_IN_PROCESS,
                    'request_data' => null,
                    'not_performed' => self::TASKS_COUNT_5 - ($i + 1),
                )
            );

            $sub_task = $task->sub_tasks()->get_perform_task();

            $this->check_asserts(
                $sub_task,
                array(
                    'task_name' => self::JOB_2,
                    'request_data' => $i,
                )
            );

            self::$task_performer1->execute_task($task);

            $this->check_asserts(
                $task,
                array(
                    'performer_name' => null,
                    'status' => Task::STATUS_IN_PROCESS,
                )
            );

            $this->check_asserts(
                $sub_task,
                array(
                    'performer_name' => TaskConst::PERFORMER_DEFAULT_NAME,
                    'status' => Task::STATUS_DONE,
                    'response' => $i * 2,
                )
            );

            self::$queue->modify_task($task);
        }
    }

    /**
     * @depends testPerformerOne_FirstTwoSubTasks
     */
    public function testPerformerTwo_Exclusive_Last_SubTask()
    {
        $task = self::$queue->get_task(
            array(
                TaskConst::PERFORMER => self::EXCLUSIVE_PERFORMER
            )
        );

        $this->check_asserts(
            $task,
            array(
                'have_subtasks' => true,
                'callback' => self::TASK_JOB_CALLBACK,
                'error_callback' => self::TASK_JOB_CALLBACK,
                'exclusive' => true,
                'status' => Task::STATUS_IN_PROCESS,
                'request_data' => null,
                'not_performed' => 0,
            )
        );

        $sub_task = $task->sub_tasks()->get_perform_task();

        $this->check_asserts(
            $sub_task,
            array(
                'task_name' => self::JOB_2,
                'request_data' => 2,
            )
        );

        self::$task_performer2->execute_task($task);

        $this->check_asserts(
            $sub_task,
            array(
                'performer_name' => self::EXCLUSIVE_PERFORMER,
                'status' => Task::STATUS_DONE,
                'response' => 4,
            )
        );

        self::$queue->modify_task($task);

        $this->check_asserts(
            $task,
            array(
                'performer_name' => self::EXCLUSIVE_PERFORMER,
                'status' => Task::STATUS_DONE,
            )
        );

    }

    /**
     * @depends testPerformerTwo_Exclusive_Last_SubTask
     */
    public function testPerformerTwo_ERROR_SubTask()
    {
        for($i=0; $i<3; $i++){
            $task = self::$queue->get_task();
            $this->check_asserts(
                $task,
                array(
                    'have_subtasks' => true,
                    'callback' => self::TASK_JOB_CALLBACK,
                    'error_callback' => self::TASK_JOB_CALLBACK,
                    'exclusive' => false,
                    'status' => Task::STATUS_IN_PROCESS,
                    'request_data' => null,
                    'not_performed' => 2,
                    'max_trial' => self::TASKS_COUNT_3,
                    'subtasks_error' => 0,
                    'error_break' => false,
                )
            );

            $sub_task = $task->sub_tasks()->get_perform_task();

            $this->check_asserts(
                $sub_task,
                array(
                    'task_name' => self::ERROR_JOB,
                    'request_data' => 2,
                    'callback' => null,
                    'error_callback' => null,
                )
            );

            self::$task_performer2->execute_task($task);

            $task = self::$queue->modify_task($task);

            if($i == 2){
                $this->check_asserts(
                    $task,
                    array(
                        'subtasks_error' => 1,
                        'not_performed' => 2,
                    )
                );

                $this->check_asserts(
                    $sub_task,
                    array(
                        'trial' => 3,
                        'performer_name' => self::EXCLUSIVE_PERFORMER,
                    )
                );
                $this->assertEquals(3, count($sub_task->response()->get_error()));
                $this->assertContains('Test Exception', array_shift($sub_task->response()->get_error()));
                $this->assertEquals(Task::STATUS_ERROR, $sub_task->get_status());
            } else {
                $this->check_asserts(
                    $task,
                    array(
                        'subtasks_error' => 0,
                        'not_performed' => 3,
                    )
                );

                $this->check_asserts(
                    $sub_task,
                    array(
                        'trial' => self::TASKS_COUNT_3 - (self::TASKS_COUNT_3 - ($i+2)),
                        'performer_name' => self::EXCLUSIVE_PERFORMER,
                    )
                );
            }
        }
    }

    /**
     * @depends testPerformerTwo_ERROR_SubTask
     */
    public function testPerformerOne_SubTask_Last1()
    {
        $task = null;
        for ($i = 3; $i < self::TASKS_COUNT_5; $i++) {
            $task = self::$queue->get_task();
            $this->check_asserts(
                $task,
                array(
                    'have_subtasks' => true,
                    'callback' => self::TASK_JOB_CALLBACK,
                    'error_callback' => self::TASK_JOB_CALLBACK,
                    'exclusive' => false,
                    'status' => Task::STATUS_IN_PROCESS,
                    'request_data' => null,
                    'not_performed' => self::TASKS_COUNT_5 - ($i+1),
                    'subtasks_error' => 1,
                    'error_break' => false,
                )
            );

            $sub_task = $task->sub_tasks()->get_perform_task();

            $this->check_asserts(
                $sub_task,
                array(
                    'task_name' => self::JOB_2,
                    'request_data' => $i,
                    'callback' => null,
                    'error_callback' => null,
                )
            );

            self::$task_performer1->execute_task($task);
            $this->check_asserts(
                $task,
                array(
                    'subtasks_error' => 1,
                    'not_performed' => self::TASKS_COUNT_5 - ($i + 1),
                )
            );

            $this->check_asserts(
                $sub_task,
                array(
                    'status' => Task::STATUS_DONE,
                    'performer_name' => TaskConst::PERFORMER_DEFAULT_NAME,
                )
            );

            self::$queue->modify_task($task);
        }
        $this->assertEquals(Task::STATUS_DONE_WITH_ERROR, $task->get_status());
    }

    /**
     * @depends testPerformerOne_SubTask_Last1
     */
    public function test_task_group_1_and_task_name_select()
    {
        $task = self::$queue->get_task(array(
                TaskConst::TASK_GROUP_ID => 1
            ));
        $this->assertFalse($task);

        $task = self::$queue->get_task(
            array(
                TaskConst::TASK_NAME => self::JOB_2
            )
        );
        $this->assertFalse($task);
    }

    /**
     * @depends test_task_group_1_and_task_name_select
     */
    public function test_error_break()
    {
        $task = null;
        for ($i = 0; $i < self::TASKS_COUNT_3-1; $i++) {
            $task = self::$queue->get_task();
            $this->check_asserts(
                $task,
                array(
                    'have_subtasks' => true,
                    'callback' => null,
                    'error_callback' => null,
                    'exclusive' => false,
                    'status' => Task::STATUS_IN_PROCESS,
                    'request_data' => null,
                    'error_break' => true,
                )
            );

            $sub_task = $task->sub_tasks()->get_perform_task();

            $this->check_asserts(
                $sub_task,
                array(
                    'task_name' => self::ERROR_JOB,
                    'request_data' => null,
                    'callback' => null,
                    'error_callback' => null,
                )
            );

            $task = self::$task_performer1->execute_task($task);
            $task = self::$queue->modify_task($task);
            if($i == self::TASKS_COUNT_3 -2){

                $this->check_asserts(
                    $task,
                    array(
                        'not_performed' => 3,
                        'subtasks_error' => 1,
                    )
                );

                $this->check_asserts(
                    $sub_task,
                    array(
                        'status' => Task::STATUS_ERROR,
                    )
                );
            } else {
                $this->check_asserts(
                    $task,
                    array(
                        'not_performed' => 4,
                    )
                );

                $this->check_asserts(
                    $sub_task,
                    array(
                        'status' => Task::STATUS_NEW,
                        'trial' => $i + 2,
                    )
                );
            }


            self::$queue->modify_task($task);
        }
        $this->assertEquals(Task::STATUS_ERROR, $task->get_status());
    }

    /**
     * @depends test_error_break
     */
    public function test_single_error_job()
    {
        $task = self::$queue->get_task();
        self::$task_performer1->execute_task($task);
        $this->assertEquals(Task::STATUS_ERROR, $task->get_status());
        self::$queue->modify_task($task);
    }

    /**
     * @depends test_single_error_job
     */
    public function test_get_task_without_set_process_by_uniqid()
    {
        $simple_task = new Task(self::JOB);
        self::$queue->add_task($simple_task);

        $uniqid = $simple_task->get_uniqid();

        $task = self::$queue->get_task(array(
                TaskConst::UNIQID => $uniqid
            ));

        $this->check_asserts($task, array(
                'uniqid' => $uniqid,
                'status' => Task::STATUS_NEW,
            ));
        $task = self::$queue->get_task();
        $task = self::$task_performer1->execute_task($task);
        $this->check_asserts($task, array(
                'uniqid' => $uniqid,
                'status' => Task::STATUS_DONE,
            ));
        self::$queue->modify_task($task);
    }

    /**
     * @depends test_get_task_without_set_process_by_uniqid
     */
    public function test_task_execution_time()
    {
        $simple_task = new Task(self::JOB);
        $simple_task->set_execution_date(date('Y-m-d H:i:s', strtotime('now') + 3));
        self::$queue->add_task($simple_task);

        $task = self::$queue->get_task();
        $this->assertFalse($task);

        sleep(10);

        $task = self::$queue->get_task();
        $this->check_asserts($task, array());

        self::$task_performer1->execute_task($task);
        $this->check_asserts($task, array('status' => Task::STATUS_DONE));
        self::$queue->modify_task($task);
    }

    private function check_asserts(Task $task, array $cfg){
        $this->assertEquals(get_class($task), "PhpQueue\\Task");

        foreach($cfg as $key => $value){
            switch($key){
                case 'have_subtasks':
                    if ($value) {
                        $this->assertTrue($task->have_subtasks());
                    } else {
                        $this->assertFalse($task->have_subtasks());
                    }
                    break;
                case 'callback':
                    if (is_null($value)) {
                        $this->assertNull($task->get_callback());
                    } else {
                        $this->assertEquals($value, $task->get_callback());
                    }
                    break;
                case 'error_callback':
                    if (is_null($value)) {
                        $this->assertNull($task->get_error_callback());
                    } else {
                        $this->assertEquals($value, $task->get_error_callback());
                    }
                    break;
                case 'exclusive':
                    if ($value) {
                        $this->assertTrue($task->get_exclusive());
                    } else {
                        $this->assertFalse($task->get_exclusive());
                    }
                    break;
                case 'status':
                    $this->assertEquals($value, $task->get_status());
                    break;
                case 'request_data':
                    if (is_null($value)) {
                        $this->assertNull($task->get_request_data());
                    } else {
                        $this->assertEquals($value, $task->get_request_data());
                    }
                    break;
                case 'not_performed':
                    $this->assertEquals($value, $task->get_subtasks_quantity_not_performed());
                    break;
                case 'task_name':
                    $this->assertEquals($value, $task->get_task_name());
                    break;
                case 'performer_name':
                    if (is_null($value)) {
                        $this->assertNull($task->get_performer());
                    } else {
                        $this->assertEquals($value, $task->get_performer());
                    }
                    break;
                case 'response':
                    if (is_null($value)) {
                        $this->assertNull($task->response()->get_response());
                    } else {
                        $this->assertEquals($value, $task->response()->get_response());
                    }
                    break;

                case 'trial':
                    if (is_null($value)) {
                        $this->assertNull($task->settings()->get_trial());
                    } else {
                        $this->assertEquals($value, $task->settings()->get_trial());
                    }
                    break;
                case 'max_trial':
                    if (is_null($value)) {
                        $this->assertNull($task->settings()->get_error_max_trial());
                    } else {
                        $this->assertEquals($value, $task->settings()->get_error_max_trial());
                    }
                    break;
                case 'subtasks_error':
                    if (is_null($value)) {
                        $this->assertNull($task->get_subtasks_error());
                    } else {
                        $this->assertEquals($value, $task->get_subtasks_error());
                    }
                    break;
                case 'error_break':
                    if (is_null($value)) {
                        $this->assertNull($task->settings()->get_error_break());
                    } else {
                        $this->assertEquals($value, $task->settings()->get_error_break());
                    }
                    break;
                case 'uniqid':
                    $this->assertEquals($value, $task->get_uniqid());
                    break;

                default:
                    throw new \Exception('Unknown check key ' . $key);
                    break;
            }
        }
    }
}
