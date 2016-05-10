<?php namespace CoasterCms\Helpers;

use Carbon\Carbon;

class DateTimeHelper
{

    public static function display($dateTime, $format = 'long')
    {
        if (is_string($dateTime) || is_int($dateTime)) {
            $dateTime = new Carbon($dateTime);
        }
        return $dateTime->format(config('coaster::date.format.'.$format));
    }

    public static function displaySeconds($seconds) {
        $time = [];
        if ($weeks = (int) floor($seconds / 604800)) {
            $time[] = $weeks . ' ' . str_plural('week', $weeks);
        }
        if ($days = floor($seconds / 86400) % 7) {
            $time[] = $days . ' ' . str_plural('day', $days);
        }
        if ($hours = floor($seconds / 3600) % 24) {
            $time[] = $hours . ' ' . str_plural('hour', $hours);
        }
        if ($minutes = (floor($seconds / 60) % 60)) {
            $time[] = $minutes . ' ' . str_plural('minute', $minutes);
        }
        if ($seconds = ($seconds % 60)) {
            $time[] = $seconds . ' ' . str_plural('second', $seconds);
        }
        $timeStr = implode(', ', array_slice($time, 0, count($time)>1?-1:null));
        if (count($time) > 1) {
            $timeStr .= ' and ' . end($time);
        }
        return $timeStr;
    }

}