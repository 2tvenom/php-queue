<?
/**
 * @var $action
 */
use PhpQueue\Task;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Queue</title>
    <meta charset="UTF-8">
    <link href="/assets/css/style.css" rel="stylesheet">
    <script type="text/javascript" src="/assets/js/jquery-2.0.2.min.js"></script>
    <script type="text/javascript" src="/assets/js/collapse.js"></script>
    <script type="text/javascript" src="/assets/js/dropdown.js"></script>
    <script type="text/javascript" src="/assets/js/main.js"></script>
</head>
<body>
    <div class="col-md-1" id="menu">
        <div class="process">
            PhpQueue
        </div>
        <div class="menu">
            <?= include('menu.php'); ?>
        </div>
    </div>
    <?= $action ?>
    <div id="overlay">
        <div id="ajax-modal">
        </div>
    </div>
</body>
</html>