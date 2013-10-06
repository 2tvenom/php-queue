<?
/**
 * @var array $status_list
 */
use PhpQueue\Task;

$menu = array(
    0 => "All",
    Task::STATUS_NEW => "New",
    Task::STATUS_IN_PROCESS => "In process",
    Task::STATUS_ERROR => "Error",
    Task::STATUS_DONE => "Done",
    Task::STATUS_CANCEL => "Cancel",
);

$need_badge = array(Task::STATUS_NEW, Task::STATUS_IN_PROCESS, Task::STATUS_ERROR);

$menu_string = "";
foreach ($menu as $status_id => $menu_name) {
    $additional_string = "";

    if (in_array($status_id, $need_badge) && $status_list[$status_id] > 0) {
        $additional_string = '<span class="badge">' . $status_list[$status_id] . '</span>';
    }

    $menu_string .= "<li><a href=\"#\" class=\"list-update\" data-status=\"{$status_id}\">{$menu_name} {$additional_string}</a></li>";
}
?>

<ul class="nav bs-sidenav">
    <li>
        <a href="#" class="menu-elem">Tasks</a>
        <ul style="display: block" class="nav bs-sidenav sub-nav">
            <?= $menu_string ?>
        </ul>
    </li>
</ul>

<? if ($status_list[Task::STATUS_IN_PROCESS] > 0) { ?>
    <div class="process">
        <?= $status_list[Task::STATUS_IN_PROCESS] ?> <img src="/assets/images/process.gif">
    </div>
<? } ?>