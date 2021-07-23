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
    protected $client_plus;
    protected $time_zone_offset;
    protected $previous_and_next_catch_meeting = 86400;
    protected $attendees = array();
    protected $connect = false;

    function init()
    {
        $this->rcmail = rcmail::get_instance();
        $this->rcube = rcube::get_instance();
        $this->include_script('roundcube_caldav.js');
        $this->load_config();
        $this->add_texts('localization/');

        $server = $this->rcube->config->get('server_caldav');
        $_login = $server['_login'];
        $_password = $server['_password'];
        $_url_base = $server['_url_base'];
        $_connexion = $server['_connexion_status'];

        if (!empty($_url_base) && !empty($_login) && !empty($_password) && $_connexion) {
            // Récupération du mot de passe chiffré dans la bdd et décodage
            $cipher = new password_encryption();
            $plain_password = $cipher->decrypt($_password, $this->rcube->config->get('des_key'), true);

            //  Connexion au serveur calDAV et récupération des calendriers dispos
            $this->client = new SimpleCalDAVClient();
            try {
                $this->client->connect($_url_base, $_login, $plain_password);
                $this->client_plus = new CalDAVClient($_url_base, $_login, $plain_password);
            } catch (CalDAVException $e) {
                exit();
            }
        }

        $this->add_hook('preferences_sections_list', array($this, 'modify_section'));
        $this->add_hook('preferences_list', array($this, 'preferences_list'));
        $this->add_hook('preferences_save', array($this, 'preferences_save'));


        if ($_connexion) {
            $this->add_hook('message_objects', array($this, 'message_objects'));
            $this->register_action('plugin.accept_action', array($this, 'accept_action'));
        }

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

        if (!empty($server['_url_base']) && !empty($server['_login']) && !empty($server['_password']) && $server['_connexion_status']) {
            $param_list = $this->connection_server_calDAV($param_list);
        }
        return $param_list;
    }

    function try_connection($_login, $_password, $_url_base)
    {
        if (!empty($_url_base) && !empty($_login) && !empty($_password)) {
            // Récupération du mot de passe chiffré dans la bdd et décodage
            $cipher = new password_encryption();
            $plain_password = $cipher->decrypt($_password, $this->rcube->config->get('des_key'), true);

            //  Connexion au serveur calDAV et récupération des calendriers dispos
            $client = new SimpleCalDAVClient();
            try {
                $client->connect($_url_base, $_login, $plain_password);
            } catch (CalDAVException $e) {
                return false;
            }
            return true;
        }
        return false;
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
                $urlbase = rcube_utils::get_input_value('_define_server_caldav', rcube_utils::INPUT_POST);
                $save_params['prefs']['server_caldav']['_url_base'] = $urlbase;
                $login = rcube_utils::get_input_value('_define_login', rcube_utils::INPUT_POST);
                $save_params['prefs']['server_caldav']['_login'] = $login;

                if (!empty($_POST['_define_password'])) {
                    $ciphered_password = $cipher->encrypt(rcube_utils::get_input_value('_define_password', rcube_utils::INPUT_POST), $this->rcube->config->get('des_key'), true);
                    $save_params['prefs']['server_caldav']['_password'] = $ciphered_password;
                    $save_params['prefs']['server_caldav']['_connexion_status'] = $this->try_connection($login, $ciphered_password, $urlbase);
                } elseif (array_key_exists('_password', $this->rcube->config->get('server_caldav'))) {
                    $save_params['prefs']['server_caldav']['_password'] = $this->rcube->config->get('server_caldav')['_password'];
                    $save_params['prefs']['server_caldav']['_connexion_status'] = $this->try_connection($login, $this->rcube->config->get('server_caldav')['_password'], $urlbase);
                }

                $main_calendar = rcube_utils::get_input_value('_define_main_calendar', rcube_utils::INPUT_POST);
                $save_params['prefs']['server_caldav']['_main_calendar'] = $main_calendar;
                $save_params['prefs']['server_caldav']['_used_calendars'][$main_calendar] = $main_calendar;

                $chosen_calendars = array(rcube_utils::get_input_value('_define_used_calendars', rcube_utils::INPUT_POST));
                foreach ($chosen_calendars as $cal) {
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
        $server = $this->rcube->config->get('server_caldav');

        $param_list['blocks']['main']['options']['calendar_choice'] = array(
            'title' => html::label('ojk', rcube::Q($this->gettext('calendar_choice'))),
        );

        try {
            $arrayOfCalendars = $this->client->findCalendars();


            foreach ($arrayOfCalendars as $cal) {
                $print = null;
                foreach ($server['_used_calendars'] as $used_calendar) {
                    if ($used_calendar == $cal->getCalendarID()) {
                        $print = $cal->getCalendarID();
                    }

                }
                if ($server['_main_calendar'] == $cal->getCalendarID()) {
                    $print = $cal->getCalendarID();
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


    function accept_action()
    {
        $uid = rcube_utils::get_input_value('_uid', rcube_utils::INPUT_POST);
        $mbox = rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_POST);
        $type = rcube_utils::get_input_value('_type', rcube_utils::INPUT_POST);
        $message = new rcube_message($uid, $mbox);

        $server = $this->rcube->config->get('server_caldav');
        $_main_calendar = $server['_main_calendar'];
        $_urlbase = $server['_url_base'];
        $_password = $server['_password'];
        $_login = $server['_login'];
        $cipher = new password_encryption();
        $plain_password = $cipher->decrypt($_password, $this->rcube->config->get('des_key'), true);

        $_used_calendars = $server['_used_calendars'];

        $arrayOfCalendars = $this->client->findCalendars();

        $calendar_id = explode('/', $arrayOfCalendars[$_main_calendar]->getUrl());
        array_pop($calendar_id);
        $calendar_id = array_pop($calendar_id);


        if (strcmp($type, 'CONFIRMED') == 0) {
            foreach ($message->attachments as &$attachment) {
                if ($attachment->mimetype == 'text/calendar') {
                    try {
                        $ics = $message->get_part_body($attachment->mime_id);
                        $ics = $this->change_ics_status($ics, $type);
                        $this->client_plus->SetCalendar($arrayOfCalendars[$_main_calendar]);
                        $this->client->setCalendar($arrayOfCalendars[$_main_calendar]);
                        $ical = new ICal\ICal($ics);


                        foreach ($ical->events() as $event) {


                            $found_event_with_good_uid = $this->find_event_with_matching_uid($event);

                            if (empty($found_event_with_good_uid)) {
                                $this->rcmail->output->command('plugin.accept', array('request' => 'ok create Accept'));
                                $ret = $this->connexion_with_curl($ics, $_urlbase, $calendar_id, $event->uid, $_login, $plain_password);
                                $this->rcmail->output->command('plugin.accept', array('request' => $ret));


                            } else {

                                $this->rcmail->output->command('plugin.accept', array('request' => 'ok change Accept'));
                                $ret = $this->connexion_with_curl($ics, $_urlbase, $calendar_id, $event->uid, $_login, $plain_password,$found_event_with_good_uid[0]->getEtag());
                                $this->rcmail->output->command('plugin.accept', array('request' => $ret));
                            }
                        }
                    } catch (CalDAVException $e) {
                        $this->rcmail->output->command('plugin.accept', array('request' => $e->getMessage() . $e->getResponseHeader()));
                    }
                }
            }
        } elseif (strcmp($type, 'TENTATIVE') == 0) {
            foreach ($message->attachments as &$attachment) {
                if ($attachment->mimetype == 'text/calendar') {
                    try {
                        $ics = $message->get_part_body($attachment->mime_id);
                        $ics = $this->change_ics_status($ics, $type);
                        $this->client_plus->SetCalendar($arrayOfCalendars[$_main_calendar]);
                        $this->client->setCalendar($arrayOfCalendars[$_main_calendar]);
                        $ical = new ICal\ICal($ics);
                        foreach ($ical->events() as $event) {

                            $found_event_with_good_uid = $this->find_event_with_matching_uid($event);

                            if (empty($found_event_with_good_uid)) {
                                $this->rcmail->output->command('plugin.accept', array('request' => 'ok create Tentative'));
                                $ret = $this->connexion_with_curl($ics, $_urlbase, $calendar_id, $event->uid, $_login, $plain_password);
                                $this->rcmail->output->command('plugin.accept', array('request' => $ret));
                            } else {
                                $this->rcmail->output->command('plugin.accept', array('request' => 'ok change tentative'));
                                $ret = $this->connexion_with_curl($ics, $_urlbase, $calendar_id, $event->uid, $_login, $plain_password,$found_event_with_good_uid[0]->getEtag());
                                $this->rcmail->output->command('plugin.accept', array('request' => $ret));


                            }

                        }
                    } catch (CalDAVException $e) {
                        $this->rcmail->output->command('plugin.accept', array('request' => 'attention' . $e->getResponseHeader()));
                    }
                }
            }
        }
    }

    function connexion_with_curl($ics, $url_base, $calendar_id, $uid, $login, $password, $etag = null)
    {
        // create curl resource
        $ch = curl_init();


        $pos_status = strpos($ics, 'METHOD:');
        if ($pos_status > 0) {
            $deb = substr($ics, 0, $pos_status);
            $end = substr($ics, $pos_status);
            $count_until_endline = strpos($end, "\n");
            $end = substr($end, $count_until_endline);
            $ics = $deb . $end;
        }

        $headers = array("Depth: 1", "Content-Type: text/calendar; charset='UTF-8'");
//        if($etag!=null){
//            $ics = array_push($headers,sprintf("If-Match: \"%s\"",$etag));
//        }

        // set url
        $url = $url_base . '/' . $calendar_id . '/' . $uid . '.ics';
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERPWD, $login . ':' . $password);

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
        $info = curl_getinfo($ch);
        return var_export($output, true) . $err . $info['http_code'];
    }

    function change_ics_status($ics, $status)
    {
        $pos_status = strpos($ics, 'STATUS:');
        if ($pos_status > 0) {
            $deb = substr($ics, 0, $pos_status + 7);
            $end = substr($ics, $pos_status + 7);
            $count_until_endline = strpos($end, "\n");
            $end = substr($end, $count_until_endline);
            return $deb . $status . $end;
        } else {
            $pos_end_event = strpos($ics, 'END:VEVENT');
            $deb = substr($ics, 0, $pos_end_event);
            $end = substr($ics, $pos_end_event);
            return $deb . "STATUS:" . $status . "\n" . $end;
        }
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

                if (!is_string($attendee) && array_key_exists('CN', $attendee) && $email) {
                    $this->attendees[$id]['name'] = $attendee['CN'];
                    $email = false;
                } elseif (!is_string($attendee) && array_key_exists('CN', $attendee)) {
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
            $display_caldav_info = $this->display_caldav_server_related_information($event);

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
        $_main_calendar = $server['_main_calendar'];
        $_used_calendars = $server['_used_calendars'];


        try {
            $arrayOfCalendars = $this->client->findCalendars();

            $meeting_collision = $this->meeting_collision_with_current_event_by_calendars($arrayOfCalendars, $_main_calendar, $_used_calendars, $current_event);

            $previous_meeting = $this->get_previous_and_next_meeting_by_calendars($arrayOfCalendars, $_main_calendar, $_used_calendars, $current_event);

        } catch (Exception $e) {
            echo $e;
        }
        $info_cal_dav_server['collision'] = $meeting_collision;
        $info_cal_dav_server['close_meeting'] = $previous_meeting;
//        var_dump($info_cal_dav_server['collision']);
        return $info_cal_dav_server;
    }

    /**
     * Affichage des événements en collision avec l'événement étudié sur tous les calendriers
     * disponible
     * @param $arrayOfCalendars
     * @param $_main_calendar
     * @param $_used_calendars
     * @param $current_event
     * @return array
     * @throws CalDAVException
     *
     *
     */
    function meeting_collision_with_current_event_by_calendars($arrayOfCalendars, $_main_calendar, $_used_calendars, $current_event)
    {
        $display_meeting_collision = array();
        $meeting_collision = '';
        $has_collision = false;

        foreach ($arrayOfCalendars as $calendar) {

            // On récupère les calendriers et on travaille qu'avec ceux définit dans les paramètres
            if ($calendar->getCalendarID() == $_main_calendar || array_key_exists($calendar->getCalendarID(), $_used_calendars)) {

                $this->client->setCalendar($arrayOfCalendars[$calendar->getCalendarID()]);


                //On preselectionne les fichiers ics en collision avec la date du fichier reçu
                $curr_date_start_with_offset = date("Ymd\THis\Z", $current_event->dtstart_array[2] - $this->time_zone_offset);
                $curr_date_end_with_offset = date("Ymd\THis\Z", $current_event->dtend_array[2] - $this->time_zone_offset);

                $rapport = $this->client->getEvents($curr_date_start_with_offset, $curr_date_end_with_offset);

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
    function get_previous_and_next_meeting_by_calendars($arrayOfCalendars, $_main_calendar, $_used_calendars, $current_event)
    {
        $close_meetings = array();
        $previous_meeting = array();
        $next_meeting = array();

        foreach ($arrayOfCalendars as $calendar) {
            // On récupère les calendriers et on travaille qu'avec ceux définit dans les paramètres
            if ($_used_calendars) {
                if ($calendar->getCalendarID() == $_main_calendar || array_key_exists($calendar->getCalendarID(), $_used_calendars)) {

                    // On definit le calendrier courant
                    $this->client->setCalendar($arrayOfCalendars[$calendar->getCalendarID()]);

                    // On convertit la date de l'événement courant dans le GMT
                    $current_dtstart_with_offset = date("Ymd\THis\Z", $current_event->dtstart_array[2] - $this->time_zone_offset);
                    $current_dtend_with_offset = date("Ymd\THis\Z", $current_event->dtend_array[2] - $this->time_zone_offset);

                    $current_dtstart_minus_24h = date("Ymd\THis\Z", $current_event->dtstart_array[2] - $this->time_zone_offset - $this->previous_and_next_catch_meeting);
                    $current_dtend_plus_24h = date("Ymd\THis\Z", $current_event->dtend_array[2] - $this->time_zone_offset + $this->previous_and_next_catch_meeting);


                    //On preselectionne les fichiers ics en collision avec la date du fichier reçu
                    $get_all_previous_meeting_found = $this->client->getEvents($current_dtstart_minus_24h, $current_dtstart_with_offset);
                    $get_all_next_meeting_found = $this->client->getEvents($current_dtend_with_offset, $current_dtend_plus_24h);

                    // On prepare le nom du calendrier que l'on affichera uniquement si un evenement est prévu  pour ce calendrier
                    $calendar_name = $calendar->getDisplayName();

                    // On recherche enfin l'evement le plus proche
                    $prev_res = $this->display_closest_meeting_by_calendars($get_all_previous_meeting_found, $current_event, $current_dtstart_minus_24h, $calendar_name, 'previous');
                    $next_res = $this->display_closest_meeting_by_calendars($get_all_next_meeting_found, $current_event, $current_dtend_plus_24h, $calendar_name);

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

    /**
     * @param $event
     * @return array
     */
    public function find_event_with_matching_uid($event)
    {
        $current_dtstart_with_offset = date("Ymd\THis\Z", $event->dtstart_array[2] - $this->time_zone_offset - 20000);
        $current_dtend_with_offset = date("Ymd\THis\Z", $event->dtend_array[2] - $this->time_zone_offset + 20000);


        $found_events = $this->client->getEvents($current_dtstart_with_offset, $current_dtend_with_offset);
        if (empty($found_events)) {
            $this->rcmail->output->command('plugin.accept', array('request' => "not found"));
        }

        $found_event_with_good_uid = array();
        foreach ($found_events as &$event_found_ics) {
            $event_found_ical = new ICal\ICal($event_found_ics->getData());

            // On regarde event par event, un fichier ics peut en contenir plusieurs (en cas de répétition)
            foreach ($event_found_ical->events() as &$event_found) {
                if ($event_found->uid == $event->uid) {
                    array_push($found_event_with_good_uid, $event_found_ics);
                }
            }
        }
        return $found_event_with_good_uid;
    }


}

