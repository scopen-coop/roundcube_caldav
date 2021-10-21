<?php


/**
 * Extraction of the chosen event (in case there are several) to reform an ics file with only it inside
 * @param string $ics
 * @param string $uid
 * @return string
 */
function extract_event_ics(string $ics, string $uid): string
{
    $head_match = array();
    $foot_match = array();
    $array_event = array();

    preg_match("@(.*?)(?=\nBEGIN:VEVENT)@s", $ics, $head_match);
    preg_match("@(?!.*\nEND:VEVENT)END:VEVENT(.*)@s", $ics, $foot_match);
    $header = $head_match[1];
    $footer = $foot_match[1];

    preg_match_all("@(?<=BEGIN:VEVENT)(.*?)(?:END:VEVENT)@s", $ics, $array_event);

    $specific_event = '';
    foreach ($array_event[1] as $event) {
        $uid_match = array();
        preg_match("@^UID:(.*?)[\r|\n]+@m", $event, $uid_match);
        if (strcmp($uid, $uid_match[1]) == 0) {
            $specific_event .= "\r\nBEGIN:VEVENT" . $event . "END:VEVENT";
        }
    }

    if (strlen($specific_event) > 0) {
        return $header . $specific_event . $footer;
    }

    return $ics;
}


/**
 * Cancel one instance of this event, adding the field EXDATE:'$date_start' to VEVENT object
 * @param string $ics
 * @param string $date_start
 * @return string
 */
function cancel_one_instance(string $ics, string $date_start): string
{
    $head_match = array();
    $foot_match = array();
    $array_event = array();

    preg_match("@(.*?)(?=\nBEGIN:VEVENT)@s", $ics, $head_match);
    preg_match("@(?!.*\nEND:VEVENT)END:VEVENT(.*)@s", $ics, $foot_match);
    $header = $head_match[1];
    $footer = $foot_match[1];
    preg_match_all("@(?<=BEGIN:VEVENT)(.*?)(?:END:VEVENT)@s", $ics, $array_event);

    $specific_event = '';
    foreach ($array_event[1] as $event) {
        $recurrence_id = "EXDATE:" . $date_start . "\r\n";
        $event = preg_replace('/(UID.*)/', $recurrence_id . "$1", $event);
        $specific_event .= "\r\nBEGIN:VEVENT" . $event . "END:VEVENT";
    }

    return $header . $specific_event . $footer;
}


/**
 * Change the start and end date fields of a VEVENT
 * @param string $new_date_start
 * @param string $new_date_end
 * @param string $ics
 * @param string $time_zone_offset
 * @param int|null $offset_start : In case of recurring event, this is the offset between current_date_start and new_date_start
 *      in order to add the difference to all instance and so reschedule all instance.
 * @param int|null $offset_end : same idea with current_dte_end and new_date_end
 * @return string
 */
function change_date_ics(string $new_date_start, string $new_date_end, string $ics, string $time_zone_offset, int $offset_start = null, int $offset_end = null): string
{
    $head_match = array();
    $foot_match = array();
    $array_event = array();

    preg_match("@(.*?)(?=BEGIN:VEVENT)@s", $ics, $head_match);
    preg_match("@(?!.*\nEND:VEVENT)END:VEVENT(.*)@s", $ics, $foot_match);
    $header = $head_match[1];
    $footer = $foot_match[1];


    preg_match_all("@(?<=BEGIN:VEVENT)(.*?)(?:END:VEVENT)@s", $ics, $array_event);

    // date start
    $all_events = '';
    foreach ($array_event[1] as $i => $event) {
        if ($i == 0) {

            if (preg_match('@DTEND.*:([0-9A-Z]+)@m', $event) == 1) {
                $event = preg_replace('@DTEND.*:([0-9A-Z]+)@m', 'DTEND:' . $new_date_end, $event);
            } elseif (preg_match('@DURATION:([0-9A-Z]+)@m', $event) == 1) {
                $event = preg_replace('@DURATION:([0-9A-Z]+)@m', 'DTEND:' . $new_date_end, $event);
            } else {
                $event = preg_replace('@(DTSTART.*:[0-9A-Z]+)@m', "$1\nDTEND:" . $new_date_end, $event);
            }
            $event = preg_replace('@DTSTART.*:([0-9A-Z]+)@m', 'DTSTART:' . $new_date_start, $event);
        } else {
            $array_dtstart = array();
            $array_dtend = array();
            preg_match('@DTSTART.*:([0-9A-Z]+)@m', $event, $array_dtstart);
            preg_match('@DTEND.*:([0-9A-Z]+)@m', $event, $array_dtend);


            $new_date_start_second_event = date("Ymd\THis", strtotime($array_dtstart[1]) + $offset_start - $time_zone_offset);
            $new_date_end_second_event = date("Ymd\THis", strtotime($array_dtend[1]) + $offset_end - $time_zone_offset);

            $event = preg_replace('@DTSTART.*:([0-9A-Z]+)@m', 'DTSTART:' . $new_date_start_second_event, $event);
            $event = preg_replace('@DTEND.*:([0-9A-Z]+)@m', 'DTEND:' . $new_date_end_second_event, $event);

            $new_date_start = date("Ymd\THis", strtotime($array_dtstart[1]) + $offset_start - $time_zone_offset);
            $new_date_end = date("Ymd\THis", strtotime($array_dtend[1]) + $offset_end - $time_zone_offset);
        }

        $all_events .= 'BEGIN:VEVENT' . $event . "END:VEVENT\r\n";

    }
    return $header . $all_events . $footer;
}

