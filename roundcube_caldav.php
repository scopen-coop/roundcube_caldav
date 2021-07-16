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

require('lib/simpleCalDAV/SimpleCalDAVClient.php');
require('lib/password_encryption/password_encryption.php');

class roundcube_caldav extends rcube_plugin
{
    public $task = 'settings|mail';

    public $rcube;
    public $rcmail;
    public $time_zone_offset;

    public $attendees = array();

    function init()
    {
        $this->rcmail = rcmail::get_instance();
        $this->rcube = rcube::get_instance();
        $this->include_script('roundcube_caldav.js');
        $this->load_config();
        $this->add_texts('localization/');


        $this->add_hook('preferences_sections_list', array($this, 'modify_section'));
        $this->add_hook('preferences_list', array($this, 'preferences_list'));
        $this->add_hook('preferences_save', array($this, 'preferences_save'));

        $this->add_hook('message_objects', array($this, 'message_objects'));

        $this->register_action('plugin.reply_attendee', array($this, 'reply_attendee'));

        $this->add_button(
            array(
                'type' => 'link-menuitem',
                'label' => 'vcard_attachments.forwardvcard',
                'command' => 'attach-vcard',
                'class' => 'icon vcard',
                'classact' => 'icon vcard active',
                'innerclass' => 'icon vcard',
            ),
            'ics_attachments');

    }

    /**
     * Affichage de la section "Configuration du serveur CalDAV"
     * @param $param_list
     * @return array
     */
    function modify_section($param_list)
    {
        $param_list['list']['server_caldav'] = array(
            'id' => 'server_caldav', 'section' => $this->gettext('server_caldav'),
        );
        return $param_list;
    }


    /**
     * Affichage des différents champs de la section
     * @param $param_list
     * @return array
     */
    function preferences_list($param_list)
    {
        if ($param_list['section'] != 'server_caldav') {
            return $param_list;
        }
        $param_list['blocks']['main']['name'] = $this->gettext('settings');

        $param_list = $this->server_caldav_form($param_list);

        $server = $this->rcube->config->get('server_caldav');

        if (!empty($server['_url_base']) && !empty($server['_login']) && !empty($server['_password'])) {
            $param_list = $this->connection_server_calDAV($param_list);
        }
        return $param_list;
    }

    /**
     * Sauvegarde des préférences une fois les différents champs remplis
     * @param $save_params
     * @return array
     */
    function preferences_save($save_params)
    {
        $cipher = new password_encryption();
        if ($save_params['section'] == 'server_caldav') {

            if (!empty($_POST['_define_server_caldav']) && !empty($_POST['_define_login'])) {
                $save_params['prefs']['server_caldav']['_url_base'] = rcube_utils::get_input_value('_define_server_caldav', rcube_utils::INPUT_POST);
                $save_params['prefs']['server_caldav']['_login'] = rcube_utils::get_input_value('_define_login', rcube_utils::INPUT_POST);

                if (!empty($_POST['_define_password'])) {
                    $ciphered_password = $cipher->encrypt(rcube_utils::get_input_value('_define_password', rcube_utils::INPUT_POST), $this->rcube->config->get('des_key'), true);
                    $save_params['prefs']['server_caldav']['_password'] = $ciphered_password;
                } elseif (array_key_exists('_password', $this->rcube->config->get('server_caldav'))) {
                    $save_params['prefs']['server_caldav']['_password'] = $this->rcube->config->get('server_caldav')['_password'];
                }

                $save_params['prefs']['server_caldav']['_main_calendar'] = rcube_utils::get_input_value('_define_main_calendar', rcube_utils::INPUT_POST);
                foreach (rcube_utils::get_input_value('_define_used_calendars', rcube_utils::INPUT_POST) as $cal) {
                    $save_params['prefs']['server_caldav']['_used_calendars'][$cal] = $cal;
                }
            } else {
                $this->rcmail->output->command('display_message', $this->gettext('save_error_msg'), 'error');
            }
        }

        return $save_params;
    }

