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
require_once('plugins/filesystem_attachments/filesystem_attachments.php');

class roundcube_caldav extends rcube_plugin
{
    public $task = 'settings|mail';

    public $rcube;
    public $rcmail;
    protected $time_zone_offset;
    protected $previous_and_next_catch_meeting = 86400;
    protected $attendees = array();


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

//        $this->register_action('plugin.accept_action', array($this, 'accept_action'));
        $this->include_stylesheet('skins/roundcube_caldav.css');
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
    function server_caldav_form(array $param_list)
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
     * Connexion avec le serveur calDAV et affichage des Champs si la connexion est réussie
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

                $param_list['blocks']['main']['options'][$cal->getCalendarID() . 'radiobutton'] = array(
                    'title' => html::label($cal->getCalendarID(), $cal->getDisplayName()),
                    'content' => $radiobutton->show($server['_main_calendar']));

                $checkbox = new html_checkbox(array('name' => '_define_used_calendars[]', 'value' => $cal->getCalendarID()));
                $param_list['blocks']['main']['options'][$cal->getCalendarID() . 'checkbox'] = array(
                    'content' => $checkbox->show($print)
                );

            }
        } catch (Exception $e) {
            $this->rcmail->output->command('display_message', $this->gettext('connect_error_msg'), 'error');
        }
        return $param_list;
    }

    /**
     * Vérifie que le mail que l'on veux regarder contient ou non une pièce jointe de type text/calendar
     * si oui on peut proceder à l'affichage des informations
     * @param $args
     * @return array
     *
     */
    function message_objects($args)
    {
        // Get arguments
        $content = $args['content'];
        $message = $args['message'];

        foreach ($message->attachments as &$attachment) {
            if ($attachment->mimetype == 'text/calendar') {
                try {


//                    // test
//                    $arg =array();
//                    $arg['data']=$message->attachements;
//                    $arg['id']='key';
//                    $this->save($args);

                    $this->process_attachment($content, $message, $attachment);
                } catch (\Exception $e) {
                }
            }
        }

        return array('content' => $content);
    }

    function str_start_with($string, $startstring)
    {
        $len = strlen($startstring);
        return (substr($string, 0, $len) === $startstring);
    }