/**
 * Change the location field of a VEVENT
 * @param string $location
 * @param string $ics
 * @return string
 */
function change_location_ics(string $location, string $ics): string
{
    $location = wordwrap($location, 73, "\r\n ", true);


    $sections = preg_split('@(\n(?! ))@m', $ics);
    $has_location_field = false;
    foreach ($sections as &$section) {
        if (preg_match('@^LOCATION:@m', $section) > 0) {
            $section = substr($section, 0, strlen('LOCATION:')) . $location . "\r";
            $has_location_field = true;
        }
    }
    $ics = implode("\n", $sections);

    if (!$has_location_field) {
        $ics = preg_replace("@END:VEVENT@", "LOCATION:" . $location . "\r\nEND:VEVENT", $ics);
    }
    return $ics;

}

/**
 * Change the status field of a VEVENT
 * @param string $status
 * @param string $ics
 * @return string
 */
function change_status_ics(string $status, string $ics): string
{
    $pos_status = strpos($ics, 'STATUS:');
    if ($pos_status > 0) {
        $ics = preg_replace('@^(STATUS:).*$@m', '$1' . $status, $ics);
    } else {
        $ics = preg_replace('@(END:VEVENT)@', 'STATUS:' . $status . "\r\nEND:VEVENT", $ics);
    }
    return $ics;
}

/**
 * Change the PARTSTAT (participation status) field of an attendee in a VEVENT
 * @param string $ics
 * @param string $status
 * @param string $email : email of participant we want to change status
 * @return string
 */
function change_partstat_ics(string $ics, string $status, string $email): string
{

    $sections = preg_split('@(\n(?! ))@m', $ics);

    if (strcmp($status, 'CANCELLED') == 0) {
        $status = 'DECLINED';
    } elseif (strcmp("CONFIRMED", $status) == 0) {
        $status = 'ACCEPTED';
    }
    foreach ($sections as &$section) {

        if (preg_match('@ATTENDEE@', $section) == 1) {
            if (preg_match('/' . $email . '/', $section) == 1) {
                $section = preg_replace('/[\r|\n]+ /', '', $section);

                $attributes = preg_split('/([;|:])/', $section, -1, PREG_SPLIT_DELIM_CAPTURE);

                $is_rsvp_field_present = false;
                foreach ($attributes as &$attribute) {
                    if ($attribute == ';' || $attributes == ':') {
                        continue;
                    }
                    $parts = explode('=', $attribute);
                    $command = $parts[0];
                    if (strcmp($command, 'PARTSTAT') == 0) {
                        $parts[1] = $status;
                        $attribute = implode('=', $parts);
                    }
                    if ($command === 'RSVP') {
                        if ($status === 'DECLINED') {
                            $parts[1] = 'FALSE';
                        } else {
                            $parts[1] = 'TRUE';
                        }
                        $is_rsvp_field_present = true;
                        $attribute = implode('=', $parts);
                    }

                }
                $section = implode("\r\n ", str_split(implode('', $attributes), 74));
                if (!$is_rsvp_field_present && $status == 'DECLINED') {
                    $section = preg_replace('@mailto:@', 'RSVP=FALSE;mailto:', $section);
                }
            }
        }

    }
    return implode("\n", $sections);
}

/**
 * Change the COMMENT field of an icalendar string
 * @param string $ics
 * @param string $comment
 * @return string
 */
function update_comment_section_ics(string $ics, string $comment): string
{
    $comment = preg_replace("/\n/", '\n', $comment);
    $comment_start = substr($comment, 0, 73 - strlen('COMMENT:'));
    $comment = substr($comment, 73 - strlen('COMMENT:'));
    $comment = wordwrap($comment, 73, " \r\n ", true);
    $comment = $comment_start . " \r\n " . $comment . "\r";

    $sections = preg_split('@(\n(?! ))@m', $ics);
    $has_comment_section = false;
    foreach ($sections as &$section) {
        if (preg_match('@^COMMENT:@m', $section) > 0) {
            $section = substr($section, 0, strlen('COMMENT:')) . $comment;
            $has_comment_section = true;
        }
    }
    $ics = implode("\n", $sections);

    if (!$has_comment_section) {
        $ics = preg_replace("@END:VEVENT@", "COMMENT:" . $comment . "\nEND:VEVENT", $ics);
    }

    return $ics;
}

/**
 * Delete the comment field  of an icalendar string
 * @param string $ics
 * @return string
 */
function delete_comment_section_ics(string $ics): string
{
    $sections = preg_split('@(\n(?! ))@m', $ics);
    foreach ($sections as $key => &$section) {
        if (preg_match('@^COMMENT:@m', $section) > 0) {
            unset($sections[$key]);
        }
    }
    return implode("\n", $sections);
}


