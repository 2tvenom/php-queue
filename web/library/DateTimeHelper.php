<?php
class DateTimeHelper {
    public static function formatDateDiff($start, $end = null)
    {
        if (!($start instanceof DateTime)) {
            $start = new DateTime($start);
        }

        if (!($end instanceof DateTime)) {
            $end = new DateTime($end);
        } elseif(empty($end) || is_null($end)) {
            $end = new DateTime();
        }

        $interval = $start->diff($end);

        $formats = array(
            'y' => 'year',
            'm' => 'month',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        );

        foreach($formats as $format_id => $human_description) {
            if ($interval->$format_id == 0) continue;
            $format[] = "%" . $format_id . " " . $human_description;
        }
        if(empty($format))
        {
            $format[] = "%s second";
        }
        // Prepend 'since ' or whatever you like
        return $interval->format(implode(' ', $format));
    }
}