//
//    function attachment_save($args){
//        // Note the file for later cleanup
//        $_SESSION['plugins']['roundcube_caldav']['temp_attachment'] = $args['data'];
//        $args['status']= true;
//        $args['id']= $this->file_id();
//        return $args;
//    }
//
//
//    function accept_or_reject_invitation($event)
//    {
//        $attrs = array(
//            'id' => "accept_button",
//        );
//        $button = new html_button($attrs);
//        return '<li>' . $button->show("accept") . '</li>';
//    }

    function accept_action()
    {
        //test
        $data = $_SESSION['plugins']['roundcube_caldav']['temp_attachment'];

        $this->rcmail->output->command('plugin.accept', array('message' => $data));
    }

    /**
     * Fonction qui procède a la récupération de la PJ et affiche les informations dans un conteneur html
     * directement sur le mail
     * @param $content
     * @param $message
     * @param $attachments
     * @throws Exception
     *
     */
    function process_attachment(&$content, &$message, &$attachments)
    {
        $ics = $message->get_part_body($attachments->mime_id);
        $ical = new \ICal\ICal($ics);


        foreach ($ical->events() as &$event) {

            $date_start = $event->dtstart_array[1];
            $date_end = $event->dtend_array[1];

            // On récupère la time_zone qui va nous servir plus tard dans une autre fonction
            $this->time_zone_offset = $ical->iCalDateToDateTime($date_start)->getOffset();


            // Les tableaux $event->attendee_array & $event->organizer_array ont une structure
            // [1] => array() : attendee1_infos
            // [2] => string : attendee1_email
            // [3] => array() : attendee2_infos
            // ...
            // D'ou cette partie pour regrouper le mail et le nom d'un participant au sein du même sous tableau
            $id = 0;
            $email = true;
            foreach (array_merge($event->organizer_array, $event->attendee_array) as $attendee) {
                if (array_key_exists('CN', $attendee) && $email) {
                    $this->attendees[$id]['name'] = $attendee['CN'];
                    $email = false;
                } elseif (array_key_exists('CN', $attendee)) {
                    $this->attendees[++$id]['name'] = $attendee['CN'];
                } elseif ($this->str_start_with($attendee, 'mailto:')) {
                    $this->attendees[$id]['email'] = substr($attendee, 7);
                    $id++;
                    $email = true;
                }
            }



            // On affiche un bouton pour répondre à tous
            $attrs = array(
                'href' => 'reply_all',
                'class' => 'rcmContactAddress',
                'onclick' => sprintf("return %s.command('reply-all','%s',this)",
                    rcmail_output::JS_OBJECT_NAME, $this->gettext('reply_all')),
            );


            // On affiche les autres informations concernant notre server caldav
             $display_caldav_info = $this->display_caldav_server_related_information($event) ;

            ob_start();
            include("plugins/roundcube_caldav/roundcube_caldav_display.php");
            $html = ob_get_clean();
            array_push($content, $html);
        }
    }

    /**
     * Affichage de toutes les informations concernant le serveur caldav
     * CAD pour chacun des calendriers choisis :
     * - affichage des evenement chevauchant l'evenement courant
     * - affichage de l'événement immédiatement avant
     * - affichage de l'événement immédiatement après
     * @param $current_event
     * @return array
     *
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
        $info_cal_dav_server['collision']= $meeting_collision;
        $info_cal_dav_server['close_meeting']=$previous_meeting;
//        var_dump($info_cal_dav_server['collision']);
        return $info_cal_dav_server;
    }

    /**
     * Affichage des événements en collision avec l'événement étudié sur tous les calendriers
     * disponible
     * @param $arrayOfCalendars
     * @param $_main_calendar
     * @param $_used_calendars
     * @param $client
     * @param $current_event
     * @return array
     * @throws CalDAVException
     *
     *
     */
    function meeting_collision_with_current_event_by_calendars($arrayOfCalendars, $_main_calendar, $_used_calendars, $client, $current_event)
    {
        $display_meeting_collision = array();
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

                $display_meeting_collision[$calendar->getCalendarID()] = array();
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
                                array_push($display_meeting_collision[$calendar->getCalendarID()], $event_found);
                                $has_collision_by_calendars = true;
                            }
                        }
                    }
                }
                if (!$has_collision_by_calendars) {
                    unset($display_meeting_collision[$calendar->getCalendarID()]);
                }
            }
        }
        return $display_meeting_collision;
    }

    /**
     * Affichage des événements précédents et suivants si ils existent sur les différents calendriers
     * @param $arrayOfCalendars
     * @param $_main_calendar
     * @param $_used_calendars
     * @param $client
     * @param $current_event
     * @return array
     */
    function get_previous_and_next_meeting_by_calendars($arrayOfCalendars, $_main_calendar, $_used_calendars, $client, $current_event)
    {
        $close_meetings = array();
        $previous_meeting = array();
        $next_meeting = array();

        foreach ($arrayOfCalendars as $calendar) {
            // On récupère les calendriers et on travaille qu'avec ceux définit dans les paramètres
            if ($calendar->getCalendarID() == $_main_calendar || array_key_exists($calendar->getCalendarID(), $_used_calendars)) {

                // On definit le calendrier courant
                $client->setCalendar($arrayOfCalendars[$calendar->getCalendarID()]);

                // On convertit la date de l'événement courant dans le GMT
                $current_dtstart_with_offset = date("Ymd\THis\Z", $current_event->dtstart_array[2] - $this->time_zone_offset);
                $current_dtend_with_offset = date("Ymd\THis\Z", $current_event->dtend_array[2] - $this->time_zone_offset);

                $current_dtstart_minus_24h = date("Ymd\THis\Z", $current_event->dtstart_array[2] - $this->time_zone_offset - $this->previous_and_next_catch_meeting);
                $current_dtend_plus_24h = date("Ymd\THis\Z", $current_event->dtend_array[2] - $this->time_zone_offset + $this->previous_and_next_catch_meeting);


                //On preselectionne les fichiers ics en collision avec la date du fichier reçu
                $get_all_previous_meeting_found = $client->getEvents($current_dtstart_minus_24h, $current_dtstart_with_offset);
                $get_all_next_meeting_found = $client->getEvents($current_dtend_with_offset, $current_dtend_plus_24h);

                // On prepare le nom du calendrier que l'on affichera uniquement si un evenement est prévu  pour ce calendrier
                $calendar_name = $calendar->getDisplayName();

                // On recherche enfin l'evement le plus proche
                $prev_res = $this->display_closest_meeting_by_calendars($get_all_previous_meeting_found, $current_event, $current_dtstart_minus_24h, $calendar_name, 'previous');
                $next_res = $this->display_closest_meeting_by_calendars($get_all_next_meeting_found, $current_event, $current_dtend_plus_24h, $calendar_name);

                $previous_meeting[$prev_res['uid']]=$prev_res;
                $next_meeting[$next_res['uid']] = $next_res;
            }
        }

        $uid_previous = $this->choose_the_closest_meeting($previous_meeting, $current_event->dtstart_array[1], 'previous');

        $uid_next = $this->choose_the_closest_meeting($next_meeting, $current_event->dtend_array[1], 'next');
        foreach ($previous_meeting as $meeting) {
            if ($uid_previous == $meeting['uid']) {
                $close_meetings['previous'] = $meeting;
            }
        }

        foreach ($next_meeting as $meeting) {
            if ($uid_next == $meeting['uid']) {
                $close_meetings['next'] = $meeting;
            }
        }



        return $close_meetings;
    }

    /**
     * Affiche l'événement le plus proche de la date actuelle
     * @param $get_all_close_meeting_found : tableau de fichier ics contenant un ou plusieurs événements
     * @param $current_event
     * @param $offset : la date avec laquelle sont retenus les meeting, cad la date de début/fin du current_event +- 24h (modifiablle)
     * @param $calendar_name
     * @param string $opt 'next' pour avoir le meeting suivant ; 'previous' pour avoir le meeting précédant
     * @return array
     */
    function display_closest_meeting_by_calendars($get_all_close_meeting_found, $current_event, $offset, $calendar_name, $opt = 'next')
    {
        $closest_meeting = array();
        $stock_closest_events = array();
        $has_meeting_by_calendars = false;
        foreach ($get_all_close_meeting_found as $event_found_ics) {
            // On recupère uniquement le fichier ics qui est dans la partie data pour le parser
            $event_found_ical = new ICal\ICal($event_found_ics->getData());

            // On regarde event par event, un fichier ics peut en contenir plusieurs (en cas de répétition)
            foreach ($event_found_ical->events() as &$event_found) {

                if ($event_found->uid != $current_event->uid) {
                    if (strcmp($opt, 'next') == 0) {
                        if ($this->is_there_an_overlap($current_event->dtend_array[1], $offset, $event_found->dtstart_array[1],
                            $event_found->dtend_array[1], $current_event->dtstart_array[2])) {

                            // On stocke tous les événements trouvés dans un tableau pour effectuer un tri ensuite
                            $stock_closest_events[$event_found->uid]['summary'] = $event_found->summary;
                            $stock_closest_events[$event_found->uid]['date_start'] = $event_found->dtstart_array[1];
                            $stock_closest_events[$event_found->uid]['date_end'] = $event_found->dtend_array[1];
                            $stock_closest_events[$event_found->uid]['uid'] = $event_found->uid;
                            $stock_closest_events[$event_found->uid]['calendar'] = $calendar_name;

                            $has_meeting_by_calendars = true;
                        }
                    } else {
                        if ($this->is_there_an_overlap($offset, $current_event->dtstart_array[1],
                            $event_found->dtstart_array[1], $event_found->dtend_array[1], $current_event->dtstart_array[2])) {

                            $stock_closest_events[$event_found->uid]['summary'] = $event_found->summary;
                            $stock_closest_events[$event_found->uid]['date_start'] = $event_found->dtstart_array[1];
                            $stock_closest_events[$event_found->uid]['date_end'] = $event_found->dtend_array[1];
                            $stock_closest_events[$event_found->uid]['uid'] = $event_found->uid;
                            $stock_closest_events[$event_found->uid]['calendar'] = $calendar_name;


                            $has_meeting_by_calendars = true;
                        }
                    }

                }
            }
        }
        if ($has_meeting_by_calendars && strcmp($opt, 'next') == 0) {
            $res = $this->choose_the_closest_meeting($stock_closest_events, $current_event->dtend_array[1], 'next');
        } elseif ($has_meeting_by_calendars) {
            $res = $this->choose_the_closest_meeting($stock_closest_events, $current_event->dtstart_array[1], 'previous');
        }


        return $stock_closest_events[$res];
    }

    /**
     * Affiche un intervalle de temps selon le formatage des dates spécifié dans la config de roundcube
     * Si l'intervalle commence et finis le même jour on affiche qu'une seule fois la date
     * Si la date ne contient pas d'horaire on se contente d'afficher date début - date de fin
     * @param $date_start
     * @param $date_end
     * @return string
     *
     */
    function pretty_date($date_start, $date_end)
    {
        $date_format = $this->rcmail->config->get('date_format');
        $time_format = $this->rcmail->config->get('time_format');

        $combined_format = $date_format . ' ' . $time_format;

        $datestr = $this->rcmail->format_date($date_start, $combined_format) . ' - ';
        $df = 'd-m-Y';

        if (strlen($date_start) == 8 && strlen($date_end) == 8) {
            if (strcmp($date_start, $date_end) == 0) {
                $datestr = $this->rcmail->format_date($date_start, $date_format) . $this->gettext('all_day');
            } else {
                $datestr = $this->rcmail->format_date($date_start, $date_format) . ' - ' . $this->rcmail->format_date($date_end, $date_format);
            }
        } else {
            if (strcmp(substr($date_start, 0, -6), substr($date_end, 0, -6)) == 0) {
                $datestr .= $this->rcmail->format_date($date_end, $time_format);
            } else {
                $datestr .= $this->rcmail->format_date($date_end, $combined_format);
            }
        }
        return $datestr;
    }

    /**
     * Recherche d'un chevauchement entre les deux intervals de temps, true en cas de chevauchement
     * @param $current_date_start
     * @param $current_date_end
     * @param $date_start
     * @param $date_end
     * @param $base_timestamp
     * @return bool
     *
     */
    function is_there_an_overlap($current_date_start, $current_date_end, $date_start, $date_end, $base_timestamp)
    {
        return (((strtotime($date_start, $base_timestamp) >= strtotime($current_date_start, $base_timestamp))
                && (strtotime($date_start, $base_timestamp) < strtotime($current_date_end, $base_timestamp))) ||
            ((strtotime($date_end, $base_timestamp) >= strtotime($current_date_start, $base_timestamp))
                && (strtotime($date_end, $base_timestamp) < strtotime($current_date_end, $base_timestamp))));
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


}

