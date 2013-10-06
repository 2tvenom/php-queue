<?
/**
 * @var array $tasks
 * @var array $templates
 * @var int $tasks_offset
 * @var int $tasks_count
 */
?>

<div class="list-group">
<? foreach($tasks as $task) { ?>
    <a href="#" data-uniqid="<?= $task['uniqid'] ?>" class="list-group-item task-list-element">
        <? include(PARTIAL_TEMPLATE_PATH . $templates[$task['task_name']] . "/" . LIST_PARTIAL_TEMPLATE_NAME) ?>
    </a>
<? } ?>
</div>

<div style="text-align: center">
    <?= Template::generate_pagination($tasks_count, TASKS_LIST_LIMIT, $tasks_offset) ?>
</div>
