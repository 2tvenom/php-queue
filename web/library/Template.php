<?php
use PhpQueue\Task;

class Template {
    const TYPE_LIST_ERROR = 'error';
    const TYPE_LIST_LOG = 'log';

    /**
     * Check exist list partial template
     * @param $task_name
     * @return bool
     */
    public static function list_partial_template_exist($task_name)
    {
        return file_exists(PARTIAL_TEMPLATE_PATH . $task_name ."/" . LIST_PARTIAL_TEMPLATE_NAME);
    }

    /**
     * Check exist details partial template
     * @param $task_name
     * @return bool
     */
    public static function details_partial_template_exist($task_name)
    {
        return file_exists(PARTIAL_TEMPLATE_PATH . $task_name . "/" . DETAILS_PARTIAL_TEMPLATE_NAME);
    }

    /**
     * Check exist log partial template
     * @param $task_name
     * @return bool
     */
    public static function log_partial_template_exist($task_name)
    {
        return file_exists(PARTIAL_TEMPLATE_PATH . $task_name . "/" . LOG_PARTIAL_TEMPLATE_NAME);
    }

    /**
     * Check exist error partial template
     * @param $task_name
     * @return bool
     */
    public static function error_partial_template_exist($task_name)
    {
        return file_exists(PARTIAL_TEMPLATE_PATH . $task_name . "/" . ERROR_PARTIAL_TEMPLATE_NAME);
    }

    /**
     * Beautiful json output
     * @param $var
     * @return mixed
     */
    public static function pretty_json($var)
    {
        $json = nl2br(str_replace(" ", "&nbsp;", json_encode($var, JSON_PRETTY_PRINT)));
        $json = preg_replace("#([{}\[\]])#", "<span class=\"text-muted\">$1</span>", $json);
        $json = preg_replace("#\"(.*?)\":&nbsp;\"(.*?)\"#", "<span class=\"text-muted\">$1</span> : <span class=\"text-danger\">$2</span>", $json);
        $json = preg_replace("#\"([a-zA-Z_.0-9]+)\"#", "<span class=\"text-muted\">$1</span>", $json);
        return $json;
    }

    /**
     * Partial task property render
     * @param $task
     * @param $params
     * @return string
     */
    public static function render_task_property($task, $params)
    {
        $return = '';
        foreach ($params as $entity => $details) {
            if (!is_null($task[$entity])) {
                $return .= '<p>' . $details . ": " . $task[$entity] . "</p>";
            }
        }
        return $return;
    }

    /**
     * Partial log render
     * @param $tasks_name
     * @param array $list
     * @return bool
     */
    public static function render_list_log($tasks_name, array $list)
    {
        $include_partial_name = PARTIAL_DEFAULT_TEMPLATE_NAME;

        if(self::log_partial_template_exist($tasks_name)) {
           $include_partial_name = $tasks_name;
        }

        include(PARTIAL_TEMPLATE_PATH . $include_partial_name . "/" . LOG_PARTIAL_TEMPLATE_NAME);
    }

    /**
     * Partial error render
     * @param $tasks_name
     * @param array $list
     * @return bool
     */
    public static function render_list_error($tasks_name, array $list)
    {
        $include_partial_name = PARTIAL_DEFAULT_TEMPLATE_NAME;

        if(self::error_partial_template_exist($tasks_name)) {
            $include_partial_name = $tasks_name ;
        }

        include(PARTIAL_TEMPLATE_PATH . $include_partial_name . "/" . ERROR_PARTIAL_TEMPLATE_NAME);
    }

    /**
     * Render pagination
     * Modified generate_pagination from phpbb3 (https://www.phpbb.com/)
     *
     * @param int $num_items
     * @param int $per_page
     * @param int $start_item
     * @return bool|string
     */
    public static function generate_pagination($num_items, $per_page, $start_item)
    {
        $total_pages = ceil($num_items / $per_page);

        if ($total_pages == 1 || !$num_items) {
            return false;
        }

        $on_page = floor($start_item / $per_page) + 1;

        $page_string = '<li ' . ($start_item == 0 ? 'class="active"' : '') . '><a href="#" class="page" data-offset="0">1</a></li>';

        if ($total_pages > 5) {
            $start_cnt = min(max(1, $on_page - 4), $total_pages - 5);
            $end_cnt = max(min($total_pages, $on_page + 4), 6);

            if ($start_cnt > 1) {
                $page_string .= '<li class="disabled"><span>...</span></li>';
            }

            for ($i = $start_cnt + 1; $i < $end_cnt; $i++) {
                $offset = (($i - 1) * $per_page);
                $page_string .= '<li ' . ($offset == $start_item ? 'class="active"' : '') . '><a href="#" class="page" data-offset="' . $offset . '">' . $i . '</a></li>';
            }

            if ($end_cnt < $total_pages){
                $page_string .= '<li class="disabled"><span>...</span></li>';
            }
        } else {
            for ($i = 2; $i < $total_pages; $i++) {
                $offset = (($i - 1) * $per_page);
                $page_string .= '<li ' . ($offset == $start_item ? 'class="active"' : '') . '><a href="#" class="page" data-offset="' . $offset . '">' . $i . '</a></li>';
            }
        }

        $total_offset = (($total_pages - 1) * $per_page);
        $page_string .= '<li ' . ($total_offset == $start_item ? 'class="active"' : '') . '><a href="#" class="page" data-offset="' . $total_offset . '">' . $total_pages . '</a></li>';

        $page_string = '<ul class="pagination pagination-sm">' . $page_string . '</ul>';
        return $page_string;
    }
}