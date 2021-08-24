<?php

/**
 * On retrouve quelle identité correspond à celle utilisé dans l'événement
 * @param $event
 * @param $my_identities
 * @return array|null : [ email , name , role ]
 */
function find_identity_matching_with_attendee_or_organizer($event,$my_identities)
{
    if (!empty($event->attendee_array) || !empty($event->organizer_array)) {
        // On boucle sur les attendee pour recuperer la bonne identity
        foreach ($event->attendee_array as $attendes) {
            if (is_string($attendes)) {
                $attendes = preg_replace('/(mailto:)?(.+)/', '$2', $attendes);
                foreach ($my_identities as $identity) {
                    if (strcmp($attendes, $identity['email']) == 0) {
                        $my_identity['email'] = $identity['email'];
                        $my_identity['name'] = $identity['name'];
                        $my_identity['role'] = 'ATTENDEE';
                        return $my_identity;
                    }
                }
            }
        }
        foreach ($event->organizer_array as $organizer) {
            if (is_string($organizer)) {
                $organizer_email = preg_replace('/(mailto:)?(.+)/', '$2', $organizer);
                foreach ($my_identities as $identity) {
                    if (strcmp($organizer_email, $identity['email']) == 0) {
                        $my_identity['email'] = $identity['email'];
                        $my_identity['name'] = $identity['name'];
                        $my_identity['role'] = 'ORGANIZER';
                        return $my_identity;
                    }
                }
            }
        }
    }
    return null;
}


/**
 * on teste si la date de début et la date de fin sont identiques
 * @param $event
 * @return bool
 */
function find_if_same_date($event)
{

    $dtstart = [];
    $dtend = [];
    preg_match('/^[0-9]+/m', $event->dtstart_array[1], $dtstart);
    preg_match('/^[0-9]+/m', $event->dtend_array[1], $dtend);

    return strcmp($dtstart[0], $dtend[0]) == 0;

}

/**
 * On cherche si il y a des nouveaux particiapant qui ont été rajouté
 * @param $array_attendee_new
 * @param $array_attendee_old
 * @return array*
 */
function find_difference_attendee($array_attendee_new, $array_attendee_old)
{
    $new_attendees = [];
    foreach ($array_attendee_new as $new_attendee) {
        $is_different = true;
        foreach ($array_attendee_old as $old_attendee) {
            if (strcmp($new_attendee['email'], $old_attendee['email']) == 0) {
                $is_different = false;
            }
        }
        if ($is_different) {

            $new_attendees[] = $new_attendee;
        }
    }
    return $new_attendees;
}

/**
 * récupération des événements possédant le même uid sur le serveur si il existe
 * @param $event
 * @param $calendar
 * @return mixed
 */
function find_event_with_matching_uid($event, $calendar, $all_events)
{
    $all_event_from_cal = (array)$all_events[$calendar];

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
 * Recherche d'un chevauchement entre les deux intervals de temps, true en cas de chevauchement
 * @param $current_date_start
 * @param $current_date_end
 * @param $date_start
 * @param $date_end
 * @param $base_timestamp
 * @return bool
 */
function is_there_an_overlap($current_date_start, $current_date_end, $date_start, $date_end, $base_timestamp)
{
    return (((strtotime($date_start, $base_timestamp) >= strtotime($current_date_start, $base_timestamp))
            && (strtotime($date_start, $base_timestamp) < strtotime($current_date_end, $base_timestamp))) ||
        ((strtotime($date_end, $base_timestamp) >= strtotime($current_date_start, $base_timestamp))
            && (strtotime($date_end, $base_timestamp) < strtotime($current_date_end, $base_timestamp))));
}

/**
 * On regarde si l'évenement observé est après l'evt courant
 * @param $current_date_end
 * @param $current_date_end_with_offset
 * @param $date_start
 * @param $base_timestamp
 * @return bool
 */
function is_after($current_date_end, $current_date_end_with_offset, $date_start, $base_timestamp)
{
    return strtotime($date_start, $base_timestamp) >= strtotime($current_date_end, $base_timestamp)
        && (strtotime($date_start, $base_timestamp) < strtotime($current_date_end_with_offset, $base_timestamp));
}

/**
 * On regarde si l'évenement observé est avant l'evt courant
 * @param $current_date_end
 * @param $current_date_end_with_offset
 * @param $date_start
 * @param $base_timestamp
 * @return bool
 */
function is_before($current_date_start, $current_date_start_with_offset, $date_end, $base_timestamp)
{
    return strtotime($date_end, $base_timestamp) <= strtotime($current_date_start, $base_timestamp)
        && (strtotime($date_end, $base_timestamp) > strtotime($current_date_start_with_offset, $base_timestamp));
}

/**
 * Decide à partir d'un tableau contenant des dates lequels de ces événements commence
 * le plus tard juste avant ou le plus tot juste après
 * @param $array_date
 * @param $date_start_or_end
 * @param $opt 'next' pour avoir le meeting suivant ; 'previous' pour avoir le meeting précédant
 * @return mixed
 *
 */
function choose_the_closest_meeting($array_date, $date_start_or_end, $opt)
{
    $first = array_key_first($array_date);
    $uid = $array_date[$first]['uid'];
    if (strcmp($opt, 'previous') == 0) {
        foreach ($array_date as $date) {
            if (strtotime($array_date[$uid]['date_start']) <= strtotime($date['date_start'])
                && strtotime($date_start_or_end) >= strtotime($date['date_end'])) {
                $uid = $date['uid'];
            }
        }
    } else {
        foreach ($array_date as $date) {
            if (strtotime($array_date[$uid]['date_start']) > strtotime($date['date_start'])
                && strtotime($date_start_or_end) <= strtotime($date['date_start'])) {
                $uid = $date['uid'];
            } elseif (strtotime($array_date[$uid]['date_start']) == strtotime($date['date_start'])
                && strtotime($array_date[$uid]['date_end']) >= strtotime($date['date_end'])
                && strtotime($date_start_or_end) <= strtotime($date['date_start'])) {
                $uid = $date['uid'];
            }
        }
    }
    return $uid;
}

?>