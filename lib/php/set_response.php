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
use ICal\ICal;

/**
 * Fill the response array with attendees informations
 * ['attendees'][] = [name,
 * bool:RSVP,
 * partstat: Participation status,
 * ROLE: attendee/organizer,
 * email,
 * onclick: action to add for the participant button]
 *
 * And Fill the action to add on reply_all button
 * $response['attr_reply_all'] = [onclick]
 * @param Event $event
 * @param array $response
 */
function set_participants_characteristics_and_set_buttons_properties(Event $event, array &$response)
{   
    if (empty($response['identity']) || empty($response['identity']['email'])) {
        return;
    }
    
    $my_email = $response['identity']['email'];

    $id = 0;
    $all_adresses = '';
    
    if (!empty($event->attendee_array)) {
        foreach ($event->attendee_array as $attendee) {
            if (
                !is_string($attendee) 
                && (
                    array_key_exists('PARTSTAT', $attendee) 
                    || array_key_exists('CN',$attendee) 
                    || array_key_exists('ROLE', $attendee)
                )
            ) {
                $response['attendees'][$id]['name'] = array_key_exists('CN', $attendee) ? $attendee['CN']: null;
                $response['attendees'][$id]['RSVP'] = array_key_exists('RSVP', $attendee) ? $attendee['RSVP']: null;
                $response['attendees'][$id]['partstat'] = array_key_exists('PARTSTAT', $attendee) ? $attendee['PARTSTAT']: null;
                $response['attendees'][$id]['ROLE'] = array_key_exists('ROLE', $attendee) ? $attendee['ROLE']: null;
            } elseif (is_string($attendee) && str_start_with($attendee, 'mailto:')) {
                $response['attendees'][$id]['email'] = substr($attendee, strlen('mailto:'));
                $response['attendees'][$id]['onclick'] = "return " . rcmail_output::JS_OBJECT_NAME . ".command('compose','" . $response['attendees'][$id]['email'] . "',this)";
                if ($my_email !== $response['attendees'][$id]['email']) {
                    $all_adresses .= $response['attendees'][$id]['email'] . ';';
                }
                $id++;
            }
        }
    }
    
    // On cherche les informations concernant l'organisateur
    if (!empty($event->organizer_array)) {
        $organizer_array = [];
        
        foreach ($event->organizer_array as $organizer) {
            if (is_string($organizer) && str_start_with($organizer, 'mailto:')) {
                $organizer_email = substr($organizer, strlen('mailto:'));
                $organizer_array['email'] = $organizer_email;
                $organizer_array['partstat'] = 'ORGANIZER';
                $organizer_array['onclick'] = "return " . rcmail_output::JS_OBJECT_NAME . ".command('compose','" . $organizer_email . "',this)";
                
                if ($my_email !== $organizer_email) {
                    $all_adresses .= $organizer_email . ';';
                }
            } else {
                $organizer_array['name'] =  array_key_exists('CN', $organizer) ? $organizer['CN']: null;
            }
        }
        
        $response['attendees'][] = $organizer_array;
    }

    // On definit les caractérisques du bouton pour répondre à tous
    $all_adresses = substr($all_adresses, 0, -1);
    
    $response['attr_reply_all'] = [
        'onclick' => "return " . rcmail_output::JS_OBJECT_NAME . ".command('compose','" . $all_adresses . "',this)"
    ];
}

/**
 * Get the  email sender's participation status before changing for a newer event in server in order to display the good
 * title
 * @param Event $event
 * @param array $response
 */