/**
 * Change LAST_MODIFIED field  of a VEVENT
 * @param string $ics
 * @return string
 */
function change_last_modified_ics(string $ics): string
{
    $new_date = gmdate("Ymd\THis\Z");

    return preg_replace("@LAST-MODIFIED:.*@", "LAST-MODIFIED:" . $new_date, $ics);
}

/**
 * Increment SEQUENCE field of a VEVENT by one
 * @param string $ics
 * @return string
 */
function change_sequence_ics(string $ics): string
{

    $num_sequence = array();
    if (preg_match("@SEQUENCE:([0-9]+)@", $ics, $num_sequence) == 1) {
        $num_sequence = intval($num_sequence[1]) + 1;
        $ics = preg_replace("@SEQUENCE:[0-9]+@", "SEQUENCE:" . $num_sequence, $ics);
    } else {
        $ics = preg_replace("@END:VEVENT@", "SEQUENCE:1\r\nEND:VEVENT", $ics);
    }

    return $ics;

}


/**
 * Change the METHOD field of a VCALENDAR
 * @param string $ics
 * @param string $method
 * @return string
 */
function change_method_ics(string $ics, string $method): string
{
    if (preg_match('/^METHOD:.*/m', $ics) == 1) {
        $ics = preg_replace('/^METHOD:.*/m', 'METHOD:' . $method, $ics);
    } else {
        $ics = preg_replace('/BEGIN:VCALENDAR/', "BEGIN:VCALENDAR\r\nMETHOD:" . $method, $ics);
    }
    return $ics;
}

/**
 * Delete the METHOD field of a VCALENDAR
 * @param $ics
 * @return string
 */
function del_method_field_ics($ics): string
{
    if (preg_match('/^METHOD:.*[\r|\n]*/m', $ics) == 1) {
        $ics = preg_replace('/^METHOD:.*/m', '', $ics);
    }
    return $ics;
}

/**
 * Find the time zone of the ICalendar file if it exist, else get server time zone
 * @param $ics
 * @return false|int
 */
function find_time_zone($ics)
{
    $vtimezone = [];

    if (preg_match("@(?<=BEGIN:VTIMEZONE)(.*?)(?:END:VTIMEZONE)@s", $ics, $vtimezone) == 1) {
        $tzid = [];

        preg_match('/^TZID:(.*)?$/m', $vtimezone[1], $tzid);

        $timezone = $tzid[1];
        $timezoneLength = strlen($timezone);

        if ("\r" === $timezone[$timezoneLength - 1]) {
            $timezone = substr($timezone, 0, $timezoneLength - 1);
        }

        $timezone = new \DateTimeZone($timezone);
    } else {
        $date = new DateTime();
        $timezone = $date->getTimezone();
    }

    return $timezone->getOffset(new DateTime());
}

/**
 * Change the participation status to NEED_ACTIONS for all attendees
 * @param $ics
 * @return string
 */
function change_partstat_of_all_attendees_to_need_action($ics): string
{

    $sections = preg_split('@(\n(?! ))@m', $ics);

    foreach ($sections as &$section) {

        if (preg_match('@ATTENDEE@', $section) == 1) {

            $section = preg_replace("/[\r|\n]+ /", '', $section);
            $attributes = preg_split('/([;|:])/', $section, -1, PREG_SPLIT_DELIM_CAPTURE);

            foreach ($attributes as &$attribute) {
                if ($attribute == ';' || $attributes == ':') {
                    continue;
                }
                $parts = explode('=', $attribute);
                $command = $parts[0];
                if (strcmp($command, 'PARTSTAT') == 0) {
                    $parts[1] = 'NEEDS_ACTION';
                    $attribute = implode('=', $parts);
                } elseif (strcmp($command, 'RSVP') == 0) {
                    $parts[1] = 'TRUE';
                    $attribute = implode('=', $parts);
                }

            }

            $section = implode("\r\n ", str_split(implode('', $attributes), 74));

        }
    }


    return implode("\n", $sections);
}

/**
 * Keep the participation status of all participant in case of update
 * @param $ics
 * @param $new_ics
 * @return string
 */
function keep_partstat_of_other_participants_ics($ics, $new_ics): string
{
    $sections_old_ics = preg_split('@(\n(?! ))@m', $ics);
    $section_to_keep = [];
    foreach ($sections_old_ics as &$section) {
        if (preg_match('@ATTENDEE@', $section) == 1) {
            $section_to_keep[] = $section;
        }
    }
    $section_to_keep = implode("\r\n", $section_to_keep);

    $sections_new_ics = preg_split('@(\n(?! ))@m', $new_ics);
    $section_to_change = [];
    foreach ($sections_new_ics as &$section) {
        if (preg_match('@ATTENDEE@', $section) == 1) {
            $section_to_change[] = $section;
        }
    }
    $section_to_change = implode("\n", $section_to_change);

    $new_ics = implode("\n", $sections_new_ics);


    return preg_replace($section_to_change, $section_to_keep, $new_ics);
}

?>