    /**
     * Affichage des champs url / login / password
     * @param array $param_list
     * @return array
     */
    public function server_caldav_form(array $param_list)
    {
        $server = $this->rcube->config->get('server_caldav');

        // Champs pour specifier l'url du serveur
        $field_id = 'define_server_caldav';
        $url_base = new html_inputfield(array('name' => '_' . $field_id, 'id' => $field_id));
        $param_list['blocks']['main']['options']['url_base'] = array(
            'title' => html::label($field_id, rcube::Q($this->gettext('url_base'))),
            'content' => $url_base->show($server['_url_base']),
        );

        // Champs pour specifier le login
        $field_id = 'define_login';
        $login = new html_inputfield(array('name' => '_' . $field_id, 'id' => $field_id));
        $param_list['blocks']['main']['options']['login'] = array(
            'title' => html::label($field_id, rcube::Q($this->gettext('login'))),
            'content' => $login->show($server['_login']),
        );

        // Champs pour specifier le mot de passe
        $field_id = 'define_password';
        $password = new html_passwordfield(array('name' => '_' . $field_id, 'id' => $field_id));
        $param_list['blocks']['main']['options']['password'] = array(
            'title' => html::label($field_id, rcube::Q($this->gettext('password'))),
            'content' => $password->show(),
        );
        return $param_list;
    }

    /**
     * Connection avec le serveur calDAV et affichage des Champs si la connexion est réussie
     * @param array $param_list
     * @return array
     */
    function connection_server_calDAV(array $param_list)
    {
        $cipher = new password_encryption();
        $server = $this->rcube->config->get('server_caldav');
        $_login = $server['_login'];
        $_password = $server['_password'];
        $_url_base = $server['_url_base'];

        $param_list['blocks']['main']['options']['calendar_choice'] = array(
            'title' => html::label('ojk', rcube::Q($this->gettext('calendar_choice'))),
        );

        $client = new SimpleCalDAVClient();
        try {


            $plain_password = $cipher->decrypt($_password, $this->rcube->config->get('des_key'), true);
            $client->connect($_url_base, $_login, $plain_password);
            $arrayOfCalendars = $client->findCalendars();

            foreach ($arrayOfCalendars as $cal) {
                $print = null;
                foreach ($server['_used_calendars'] as $used_calendar) {
                    if ($used_calendar == $cal->getCalendarID()) {
                        $print = $cal->getCalendarID();
                    }
                }
                $radiobutton = new html_radiobutton(array('name' => '_define_main_calendar', 'value' => $cal->getCalendarID()));
                $checkbox = new html_checkbox(array('name' => '_define_used_calendars[]', 'value' => $cal->getCalendarID()));
                $param_list['blocks']['main']['options'][$cal->getCalendarID()] = array(
                    'title' => html::label($cal->getCalendarID(), $cal->getDisplayName()),
                    'content' => html::div('input-group', $radiobutton->show($server['_main_calendar']) .
                        $checkbox->show($print)
                    ));

            }
        } catch (Exception $e) {
            $this->rcmail->output->command('display_message', $this->gettext('connect_error_msg'), 'error');
        }
        return $param_list;
    }

    /**
     * This callback function adds a box above the message content
     * if there is an ical attachment available
     */
    function message_objects($args)
    {
        // Get arguments
        $content = $args['content'];
        $message = $args['message'];

        foreach ($message->attachments as &$attachment) {
            if ($attachment->mimetype == 'text/calendar') {
                try {
                    $this->process_attachment($content, $message, $attachment);
                } catch (\Exception $e) {
                }
            }
        }

        return array('content' => $content);
    }

    function start_with($string, $startstring)
    {
        $len = strlen($startstring);
        return (substr($string, 0, $len) === $startstring);
    }

