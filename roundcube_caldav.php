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

use it\thecsea\simple_caldav_client\CalDAVException;
use it\thecsea\simple_caldav_client\CalDAVFilter;
use it\thecsea\simple_caldav_client\CalDAVObject;
use it\thecsea\simple_caldav_client\SimpleCalDAVClient;
use it\thecsea\simple_caldav_client\CalDAVClient;

require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/lib/password_encryption/password_encryption.php');

class roundcube_caldav extends rcube_plugin
{
    public $task = 'settings|mail';

    public $rcube;
    public $rcmail;
    protected $client;
    protected $time_zone_offset;
    protected $previous_and_next_catch_meeting = 86400;
    protected $attendees = array();
    protected $arrayOfCalendars;
    protected $all_events = array();
    protected $connected = false;

    function init()
    {
        $this->rcmail = rcmail::get_instance();
        $this->rcube = rcube::get_instance();
        $this->load_config();

        $this->add_texts('localization/', true);
        $this->include_script('roundcube_caldav.js');
        $this->include_stylesheet('skins/roundcube_caldav.css');

        $server = $this->rcube->config->get('server_caldav');
        $_connexion = $server['_connexion_status'];

        $this->add_hook('preferences_sections_list', array($this, 'modify_section'));
        $this->add_hook('preferences_list', array($this, 'preferences_list'));
        $this->add_hook('preferences_save', array($this, 'preferences_save'));

        // on affiche les informations ics uniquement si l'on a une configuration fonctionnelle qui permet de se connecter au serveur
        if ($_connexion) {
            $this->add_hook('message_objects', array($this, 'message_objects'));
            $this->register_action('plugin.import_action', array($this, 'import_action'));
            $this->register_action('plugin.get_status', array($this, 'get_status'));
        }


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

        if (!empty($server['_url_base']) && !empty($server['_login']) && !empty($server['_password']) && $server['_connexion_status']) {
            $param_list = $this->calendar_selection($param_list);
        }
        return $param_list;
    }

