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

    preg_match("@(.*?)(?=BEGIN:VEVENT)@s", $ics, $head_match);
    preg_match("@(?!.*\nEND:VEVENT)END:VEVENT(.*)@s", $ics, $foot_match);
    $header = $head_match[1];
    $footer = $foot_match[1];

    preg_match_all("@(?<=BEGIN:VEVENT)(.*?)(?:END:VEVENT)@s", $ics, $array_event);

    $specific_event = '';
    foreach ($array_event[1] as $event) {
        $uid_match = array();
        preg_match("@^UID:(.*?)[\r|\n]+@m", $event, $uid_match);
        if (strcmp($uid, $uid_match[1]) == 0) {
            $specific_event .= 'BEGIN:VEVENT' . $event . "END:VEVENT";
        }
    }

    if (strlen($specific_event) > 0) {
        return $header . $specific_event . $footer;
    }
    return null;
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
    preg_match("@(.*?)(?=BEGIN:VEVENT)(.*)@s", $ics, $head_match);
    $header = $head_match[1];
    $body = $head_match[2];

    // date start
    if (is_null($offset_start) && is_null($offset_end)) {
        $body = preg_replace('@DTSTART.*:([0-9A-Z]+)@m', 'DTSTART:' . $new_date_start, $body);
        $body = preg_replace('@DTEND.*:([0-9A-Z]+)@m', 'DTEND:' . $new_date_end, $body);

        $ics = $header . $body;

    } else {
        $foot_match = array();
        preg_match("@(.*?)(?=END:VEVENT)(.*)@s", $body, $foot_match);
        $begin_events = $foot_match[1];
        $end = $foot_match[2];


        $begin_events = preg_replace('@DTSTART.*:([0-9A-Z]+)@m', 'DTSTART:' . $new_date_start, $begin_events);
        $begin_events = preg_replace('@DTEND.*:([0-9A-Z]+)@m', 'DTEND:' . $new_date_end, $begin_events);


        $array_dtstart = array();
        $array_dtend = array();
        preg_match('@DTSTART.*:([0-9A-Z]+)@m', $end, $array_dtstart);
        preg_match('@DTEND.*:([0-9A-Z]+)@m', $end, $array_dtend);


        $new_date_start_second_event = date("Ymd\THis", strtotime($array_dtstart[1]) + $offset_start - $time_zone_offset);
        $new_date_end_second_event = date("Ymd\THis", strtotime($array_dtend[1]) + $offset_end - $time_zone_offset);

        $end = preg_replace('@DTSTART.*:([0-9A-Z]+)@m', 'DTSTART:' . $new_date_start_second_event, $end);
        $end = preg_replace('@DTEND.*:([0-9A-Z]+)@m', 'DTEND:' . $new_date_end_second_event, $end);

        $ics = $header . $begin_events . $end;
    }
    return $ics;
}

/**
 * Modifie le parametre 'LOCATION' d'un fichier ics
 * @param $location
 * @param $ics
 * @return string le fichier ics mis a jour
 */
function change_location_ics($location, $ics)
{
    $splited_location = str_split($location, 66);
    $sections = preg_split('@(\n(?! ))@m', $ics);
    $is_location_present = false;
    foreach ($sections as &$section) {
        if (preg_match('@^LOCATION:@m', $section) > 0) {
            $section = substr($section, 0, 9) . implode($splited_location);
            $is_location_present = true;
        }
    }
    $ics = implode("\n", $sections);

    if (!$is_location_present) {
        $ics = preg_replace("@END:VEVENT@", "LOCATION:" . implode($splited_location) . "\nEND:VEVENT", $ics);
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


function change_partstat_ics($ics,$status,$email){

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
                $attributes = preg_split('/([;|:])/', $section,-1,PREG_SPLIT_DELIM_CAPTURE);

                $is_rsvp_field_present = false;
                foreach ($attributes as &$attribute) {
                    if($attribute == ';' || $attributes == ':'){
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
                if(!$is_rsvp_field_present && $status == 'DECLINED'){
                    $attributes[] = ';RSVP=FALSE';
                }

                $section = implode("", str_split(implode('', $attributes), 75));

            }
        }

    }
    return implode("\n", $sections);
}

/**
 * Supprime le champs status du fichier ics que l'on veut envoyer aux autres participants
 * @param $ics
 * @return string|null
 */
function delete_status_section_for_sending($ics)
{
    return preg_replace('/STATUS:.*[\r\n]+/', '', $ics);
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