function get_sender_s_partstat(Event $event, array &$response, bool $event_on_server = false)
{
    $sender_email = $response['sender_email'];
    $array_attendees = [];
    $id = 0;
    $res = -1;
    
    if (!empty($event->attendee_array)) {
        foreach ($event->attendee_array as $attendee) {
            if (is_string($attendee) && str_start_with($attendee, 'mailto:')) {
                $array_attendees[$id]['email'] = substr($attendee, strlen('mailto:'));
                
                if ($sender_email === $array_attendees[$id]['email']) {
                    $res = $id;
                }
                
                $id++;
            } elseif (is_array($attendee) && array_key_exists('PARTSTAT', $attendee)) {
                $array_attendees[$id]['partstat'] = $attendee['PARTSTAT'];
            }
        }
    }

    if ($res >= 0) {
        if ($event_on_server) {
            $response['sender_partstat_on_server'] = $array_attendees[$res]['partstat'];
        } else {
            $response['sender_partstat'] = $array_attendees[$res]['partstat'];
        }
    }
}

/**
 * Test if event have a duration instead of dtend or only a dtstart.
 * If so, fill $event->dtend_array with correct value in order to use it in others functions which need a dtend
 * @param Event $event
 */
function if_no_dtend_add_one_to_event(Event &$event)
{
    if ($event->dtend) {
        return;
    }
    
    if ($event->duration) {
        $offset = calculate_duration($event->duration);
    } else {
        $offset = 0;
    }
    
    $event->dtend_array = [
        [],
        date("Ymd\THis", $event->dtstart_array[2] + $offset),
        $event->dtstart_array[2] + $offset,
    ];
}

/**
 * Get method of the ICalendar object and fill the response array with.
 * $response['METHOD'] = method
 * @param ICal $ical
 * @param $response
 * @param bool $is_Organizer
 */
function set_method_field(ICal $ical, &$response, bool $is_Organizer)
{
    if (
        is_array($ical->cal['VCALENDAR'])
        && array_key_exists('METHOD', $ical->cal['VCALENDAR'])
        && $ical->cal['VCALENDAR']['METHOD']
    ) {
        $response['METHOD'] = $ical->cal['VCALENDAR']['METHOD'];
    } elseif ($is_Organizer) {
        $response['METHOD'] = 'REPLY';
    } else {
        $response['METHOD'] = 'REQUEST';
    }
}

/**
 * Check if this event already exist on server in an older version, and if it is the case, set the response array.
 * $response['found_older_event_on_calendar'] = id of calendar where an event was found
 * $response['older_event'] = the event in question.
 * @param Event $event
 * @param array $response
 * @param array $arrayOfCalendars
 * @param array $all_events
 */
function set_if_an_older_event_was_found_on_server(Event $event, array &$response, array $arrayOfCalendars, array $all_events): void
{
    foreach ($arrayOfCalendars as $calendar) {
        // On trouve les événements qui matchent dans chacun des calendriers
        $event_found_on_server = find_event_with_matching_uid(
            $event, 
            $calendar->getCalendarID(),
            $all_events
        );
        
        if ($event_found_on_server) {
            $response['found_older_event_on_calendar'] = $calendar->getCalendarID();

            $ical = new ICal($event_found_on_server->getData());
            $older_events = $ical->events();
            
            foreach ($older_events as $older_event) {
                if ($older_event->uid == $event->uid) {
                    $response['older_event'] = $older_event;
                    break;
                }
            }
            
            break;
        }
    }
}

/**
 * Compare the current event with one found on server caldav.
 *
 * If there is a modification on dates, location, description or in attendees' list, fill the response array with the
 * modification.
 * @param array $response
 * @param bool $is_Organizer
 * @param Event $event : current event
 * @param string $langs
 * @param array|null $attendees : array with all the attendees of current event
 */