    public function process_attachment(&$content, &$message, &$a)
    {
        $rcmail = $this->rcmail;


        $ics = $message->get_part_body($a->mime_id);
        $ical = new \ICal\ICal($ics);


        foreach ($ical->events() as &$event) {

            $date_start = $event->dtstart_array[1];
            $date_end = $event->dtend_array[1];

            $this->time_zone_offset = $ical->iCalDateToDateTime($date_start)->getOffset();


            $datestr = $this->pretty_date($date_start, $date_end);


            $id = 0;

            $email = true;
            foreach (array_merge($event->organizer_array, $event->attendee_array) as $attendee) {
                if (array_key_exists('CN', $attendee) && $email) {
                    $this->attendees[$id]['name'] = $attendee['CN'];
                    $email = false;

                } elseif (array_key_exists('CN', $attendee)) {
                    $this->attendees[++$id]['name'] = $attendee['CN'];


                } elseif ($this->start_with($attendee, 'mailto:')) {
                    $this->attendees[$id]['email'] = substr($attendee, 7);
                    $id++;
                    $email = true;
                }
            }


            $html = '<div >' . '<ul>';
            $html .= '<li>' . '<b>' . $event->summary . '</b>' . '<br/>' . '</li>';

            if (!empty($event->description)) {
                $html .= '<li>' . $event->description . '<br/>' . '</li>';
            }
            if (!empty($event->location)) {
                $html .= '<li>' . $event->location . '<br/>' . '</li>';
            }

            $html .= '<li>' . $datestr . '<br/>' . '</li>';

            if (!empty($this->attendees)) {
                $html .= '<li> ' . $this->gettext("attendee") . ' <ul>';
                foreach ($this->attendees as $attendee) {
                    $attrs = array(
                        'href' => 'mailto:' . $attendee["email"],
                        'class' => 'rcmContactAddress',
                        'onclick' => sprintf("return %s.command('compose','%s',this)",
                            rcmail_output::JS_OBJECT_NAME, rcube::JQ(format_email_recipient($attendee["email"], $attendee['name']))),
                    );

                    $html .= '<li>' . html::a($attrs, $attendee['name']) . '</li>';

                }
                $html .= '</ul></li>';
            }
            $attrs = array(
                'href' => 'reply_all',
                'class' => 'rcmContactAddress',
                'onclick' => sprintf("return %s.command('reply-all','%s',this)",
                    rcmail_output::JS_OBJECT_NAME, 'Répondre à tous'),
            );

            $html .= '<li>' . html::a($attrs, 'Répondre à tous') . '</li>';


            $html .= '<li>' . $this->display_caldav_server_related_information($event) . '<li>';
            $html .= '</ul>';
            $html .= '</div>';
            array_push($content, $html);
        }
    }

    /**
     * @param $current_event
     * @return string
     * Affichage de toutes les informations concernant le serveur caldav
     * CAD pour chacun des calendriers choisis :
     * - affichage des evenement chevauchant l'evenement courant
     * - affichage de l'événement immédiatement avant
     * - affichage de l'événement immédiatement après
     */
    function display_caldav_server_related_information($current_event)
    {
        $server = $this->rcube->config->get('server_caldav');
        $_login = $server['_login'];
        $_password = $server['_password'];
        $_url_base = $server['_url_base'];
        $_main_calendar = $server['_main_calendar'];
        $_used_calendars = $server['_used_calendars'];


        try {
            // Récupération du mot de passe chiffré dans la bdd et décodage
            $cipher = new password_encryption();
            $plain_password = $cipher->decrypt($_password, $this->rcube->config->get('des_key'), true);

            //  Connexion au serveur calDAV et récupération des calendriers dispos
            $client = new SimpleCalDAVClient();
            $client->connect($_url_base, $_login, $plain_password);
            $arrayOfCalendars = $client->findCalendars();

            $meeting_collision = $this->meeting_collision_with_current_event_by_calendars($arrayOfCalendars, $_main_calendar, $_used_calendars, $client, $current_event);
            $previous_meeting = $this->get_previous_and_next_meeting_by_calendars($arrayOfCalendars, $_main_calendar, $_used_calendars, $client, $current_event);

        } catch (Exception $e) {
            echo $e;
        }
        return $meeting_collision . '<br/>' . $previous_meeting;
    }