    /**
     * On teste la connexion au serveur caldav
     * @param $_login
     * @param $_password
     * @param $_url_base
     * @return bool
     * @throws Exception
     */
    function try_connection($_login, $_password, $_url_base)
    {
        if (!empty($_url_base) && !empty($_login) && !empty($_password)) {
            // Récupération du mot de passe chiffré dans la bdd et décodage

            $cipher = new password_encryption();

            $plain_password = $cipher->decrypt($_password, $this->rcube->config->get('des_key'), true);


            //  Connexion au serveur calDAV et récupération des calendriers dispos

            $this->client = new SimpleCalDAVClient();

            try {
                $this->client->connect($_url_base, $_login, $plain_password, ['timeout' => 1]); // 0.6s
            } catch (Exception $e) {
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Sauvegarde des préférences une fois les différents champs remplis
     * Une nouvelle sauvegarde supprime toutes les données précédément sauvées c'est pourquoi il faut veiller à tout resauvegarder
     * @param $save_params
     * @return array
     */
    function preferences_save($save_params)
    {
        $cipher = new password_encryption();
        if ($save_params['section'] == 'server_caldav') {

            if (!empty($_POST['_define_server_caldav']) && !empty($_POST['_define_login'])) {
                // On récupère l'url et le login dans les champs (ils sont remplis par défault avec l'ancienne valeur)
                $urlbase = rcube_utils::get_input_value('_define_server_caldav', rcube_utils::INPUT_POST);
                $save_params['prefs']['server_caldav']['_url_base'] = $urlbase;
                $login = rcube_utils::get_input_value('_define_login', rcube_utils::INPUT_POST);
                $save_params['prefs']['server_caldav']['_login'] = $login;

                // Si le mot de passe est spécifié on le change et on teste la connexion sinon on récupère l'ancien
                if (!empty($_POST['_define_password'])) {
                    $ciphered_password = $cipher->encrypt(rcube_utils::get_input_value('_define_password', rcube_utils::INPUT_POST), $this->rcube->config->get('des_key'), true);
                    $save_params['prefs']['server_caldav']['_password'] = $ciphered_password;
                    $save_params['prefs']['server_caldav']['_connexion_status'] = $this->try_connection($login, $ciphered_password, $urlbase);
                } elseif (array_key_exists('_password', $this->rcube->config->get('server_caldav'))) {
                    $save_params['prefs']['server_caldav']['_password'] = $this->rcube->config->get('server_caldav')['_password'];
                    $save_params['prefs']['server_caldav']['_connexion_status'] = $this->rcube->config->get('server_caldav')['_connexion_status'];
                }

                // on récupère le calendrier principal que l'on ajoute également à la liste des calendriers utilisés
                $main_calendar = rcube_utils::get_input_value('_define_main_calendar', rcube_utils::INPUT_POST);
                $save_params['prefs']['server_caldav']['_main_calendar'] = $main_calendar;
                $save_params['prefs']['server_caldav']['_used_calendars'][$main_calendar] = $main_calendar;

                $chosen_calendars = array(rcube_utils::get_input_value('_define_used_calendars', rcube_utils::INPUT_POST));
                foreach ($chosen_calendars[0] as $cal) {
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
    function calendar_selection(array $param_list)
    {
        $server = $this->rcube->config->get('server_caldav');

        $param_list['blocks']['main']['options']['calendar_choice'] = array(
            'title' => html::label('ojk', rcube::Q($this->gettext('calendar_choice'))),
        );

        try {
            $this->connection_to_calDAV_server();

            foreach ($this->arrayOfCalendars as $cal) {
                $print = null;
                if ($server['_main_calendar'] == $cal->getCalendarID()) {
                    $print = $cal->getCalendarID();
                }
                foreach ($server['_used_calendars'] as $used_calendar) {
                    if ($used_calendar == $cal->getCalendarID()) {
                        $print = $cal->getCalendarID();
                    }

                }

                $checkbox = new html_checkbox(array('name' => '_define_used_calendars[]', 'value' => $cal->getCalendarID()));
                $radiobutton = new html_radiobutton(array('name' => '_define_main_calendar', 'value' => $cal->getCalendarID()));

                $param_list['blocks']['main']['options'][$cal->getCalendarID() . 'radiobutton'] = array(
                    'title' => html::label($cal->getCalendarID(), $this->gettext("use_this_calendar") . $cal->getDisplayName()),
                    'content' => $checkbox->show($print)
                );


                $param_list['blocks']['main']['options'][$cal->getCalendarID() . 'checkbox'] = array(
                    'title' => html::label($cal->getCalendarID(), $this->gettext("make_this_calendar_default1") . $cal->getDisplayName() . $this->gettext("make_this_calendar_default2")),
                    'content' => $radiobutton->show($server['_main_calendar']),
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
        $this->connection_to_calDAV_server();
        $this->get_all_events();

        $ics = $message->get_part_body($attachments->mime_id);
        $ical = new \ICal\ICal($ics);
        $events = $ical->events();


        $recurrent_events = array();
        foreach ($events as $event) {
            $recurrent_events[$event->uid][] = $event;
        }


        $same_uid = $events[0]->uid;



        foreach ($events as $i => &$event) {
            $used_event = $event;

            if ($same_uid == $used_event->uid && $i != 0) {
                continue;
            } else {
                $same_uid = $used_event->uid;
            }

            $found_advance = $this->is_server_in_advance($event);

            if ($found_advance) {
                $used_event = $found_advance[1];
                $found_on_calendar = $found_advance[2];
            }


            $date_start = $used_event->dtstart_array[1];
            $date_end = $used_event->dtend_array[1];


            $same_date = false;
            if (strcmp(substr($date_start, 0, -6), substr($date_end, 0, -6)) == 0) {
                $same_date = true;
            }

            // On récupère la time_zone qui va nous servir plus tard dans une autre fonction
            $this->time_zone_offset = $ical->iCalDateToDateTime($date_start)->getOffset();


            // Les tableaux $event->attendee_array & $event->organizer_array ont une structure
            // [1] => array() : attendee1_infos
            // [2] => string : attendee1_email
            // [3] => array() : attendee2_infos
            // ...
            // D'ou cette partie pour regrouper le mail et le nom d'un participant au sein du même sous tableau
            $id = 0;
            foreach (array_merge($used_event->organizer_array, $used_event->attendee_array) as $attendee) {

                if (!is_string($attendee) && array_key_exists('CN', $attendee)) {
                    $this->attendees[$id]['name'] = $attendee['CN'];
                    $this->attendees[$id]['onclick'] = "return " . rcmail_output::JS_OBJECT_NAME . ".command('compose','" . rcube::JQ(format_email_recipient($attendee["email"], $attendee['name'])) . "',this)";
                } elseif ($this->str_start_with($attendee, 'mailto:')) {
                    $this->attendees[$id]['email'] = substr($attendee, 7);
                    $id++;

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

            $display_caldav_info = $this->display_caldav_server_related_information($used_event); // 1



            $server = $this->rcube->config->get('server_caldav');
            $_used_calendars = $server['_used_calendars'];
            $main_calendar = $server['_main_calendar'];


            ob_start();
            include("plugins/roundcube_caldav/roundcube_caldav_display.php");
            $html = ob_get_clean();
            $content[] = $html;
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
        $_main_calendar = $server['_main_calendar'];
        $_used_calendars = $server['_used_calendars'];


        $meeting_collision = $this->meeting_collision_with_current_event_by_calendars($_main_calendar, $_used_calendars, $current_event); // 0.04s
        $info_cal_dav_server['collision'] = $meeting_collision;
        $close_meeting = $this->get_previous_and_next_meeting_by_calendars($_main_calendar, $_used_calendars, $current_event); //0.025s
        $info_cal_dav_server['close_meeting'] = $close_meeting;
        return $info_cal_dav_server;

    }

    /**
     * Affichage des événements en collision avec l'événement étudié sur tous les calendriers
     * disponible
     * @param $_main_calendar
     * @param $_used_calendars
     * @param $current_event
     * @return array
     */
    function meeting_collision_with_current_event_by_calendars($_main_calendar, $_used_calendars, $current_event)
    {

        $display_meeting_collision = array();

        foreach ($this->arrayOfCalendars as $calendar) {

            // On récupère les calendriers et on travaille qu'avec ceux définit dans les paramètres
            if ($calendar->getCalendarID() == $_main_calendar || array_key_exists($calendar->getCalendarID(), $_used_calendars)) {


                $display_meeting_collision[$calendar->getDisplayName()] = array();
                $has_collision_by_calendars = false;

                foreach ($this->all_events[$calendar->getCalendarID()] as $event_found_ics) {

                    // On recupère uniquement le fichier ics qui est dans la partie data pour le parser
                    $event_found_ical = new ICal\ICal($event_found_ics->getData());

                    // On regarde event par event, un fichier ics peut en contenir plusieurs (en cas de répétition)
                    foreach ($event_found_ical->events() as &$event_found) {

                        if ($event_found->uid != $current_event->uid) {
                            if ($this->is_there_an_overlap($current_event->dtstart_array[1], $current_event->dtend_array[1],
                                $event_found->dtstart_array[1], $event_found->dtend_array[1], $current_event->dtstart_array[2])) {

                                // Affichage de l'événement
                                $display_meeting_collision[$calendar->getDisplayName()][] = $event_found;
                                $has_collision_by_calendars = true;
                            }
                        }
                    }
                }
                if (!$has_collision_by_calendars) {
                    unset($display_meeting_collision[$calendar->getDisplayName()]);
                }
            }
        }
        return $display_meeting_collision;
    }

    /**
     * Affichage des événements précédents et suivants si ils existent sur les différents calendriers
     * @param $_main_calendar
     * @param $_used_calendars
     * @param $client
     * @param $current_event
     * @return array
     */
    function get_previous_and_next_meeting_by_calendars($_main_calendar, $_used_calendars, $current_event)
    {
        $close_meetings = array();
        $previous_meeting = array();
        $next_meeting = array();

        foreach ($this->arrayOfCalendars as $calendar) {
            // On récupère les calendriers et on travaille qu'avec ceux définit dans les paramètres
            if ($_used_calendars) {
                if ($calendar->getCalendarID() == $_main_calendar || array_key_exists($calendar->getCalendarID(), $_used_calendars)) {

                    // On definit le calendrier courant
                    $this->client->setCalendar($this->arrayOfCalendars[$calendar->getCalendarID()]);


                    $current_dtstart_minus_24h = date("Ymd\THis\Z", $current_event->dtstart_array[2] - $this->time_zone_offset - $this->previous_and_next_catch_meeting);
                    $current_dtend_plus_24h = date("Ymd\THis\Z", $current_event->dtend_array[2] - $this->time_zone_offset + $this->previous_and_next_catch_meeting);



                    $prev_res = $this->display_closest_meeting_by_calendars($current_event, $current_dtstart_minus_24h, $calendar, 'previous');
                    $next_res = $this->display_closest_meeting_by_calendars($current_event, $current_dtend_plus_24h, $calendar);

                    $previous_meeting[$prev_res['uid']] = $prev_res;
                    $next_meeting[$next_res['uid']] = $next_res;
                }
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
    function display_closest_meeting_by_calendars($current_event, $offset, $calendar, $opt = 'next')
    {

        $stock_closest_events = array();
        $has_meeting_by_calendars = false;
        foreach ($this->all_events[$calendar->getCalendarID()] as $event_found_ics) {
            // On recupère uniquement le fichier ics qui est dans la partie data pour le parser
            $event_found_ical = new ICal\ICal($event_found_ics->getData());

            // On regarde event par event, un fichier ics peut en contenir plusieurs (en cas de répétition)
            foreach ($event_found_ical->events() as &$event_found) {

                if ($event_found->uid != $current_event->uid) {
                    if (strcmp($opt, 'next') == 0) {

                        if ($this->is_after($current_event->dtend_array[1], $offset, $event_found->dtstart_array[1], $current_event->dtstart_array[2])) {

                            // On stocke tous les événements trouvés dans un tableau pour effectuer un tri ensuite
                            $stock_closest_events[$event_found->uid]['summary'] = $event_found->summary;
                            $stock_closest_events[$event_found->uid]['date_start'] = $event_found->dtstart_array[1];
                            $stock_closest_events[$event_found->uid]['date_end'] = $event_found->dtend_array[1];
                            $stock_closest_events[$event_found->uid]['uid'] = $event_found->uid;
                            $stock_closest_events[$event_found->uid]['calendar'] = $calendar->getDisplayName();

                            $has_meeting_by_calendars = true;
                        }
                    } else {
                        if ($this->is_before( $current_event->dtstart_array[1],$offset, $event_found->dtend_array[1], $current_event->dtstart_array[2])) {

                            $stock_closest_events[$event_found->uid]['summary'] = $event_found->summary;
                            $stock_closest_events[$event_found->uid]['date_start'] = $event_found->dtstart_array[1];
                            $stock_closest_events[$event_found->uid]['date_end'] = $event_found->dtend_array[1];
                            $stock_closest_events[$event_found->uid]['uid'] = $event_found->uid;
                            $stock_closest_events[$event_found->uid]['calendar'] = $calendar->getDisplayName();


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
    function is_after( $current_date_end,$current_date_end_with_offset, $date_start, $base_timestamp)
    {
        return strtotime($date_start, $base_timestamp) >= strtotime($current_date_end, $base_timestamp)
                && (strtotime($date_start, $base_timestamp) < strtotime($current_date_end_with_offset, $base_timestamp));
    }
    function is_before( $current_date_start,$current_date_start_with_offset, $date_end, $base_timestamp)
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


    /**
     * Récupère les informations concernant le fichier ics en pj ainsi que le calendrier choisi
     * et ajoute l'évenement dans ce calendrier selon le type d'ajout choisi :
     * - CONFIRMED
     * - TENTATIVE
     * - CANCELLED
     * @return bool
     * @throws Exception
     */
    function import_action()
    {

        $mail_uid = rcube_utils::get_input_value('_mail_uid', rcube_utils::INPUT_POST);
        $event_uid = rcube_utils::get_input_value('_event_uid', rcube_utils::INPUT_POST);
        $mbox = rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_POST);
        $type = rcube_utils::get_input_value('_type', rcube_utils::INPUT_POST);
        $chosen_calendar = rcube_utils::get_input_value('_calendar', rcube_utils::INPUT_POST);
        $chosen_date_start = rcube_utils::get_input_value('_chosenDateStart', rcube_utils::INPUT_POST);
        $chosen_date_end = rcube_utils::get_input_value('_chosenDateEnd', rcube_utils::INPUT_POST);
        $chosen_time_start = rcube_utils::get_input_value('_chosenTimeStart', rcube_utils::INPUT_POST);
        $chosen_time_end = rcube_utils::get_input_value('_chosenTimeEnd', rcube_utils::INPUT_POST);
        $chosen_location = rcube_utils::get_input_value('_chosenLocation', rcube_utils::INPUT_POST);


        // Récupération du mail
        $message = new rcube_message($mail_uid, $mbox);

        // Récupération de l'url, du login et du mdp
        $server = $this->rcube->config->get('server_caldav');
        $_urlbase = $server['_url_base'];
        $_password = $server['_password'];
        $_login = $server['_login'];
        $cipher = new password_encryption();
        $plain_password = $cipher->decrypt($_password, $this->rcube->config->get('des_key'), true);


        $this->connection_to_calDAV_server(); // ~2s
        $this->get_all_events();

        // Formatage de l'url du calendrier
        $calendar_id = explode('/', $this->arrayOfCalendars[$chosen_calendar]->getUrl());
        array_pop($calendar_id);
        $calendar_id = array_pop($calendar_id);

        $this->rcmail->output->command('plugin.affichage', array('request' => $chosen_location));

        foreach ($message->attachments as &$attachment) {
            if ($attachment->mimetype == 'text/calendar') {
                try {
                    // On récupère la PJ
                    $ics = $message->get_part_body($attachment->mime_id);


                    $ical = new \ICal\ICal($ics);
                    $array_event = $ical->events();
                    foreach ($array_event as $e) {
                        if ($e->uid == $event_uid) {
                            $event = $e;
                        }
                    }

                    $found_advance = $this->is_server_in_advance($event); //1s

                    if ($found_advance) {
                        $ics = $found_advance[0];
                        $event = $found_advance[1];
                    }

                    $has_modif = false;
                    $new_ics = $this->extract_event_ics($ics, $event_uid);

                    // On parse la date puis on remplace par la nouvelle date dans le fichier ics
                    if ($chosen_date_start && $chosen_date_end && $chosen_time_start && $chosen_time_end) {
                        $has_modif = true;
                        $new_date_start_int = strtotime($chosen_date_start . ' ' . $chosen_time_start);
                        $new_date_end_int = strtotime($chosen_date_end . ' ' . $chosen_time_end);
                        $new_date_start = date("Ymd\THis", $new_date_start_int - $this->time_zone_offset);
                        $new_date_end = date("Ymd\THis", $new_date_end_int - $this->time_zone_offset);


                        if (count($array_event) > 1) {

                            $offset_start = $new_date_start_int - $event->dtstart_array[2];
                            $offset_end = $new_date_end_int - $event->dtend_array[2];
                            $new_ics = $this->change_date_ics($new_date_start, $new_date_end, $new_ics, $offset_start, $offset_end);
                        } else {
                            $new_ics = $this->change_date_ics($new_date_start, $new_date_end, $new_ics);
                        }
                    }

                    // On remplace par le nouveau lieu dans le fichier ics
                    if ($chosen_location) {
                        $has_modif = true;
                        $new_ics = $this->change_location_ics($chosen_location, $new_ics);

                    }


                    // On change le status de l'événement en celui qui nous convient
                    $new_ics = $this->change_status_ics($type, $new_ics);

                    if ($has_modif) {
                        $new_ics = $this->change_last_modified_ics($new_ics);

                    }
                    $this->rcmail->output->command('plugin.affichage', array('request' => $new_ics));

                    // On cherche si le serveur possède déja un événement avec cet uid
                    $found_event_with_good_uid = $this->find_event_with_matching_uid($event, $chosen_calendar); //0.5

                    // On ajoute le fichier au serveur calDAV
                    if ($found_event_with_good_uid) {

                        $res = $this->add_ics_event_to_caldav_server($new_ics, $_urlbase, $calendar_id, $event_uid, $_login, $plain_password); // 0.6


                    } else {
                        $res = $this->add_ics_event_to_caldav_server($new_ics, $_urlbase, $calendar_id, $event_uid, $_login, $plain_password, $found_event_with_good_uid->getHref());
                    }

                } catch (CalDAVException $e) {
                    $this->rcmail->output->command('display_message', $e, 'error');
                }
            }
        }
        // On affiche une confirmation de l'ajout de l'événement
        if (strcmp($res, '') == 0) {
            $this->rcmail->output->command('display_message', $this->gettext('successfully_saved'), 'confirmation');
        } else {
            $this->rcmail->output->command('display_message', $this->gettext('something_happened') . $res, 'error');
        }

        return $res;
    }

    /**
     * Fonction permettant la connexion au serveur calDAV ainsi que l'ajout du fichier ics
     * Si le serveur possede déja un événement avec le même uid on lui fournit l'url de cet événement
     * @param $ics
     * @param $url_base
     * @param $calendar_id
     * @param $uid
     * @param $login
     * @param $password
     * @param null $href url de l'évenement si celui çi existe sur le serveur mais qu'on souhaite le modifier
     * @return bool
     */
    function add_ics_event_to_caldav_server($ics, $url_base, $calendar_id, $uid, $login, $password, $href = null)
    {
        // create curl resource
        $ch = curl_init();

        // On supprime le champ METHOD du fichier ics qui bloque l'ajout
        $pos_status = strpos($ics, 'METHOD:');
        if ($pos_status > 0) {
            $deb = substr($ics, 0, $pos_status);
            $end = substr($ics, $pos_status);
            $count_until_endline = strpos($end, "\n");
            $end = substr($end, $count_until_endline);
            $ics = $deb . $end;
        }

        // on formate l'url sur laquelle on veut déposer notre event
        $url = $url_base . '/' . $calendar_id . '/' . $uid . '.ics';

        // Si href n'est pas nul alors on remplace l'url par href pour récupérer le bon événement
        if ($href != null) {
            $url = $href;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERPWD, $login . ':' . $password);

        $headers = array("Depth: 1", "Content-Type: text/calendar; charset='UTF-8'");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $ics);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // $output contains the output string
        $output = curl_exec($ch);

        if ($errno = curl_errno($ch)) {
            $err = curl_strerror($errno);
        }

        // close curl resource to free up system resources
        curl_close($ch);


        return $output;
    }


    /**
     * Récupere le status d'un événement si celui çi existe déja sur le serveur puis l'envoie au client pour l'affichage
     * correct des boutons
     */
    function get_status()
    {
        $uid = rcube_utils::get_input_value('_uid', rcube_utils::INPUT_POST);
        $mbox = rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_POST);
        $chosen_calendar = rcube_utils::get_input_value('_calendar', rcube_utils::INPUT_POST);

        // Récupération du mail
        $message = new rcube_message($uid, $mbox);
        $this->connection_to_calDAV_server();
        foreach ($message->attachments as &$attachment) {
            if ($attachment->mimetype == 'text/calendar') {
                try {
                    $ics = $message->get_part_body($attachment->mime_id);
                    $ical = new ICal\ICal($ics);
                    foreach ($ical->events() as $event) {

                        $ics_found = $this->find_event_with_matching_uid($event, $chosen_calendar);
                        if (!empty($ics_found)) {
                            $ical = new ICal\ICal($ics_found->getData());
                            foreach ($ical->events() as &$event_found) {
                                // Si l'on a trouvé un événement avec le meme uid sur le serveur on indique au client quel status pour qu'il puisse afficher correctement les boutons
                                if (isset($event_found->status)) {
                                    $this->rcmail->output->command('plugin.change_status', array('request' => $event_found->status));
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                }
            }
        }
    }

    /**
     * récupération des événements possédant le même uid sur le serveur si il existe
     * @param $event
     * @param $calendar
     * @return mixed
     */
    function find_event_with_matching_uid($event, $calendar) //0.5
    {
        foreach ($this->all_events[$calendar] as $event_found) {
            $uid = array();
            preg_match("/UID:(\S+)/", $event_found->getData(), $uid);

            if ($uid[1] == $event->uid) {
                return $event_found;

            }
        }
        return null;

    }

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
    function change_date_ics($new_date_start, $new_date_end, $ics, $offset_start = null, $offset_end = null)
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


            $new_date_start_second_event = date("Ymd\THis", strtotime($array_dtstart[1]) + $offset_start - $this->time_zone_offset);
            $new_date_end_second_event = date("Ymd\THis", strtotime($array_dtend[1]) + $offset_end - $this->time_zone_offset);

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
            if (preg_match('@LOCATION:@', $section) > 0) {
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
        if (strcmp("CONFIRMED", $status) == 0) {
            $status = 'ACCEPTED';
        }

        $identity = $this->rcmail->user->list_identities(null, true);
        $email = $identity[0]['email'];
        $sections = preg_split('@(\n(?! ))@m', $ics);

        foreach ($sections as &$section) {

            if (preg_match('@ATTENDEE@', $section) == 1) {
                if (preg_match('/' . $email . '/', $section) == 1) {
                    $section = implode('', explode("\r\n ", $section));
                    $attributes = explode(';', $section);
                    foreach ($attributes as &$attribute) {

                        $parts = explode('=', $attribute);
                        $command = $parts[0];
                        if (strcmp($command, 'PARTSTAT') == 0) {
                            $parts[1] = $status;
                            $attribute = implode('=', $parts);
                        }
                    }
                    $section = implode("\r\n ", str_split(implode(';', $attributes), 75));
                }
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
        $ics = preg_replace("@LAST-MODIFIED:.*@", "LAST-MODIFIED:" . $new_date, $ics);

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
     * Gere la connexion au serveur calDAV et retourne la valeur de succes ou d'echec et initialise les calendriers
     * @return bool
     * @throws Exception
     */
    public function connection_to_calDAV_server()
    {

        $server = $this->rcube->config->get('server_caldav');
        $_login = $server['_login'];
        $_password = $server['_password'];
        $_url_base = $server['_url_base'];
        $_connexion = $server['_connexion_status'];

        if ($this->connected) {
            return true;
        } elseif ($_connexion) {
            try{
                $success = $this->try_connection($_login, $_password, $_url_base); //0.86
                $this->arrayOfCalendars = $this->client->findCalendars(); //1.53
                $this->connected = true;
                return $success;
            }catch (Exception $e){
                return false;
            }
        } else {
            return false;
        }



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


    //PAS TRES PROPRE

    /**
     * On vérifie qu'il n'existe pas d'autre événement plus récent sur notre serveur.
     * On retourne [ ics de l'evt, Objet Evt parsé, le calendrier auquel apartient l'evt ] ou false dans
     * le cas ou aucun n'evt plus récent n'est trouvé
     * @param $event
     * @return array|false
     */
    public function is_server_in_advance($event) // 1.5
    {
        $is_server_in_advance = array();
        $stockage_ics = array();

        // On regarde dans tous les calendriers
        foreach ($this->arrayOfCalendars as $calendar ) {
            // On trouve les événements qui matchent dans chacun des calendriers
            $event_found_on_server = $this->find_event_with_matching_uid($event, $calendar->getCalendarID()); // 0.5 *2 = 1s

            if ($event_found_on_server) {
                $event_ics = $event_found_on_server->getData();
                $ical_found = new \ICal\ICal($event_ics);
                // On parse l'evt trouvé pour avoir sa séquence
                $event_found = $ical_found->events()[0];


                // On compare les séquence avec l'evt courant et on stocke les evt avec une séquence supérieure
                if ($event->sequence < $event_found->sequence) {
                    $is_server_in_advance[$calendar->getDisplayName()] = $event_found;
                    $stockage_ics[$calendar->getDisplayName()] = $event_ics;
                }
            }

        }
        if (!empty($is_server_in_advance)) {
            // On initialise les valeurs avec le premier elt des tableaux
            $found_on_calendar = array_key_first($is_server_in_advance);
            $event_with_higher_sequence = array_shift($is_server_in_advance);
            $ics_to_return = array_shift($stockage_ics);


            // On compare toutes les evt obtenu selon leur séquence
            foreach ($is_server_in_advance as $calendar => $event_found) {
                if ($event_with_higher_sequence->sequence < $event_found->sequence) {
                    $event_with_higher_sequence = $event_found;
                    $ics_to_return = $stockage_ics[$calendar];

                    $found_on_calendar = $calendar;
                }
            }

            return [$ics_to_return, $event_with_higher_sequence, $found_on_calendar];
        } else {
            return false;
        }
    }

    public function get_all_events(): void
    {
        $begin_of_unix_timestamp = date("Ymd\THis\Z", 0);
        $end_of_unix_timestamp = date("Ymd\THis\Z", 2 ** 31);
        foreach ($this->arrayOfCalendars as $calendar) {
            $this->client->setCalendar($this->arrayOfCalendars[$calendar->getCalendarID()]);

            $this->all_events[$calendar->getCalendarID()] = $this->client->getEvents($begin_of_unix_timestamp, $end_of_unix_timestamp);

        }
    }


}