function set_if_modification_date_location_description_attendees(array &$response, bool $is_Organizer, Event $event, string $langs, ?array $attendees): void
{
    $event_to_compare_with = $is_Organizer ? $response['used_event'] : $response['older_event'];

    if (
        !empty($event_to_compare_with->location)
        && !empty($event->location)
        && strcmp($event_to_compare_with->location, $event->location) != 0
    ) {
        $response['new_location'] = $event->location;
    }
    
    if (
        $event_to_compare_with->dtstart_array[2] != $event->dtstart_array[2] 
        || $event_to_compare_with->dtend_array[2] != $event->dtend_array[2]
    ) {
        setlocale(LC_TIME, $langs);

        $response['new_date']['date_month_start'] = date("M/Y", $event->dtstart_array[2]);
        $response['new_date']['date_day_start'] = date("d", $event->dtstart_array[2]);
        $response['new_date']['date_weekday_start'] = date("l", $event->dtstart_array[2]);
        $response['new_date']['date_hours_start'] = date("G:i", $event->dtstart_array[2]);

        $response['new_date']['date_month_end'] = date("M/Y", $event->dtend_array[2]);
        $response['new_date']['date_day_end'] = date("d", $event->dtend_array[2]);
        $response['new_date']['date_weekday_end'] = date("l", $event->dtstart_array[2]);
        $response['new_date']['date_hours_end'] = date("G:i", $event->dtend_array[2]);
        $same_date = false;

        if (strcmp(substr($event->dtstart_array[1], 0, 8), substr($event->dtend_array[1], 0, 8)) == 0) {
            $same_date = true;
        }
        
        $response['new_date']['same_date'] = $same_date;
    }
    
    if (!empty($event->description) && $event_to_compare_with->description === $event->description) {
        $response['new_description'] = nl2br($event->description);
    }
    
    if (
        ($event_to_compare_with->attendee_array || $event->attendee_array)
        && !empty($attendees) && !empty($response['attendees'])
    ) {
        $response['new_attendees'] = find_difference_attendee($attendees, $response['attendees']);
    }

    if ($event_to_compare_with->duration) {
        $offset = calculate_duration($event_to_compare_with->duration);
        $event_to_compare_with->dtend_array = [
            [],
            date("Ymd\THis", $event_to_compare_with->dtstart_array[2] + $offset),
            $event_to_compare_with->dtstart_array[2] + $offset,
        ];
    }
}

/**
 * Fill the response array with a formated date/time for display and check if the date of start and end are same
 * $response['date_month_start'] : "M/Y"
 * $response['date_month_end'] : "M/Y"
 * $response['date_day_start'] : "d"
 * $response['date_day_end'] : "d"
 * $response['date_hours_start'] : "G:i"
 * $response['date_hours_end'] : "G:i"
 * $response['same_date'] : test if date are equals
 * @param Event $event
 * @param array $response
 * @param string $langs
 */
function set_formated_date_time(Event $event, array &$response, string $langs): void
{
    setlocale(LC_TIME, $langs);
    $response['date_start'] = date("Y-m-d", $event->dtstart_array[2]);
    $response['date_month_start'] = date("M/Y", $event->dtstart_array[2]);
    $response['date_day_start'] = date("d", $event->dtstart_array[2]);
    $response['date_weekday_start'] = date("l", $event->dtstart_array[2]);
    $response['date_hours_start'] = date("H:i", $event->dtstart_array[2]);

    $response['date_end'] = date("Y-m-d", $event->dtend_array[2]);
    $response['date_month_end'] = date("M/Y", $event->dtend_array[2]);
    $response['date_day_end'] = date("d", $event->dtend_array[2]);
    $response['date_weekday_end'] = date("l", $event->dtstart_array[2]);
    $response['date_hours_end'] = date("H:i", $event->dtend_array[2]);

    $response['same_date'] = $response['date_start'] === $response['date_end'];
}

/**
 * Fill the response array with calendar name and id for the select input and test if the select input have to be displayed.
 * $response['used_calendar'] = array of calendar display name to use indexed by their id
 * $response['main_calendar_name'] = main calendar display name for default input
 * $response['main_calendar_id'] = main calendar id for default value
 * $response['display_select'] = bool : test if the calendar select input should be displayed
 * @param array $response
 * @param array $server_caldav_config : configuration du server caldav
 * @param array $arrayOfCalendars
 */