    /**
     * @param $arrayOfCalendars
     * @param $_main_calendar
     * @param $_used_calendars
     * @param $client
     * @param $current_event
     * @return string
     * @throws CalDAVException
     *
     * Affichage des événements en collision avec l'événement étudié sur tous les calendriers
     * disponible
     */
    public function meeting_collision_with_current_event_by_calendars($arrayOfCalendars, $_main_calendar, $_used_calendars, $client, $current_event)
    {
        $display_meeting_collision = '';
        $meeting_collision = '';
        $has_collision = false;

        foreach ($arrayOfCalendars as $calendar) {

            // On récupère les calendriers et on travaille qu'avec ceux définit dans les paramètres
            if ($calendar->getCalendarID() == $_main_calendar || array_key_exists($calendar->getCalendarID(), $_used_calendars)) {

                $client->setCalendar($arrayOfCalendars[$calendar->getCalendarID()]);


                //On preselectionne les fichiers ics en collision avec la date du fichier reçu
                $curr_date_start_with_offset = date("Ymd\THis\Z", $current_event->dtstart_array[2] - $this->time_zone_offset);
                $curr_date_end_with_offset = date("Ymd\THis\Z", $current_event->dtend_array[2] - $this->time_zone_offset);

                $rapport = $client->getEvents($curr_date_start_with_offset, $curr_date_end_with_offset);


                // On affiche le nom du calendrier
                $temp = '<b>' . $calendar->getDisplayName() . '</b>' . ': <br/>';


                $has_collision_by_calendars = false;
                foreach ($rapport as $event_found_ics) {

                    // On recupère uniquement le fichier ics qui est dans la partie data pour le parser
                    $event_found_ical = new ICal\ICal($event_found_ics->getData());

                    // On regarde event par event, un fichier ics peut en contenir plusieurs (en cas de répétition)
                    foreach ($event_found_ical->events() as &$event_found) {
                        if ($event_found->uid != $current_event->uid) {
                            if ($this->is_there_an_overlap($current_event->dtstart_array[1], $current_event->dtend_array[1],
                                $event_found->dtstart_array[1], $event_found->dtend_array[1], $current_event->dtstart_array[2])) {

                                // Affichage de l'événement
                                $temp .= $event_found->summary . ': ' . $this->pretty_date($event_found->dtstart_array[1], $event_found->dtend_array[1]) . '<br/>';
                                $has_collision = true;
                                $has_collision_by_calendars = true;
                            }
                        }
                    }
                }
                if ($has_collision_by_calendars) {
                    $meeting_collision .= $temp;
                }
            }
        }
        // On affiche le texte seulement si il y a eu une collision
        if ($has_collision) {
            $display_meeting_collision = $this->gettext('same_hour_meeting') . '<br/>' . $meeting_collision;
        }
        return $display_meeting_collision;
    }

