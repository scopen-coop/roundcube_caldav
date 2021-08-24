<?php


/**
 * Extraction de l'évenement choisi (dans le cas ou il y en a plusieurs) pour reformer un fichier ics avec lui seul a l'interieur
 * @param $ics
 * @param $uid
 * @return string|null
 */
function extract_event_ics($ics, $uid)
{
    $head_match = array();
    $foot_match = array();
    $array_event = array();

    preg_match("@(.*?)(?=\nBEGIN:VEVENT)@s", $ics, $head_match);
    preg_match("@(?!.*\nEND:VEVENT)END:VEVENT(.*)@s", $ics, $foot_match);
    $header = $head_match[1];
    $footer =$foot_match[1];

    preg_match_all("@(?<=BEGIN:VEVENT)(.*?)(?:END:VEVENT)@s", $ics, $array_event);

    $specific_event = '';
    foreach ($array_event[1] as $event) {
        $uid_match = array();
        preg_match("@^UID:(.*?)[\r|\n]+@m", $event, $uid_match);
        if (strcmp($uid, $uid_match[1]) == 0) {
            $specific_event .= "\nBEGIN:VEVENT" . $event . "END:VEVENT";
        }
    }

    if (strlen($specific_event) > 0) {
        return $header . $specific_event . $footer;
    }

    return null;
}

function cancel_one_instance($ics, $date_start)
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
//        if (preg_match('/RRULE(.*)/', $event) == 1) {
            $recurrence_id = "EXDATE:" . $date_start."\n";
//        $event = preg_replace('/(RRULE.*)/', $recurrence_id, $event);

        $event = preg_replace('/(UID.*)/', $recurrence_id."$1", $event);
            $specific_event .= "\nBEGIN:VEVENT" . $event . "END:VEVENT";

//        }
    }

    return $header . $specific_event .  $footer;
}


/**
 * Change la date de début et de fin d'un evenement
 * @param $new_date_start
 * @param $new_date_end
 * @param $ics
 * @param null $offset_start
 * @param null $offset_end
 * @return string
 */
function change_date_ics($new_date_start, $new_date_end, $ics, $time_zone_offset, $offset_start = null, $offset_end = null)
{
    $head_match = array();
    $foot_match = array();
    $array_event = array();

    preg_match("@(.*?)(?=BEGIN:VEVENT)@s", $ics, $head_match);
    preg_match("@(?!.*\nEND:VEVENT)END:VEVENT\n(.*)@s", $ics, $foot_match);
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
        }else{
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

        $all_events .= 'BEGIN:VEVENT' . $event . "END:VEVENT\n";

    }

    return $header . $all_events . $footer;
}

/**
 * Modifie le parametre 'LOCATION' d'un fichier ics
 * @param $location
 * @param $ics
 * @return string le fichier ics mis a jour
 */
function change_location_ics($location, $ics)
{
    $location = wordwrap($location, 75, "\n", true);


    $sections = preg_split('@(\n(?! ))@m', $ics);
    $has_location_field = false;
    foreach ($sections as &$section) {
        if (preg_match('@^LOCATION:@m', $section) > 0) {
            $section = substr($section, 0, strlen('LOCATION:')) . $location;
            $has_location_field = true;
        }
    }
    $ics = implode("\n", $sections);

    if (!$has_location_field) {
        $ics = preg_replace("@END:VEVENT@", "LOCATION:" . $location . "\nEND:VEVENT", $ics);
    }
    return $ics;

}

/**
 * Modifie le parametre 'STATUS' d'un fichier ics ainsi que la confirmation de sa participation si elle est demandée
 * @param $status
 * @param $ics
 * @return string le fichier ics mis a jour
 */
function change_status_ics($status, $ics)
{
    $pos_status = strpos($ics, 'STATUS:');
    if ($pos_status > 0) {
        $ics = preg_replace('@^(STATUS:).*$@m', '$1' . $status, $ics);
    } else {
        $ics = preg_replace('@(END:VEVENT)@', 'STATUS:' . $status . "\nEND:VEVENT", $ics);
    }
    return $ics;
}


function change_partstat_ics($ics, $status, $email)
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
                $section = implode('', explode("\r\n ", $section));
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
                    if (strcmp($status, 'DECLINED') == 0 && strcmp($command, 'RSVP') == 0) {
                        $is_rsvp_field_present = true;
                        $parts[1] = 'FALSE';
                        $attribute = implode('=', $parts);
                    }
                }
                if (!$is_rsvp_field_present && $status == 'DECLINED') {
                    $attributes[] = ';RSVP=FALSE';
                }

                $section = implode("", str_split(implode('', $attributes), 75));

            }
        }

    }
    return implode("\n", $sections);
}

/**
 * On modifie la section commentaire si le champ a été renseigné
 * @param $ics
 * @param $comment
 * @return string
 */
function update_comment_section_ics($ics, $comment)
{
    $comment = wordwrap($comment, 75, "\n ", true);
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
 * On supprime la section commentaire avant de sauvegarder l'événement
 * @param $ics
 * @return string
 */
function delete_comment_section_ics($ics)
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
 * Modifie la dtae de dernière modification
 * @param $ics
 * @return mixed
 */
function change_last_modified_ics($ics)
{
    $new_date = gmdate("Ymd\THis\Z");

    return preg_replace("@LAST-MODIFIED:.*@", "LAST-MODIFIED:" . $new_date, $ics);
}

/**
 * Incrémente le champs séquence
 * @param $ics
 * @return array|string|string[]|null
 */
function change_sequence_ics($ics)
{

    $num_sequence = array();
    if (preg_match("@SEQUENCE:([0-9]+)@", $ics, $num_sequence) == 1) {
        $num_sequence = intval($num_sequence[1]) + 1;
        $ics = preg_replace("@SEQUENCE:[0-9]+@", "SEQUENCE:" . $num_sequence, $ics);
    } else {
        $ics = preg_replace("@END:VEVENT@", "SEQUENCE:1\nEND:VEVENT", $ics);
    }

    return $ics;

}

/**
 * Parse une durée au format ics en seconde
 * @param $duration
 * @return false|float|int*
 */
function calculate_duration($duration)
{
    $ladder = ['S' => 1, 'M' => 60, 'H' => 3600, 'D' => 86400, 'W' => 604800];
    $match_array = [];
    $res = preg_match('/P([0-9]*W)?([0-9]*D)?T?([0-9]*H)?([0-9]*M)?([0-9]*S)?/', $duration, $match_array);
    if ($res) {
        array_shift($match_array);
        $duration_in_second = 0;
        foreach ($match_array as $match) {
            $scale = [];
            preg_match('/([0-9]*)([A-Z])/', $match, $scale);
            $duration_in_second += intval($scale[1]) * $ladder[$scale[2]];
        }
        return $duration_in_second;
    } else {
        return false;
    }

}


function change_method_ics($ics, $method)
{
    if (preg_match('/^METHOD:.*/m', $ics) == 1) {
        $ics = preg_replace('/^METHOD:.*/m', 'METHOD:' . $method, $ics);
    } else {
        $ics = preg_replace('/BEGIN:VCALENDAR/', "BEGIN:VCALENDAR\nMETHOD:" . $method, $ics);
    }
    return $ics;
}

function del_method_field_ics($ics)
{
    if (preg_match('/^METHOD:.*[\r|\n]*/m', $ics) == 1) {
        $ics = preg_replace('/^METHOD:.*/m', '', $ics);
    }
    return $ics;
}

function str_start_with($string, $startstring)
{
    $len = strlen($startstring);
    return (substr($string, 0, $len) === $startstring);
}

?>