function set_calendar_to_use_for_select_input(array &$response, array $server_caldav_config, array $arrayOfCalendars): string
{
    $msg = '';
    
    foreach ($server_caldav_config['_used_calendars'] as $used_calendars) {
        if ($arrayOfCalendars[$used_calendars]) {
            $response['used_calendar'][$used_calendars] = $arrayOfCalendars[$used_calendars]->getDisplayName();
        }
    }
    
    if ($arrayOfCalendars[$server_caldav_config['_main_calendar']]) {
        $response['main_calendar_name'] = $arrayOfCalendars[$server_caldav_config['_main_calendar']]->getDisplayName();
        $response['main_calendar_id'] = $server_caldav_config['_main_calendar'];
    } else {
        $msg = "main_calendar_not_exist";
    }

    $found_older = !empty($response['found_older_event_on_calendar']) ? $response['found_older_event_on_calendar'] : false;

    $response['display_select'] = ($response['METHOD'] !== 'CANCEL') && !$found_older;
    
    return $msg;
}

/**
 * Fill the response array with email of sender and receiver
 * $response['sender_email']
 * $response['receiver_email']
 * @param $message
 * @param array $response
 */
function set_sender_and_receiver_email($message, array &$response): void
{
    $response['sender_email'] = $message->get_header('from');
    
    if (strpos($response['sender_email'], ';')) {
        $response['sender_email'] = substr($response['sender_email'], 0, -1);
    }

    $response['receiver_email'] = $message->get_header('to');
    
    if (strpos($response['receiver_email'], ';')) {
        $response['receiver_email'] = substr($response['receiver_email'], 0, -1);
    }
}

/**
 * Find if there are new attendee on the list
 * @param array $array_attendee_new
 * @param array $array_attendee_old
 * @return array : array with new attendees
 */
function find_difference_attendee(array $array_attendee_new, array $array_attendee_old): array
{
    $new_attendees = [];
    
    foreach ($array_attendee_new as $new_attendee) {
        $is_different = true;
        
        foreach ($array_attendee_old as $old_attendee) {
            if ($new_attendee['email'] === $old_attendee['email']) {
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
 * Find out which identity corresponds to the one used in the event
 * @param Event $event
 * @param array $my_identities
 * @param Event|null $event_on_server
 * @return array|null : [email, name, role]
 */
function find_identity_matching_with_attendee_or_organizer(Event $event, array $my_identities, Event $event_on_server = null): ?array
{
    $attendee_array = [];
    $organizer_array = [];

    if (
        !empty($event->attendee_array) 
        || !empty($event->organizer_array)
        || $event_on_server 
        && (
            !empty($event_on_server->attendee_array)
            || !empty($event_on_server->organizer_array)
        )
    ) {
        // On boucle sur les attendee pour recuperer la bonne identity
        foreach ($event->attendee_array as $attendee) {
            array_push($attendee_array, $attendee);
        }
        
        if ($event_on_server) {
            foreach ($event_on_server->attendee_array as $attendee) {
                array_push($attendee_array, $attendee);
            }
        }

        foreach ($attendee_array as $attendee) {
            if (is_string($attendee)) {
                $attendee = preg_replace('/(mailto:)?(.+)/', '$2', $attendee);
                
                foreach ($my_identities as $identity) {
                    if (strcmp($attendee, $identity['email']) == 0) {
                        $my_identity['email'] = $identity['email'];
                        $my_identity['name'] = $identity['name'];
                        $my_identity['role'] = 'ATTENDEE';
                        return $my_identity;
                    }
                }
            }
        }
        
        foreach ($event->organizer_array as $organizer) {
            array_push($organizer_array, $organizer);
        }
        
        if ($event_on_server) {
            foreach ($event_on_server->organizer_array as $organizer) {
                array_push($organizer_array, $organizer);
            }
        }


        foreach ($organizer_array as $organizer) {
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

?>