    /**
     * @param $arrayOfCalendars
     * @param $_main_calendar
     * @param $_used_calendars
     * @param $client
     * @param $current_event
     * @return string
     *
     * Affichage des événements précédents et suivants si ils existent sur les différents calendriers
     */
    public function get_previous_and_next_meeting_by_calendars($arrayOfCalendars, $_main_calendar, $_used_calendars, $client, $current_event)
    {
        $has_meeting_the_previous_day = false;
        $previous_meeting = '';
        $next_meeting = '';


        foreach ($arrayOfCalendars as $calendar) {
            // On récupère les calendriers et on travaille qu'avec ceux définit dans les paramètres
            if ($calendar->getCalendarID() == $_main_calendar || array_key_exists($calendar->getCalendarID(), $_used_calendars)) {

                // On definit le calendrier courant
                $client->setCalendar($arrayOfCalendars[$calendar->getCalendarID()]);

                // On convertit la date de l'événement courant dans le GMT
                $current_dtstart_with_offset = date("Ymd\THis\Z", $current_event->dtstart_array[2] - $this->time_zone_offset);
                $current_dtend_with_offset = date("Ymd\THis\Z", $current_event->dtend_array[2] - $this->time_zone_offset);

                $current_dtstart_minus_24h = date("Ymd\THis\Z", $current_event->dtstart_array[2] - $this->time_zone_offset - 86400);
                $current_dtend_plus_24h = date("Ymd\THis\Z", $current_event->dtend_array[2] - $this->time_zone_offset + 86400);


                //On preselectionne les fichiers ics en collision avec la date du fichier reçu
                $get_all_previous_meeting_found = $client->getEvents($current_dtstart_minus_24h, $current_dtstart_with_offset);
                $get_all_next_meeting_found = $client->getEvents($current_dtend_with_offset, $current_dtend_plus_24h);

                // On affiche le nom du calendrier
                $calendar_name = '<b>' . $calendar->getDisplayName() . '</b>' . ': <br/>';


                $has_meeting_the_previous_day_by_calendars = false;
                $has_meeting_the_next_day_by_calendars = false;


                $stock_previous_events = array();
                $stock_next_events = array();

//                foreach ($get_all_previous_meeting_found as $event_found_ics) {
//
//                    // On recupère uniquement le fichier ics qui est dans la partie data et on le parse
//                    $event_found_ical = new ICal\ICal($event_found_ics->getData());
//
//                    // On regarde event par event, un fichier ics peut en contenir plusieurs (en cas de répétition)
//                    foreach ($event_found_ical->events() as &$event_found) {
//
//                        // On verifie que l'événement courant n'existe pas déja dans ce calendrier
//                        if ($event_found->uid != $current_event->uid) {
//                            if ($this->is_there_an_overlap($current_dtstart_minus_24h, $current_event->dtstart_array[1],
//                                $event_found->dtstart_array[1], $event_found->dtend_array[1], $current_event->dtstart_array[2])) {
//
//                                // On stocke tous les événements trouvés dans un tableau pour effectuer un tri ensuite
//                                $temp_value = $event_found->summary . ': ' . $this->pretty_date($event_found->dtstart_array[1], $event_found->dtend_array[1]) . '<br/>';
//                                $stock_previous_events[$event_found->uid]['str'] = $temp_value;
//                                $stock_previous_events[$event_found->uid]['date_start'] = $event_found->dtstart_array[1];
//                                $stock_previous_events[$event_found->uid]['uid'] = $event_found->uid;
//
//                                $has_meeting_the_previous_day = true;
//                                $has_meeting_the_previous_day_by_calendars = true;
//                            }
//                        }
//                    }
//                }
//                if ($has_meeting_the_previous_day_by_calendars) {
//                    // On selectionne le rdv le plus proche par calendriers
//                    $res = $this->choose_the_closest_meeting($stock_previous_events, true);
//                    $previous_meeting .= $calendar_name . $stock_previous_events[$res]['str'];
//                }
                $previous_meeting .= $this->get_closest_meeting_by_calendars($get_all_previous_meeting_found, $current_event, $current_dtstart_minus_24h, $calendar_name);
                // On fait la même procédure pour les rendez vous précédents
                $next_meeting .= $this->get_closest_meeting_by_calendars($get_all_next_meeting_found, $current_event, $current_dtend_plus_24h, $calendar_name);
            }
        }
        if ($has_meeting_the_previous_day) {
            $previous_meeting = $this->gettext('previous_meeting') . '<br/>' . $previous_meeting;
        }
        if (strcmp($next_meeting, '') != 0) {
            $next_meeting = $this->gettext('next_meeting') . '<br/>' . $next_meeting;
        }
        return $previous_meeting . '<br/>' . $next_meeting;
    }

    /**
     * @param $date_start
     * @param $date_end
     * @return string
     */
    public function pretty_date($date_start, $date_end)
    {
        $date_format = $this->rcmail->config->get('date_format', 'D-m-y');
        $time_format = $this->rcmail->config->get('time_format');

        $combined_format = $date_format . ' ' . $time_format;

        $datestr = $this->rcmail->format_date($date_start, $combined_format) . ' - ';
        $df = 'd-m-Y';

        if (strlen($date_start) == 8 && strlen($date_end) == 8) {
            if (strcmp($date_start, $date_end) == 0) {
                $datestr = $this->rcmail->format_date($date_start, $date_format) . $this->gettext('all_day');
            } else {
                $datestr = $this->rcmail->format_date($date_start, $date_format) . ' - ' . $datestr = $this->rcmail->format_date($date_end, $date_format);
            }
        } else {
            if (date($df, $date_start) == date($df, $date_end)) {
                $datestr .= $this->rcmail->format_date($date_end, $time_format);
            } else {
                $datestr .= $this->rcmail->format_date($date_end, $combined_format);
            }
        }
        return $datestr;
    }

