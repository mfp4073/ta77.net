<?php

/**
 * Represent an interval to run report
 * @package Am_Report
 */
class Am_Interval {

    const PERIOD_TODAY = 'today';
    const PERIOD_YESTERDAY = 'yesterday';
    const PERIOD_THIS_WEEK_FROM_SUN = 'this-week-from-sun';
    const PERIOD_THIS_WEEK_FROM_MON = 'this-week-from-mon';
    const PERIOD_LAST_7_DAYS = 'last-7-days';
    const PERIOD_LAST_WEEK_FROM_SUN = 'last-week-from-sun';
    const PERIOD_LAST_WEEK_FROM_MON = 'last-week-from-mon';
    const PERIOD_LAST_WEEK_BUSINESS = 'last-week-business';
    const PERIOD_LAST_14_DAYS = 'last-14-days';
    const PERIOD_THIS_MONTH = 'this-month';
    const PERIOD_LAST_30_DAYS = 'last-30-days';
    const PERIOD_LAST_MONTH = 'last-month';
    const PERIOD_ALL = 'all';

    public function getOptions()
    {
        return array(
            self::PERIOD_TODAY => ___('Today'),
            self::PERIOD_YESTERDAY => ___('Yesterday'),
            self::PERIOD_THIS_WEEK_FROM_SUN => ___('This Week (Sun-Sat)'),
            self::PERIOD_THIS_WEEK_FROM_MON => ___('This Week (Mon-Sun)'),
            self::PERIOD_LAST_7_DAYS => ___('Last 7 Days'),
            self::PERIOD_LAST_WEEK_FROM_SUN => ___('Last Week (Sun-Sat)'),
            self::PERIOD_LAST_WEEK_FROM_MON => ___('Last Week (Mon-Sun)'),
            self::PERIOD_LAST_WEEK_BUSINESS => ___('Last Business Week (Mon-Fri)'),
            self::PERIOD_LAST_14_DAYS => ___('Last 14 Days'),
            self::PERIOD_THIS_MONTH => ___('This Month'),
            self::PERIOD_LAST_30_DAYS => ___('Last 30 Days'),
            self::PERIOD_LAST_MONTH => ___('Last Month'),
            self::PERIOD_ALL => ___('All Time')
        );
    }

    function getTitle($type)
    {
        $options = $this->getOptions();
        return isset($options[$type]) ? $options[$type] : null;
    }

    function getStartStop($type, DateTime $now = null)
    {
        is_null($now) && $now = Am_Di::getInstance()->dateTime;

        $start = $now;
        $stop = clone $now;

        switch ($type) {
            case self::PERIOD_TODAY :
                return array(
                    $start->format('Y-m-d 00:00:00'),
                    $stop->format('Y-m-d 23:59:59'));
            case self::PERIOD_YESTERDAY :
                $start->modify('-1 day');
                $stop->modify('-1 day');
                return array(
                    $start->format('Y-m-d 00:00:00'),
                    $stop->format('Y-m-d 23:59:59'));
            case self::PERIOD_THIS_WEEK_FROM_SUN :
                $w = $start->format('w');
                $start->modify("-$w days");
                $nearestSunday = $start;
                return array(
                    $nearestSunday->format('Y-m-d 00:00:00'),
                    $stop->format('Y-m-d 23:59:59'));
            case self::PERIOD_THIS_WEEK_FROM_MON :
                $w = $start->format('w');
                $day = (7 + $w - 1) % 7;
                $start->modify("-$day days");
                $nearestMonday = $start;
                return array(
                    $nearestMonday->format('Y-m-d 00:00:00'),
                    $stop->format('Y-m-d 23:59:59'));
            case self::PERIOD_LAST_7_DAYS :
                    $start->modify('-7 days');
                return array(
                    $start->format('Y-m-d 00:00:00'),
                    $stop->format('Y-m-d 23:59:59'));
            case self::PERIOD_LAST_WEEK_FROM_SUN :
                $w = $start->format('w');
                $day = (7 + $w - 6) % 7;
                $start->modify("-$day days");
                $saturday = $start;
                $sunday = clone $saturday;
                $sunday->modify('-6 days');
                return array(
                    $sunday->format('Y-m-d 00:00:00'),
                    $saturday->format('Y-m-d 23:59:59'));
            case self::PERIOD_LAST_WEEK_FROM_MON:
                $w = $start->format('w');
                $day = (7 + $w - 0) % 7;
                $start->modify("-$day days");
                $sunday = $start;
                $monday = clone $sunday;
                $monday->modify('-6 days');
                return array(
                    $monday->format('Y-m-d 00:00:00'),
                    $sunday->format('Y-m-d 23:59:59'));
            case self::PERIOD_LAST_WEEK_BUSINESS :
                $w = $start->format('w');
                $day = (7 + $w - 5) % 7;
                $start->modify("-$day days");
                $friday = $start;
                $monday = clone $friday;
                $monday->modify('-4 days');
                return array(
                    $monday->format('Y-m-d 00:00:00'),
                    $friday->format('Y-m-d 23:59:59'));
            case self::PERIOD_LAST_14_DAYS :
                $start->modify('-14 days');
                return array(
                    $start->format('Y-m-d 00:00:00'),
                    $stop->format('Y-m-d 23:59:59'));
            case self::PERIOD_THIS_MONTH :
                return array(
                    $start->format('Y-m-01 00:00:00'),
                    $stop->format('Y-m-d 23:59:59'));
            case self::PERIOD_LAST_30_DAYS :
                $start->modify('-30 days');
                return array(
                    $start->format('Y-m-d 00:00:00'),
                    $stop->format('Y-m-d 23:59:59'));
            case self::PERIOD_LAST_MONTH :
                $start->modify('last month');
                $stop->modify('last month');
                return array(
                    $start->format('Y-m-01 00:00:00'),
                    $stop->format('Y-m-t 23:59:59'));
            case self::PERIOD_ALL :
                return array(
                    date('Y-m-d 00:00:00', 0),
                    $stop->format('Y-m-d 23:59:59'));
            default:
                throw new Am_Exception_InputError(sprintf('Unknown period type [%s]', $type));
        }
    }
}
