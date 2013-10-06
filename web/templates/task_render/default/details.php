<?php
/**
 * @var array $head_task_details
 * @var array $sub_task_details
 */
use PhpQueue\Task;
use PhpQueue\TaskConst;

$have_sub_tasks = $head_task_details[TaskConst::SUBTASKS_QUANTITY] > 0;

if($have_sub_tasks)
{
    $percent_complete = (100 - (int)$head_task_details[TaskConst::SUBTASKS_QUANTITY_NOT_PERFORMED] / ((int)$head_task_details[TaskConst::SUBTASKS_QUANTITY] / 100));
}

$render_property = array(
    TaskConst::TASK_GROUP_ID => 'Task group id',
    TaskConst::PRIORITY => 'Priority',
    TaskConst::CREATE_DATE => 'Create date',
    TaskConst::START_DATE => 'Start date',
    TaskConst::DONE_DATE => 'Done date',
    TaskConst::EXECUTION_DATE => 'Execution time',
    TaskConst::CALLBACK => 'Callback',
    TaskConst::ERROR_CALLBACK => 'Error Callback',
);

?>

<div class="pull-right col-lg-2">
    <span id="task-header" class="label label-<?= TaskModel::$class_by_status[$head_task_details[TaskConst::STATUS]] ?>">
        <?= TaskModel::$status_text[$head_task_details[TaskConst::STATUS]] ?>
        <? if ($head_task_details[TaskConst::STATUS] == Task::STATUS_IN_PROCESS && $have_sub_tasks) { ?>
            <?= round(
                100 - (int)$head_task_details[TaskConst::SUBTASKS_QUANTITY_NOT_PERFORMED] / ((int)$head_task_details[TaskConst::SUBTASKS_QUANTITY] / 100)
            ); ?>%
        <? } ?>
    </span>
</div>

<h1><?= $head_task_details[TaskConst::TASK_NAME] ?></h1>
<h6 class="text-muted"><?= $head_task_details[TaskConst::UNIQID] ?></h6>
<h3 class="text-muted"><?= $head_task_details[TaskConst::PERFORMER] ?></h3>
<? if($head_task_details[TaskConst::EXCLUSIVE] == 1){ ?>
    <h4 class="text-warning">Exclusive task</h4>
<? } ?>
<h5 class="text-muted">(<?= \DateTimeHelper::formatDateDiff($head_task_details[TaskConst::START_DATE], $head_task_details[TaskConst::DONE_DATE]); ?>)</h5>

<h4>Property:</h4>
<div class="text-info">
<?= Template::render_task_property($head_task_details, $render_property); ?>
</div>

<? if($head_task_details[TaskConst::STATUS] == Task::STATUS_IN_PROCESS && $have_sub_tasks) { ?>
    <div class="progress progress-striped active" style="margin-bottom: 30px;">
        <div class="progress-bar progress-bar-warning" role="progressbar" style="width: <?= $percent_complete ?>%;">
            <span class="sr-only"><?= $percent_complete ?>% Complete</span>
        </div>
    </div>
<? } ?>

<? $request = unserialize($head_task_details[TaskConst::REQUEST_DATA]);?>
<? if(!is_null($request) && $request !== false) { ?>
    <h4>Request:</h4>
    <div class="well well-sm">
        <?= Template::pretty_json($request); ?>
    </div>
<? } ?>

<?
if(!$have_sub_tasks) $sub_task_details = array($head_task_details);
?>

<div class="panel-group" data-toggle="collapse" id="sub-tasks">
    <? foreach ($sub_task_details as $sub_task_detail) { ?>
        <div class="panel panel-default">
            <div class="panel-heading">
                <a class="sub-tasks-toggle" data-toggle="collapse" data-parent="#sub-tasks" href="#collapse<?= $sub_task_detail[TaskConst::UNIQID] ?>">
                    <?= $sub_task_detail[TaskConst::TASK_NAME] ?>
                </a>
                <? if($sub_task_detail[TaskConst::STATUS] == Task::STATUS_DONE){ ?>
                    <small class="text-muted">(<?= \DateTimeHelper::formatDateDiff($sub_task_detail[TaskConst::START_DATE],$sub_task_detail[TaskConst::DONE_DATE]); ?>)</small>
                <? } ?>
                <span class="pull-right label label-<?= TaskModel::$class_by_status[$sub_task_detail[TaskConst::STATUS]] ?>"><?= TaskModel::$status_text[$sub_task_detail[TaskConst::STATUS]] ?></span>
            </div>
            <div id="collapse<?= $sub_task_detail[TaskConst::UNIQID] ?>" class="panel-collapse collapse <?= $sub_task_detail[TaskConst::STATUS] == \PhpQueue\Task::STATUS_ERROR ? "in" : "" ?>">
                <div class="panel-body">
                    <h4>Property:</h4>

                    <div class="well well-sm">
                    <?= Template::render_task_property($sub_task_detail, $render_property); ?>
                    </div>

                    <h4>Settings:</h4>
                    <?
                    /**
                     * @var \PhpQueue\TaskSettings $settings
                     */
                    $settings = unserialize($sub_task_detail[TaskConst::SETTINGS]);
                    $trial = 0;
                    $max_trial = 0;
                    if ($settings instanceof \PhpQueue\TaskSettings) {
                        $trial = $settings->get_nested_settings('trial');
                        $max_trial = $settings->get_nested_settings('error_max_trial');
                    }
                    ?>
                    <div class="well well-sm">
                        Current trial: <?= $trial ?> <br>
                        Max trial: <?= $max_trial ?> <br>
                    </div>

                    <? $request = unserialize($sub_task_detail[TaskConst::REQUEST_DATA]);?>
                    <? if(!is_null($request) && $request !== false) { ?>
                        <h4>Request:</h4>
                        <div class="well well-sm">
                            <?= Template::pretty_json($request); ?>
                        </div>
                    <? } ?>

                    <?
                    /**
                     * @var \PhpQueue\TaskResponse $response
                     */
                    $response = unserialize($sub_task_detail[TaskConst::RESPONSE_DATA]);
                    ?>
                    <? if($response instanceof \PhpQueue\TaskResponse) { ?>
                        <? if(!is_null($response->get_response())){ ?>
                            <h4>Response:</h4>
                            <div class="well well-sm">
                                <?= Template::pretty_json($response->get_response()); ?>
                            </div>
                        <? } ?>

                        <? $errors = $response->get_error(); ?>
                        <? if(!empty($errors)){ ?>
                            <h4>Errors:</h4>
                            <div class="well alert-danger">
                                <?= Template::render_list_error($sub_task_detail[TaskConst::TASK_NAME], $errors); ?>
                            </div>
                        <? } ?>

                        <? $log = $response->get_log(); ?>
                        <? if(!empty($log)){ ?>
                            <h4>Log:</h4>
                            <div class="well well-sm">
                                <?= Template::render_list_log($sub_task_detail[TaskConst::TASK_NAME], $log); ?>
                            </div>
                        <? } ?>
                    <? } ?>
                </div>
            </div>
        </div>
    <? } ?>
</div>