    public function is_there_an_overlap($current_date_start, $current_date_end, $date_start, $date_end, $base_timestamp)
    {
        return (((strtotime($date_start, $base_timestamp) >= strtotime($current_date_start, $base_timestamp))
                && (strtotime($date_start, $base_timestamp) < strtotime($current_date_end, $base_timestamp))) ||
            ((strtotime($date_end, $base_timestamp) >= strtotime($current_date_start, $base_timestamp))
                && (strtotime($date_end, $base_timestamp) < strtotime($current_date_end, $base_timestamp))));
    }

    public function choose_the_closest_meeting($array_date, $start)
    {
        if ($start) {
            $uid = current($array_date)['uid'];
            foreach ($array_date as $date) {
                if (strtotime($array_date[$uid]['date_start']) <= strtotime($date['date_start'])) {
                    $uid = $date['uid'];
                }
            }
        } else {
            $uid = current($array_date)['uid'];
            foreach ($array_date as $date) {
                if (strtotime($array_date[$uid]['date_start']) >= strtotime($date['date_start'])) {
                    $uid = $date['uid'];
                }
            }

        }
        return $uid;
    }


    /**
     * @param $get_all_closest_meeting_found
     * @param $current_event
     * @param $offset
     * @param $calendar_name
     * @return string
     */



    // A FINIR DE FACTORISER
    public function get_closest_meeting_by_calendars($get_all_closest_meeting_found, $current_event, $offset, $calendar_name, $opt='next')
    {
        $closest_meeting = '';
        $stock_closest_events = array();
        $has_meeting_by_calendars = false;
        foreach ($get_all_closest_meeting_found as $event_found_ics) {
            // On recupère uniquement le fichier ics qui est dans la partie data pour le parser
            $event_found_ical = new ICal\ICal($event_found_ics->getData());

            // On regarde event par event, un fichier ics peut en contenir plusieurs (en cas de répétition)
            foreach ($event_found_ical->events() as &$event_found) {

                if ($event_found->uid != $current_event->uid) {
                    if (strcmp($opt, 'next') == 0) {
                        if ($this->is_there_an_overlap($current_event->dtend_array[1], $offset, $event_found->dtstart_array[1],
                            $event_found->dtend_array[1], $current_event->dtstart_array[2])) {
                            $temp_value = $event_found->summary . ': ' . $this->pretty_date($event_found->dtstart_array[1], $event_found->dtend_array[1]) . '<br/>';

                            $stock_closest_events[$event_found->uid]['str'] = $temp_value;
                            $stock_closest_events[$event_found->uid]['date_start'] = $event_found->dtstart_array[1];
                            $stock_closest_events[$event_found->uid]['uid'] = $event_found->uid;


                            $has_meeting_by_calendars = true;
                        }
                    }else{
                        if ($this->is_there_an_overlap($offset, $current_event->dtstart_array[1],
                            $event_found->dtstart_array[1], $event_found->dtend_array[1], $current_event->dtstart_array[2])) {

                            // On stocke tous les événements trouvés dans un tableau pour effectuer un tri ensuite
                            $temp_value = $event_found->summary . ': ' . $this->pretty_date($event_found->dtstart_array[1], $event_found->dtend_array[1]) . '<br/>';
                            $stock_closest_events[$event_found->uid]['str'] = $temp_value;
                            $stock_closest_events[$event_found->uid]['date_start'] = $event_found->dtstart_array[1];
                            $stock_closest_events[$event_found->uid]['uid'] = $event_found->uid;

                            $has_meeting_by_calendars = true;
                        }
                    }

                }
            }
        }
        if ($has_meeting_by_calendars) {
            $res = $this->choose_the_closest_meeting($stock_closest_events, false);
            $closest_meeting = $calendar_name . $stock_closest_events[$res]['str'];
        }
        return $closest_meeting;
    }
}

