<?php
/**
 * @var array $task
 */
use PhpQueue\Task;
use PhpQueue\TaskConst;
?>
<div class="row">
    <div class="col-md-12">
        <span class="pull-right label label-<?= TaskModel::$class_by_status[$task[TaskConst::STATUS]] ?>">
            <?= TaskModel::$status_text[$task[TaskConst::STATUS]] ?>
            <? if ($task[TaskConst::STATUS] == Task::STATUS_IN_PROCESS && $task[TaskConst::SUBTASKS_QUANTITY] > 0) { ?>
                <?= round(100 - (int)$task[TaskConst::SUBTASKS_QUANTITY_NOT_PERFORMED] / ((int)$task[TaskConst::SUBTASKS_QUANTITY] / 100)); ?>%
            <? } ?>
        </span>
        <h4 class="list-group-item-heading">
            <?= $task[TaskConst::TASK_NAME] ?>
        </h4>
        <small><?= $task[TaskConst::PERFORMER] ?></small>
        <p><small><?= $task[TaskConst::START_DATE] ?></small></p>
    </div>
</div>