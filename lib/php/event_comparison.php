<?php

/**
 *
 * Rouncube calDAV handling plugin
 *
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 */
use ICal\Event;
use it\thecsea\simple_caldav_client\CalDAVObject;

/**
 * Find an event with matching uid
 * @param Event $event
 * @param string $calendar
 * @param array $all_events
 * @return CalDAVObject|null
 */
function find_event_with_matching_uid(Event $event, string $calendar, array $all_events): ?CalDAVObject
{
    $all_event_from_cal = (array) $all_events[$calendar];

    foreach ($all_event_from_cal as $event_found) {
        $uid = array();
        preg_match("/UID:(\S+)/", $event_found->getData(), $uid);

        if ($uid[1] == $event->uid) {
            return $event_found;
        }
    }
    
    return null;
}

/**
 * Search for an overlap between the two time intervals, true in case of overlap.
 * @param int $base_timestamp
 * @param $current_event
 * @param $event_found
 * @return bool
 */
function is_there_an_overlap(int $base_timestamp, $current_event, $event_found): bool
{
    if (
        empty($current_event->dtstart_array[1]) 
        || empty($current_event->dtend_array[1])
        || empty($event_found->dtstart_array[1])
    ) {
        return false;
    }

    $current_date_start = $current_event->dtstart_array[1];
    $current_date_end = $current_event->dtend_array[1];
    $date_start_to_compare_with = $event_found->dtstart_array[1];

    if (empty($event_found->dtend_array) || empty($event_found->dtend_array[1])) {
        $date_end_to_compare_with = date(
                "Ymd\THis\Z", strtotime($date_start_to_compare_with, $base_timestamp) + 86400);
    } else {
        $date_end_to_compare_with = $event_found->dtend_array[1];
    }


    return 
        (
            strtotime($date_start_to_compare_with, $base_timestamp) > strtotime($current_date_start,$base_timestamp)
            && strtotime($date_start_to_compare_with, $base_timestamp) < strtotime($current_date_end,$base_timestamp)
        ) || (
            strtotime($date_end_to_compare_with, $base_timestamp) > strtotime($current_date_start,$base_timestamp)
            && strtotime($date_end_to_compare_with, $base_timestamp) < strtotime($current_date_end,$base_timestamp)
        );
}

/**
 * Find if the event to determine is after the current event.
 * @param string $current_date_end
 * @param string $current_date_end_with_offset
 * @param string $date_start_to_compare_with
 * @param int $base_timestamp
 * @return bool
 */
function is_after(string $current_date_end, string $current_date_end_with_offset, string $date_start_to_compare_with, int $base_timestamp): bool
{
    return 
        strtotime($date_start_to_compare_with, $base_timestamp) >= strtotime($current_date_end, $base_timestamp)
        && strtotime($date_start_to_compare_with, $base_timestamp) < strtotime($current_date_end_with_offset, $base_timestamp);
}

/**
 * Find if the event to determine is before the current event.
 * @param string $current_date_start
 * @param string $current_date_start_with_offset
 * @param string|null $date_end_to_compare_with
 * @param int $base_timestamp
 * @return bool
 */
function is_before(string $current_date_start, string $current_date_start_with_offset, ?string $date_end_to_compare_with, int $base_timestamp): bool
{
    if (!$date_end_to_compare_with) {
        return false;
    }
    
    return 
        strtotime($date_end_to_compare_with, $base_timestamp) <= strtotime($current_date_start, $base_timestamp)
        && strtotime($date_end_to_compare_with, $base_timestamp) > strtotime($current_date_start_with_offset, $base_timestamp);
}

/**
 * Find in a date array which event is the closest of a date.
 * @param array $array_date : indexed by the uid of events.
 * @param string $date_start_or_end : if 'previous' : the dtstart of event, else the dtend.
 * @param string $opt : if 'previous' it find the closest previous event, else the closest next event.
 * @return string|null
 */
function choose_the_closest_meeting(array $array_date, string $date_start_or_end, string $opt): ?string
{
    if (empty($array_date)) {
        return null;
    }

    $first = array_key_first($array_date);
    $uid = $array_date[$first]['uid'];

    if (strcmp($opt, 'previous') == 0) {
        foreach ($array_date as $date) {
            if (
                strtotime($array_date[$uid]['date_start']) <= strtotime($date['date_start']) 
                && strtotime($date_start_or_end) >= strtotime($date['date_end'])
            ) {
                $uid = $date['uid'];
            }
        }
    } else {
        foreach ($array_date as $date) {
            if (
                strtotime($array_date[$uid]['date_start']) > strtotime($date['date_start']) 
                && strtotime($date_start_or_end) <= strtotime($date['date_start'])
            ) {
                $uid = $date['uid'];
            } elseif (
                strtotime($array_date[$uid]['date_start']) == strtotime($date['date_start']) 
                && strtotime($array_date[$uid]['date_end']) >= strtotime($date['date_end'])
                && strtotime($date_start_or_end) <= strtotime($date['date_start'])
            ) {
                $uid = $date['uid'];
            }
        }
    }
    
    return $uid;
}

/**
 * Parse the duration field to get the duration in second of an event
 * @param string $duration
 * @return false|int
 */
function calculate_duration(string $duration)
{
    $ladder = ['S' => 1, 'M' => 60, 'H' => 3600, 'D' => 86400, 'W' => 604800];
    $match_array = [];
    
    $res = preg_match(
            '/P([0-9]*W)?([0-9]*D)?T?([0-9]*H)?([0-9]*M)?([0-9]*S)?/',
            $duration,
            $match_array
    );
    
    if (!$res) {
        return false;
    }

    array_shift($match_array);
    $duration_in_second = 0;

    if (!empty($match_array)) {
        foreach ($match_array as $match) {
            $scale = [];
            preg_match('/([0-9]*)([A-Z])/', $match, $scale);

            if (!empty($scale)) {
                $duration_in_second += intval($scale[1]) * $ladder[$scale[2]];
            }
        }
    }

    return $duration_in_second;
}

/**
 * Check if a string begin with a substring
 * @param string $string
 * @param string $start_string
 * @return bool
 */
function str_start_with(string $string, string $start_string): bool
{
    $len = strlen($start_string);
    return (substr($string, 0, $len) === $start_string);
}

?>