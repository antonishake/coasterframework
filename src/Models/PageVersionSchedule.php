<?php namespace CoasterCms\Models;

use Carbon\Carbon;
use DateTimeHelper;
use Eloquent;

class PageVersionSchedule extends Eloquent
{
    protected $table = 'page_versions_schedule';

    private $_now;
    private static $_latestPublish;

    private static $_repeatOptions = [
        0 => 'No',
        86400 => 'Repeat Daily',
        604800 => 'Repeat Weekly',
        'm' => 'Same day of Month'
    ];

    public static function checkPageVersionIds()
    {
        $now = (new Carbon)->format('Y-m-d H:i:s');
        $pageVersionSchedules = self::where('live_from', '<=', $now)->orderBy('live_from')->get();

        $newVersions = [];

        if (!$pageVersionSchedules->isEmpty()) {

            $pageVersionSchedulesArr = [];
            foreach ($pageVersionSchedules as $pageVersionSchedule) {
                $pageVersionSchedulesArr[$pageVersionSchedule->page_version_id] = $pageVersionSchedule;
            }

            $pageVersionIdToPageVersion = [];
            $pageVersions = PageVersion::whereIn('id', array_keys($pageVersionSchedulesArr))->get();
            foreach ($pageVersions as $pageVersion) {
                $pageVersionIdToPageVersion[$pageVersion->id] = $pageVersion;
            }

            $pages = [];
            foreach ($pageVersionSchedulesArr as $pageVersionId => $pageVersionSchedule) {
                if (!isset($pageVersionIdToPageVersion[$pageVersionId])) {
                    continue;
                }
                if (!isset($pages[$pageVersionIdToPageVersion[$pageVersionId]->page_id])) {
                    $pages[$pageVersionIdToPageVersion[$pageVersionId]->page_id] = [];
                }
                $pages[$pageVersionIdToPageVersion[$pageVersionId]->page_id][$pageVersionId] = $pageVersionSchedule;
            }

            foreach ($pages as $page_id => $pageVersionSchedules) {
                self::$_latestPublish = ['time' => '', 'page_version_id' => ''];
                foreach ($pageVersionSchedules as $pageVersionSchedule) {
                    $pageVersionSchedule->delete();
                }
                if (self::$_latestPublish['page_version_id']) {
                    $pageVersionToPublish = $pageVersionIdToPageVersion[self::$_latestPublish['page_version_id']];
                    $pageVersionToPublish->publish(false, true);
                    $newVersions[$page_id] = $pageVersionToPublish->version_id;
                }
            }

        }

        return $newVersions;
    }

    public function delete()
    {
        $this->_now = new Carbon;
        $newDate = new Carbon($this->live_from);
        $this->repeat($newDate);

        if ($this->live_from != $this->getOriginal('live_from')) {
            $newPageVersionSchedule = $this->replicate();
            $newPageVersionSchedule->live_from = $this->live_from;
            $newPageVersionSchedule->save();
        }

        return parent::delete();
    }

    private function repeat($newDate)
    {
        if ($this->_now >= $newDate && (empty(self::$_latestPublish['time']) || self::$_latestPublish['time'] < $newDate )) {
            self::$_latestPublish['time'] = clone $newDate;
            self::$_latestPublish['page_version_id'] = $this->page_version_id;
        }

        if ($this->repeat_in) {
            $newDate->modify('+'.$this->repeat_in.' seconds');
        } elseif ($this->repeat_in_func) {
            switch ($this->repeat_in_func) {
                case 'm':
                    $currentDay = '0';
                    $newDay = (int) $newDate->format('d');
                    $newDateClone = clone $newDate;
                    $i = 1;
                    while ($currentDay != $newDay) {
                        $newDateClone = clone $newDate;
                        $currentDay = (int) $newDateClone->format('d');
                        $newDateClone->modify('+'.$i++.' month');
                        $newDay = (int) $newDateClone->format('d');
                    }
                    $newDate = clone $newDateClone;
                break;
                default:
                    $this->repeat_in_func = '';
            }
        }

        if (($this->repeat_in || $this->repeat_in_func) && $this->_now > $newDate) {
            $this->repeat($newDate);
        } else {
            $this->live_from = $newDate->format('Y-m-d H:i:s');
        }
    }

    public static function selectOptions()
    {
        return self::$_repeatOptions;
    }

    public function repeat_text()
    {
        $repeaterText = '';
        if ($this->repeat_in) {
            $repeatOption = $this->repeat_in;
        } elseif($this->repeat_in_func) {
            $repeatOption = $this->repeat_in_func;
        }
        if (isset($repeatOption)) {
            if (!empty(self::$_repeatOptions[$repeatOption])) {
                $repeaterText = self::$_repeatOptions[$repeatOption];
            } elseif ($this->repeat_in) {
                $repeaterText = 'Repeat every ' . DateTimeHelper::displaySeconds($this->repeat_in);
            } else {
                $repeaterText = 'Unknown repeat function';
            }
        }
        if ($repeaterText) {
            return '(' . $repeaterText . ')';
        } else {
            return null;
        }
    }

    public static function restore($obj)
    {
        $obj->save();
    }

}