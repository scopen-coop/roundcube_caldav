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
ini_set("xdebug.var_display_max_children", '-1');
ini_set("xdebug.var_display_max_data", '-1');
ini_set("xdebug.var_display_max_depth", '-1');

use ICal\Event;
use ICal\ICal;
use it\thecsea\simple_caldav_client\CalDAVCalendar;
use it\thecsea\simple_caldav_client\CalDAVException;
use it\thecsea\simple_caldav_client\SimpleCalDAVClient;

require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/lib/php/password_encryption.php');
require_once(__DIR__ . '/lib/php/ics_file_modification.php');
require_once(__DIR__ . '/lib/php/event_comparison.php');
require_once(__DIR__ . '/lib/php/set_response.php');

class roundcube_caldav extends rcube_plugin
{

    public $task = 'settings|mail';
    public $rcube;
    public $rcmail;

    /** @var SimpleCalDAVClient $client */
    protected $client;
    protected $time_zone_offset;
    protected $twenty_four_hour = 86400;
    protected $arrayOfCalendars;
    protected $all_events = [];
    protected $connected = false;

    function init()
    {
        $this->rcmail = rcmail::get_instance();
        $this->rcube = rcube::get_instance();
        $this->load_config();

        $this->add_texts('localization/', true);
        $this->include_script('roundcube_caldav.js');
        $this->include_script('lib/js/display.js');
        $this->include_stylesheet('skins/roundcube_caldav.css');

        $this->include_script('vendor/fortawesome/font-awesome/js/all.js');
        $this->include_stylesheet('vendor/fortawesome/font-awesome/css/all.css');

        $this->add_hook('preferences_sections_list', [$this, 'preference_sections_list']);
        $this->add_hook('preferences_list', [$this, 'preferences_list']);
        $this->add_hook('preferences_save', [$this, 'preferences_save']);

        $server = $this->rcube->config->get('server_caldav');
        
        if (is_array($server) && array_key_exists('_connexion_status', $server)) {
            $_connexion = $server['_connexion_status'];

            if ($_connexion && $server['_main_calendar'] != null) {
                $this->add_hook('message_objects', [$this, 'message_objects']);
                
                $this->register_action(
                    'plugin.roundcube_caldav_get_info_server',
                    [$this, 'get_info_server']
                );
                
                $this->register_action(
                    'plugin.roundcube_caldav_import_event_on_server',
                    [$this, 'import_event_on_server']
                );
                $this->register_action(
                    'plugin.roundcube_caldav_decline_counter',
                    [$this, 'decline_counter']
                );
            }
        }
    }

    /**
     * Displaying the "CalDAV server configuration" section
     * @param $param_list
     * @return array
     */
    function preference_sections_list($param_list): array
    {
        $param_list['list']['server_caldav'] = array(
            'id'      => 'server_caldav',
            'section' => $this->gettext('server_caldav'),
        );
        return $param_list;
    }

    /**
     * Display of the different fields of the section
     * @param $param_list
     * @return array
     */
    function preferences_list($param_list): array
    {

        if ($param_list['section'] != 'server_caldav') {
            return $param_list;
        }
        $param_list['blocks']['main']['name'] = $this->gettext('settings');

        $param_list = $this->display_server_caldav_form($param_list);

        $server = $this->rcube->config->get('server_caldav');
        
        if (
            !empty($server['_url_base'])
            && !empty($server['_login'])
            && !empty($server['_password'])
            && $server['_connexion_status']
        ) {
            $param_list = $this->calendar_selection_form($param_list);
        }
        return $param_list;
    }

    /**
     * Saving the preferences once the different fields are filled in.
     * A new backup deletes all the data previously saved, so be sure to save everything again
     * @param $save_params
     * @return array : $save_param['prefs'] will be saved
     * @throws Exception
     */
    function preferences_save($save_params): array
    {
        $cipher = new password_encryption();
        if ($save_params['section'] != 'server_caldav') {
            return $save_params;
        }

        if (empty($_POST['_define_server_caldav']) || empty($_POST['_define_login'])) {
            $this->rcmail->output->command(
                'display_message', 
                $this->gettext('save_error_msg'),
                'error'
            );
            
            $save_params['abort'] = true;
            $save_params['result'] = false;
            return $save_params;
        }

        // On récupère l'url et le login dans les champs (ils sont remplis par défault avec l'ancienne valeur)
        $urlbase = rcube_utils::get_input_value('_define_server_caldav', rcube_utils::INPUT_POST);
        $urlbase = preg_replace('/(.*)personal\/*$/', '$1', $urlbase);
        $save_params['prefs']['server_caldav']['_url_base'] = $urlbase;
        $login = rcube_utils::get_input_value('_define_login', rcube_utils::INPUT_POST);
        $save_params['prefs']['server_caldav']['_login'] = $login;

        $server = $this->rcube->config->get('server_caldav');

        // Si le mot de passe est spécifié on le change et on teste la connexion sinon on récupère l'ancien
        $new_password = false;
        
        if (!empty($_POST['_define_password'])) {
            $pwd = rcube_utils::get_input_value('_define_password', rcube_utils::INPUT_POST, true);
            $save_params['prefs']['server_caldav']['_password'] = 
                    $cipher->encrypt($pwd,$this->rcube->config->get('des_key'), true);
            
            if ($cipher->decrypt($server['_password'], $this->rcube->config->get('des_key'),true) != $pwd) {
                $new_password = true;
            }
        } elseif (array_key_exists('_password', $server)) {
            $save_params['prefs']['server_caldav']['_password'] = $server['_password'];
        }

        try {
            $save_params['prefs']['server_caldav']['_connexion_status'] = $this->try_connection(
                $login,
                $save_params['prefs']['server_caldav']['_password'],
                $urlbase
            );
        } catch (Exception $e) {
            $this->rcmail->output->command(
                'display_message',
                $this->gettext('save_error_msg') . "\n" . $e->getMessage(),
                'error'
            );
            
            $save_params['abort'] = true;
            $save_params['result'] = false;
            return $save_params;
        }

        if ($save_params['prefs']['server_caldav']['_connexion_status'] && $new_password) {
            return $save_params;
        } elseif (!$save_params['prefs']['server_caldav']['_connexion_status']) {
            $this->rcmail->output->command(
                'display_message', 
                $this->gettext('save_error_msg'),
                'error'
            );
            
            $save_params['abort'] = true;
            $save_params['result'] = false;
            return $save_params;
        }

        if (!isset($_POST['_define_main_calendar'])) {
            $this->rcmail->output->command(
                'display_message',
                $this->gettext('main_calendar_error'),
                'error'
            );
            
            $save_params['abort'] = true;
            $save_params['result'] = false;
            return $save_params;
        }

        // on récupère le calendrier principal que l'on ajoute également à la liste des calendriers utilisés
        // si aucun calendrier principal n'est selectionné on annule la sauvegarde
        $main_calendar = rcube_utils::get_input_value(
                '_define_main_calendar',
                rcube_utils::INPUT_POST
         );

        if ($main_calendar) {
            $save_params['prefs']['server_caldav']['_main_calendar'] = $main_calendar;
            $save_params['prefs']['server_caldav']['_used_calendars'][$main_calendar] = $main_calendar;
        }

        // On sauvegarde la liste des calendriers secondaires
        $chosen_calendars = [
            rcube_utils::get_input_value('_define_used_calendars',rcube_utils::INPUT_POST)
        ];
        
        if (!empty($chosen_calendars[0])) {
            foreach ($chosen_calendars[0] as $cal) {
                $save_params['prefs']['server_caldav']['_used_calendars'][$cal] = $cal;
            }
        }

        return $save_params;
    }

    /**
     * Display url / login / password fields
     * @param array $param_list
     * @return array
     */
    function display_server_caldav_form(array $param_list): array
    {
        $server = $this->rcube->config->get('server_caldav');

        $_url_base_server = '';
        $_login_server = '';

        if (is_array($server) && array_key_exists('_url_base', $server)) {
            $_url_base_server = $server['_url_base'];
        }
        
        if (is_array($server) && array_key_exists('_login', $server)) {
            $_login_server = $server['_login'];
        }

        // Champs pour specifier l'url du serveur
        $field_id = 'define_server_caldav';
        $url_base = new html_inputfield(array('name' => '_' . $field_id, 'id' => $field_id));
       
        $param_list['blocks']['main']['options']['url_base'] = array(
            'title'   => html::label($field_id, rcube::Q($this->gettext('url_base'))),
            'content' => $url_base->show($_url_base_server),
        );

        // Champs pour specifier le login
        $field_id = 'define_login';
        $login = new html_inputfield(array('name' => '_' . $field_id, 'id' => $field_id));
       
        $param_list['blocks']['main']['options']['login'] = array(
            'title'   => html::label($field_id, rcube::Q($this->gettext('login'))),
            'content' => $login->show($_login_server),
        );

        // Champs pour specifier le mot de passe
        $field_id = 'define_password';
        
        $password = new html_passwordfield([
            'name' => '_' . $field_id, 
            'id' => $field_id,
            'autocomplete' => "off"
        ]);
        
        $param_list['blocks']['main']['options']['password'] = array(
            'title'   => html::label($field_id, rcube::Q($this->gettext('password'))),
            'content' => $password->show(),
        );
        
        return $param_list;
    }

    /**
     * Connection with the calDAV server and display of the calendar selection fields if the connection is successful
     * @param array $param_list
     * @return array
     */
    function calendar_selection_form(array $param_list): array
    {

        // Connexion to caldav server
        $server = $this->rcube->config->get('server_caldav');
        $_login = $server['_login'];
        $_password = $server['_password'];
        $_url_base = $server['_url_base'];

        try {
            $success = $this->try_connection($_login, $_password, $_url_base);
        } catch (Exception $e) {
            $this->rcmail->output->command(
                'display_message',
                $this->gettext('connect_error_msg') . "\n" . $e->getMessage(),
                'error'
            );
            
            return $param_list;
        }

        if (!$success) {
            $this->rcmail->output->command(
                    'display_message',
                    $this->gettext('connect_error_msg'),
                    'error'
            );
            
            return $param_list;
        }
        
        try {
            $available_calendars = $this->client->findCalendars();
        } catch (Exception $e) {
            $this->rcmail->output->command(
                    'display_message', 
                    $this->gettext('find_calendar_error'),
                    'error'
            );
            
            return $param_list;
        }
        
        if (empty($available_calendars)) {
            $this->rcmail->output->command(
                    'display_message',
                    $this->gettext('no_calendar_found'),
                    'error'
            );
            
            return $param_list;
        }
        
        $param_list['blocks']['main']['options']['calendar_choice'] = array(
            'title' => html::label('ojk', rcube::Q($this->gettext('calendar_choice'))),
        );
        
        foreach ($available_calendars as $available_calendar) {
            $print = null;
           
            if ($server['_main_calendar'] == $available_calendar->getCalendarID()) {
                $print = $available_calendar->getCalendarID();
            }
            
            if (!empty($server['_used_calendars'])) {
                foreach ($server['_used_calendars'] as $used_calendar) {
                    if ($used_calendar == $available_calendar->getCalendarID()) {
                        $print = $available_calendar->getCalendarID();
                        break;
                    }
                }
            }

            $checkbox = new html_checkbox(array('name' => '_define_used_calendars[]', 'value' => $available_calendar->getCalendarID()));
            $radiobutton = new html_radiobutton(array('name' => '_define_main_calendar', 'value' => $available_calendar->getCalendarID()));

            $param_list['blocks']['main']['options'][$available_calendar->getCalendarID() . 'radiobutton'] = array(
                'title'   => html::label(
                    $available_calendar->getCalendarID(),
                    $this->gettext("use_this_calendar") . $available_calendar->getDisplayName()
                ),
                'content' => $checkbox->show($print)
            );
            
            $param_list['blocks']['main']['options'][$available_calendar->getCalendarID() . 'checkbox'] = array(
                'title'   => html::label(
                    $available_calendar->getCalendarID(),
                    $this->gettext("make_this_calendar_default1") . $available_calendar->getDisplayName() . $this->gettext("make_this_calendar_default2")
                ),
                'content' => $radiobutton->show($server['_main_calendar']),
            );
        }
        return $param_list;
    }

    /**
     * Check if the mail we want to watch contains or not a text/calendar attachment.
     * If it does, we can proceed to the loading of the page (which will be recovered by the javascript)
     * @param $args
     * @return array : ['content'] =  HTML code to display
     *
     */
    function message_objects($args): array
    {
        // Get arguments
        $content = $args['content'];
        $message = $args['message'];

        $has_ICalendar_attachments = false;
        
        if (empty($message) || empty($message->attachments)) {
            return array('content' => $content);
        }
        
        foreach ($message->attachments as &$attachment) {
            if ($attachment->mimetype == 'text/calendar') {
                $has_ICalendar_attachments = true;
            }
        }
        
        if ($has_ICalendar_attachments) {
            ob_start();
            include("plugins/roundcube_caldav/roundcube_caldav_display.php");
            $html = ob_get_clean();
            $content[] = $html;
        }
        
        return array('content' => $content);
    }

    /**
     * Manages the connection to the calDAV server, returns the success or failure value and initializes
     * the array with the calendars $this->arrayOfCalendars
     * @return bool
     * @throws Exception
     */
    public function connection_to_calDAV_server(): bool
    {
        $server = $this->rcube->config->get('server_caldav');
        $_login = $server['_login'];
        $_password = $server['_password'];
        $_url_base = $server['_url_base'];
        $_connexion = $server['_connexion_status'];

        if ($this->connected) {
            return true;
        }
        
        if (!$_connexion) {
            return false;
        }
        
        if (empty($server['_used_calendars'])) {
            return false;
        }
        
        try {
            $success = $this->try_connection($_login, $_password, $_url_base);
            $available_calendars = $this->client->findCalendars();

            foreach ($server['_used_calendars'] as $used_calendar) {
                foreach ($available_calendars as $available_calendar) {
                    if ($used_calendar == $available_calendar->getCalendarID()) {
                        $this->arrayOfCalendars[$available_calendar->getCalendarID()] = $available_calendar;
                    }
                }
            }

            $this->get_all_events();
            $this->connected = true;
            return $success;
        } catch (Exception $e) {
            $this->rcmail->output->command('display_message', $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Test of connection with the Caldav server
     * @param string $_login
     * @param string $_password
     * @param string $_url_base
     * @return bool
     * @throws Exception
     */
    function try_connection(string $_login, string $_password, string $_url_base): bool
    {
        if (!empty($_url_base) && !empty($_login) && !empty($_password)) {
            // Récupération du mot de passe chiffré dans la bdd et décodage
            $cipher = new password_encryption();

            $plain_password = $cipher->decrypt(
                $_password, 
                $this->rcube->config->get('des_key'),
                true
            );

            //  Connexion au serveur calDAV et récupération des calendriers dispos
            $this->client = new SimpleCalDAVClient();

            $this->client->connect($_url_base, $_login, $plain_password);
            return true;
        }
        
        return false;
    }

    /**
     * Get all events on the server in order to minimize the number of calls, and store them in $this->all_events[]
     */
    public function get_all_events(): void
    {
        try {
            $begin_of_unix_timestamp = date("Ymd\THis\Z", 0);
            $end_of_unix_timestamp = date("Ymd\THis\Z", 2 ** 31);
            
            if (empty($this->arrayOfCalendars)) {
                throw new Exception($this->gettext("ErrorEmptyArrayOfCalendar"));
            }
            
            foreach ($this->arrayOfCalendars as $calendar) {
                $this->client->setCalendar($this->arrayOfCalendars[$calendar->getCalendarID()]);
                $this->all_events[$calendar->getCalendarID()] = $this->client->getEvents($begin_of_unix_timestamp,
                        $end_of_unix_timestamp);
            }
        } catch (Exception $e) {
            $this->rcmail->output->command(
                'display_message',
                $this->gettext('something_happened_while_getting_events') . "\n" . $e->getMessage(),
                'error'
            );
        }
    }

    /**
     * Retrieve all the information necessary to display the event and send the answers to the client.
     */
    function get_info_server()
    {

        $uid = rcube_utils::get_input_value('_uid', rcube_utils::INPUT_POST);
        $mbox = rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_POST);

        // Récupération du mail
        $message = new rcube_message($uid, $mbox);
        
        try {
            foreach ($message->attachments as &$attachment) {
                if ($attachment->mimetype == 'text/calendar') {
                    $this->send_info_to_be_displayed_to_client($message, $attachment);
                }
            }
        } catch (Exception $e) {
            $this->rcmail->output->command(
                'display_message',
                $this->gettext('something_happened_while_sending_information_to_client') . "\n" . $e->getMessage(),
                'error'
            );
        }
    }

    /**
     * Fill an array which will be send back to client with all information needed for display
     * @param rcube_message $message
     * @param $attachment
     * @throws Exception
     */
    function send_info_to_be_displayed_to_client(rcube_message &$message, $attachment)
    {
        $response = array();
        $langs = $this->rcmail->config->get('language');

        $this->connection_to_calDAV_server();

        $ics = $message->get_part_body($attachment->mime_id);
        $ical = new ICal($ics);

        $array_events = $ical->cal;
        $array_events = $array_events['VEVENT'] ?? $array_events;
        
        if (count($array_events) < 10) {
            $events = $ical->events();
        } else {
            $events = [];
            
            for ($i = 0; $i < 10; $i++) {
                $events[] = new Event(array_shift($array_events));
            }
        }

        set_sender_and_receiver_email($message, $response);
        
        foreach ($events as $event) {
            if_no_dtend_add_one_to_event($event);
            $response['recurrent_events'][$event->uid][] = $this->pretty_date(
                $event->dtstart_array[1], 
                $event->dtend_array[1]
            );
        }

        $same_uid = $events[0]->uid;
        
        foreach ($events as $i => &$event) {
            // Si l'evenement à le même uid que son prédecesseur on ne l'affiche pas
            if ($same_uid == $event->uid && $i != 0) {
                continue;
            } else {
                $same_uid = $event->uid;
            }
            
            $response['uid'] = $event->uid;

            if ($event->rrule) {
                $rrule = new Recurr\Rule($event->rrule, $event->dtstart_array[1]);
                $array_match = [];
                
                if (preg_match('/[a-z]+/', $langs, $array_match) > 0) {
                    $langs_short = $array_match[0];
                }
                
                try {
                    $transformer = new \Recurr\Transformer\TextTransformer(new \Recurr\Transformer\Translator($langs_short));
                    $response['rrule_to_text'] = $transformer->transform($rrule);
                } catch (Exception $e) {
                    $this->rcube::write_log('errors', $e->getMessage());
                }
            }

            $found_advance = $this->is_server_in_advance($event);

            // On récupère les informations correspondant à l'identité qui a été solicité dans le champs attendee ou organisateur
            if ($found_advance) {
                $response['identity'] = find_identity_matching_with_attendee_or_organizer(
                    $event,
                    $this->rcmail->user->list_identities(null, true), 
                    $found_advance[0]['event']
                );
            } else {
                $response['identity'] = find_identity_matching_with_attendee_or_organizer(
                    $event,
                    $this->rcmail->user->list_identities(null, true)
                );
            }
            
            $is_Organizer = false;
            
            if ($response['identity']) {
                $is_Organizer = strcmp($response['identity']['role'], 'ORGANIZER') == 0;
            }
            
            if ($is_Organizer) {
                get_sender_s_partstat($event, $response);
            }

            set_method_field($ical, $response, $is_Organizer);

            $response['comment'] = nl2br($event->comment);

            set_if_an_older_event_was_found_on_server(
                $event, 
                $response, 
                $this->arrayOfCalendars,
                $this->all_events
            );

            if ($found_advance) {
                $response['found_advance'] = $found_advance;
                $response['found_on_calendar']['display_name'] = $found_advance[1];
                $response['found_on_calendar']['calendar_id'] = $found_advance[0]['calendar_id'];
                $response['is_sequences_equal'] = $found_advance[2];

                if ($is_Organizer && $response['is_sequences_equal'] && $response['METHOD'] != 'COUNTER') {
                    $response['used_event'] = $event;
                } else {
                    $response['used_event'] = $found_advance[0]['event'];
                }
            } else {
                $response['used_event'] = $event;
            }
            
            set_participants_characteristics_and_set_buttons_properties(
                    $response['used_event'],
                    $response
            );

            $new_attendees_array = [];
            set_participants_characteristics_and_set_buttons_properties($event, $new_attendees_array);

            if (!$is_Organizer && $response['found_older_event_on_calendar']) {
                $response['display_modification_made_by_organizer'] = true;
            }


            $date_time_already_set = false;

            if (($found_advance && $response['METHOD'] == 'COUNTER')) {
                set_if_modification_date_location_description_attendees(
                    $response, 
                    $is_Organizer,
                    $event, 
                    $langs, 
                    $new_attendees_array['attendees']
                );
                
                set_formated_date_time($response['used_event'], $response, $langs);
                $date_time_already_set = true;
            } elseif ($response['display_modification_made_by_organizer']) {
                set_if_modification_date_location_description_attendees(
                        $response, 
                        $is_Organizer,
                        $event,
                        $langs, 
                        $new_attendees_array['attendees']
                );
                
                set_formated_date_time($response['older_event'], $response, $langs);
                $date_time_already_set = true;
            }

            $event = $response['used_event'];

            if (!$date_time_already_set) {
                set_formated_date_time($event, $response, $langs);
            }

            $response['description'] = nl2br($event->description);
            $response['location'] = $event->location;

            $msg = set_calendar_to_use_for_select_input(
                $response,
                $this->rcube->config->get('server_caldav'),
                $this->arrayOfCalendars
            );
            
            if ($msg) {
                $this->rcmail->output->command('display_message', $this->gettext($msg), 'error');
            }

            $timezone_array = find_time_zone($ics);
            $this->time_zone_offset = $timezone_array[0];

            // On affiche les autres informations concernant notre server caldav
            $this->set_caldav_server_related_information($event, $ical, $response);
            
            if ($found_advance) {
                get_sender_s_partstat($found_advance[0]['event'], $response, true);
            }
            
            $this->select_buttons_to_display(
                    $response['identity']['role'] ?: '',
                    $response['METHOD'],
                    $response
            );
            
            $this->rcmail->output->command(
                'plugin.undirect_rendering_js',
                array('request' => $response)
            );
        }
    }

    /**
     * Get POST from client and modify the ics file in order to save it on server and/or send it to attendees/organizer
     * there are different behavior for each role( attendee / organizer / event with no participant) and for each method
     * with which we want to send the ICalendar object.
     * @throws Exception
     */
    function import_event_on_server()
    {
        // On récupère toute les informations nécessaires depuis le js qu'ils soient ou non spécifiés
        $mail_uid = rcube_utils::get_input_value('_mail_uid', rcube_utils::INPUT_POST);
        $event_uid = rcube_utils::get_input_value('_event_uid', rcube_utils::INPUT_POST);
        $mbox = rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_POST);
        $status = rcube_utils::get_input_value('_status', rcube_utils::INPUT_POST);
        $identity = rcube_utils::get_input_value('_identity', rcube_utils::INPUT_POST);
        $method = rcube_utils::get_input_value('_method', rcube_utils::INPUT_POST);
        $comment = rcube_utils::get_input_value('_comment', rcube_utils::INPUT_POST);
        $chosen_calendar = rcube_utils::get_input_value('_calendar', rcube_utils::INPUT_POST);
        $modification = rcube_utils::get_input_value('_modification', rcube_utils::INPUT_POST);

        // Récupération du mail
        $message = new rcube_message($mail_uid, $mbox);

        // On se connecte au serveur et on récupèrere tous les evt pour ne plus avoir à échanger des infos avec lui
        $this->connection_to_calDAV_server();

        foreach ($message->attachments as &$attachment) {
            if ($attachment->mimetype !== 'text/calendar') {
                continue;
            }

            // On récupère la PJ
            $ics = $message->get_part_body($attachment->mime_id);
            // On regarde uniquement l'évenement qui nous interesse parmis les différents présent dans le fichier
            $ical = new ICal($ics);

            $array_event = $ical->events();

            foreach ($array_event as $e) {
                if ($e->uid == $event_uid) {
                    $event = $e;
                    break;
                }
            }

            if (!$event) {
                continue;
            }

            $timezone_array = find_time_zone($ics);
            $this->time_zone_offset = $timezone_array[0];

            $is_organizer = false;
            $has_participants = ($identity !== 'NO_PARTICIPANTS');

            if ($has_participants) {
                $is_organizer = strcmp($identity['role'], 'ORGANIZER') == 0;
            }

            $update_event_on_server_only = strcmp($method, 'UPDATED') == 0;
            $decline_counter = strcmp($method, 'DECLINECOUNTER') == 0;
            $cancel_recurrent = strcmp($status, 'CANCELLED_ONE_EVENT') == 0;
            $cancel_event_on_server_only = strcmp($method, 'EVENT_CANCELLED') == 0;

            if ($cancel_event_on_server_only) {
                $status = 'CANCELLED';
            }

            if (!$is_organizer || $update_event_on_server_only) {
                // On Regarde si le serveur est en avance sur cet événement par rapport à l'ics
                // et si oui on récupère la version la plus récente pour nos modifs
                $found_advance = $this->is_server_in_advance($event); //1s
                if (is_array($found_advance)) {
                    $ics = $found_advance[0]['ics'];
                    $event = $found_advance[0]['event'];
                }
            }

            if ($update_event_on_server_only) {
                $new_ics = change_partstat_ics($ics, $status, $message->sender['mailto']);
            } else {
                // On reforme un fichier ics avec uniquement l'événement qui nous interesse
                $new_ics = extract_event_ics($ics, $event_uid);
            }



            // On change la date de dernière modif
            $new_ics = change_last_modified_ics($new_ics);

            if (!$is_organizer && $has_participants) {
                // On change la valeur du champs PARTSTAT correspondant à l'utilisateur
                $new_ics = change_partstat_ics($new_ics, $status, $identity['email']);
            }


            if ($is_organizer && $method == 'REQUEST' && $status = 'CONFIRMED') {
                $new_ics = change_partstat_of_all_attendees_to_need_action($new_ics);
            }

            if ($cancel_recurrent) {
                $status = 'CONFIRMED';
                $new_ics = cancel_one_instance($new_ics, $event->dtstart_array[1]);
            }

            $send_event = true;

            // On enregistre pas sur le serveur si la method est decline counter
            if (!$decline_counter) {
                if (!empty($modification)) {
                    // On change la date et l'emplacement dans le fichier ics si ceux si ont été modifié
                    $ics_with_modified_date_and_location = $this->change_date_and_location(
                            $modification,
                            $array_event, 
                            $event,
                            $new_ics
                    );
                    
                } else {
                    $ics_with_modified_date_and_location = $new_ics;
                }

                if ($is_organizer || !$has_participants) {
                    // On sauvegarde sur le serveur avec les dates et lieu modifiés
                    $new_ics = $ics_with_modified_date_and_location;
                    // On change le numéro de sequence si l'utilisateur est l'organisateur de l'evenement ou si l'événement n'a pas de participants
                    $new_ics = change_sequence_ics($new_ics);
                    $new_ics = clean_ics($new_ics);
                    
                    $send_event = $this->save_event_on_caldav_server_and_display_message(
                            $new_ics,
                            $status,
                            $event, 
                            $chosen_calendar,
                            $event_uid
                    );
                } else {
                    $new_ics = clean_ics($new_ics);
                    
                    $send_event = $this->save_event_on_caldav_server_and_display_message(
                            $new_ics,
                            $status, 
                            $event, 
                            $chosen_calendar,
                            $event_uid
                    );

                    // On sauvegarde sur le serveur sans les dates et lieu modifiés
                    $new_ics = $ics_with_modified_date_and_location;
                }
            }

            // On envoie une réponse uniquement si il y a des participants à qui répondre,
            // et si ce n'est pas une simple mise a jour de l'événement sur le serveur de l'utilisateur
            if ($send_event && $has_participants && !$update_event_on_server_only && !$cancel_event_on_server_only) {
                $this->send_mail_and_display_message(
                    $is_organizer, 
                    $method, 
                    $status,
                    $new_ics,
                    $message,
                    $identity, 
                    $comment, 
                    (bool) $modification
                );
            }

            $this->rcmail->output->command(
                    'plugin.display_after_response',
                    array('uid' => $event_uid)
            );
        }
    }

    /**
     * Choice of the buttons that the client will have to display according to its role and according to the method of
     * the file received
     * @param string $role
     * @param string $method
     * @param array $response
     */
    function select_buttons_to_display(string $role, string $method, array &$response)
    {
        $has_modifications = $response['new_description'] || $response['new_location'] || $response['new_date'];
        $is_recurrent = count($response['recurrent_events'][$response['uid']]) > 1;
        $buttons_to_display = [];
        
        switch ($role) {
            case 'ORGANIZER':
                switch ($method) {
                    case 'REPLY':
                        $buttons_to_display = ['reschedule', 'cancel_button_organizer'];
                        
                        if ($is_recurrent) {
                            $buttons_to_display[] = 'cancel_recurrent_button_organizer';
                        }
                        
                        if (
                            isset($response['sender_partstat_on_server'])
                            && (
                                $response['sender_partstat_on_server'] == 'NEEDS-ACTION' 
                                || $response['sender_partstat_on_server'] == 'NEEDS_ACTION'
                            )
                         ) {
                            $buttons_to_display[] = 'update_button_organizer';
                        }
                        
                        break;
                    case 'REQUEST':
                    case 'DECLINECOUNTER':
                        $buttons_to_display = ['reschedule', 'cancel_button_organizer'];
                        
                        if ($is_recurrent) {
                            $buttons_to_display[] = 'cancel_recurrent_button_organizer';
                        }
                        
                        if ($response['display_select']) {
                            $buttons_to_display[] = 'update_button_organizer';
                        }
                        
                        break;
                    case 'COUNTER':
                        if ($has_modifications) {
                            $buttons_to_display = ['reschedule', 'confirm_button_organizer', 'decline_button_organizer',
                                'cancel_button_organizer'];
                        } else {
                            $buttons_to_display = ['reschedule', 'cancel_button_organizer'];
                        }
                        
                        if ($is_recurrent) {
                            $buttons_to_display[] = 'cancel_recurrent_button_organizer';
                        }
                        
                        break;
                }
                break;

            case 'ATTENDEE':
                switch ($method) {
                    case 'REQUEST':
                    case 'DECLINECOUNTER':
                    case 'REPLY':
                    case 'COUNTER':
                        $buttons_to_display = ['reschedule', 'confirm_button', 'tentative_button', 'decline_button'];
                        break;
                    case 'CANCEL':
                        $buttons_to_display = ['update_button'];
                }
                break;
            default:
                $buttons_to_display = ['reschedule', 'confirm_button', 'tentative_button', 'decline_button'];
        }

        $response['buttons_to_display'] = $buttons_to_display;
    }

    /**
     * Display of all information concerning the caldav server  i.e. for each of the chosen calendars:
     * - display of events overlapping the current event
     * - display of the event immediately before
     * - display of the event immediately after
     * @param Event $current_event
     * @param ICal $ical
     * @param array $response
     * @throws Exception
     */
    function set_caldav_server_related_information(Event $current_event, ICal $ical, array &$response)
    {
        $server = $this->rcube->config->get('server_caldav');
        $_used_calendars = $server['_used_calendars'];

        if (empty($this->arrayOfCalendars[$server['_main_calendar']])) {
            $_main_calendar = array_key_first($this->arrayOfCalendars);
        } else {
            $_main_calendar = $server['_main_calendar'];
        }

        // On récupère la time_zone qui va nous servir plus tard dans la fonction
        $this->time_zone_offset = $ical->iCalDateToDateTime($current_event->dtstart_array[1])->getOffset();
        date_default_timezone_set($this->time_zone_offset);

        $meeting_collision = $this->meeting_in_collision_with_current_event_by_calendars(
                $_main_calendar,
                $_used_calendars, 
                $current_event
        );
        
        $info_cal_dav_server['collision'] = $meeting_collision;

        $close_meeting = $this->get_previous_and_next_meeting_by_calendars(
                $_main_calendar,
                $_used_calendars, 
                $current_event
        );
        
        $info_cal_dav_server['close_meeting'] = $close_meeting;

        $response['display_caldav_info'] = $info_cal_dav_server;
    }

    /**
     * Get the events in collision with the studied event on all available calendars.Returns an array indexed on
     * the calendars with each time the closest event by calendars
     * @param string $_main_calendar
     * @param array $_used_calendars
     * @param Event $current_event
     * @return array
     */
    function meeting_in_collision_with_current_event_by_calendars(string $_main_calendar, array $_used_calendars, Event $current_event): array
    {

        $display_meeting_collision = array();
        
        if (empty($this->arrayOfCalendars)) {
            return [];
        }
        
        foreach ($this->arrayOfCalendars as $calendar) {

            // On récupère les calendriers et on travaille qu'avec ceux définit dans les paramètres
            if (
                $calendar->getCalendarID() != $_main_calendar 
                && !array_key_exists($calendar->getCalendarID(), $_used_calendars)
            ) {
                continue;
            }

            $display_meeting_collision[$calendar->getDisplayName()] = array();
            $has_collision_by_calendars = false;

            foreach ($this->all_events[$calendar->getCalendarID()] as $event_found_ics) {

                // On recupère uniquement le fichier ics qui est dans la partie data pour le parser
                $event_found_ical = new ICal($event_found_ics->getData());

                $events_found = $event_found_ical->events();
                
                if (empty($events_found)) {
                    return [];
                }
                
                // On regarde event par event, un fichier ics peut en contenir plusieurs (en cas de répétition)
                foreach ($events_found as &$event_found) {
                    if (
                        $event_found->uid != $current_event->uid 
                        && $event_found->status !== 'CANCELLED'
                        && is_there_an_overlap(
                                $current_event->dtstart_array[2],
                                $current_event->dtstart_array[1],
                                $current_event->dtend_array[1],
                                $event_found->dtstart_array[1],
                                $event_found->dtend_array[1]
                        )
                    ) {
                        // Affichage de l'événement
                        $display_meeting_collision[$calendar->getDisplayName()][] = $event_found;
                        $has_collision_by_calendars = true;
                    }
                }
            }
            
            if (!$has_collision_by_calendars) {
                unset($display_meeting_collision[$calendar->getDisplayName()]);
            }
        }
        
        return $display_meeting_collision;
    }

    /**
     * Get the previous and next events, if they exist, on the different calendars
     * @param string $_main_calendar
     * @param array $_used_calendars
     * @param Event $current_event
     * @return array
     * @throws CalDAVException
     */
    function get_previous_and_next_meeting_by_calendars(string $_main_calendar, array $_used_calendars, Event $current_event): array
    {
        $close_meetings = array();
        $previous_meeting = array();
        $next_meeting = array();

        foreach ($this->arrayOfCalendars as $calendar) {
            // On récupère les calendriers et on travaille qu'avec ceux définit dans les paramètres
            if ($_used_calendars) {
                if (
                    $calendar->getCalendarID() == $_main_calendar 
                    || array_key_exists($calendar->getCalendarID(), $_used_calendars)
                ) {
                    // On definit le calendrier courant
                    $this->client->setCalendar($this->arrayOfCalendars[$calendar->getCalendarID()]);

                    $current_dtstart_minus_24h = date(
                        "\T\Z\I\D=e:Ymd\THis",
                        $current_event->dtstart_array[2] - $this->twenty_four_hour
                    );
                    
                    $current_dtend_plus_24h = date(
                        "\T\Z\I\D=e:Ymd\THis",
                        $current_event->dtend_array[2] + $this->twenty_four_hour
                    );

                    $prev_res = $this->display_closest_meeting_by_calendars(
                        $current_event,
                        $current_dtstart_minus_24h,
                        $calendar,
                        'previous'
                    );
                    
                    $next_res = $this->display_closest_meeting_by_calendars(
                        $current_event,
                        $current_dtend_plus_24h, 
                        $calendar
                    );

                    if ($prev_res) {
                        $previous_meeting[$prev_res['uid']] = $prev_res;
                    }
                    
                    if ($next_res) {
                        $next_meeting[$next_res['uid']] = $next_res;
                    }
                }
            }
        }

        $uid_previous = choose_the_closest_meeting(
            $previous_meeting,
            $current_event->dtstart_array[1],
            'previous'
        );

        $uid_next = choose_the_closest_meeting($next_meeting, $current_event->dtend_array[1], 'next');

        foreach ($previous_meeting as $meeting) {
            if ($uid_previous == $meeting['uid']) {
                $close_meetings['previous'] = $meeting;
                break;
            }
        }
        
        foreach ($next_meeting as $meeting) {
            if ($uid_next == $meeting['uid']) {
                $close_meetings['next'] = $meeting;
                break;
            }
        }
        
        return $close_meetings;
    }

    /**
     * Find the event closest to the current date and return a array with its characteristics :
     * summary, date_start, date_end, uid, calendar (DisplayName), pretty_date (formatted date).
     * @param Event $current_event
     * @param string $date_with_offset
     * @param CalDAVCalendar $calendar
     * @param string $opt : 'next' to get the next meeting; 'previous' to get the previous meeting
     * @return mixed|null
     */
    function display_closest_meeting_by_calendars(Event $current_event, string $date_with_offset, CalDAVCalendar $calendar, string $opt = 'next')
    {

        $stock_closest_events = array();
        $has_meeting_by_calendars = false;
        foreach ($this->all_events[$calendar->getCalendarID()] as $event_found_ics) {
            // On recupère uniquement le fichier ics qui est dans la partie data pour le parser
            $event_found_ical = new ICal($event_found_ics->getData());

            // On regarde event par event, un fichier ics peut en contenir plusieurs (en cas de répétition)
            foreach ($event_found_ical->events() as &$event_found) {

                if ($event_found->uid == $current_event->uid) {
                    continue;
                }
                
                if (strcmp($opt, 'next') == 0) {
                    if (
                        $event_found->status !== 'CANCELLED'
                        && is_after(
                            $current_event->dtend_array[1],
                            $date_with_offset,
                            $event_found->dtstart_array[1],
                            $current_event->dtstart_array[2]
                        )
                    ) {
                        // On stocke tous les événements trouvés dans un tableau pour effectuer un tri ensuite
                        $stock_closest_events[$event_found->uid]['summary'] = $event_found->summary;
                        $stock_closest_events[$event_found->uid]['date_start'] = $event_found->dtstart_array[1];
                        $stock_closest_events[$event_found->uid]['date_end'] = $event_found->dtend_array[1];
                        $stock_closest_events[$event_found->uid]['uid'] = $event_found->uid;
                        $stock_closest_events[$event_found->uid]['calendar'] = $calendar->getDisplayName();
                        
                        $stock_closest_events[$event_found->uid]['pretty_date'] = $this->pretty_date(
                            $event_found->dtstart_array[1], 
                            $event_found->dtend_array[1]
                        );
                        
                        $has_meeting_by_calendars = true;
                    }
                } else if (
                    $event_found->status !== 'CANCELLED'
                    && is_before(
                        $current_event->dtstart_array[1],
                        $date_with_offset, $event_found->dtend_array[1],
                        $current_event->dtstart_array[2]
                    )
                ) {
                    $stock_closest_events[$event_found->uid]['summary'] = $event_found->summary;
                    $stock_closest_events[$event_found->uid]['date_start'] = $event_found->dtstart_array[1];
                    $stock_closest_events[$event_found->uid]['date_end'] = $event_found->dtend_array[1];
                    $stock_closest_events[$event_found->uid]['uid'] = $event_found->uid;
                    $stock_closest_events[$event_found->uid]['calendar'] = $calendar->getDisplayName();
                    
                    $stock_closest_events[$event_found->uid]['pretty_date'] = $this->pretty_date(
                        $event_found->dtstart_array[1],
                        $event_found->dtend_array[1]
                    );
                    
                    $has_meeting_by_calendars = true;
                }
            }
        }
        
        if ($has_meeting_by_calendars && strcmp($opt, 'next') == 0) {
            $uid_closest_meeting = choose_the_closest_meeting(
                $stock_closest_events,
                $current_event->dtend_array[1],
                'next'
            );
        } elseif ($has_meeting_by_calendars) {
            $uid_closest_meeting = choose_the_closest_meeting(
                $stock_closest_events,
                $current_event->dtstart_array[1],
                'previous'
            );
        }

        return empty($uid_closest_meeting) ? null : $stock_closest_events[$uid_closest_meeting];
    }

    /**
     * Connection to the calDAV server and import of the ics file.
     * If the server already has an event with the same uid we must provide the url of this event.
     * @param string $ics
     * @param string $calendar_id
     * @param string $uid
     * @param string|null $href : url of the event on server we want to modify
     * @return bool|string
     * @throws Exception
     */
    function add_ics_event_to_caldav_server(string $ics, string $calendar_id, string $uid, string $href = null)
    {

        // Récupération de l'url, du login et du mdp
        $server = $this->rcube->config->get('server_caldav');
        $url_base = $server['_url_base'];
        $_password = $server['_password'];
        $login = $server['_login'];
        $cipher = new password_encryption();
        $password = $cipher->decrypt($_password, $this->rcube->config->get('des_key'), true);

        // create curl resource
        $ch = curl_init();

        // On supprime le champ METHOD du fichier ics qui bloque l'ajout
        $ics = del_method_field_ics($ics);

        // on formate l'url sur laquelle on veut déposer notre event
        $url = rtrim($url_base, '/') . '/' . $calendar_id . '/' . $uid . '.ics';

        // Si href n'est pas nul alors on remplace l'url par href pour récupérer le bon événement
        if ($href != null) {
            $url = $href;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERPWD, $login . ':' . $password);

        $headers = array("Depth: 1", "Content-Type: text/calendar; charset='UTF-8'");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $ics);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $resOutput = curl_exec($ch);

        $ret = "";
        
        if (!$resOutput) {
            $errno = curl_errno($ch);
            
            if ($errno != 0) {
                $ret = curl_strerror($errno);
            }
        } else {
            $ret = $resOutput;
        }

        // close curl resource to free up system resources
        curl_close($ch);
        return $ret;
    }

    /**
     * Check that there is no more recent event with the same uid on Caldav server.
     * @param Event $event : Event to compare with
     * @return array|false : [ ics of the evt, Object Evt parsed, the calendar to which the evt belongs ] or false in case no more recent evt is found
     */
    public function is_server_in_advance(Event $event)
    {
        $is_server_in_advance = array();
        // On regarde dans tous les calendriers
        foreach ($this->arrayOfCalendars as $calendar) {

            // On trouve les événements qui matchent dans chacun des calendriers
            $event_found_on_server = find_event_with_matching_uid(
                $event,
                $calendar->getCalendarID(), 
                $this->all_events
            );
            
            if ($event_found_on_server) {
                $event_ics = $event_found_on_server->getData();
                $ical_found = new ICal($event_ics);

                // On parse l'evt trouvé pour avoir sa séquence
                $event_found = $ical_found->events()[0];

                // On compare les séquence avec l'evt courant et on stocke les evt avec une séquence supérieure ou égale
                if ($event->sequence <= $event_found->sequence) {
                    $is_server_in_advance[$calendar->getDisplayName()]['event'] = $event_found;
                    $is_server_in_advance[$calendar->getDisplayName()]['ics'] = $event_ics;
                    $is_server_in_advance[$calendar->getDisplayName()]['calendar_id'] = $calendar->getCalendarID();
                }
            }
        }

        if (!empty($is_server_in_advance)) {

            // On initialise les valeurs avec le premiers elt des tableaux
            $found_on_calendar = array_key_first($is_server_in_advance);
            $event_with_higher_sequence = array_shift($is_server_in_advance);

            $is_sequences_equal = false;
            
            // On compare toutes les evt obtenu selon leur séquence
            foreach ($is_server_in_advance as $calendar => $event_found) {
                if ($event_with_higher_sequence['event']->sequence < $event_found['event']->sequence) {
                    $event_with_higher_sequence = $event_found;
                    $found_on_calendar = $calendar;
                }
            }
            
            if ($event_with_higher_sequence['event']->sequence == $event->sequence) {
                $is_sequences_equal = true;
            }
            
            return [
                $event_with_higher_sequence,
                $found_on_calendar,
                $is_sequences_equal
            ];
        } else {
            return false;
        }
    }

    /**
     * Delete the comment section and change the status on the ics file. Then add the ics event to the server and display
     * message of success or failure
     * @param string $ics
     * @param string $status
     * @param Event $event
     * @param string $calendar_id
     * @param string $event_uid
     * @return bool
     * @throws Exception
     */
    public function save_event_on_caldav_server_and_display_message(string $ics, string $status, Event $event, string $calendar_id, string $event_uid): bool
    {
        // On supprime le champ commentaire pour ajouter au serveur
        $ics = delete_comment_section_ics($ics);

        // On change le status pour la sauvegarde
        $ics = change_status_ics($status, $ics);

        // On cherche si le serveur possède déjà un événement avec cet uid
        $found_event_with_good_uid = find_event_with_matching_uid(
            $event, 
            $calendar_id,
            $this->all_events
        );

        // On ajoute le fichier au serveur calDAV
        if (!$found_event_with_good_uid) {
            $save_error = $this->add_ics_event_to_caldav_server($ics, $calendar_id, $event_uid);
        } else {
            $save_error = $this->add_ics_event_to_caldav_server(
                $ics, 
                $calendar_id,
                $event_uid,
                $found_event_with_good_uid->getHref()
            );
        }

        if (empty($save_error)) {
            // On affiche une confirmation de l'ajout de l'événement
            $this->rcmail->output->command(
                'display_message',
                $this->gettext('successfully_saved'),
                'confirmation'
            );
            
            return true;
        } else {
            $this->rcmail->output->command(
                'display_message',
                $this->gettext('something_happened') . $save_error, 
                'error'
            );
            
            return false;
        }
    }

    /**
     * Add comment field, send the email and display messages of success or not of the operation.
     * @param bool $is_organizer
     * @param string $method
     * @param string $status
     * @param string $ics_to_send
     * @param rcube_message $message
     * @param array $identity
     * @param string $comment
     * @param bool $has_modif
     */
    public function send_mail_and_display_message(bool $is_organizer, string $method, string $status, string $ics_to_send, rcube_message $message, array $identity, string $comment, bool $has_modif)
    {
        if (!empty($comment)) {
            $ics_to_send = update_comment_section_ics($ics_to_send, $comment);
        } else {
            $ics_to_send = delete_comment_section_ics($ics_to_send);
        }

        if ($method !== 'CANCEL') {
            $ics_to_send = delete_status_section_ics($ics_to_send);
        }

        $ics_to_send = change_method_ics($ics_to_send, $method);
        $ics_to_send = clean_ics($ics_to_send);

        $method = $is_organizer ? $method : $status;
        $send_succes = $this->send_mail($ics_to_send, $message, $method, $identity, $has_modif);

        if ($send_succes[0]) {
            $this->rcmail->output->command(
                'display_message', 
                $this->gettext('successfully_sent'),
                'confirmation'
            );
        } elseif (!$send_succes[1]) {
            $this->rcmail->output->command(
                'display_message',
                $this->gettext('no_participant_to_answer'),
                'notice'
            );
        } else {
            $this->rcmail->output->command(
                'display_message',
                $this->gettext('something_happened_when_sending'),
                'error'
            );
        }
    }

    /**
     * Sending a response email to all attendees (if you are an organizer) or to the organizer if you are an attendee
     * @param string $ics_to_send
     * @param rcube_message $message
     * @param string $method
     * @param array $my_identity
     * @param bool $has_modif
     * @return array : [ bool: result of rcmail_sendmail->deliver_message() function, bool: test if there is someone to answer]
     */
    public function send_mail(string $ics_to_send, rcube_message $message, string $method, array $my_identity, bool $has_modif = false): array
    {
        // On parse l'ics reçu
        $ical = new ICal($ics_to_send, array('skipRecurrence' => true));

        $ical_events = $ical->events();
        $event = array_shift($ical_events);

        $subject = $this->change_mail_subject($message, $has_modif, $method);
        
        list($mailto, $RSVP) = $this->find_to_whom_should_we_answer(
            $my_identity, 
            $event, 
            $method,
            $message
        );

        if (!empty($mailto)) {
            $header = [
                'date'    => $this->rcmail->user_date(),
                'from'    => $my_identity['email'],
                'to'      => $mailto,
                'subject' => $subject,
            ];

            $options = [
                'sendmail' => true,
                'from'     => $my_identity['email'],
                'mailto'   => $mailto,
                'charset'  => "UTF-8"
            ];

            // TODO Add Body
            $body = '';

            // creation de la PJ
            $disp_name = $event->uid . '.ics';

            // Procedure d'envoi de mail
            $rcmail_sendmail = new rcmail_sendmail('', $options);
            $message = $rcmail_sendmail->create_message($header, $body);
            
            $message->addAttachment(
                $ics_to_send, 
                'text/calendar',
                $disp_name, 
                false, 
                'base64',
                'attachment',
                'UTF-8'
            );

            $result = $rcmail_sendmail->deliver_message($message);
            $rcmail_sendmail->save_message($message);
            
            return [$result, $RSVP];
        }
        
        return [false, $RSVP];
    }

    /**
     * Find the list of email adresses  to which to reply to
     * @param array $my_identity
     * @param Event $event
     * @param string $method
     * @param rcube_message $message
     * @return array: [ string: list of e-mail addresses to which to reply to, separated by coma ; bool: test if there is someone to answer ]
     */
    public function find_to_whom_should_we_answer(array $my_identity, Event $event, string $method, rcube_message $message): array
    {
        $mailto = '';
        $RSVP = true;
        
        if (strcmp($my_identity['role'], 'ATTENDEE') == 0) {
            // On récupère l'adresse mail de l'organisateur de l'evt
            foreach ($event->organizer_array as $organizer_or_email) {
                if (is_string($organizer_or_email) && str_start_with($organizer_or_email, 'mailto:')) {
                    $organizer_or_email = substr($organizer_or_email, strlen('mailto:'));
                    
                    if (strcmp($organizer_or_email, $my_identity['email']) != 0) {
                        $mailto = $organizer_or_email;
                        break;
                    }
                }
            }
        } elseif ((strcmp($method, 'REQUEST') == 0 || strcmp($method, 'CANCEL') == 0) && $my_identity) {
            $array_attendee = [];
            set_participants_characteristics_and_set_buttons_properties($event, $array_attendee);
            
            foreach ($array_attendee['attendees'] as $attendee) {
                if (empty($attendee['email'])) {
                    continue;
                }
                
                if (strcmp($attendee['RSVP'], 'TRUE') == 0) {
                    $mailto .= $attendee['email'] . ', ';
                } elseif (!empty($attendee['ROLE']) && strcmp($attendee['ROLE'], 'NON-PARTICIPANT') != 0) {
                    $mailto .= $attendee['email'] . ', ';
                }
            }

            if (empty($mailto)) {
                $RSVP = false;
            }
        } else {
            $mailto = $message->get_header('from');
        }
        
        return array($mailto, $RSVP);
    }

    /**
     * Change the subject of previous message in order to add the status or method inside
     * @param rcube_message $message
     * @param bool $has_modif
     * @param string $method
     * @return string
     */
    public function change_mail_subject(rcube_message $message, bool $has_modif, string $method): string
    {
        $orig_subject = $message->get_header('subject');

        // On supprime le prefixe de l'ancien mail reçu
        if (
            strpos($orig_subject, $this->gettext('MODIFIED')) >= 0 
            || strpos($orig_subject, $this->gettext('CONFIRMED')) >= 0 
            || strpos($orig_subject, $this->gettext('TENTATIVE')) >= 0 
            || strpos($orig_subject, $this->gettext('CANCELLED')) >= 0 
            || strpos($orig_subject, $this->gettext('CANCEL')) >= 0
            || strpos($orig_subject, $this->gettext('DECLINECOUNTER')) >= 0
        ) {
            $orig_subject = preg_replace('@\[.*?:@', '', $orig_subject);
        }

        // On rajoute un nouveau préfixe correspondant à l'action effectuée
        if ($has_modif || strcmp($method, 'REQUEST') == 0) {
            $prefix = $this->gettext('MODIFIED');
        } else {
            $prefix = $this->gettext($method);
        }

        return $prefix . $orig_subject;
    }

    /**
     * Change date and Location on an ics file.
     * @param array $modification : array of modification sent by the client
     * @param array $array_events : Array with all events contained on the ics file
     * @param Event $event
     * @param string $ics
     * @return string : $ics altered
     */
    public function change_date_and_location(array $modification, array $array_events, Event $event, string $ics): string
    {

        $chosen_date_start = $modification['_chosenDateStart'];
        $chosen_date_end = $modification['_chosenDateEnd'];
        $chosen_time_start = $modification['_chosenTimeStart'];
        $chosen_time_end = $modification['_chosenTimeEnd'];
        $chosen_location = $modification['_chosenLocation'];

        // On parse la date puis on remplace par la nouvelle date dans le fichier ics
        if ($chosen_date_start && $chosen_date_end && $chosen_time_start && $chosen_time_end) {
            $new_date_start_int = strtotime($chosen_date_start . ' ' . $chosen_time_start);
            $new_date_end_int = strtotime($chosen_date_end . ' ' . $chosen_time_end);

            $timezone_array = find_time_zone($ics);
            date_default_timezone_set($timezone_array[1]->getName());

            $new_date_start = date("Ymd\THis\Z", $new_date_start_int - $this->time_zone_offset);
            $new_date_end = date("Ymd\THis\Z", $new_date_end_int - $this->time_zone_offset);

            // pour la modification des événements récurrents
            $number_of_event_with_same_uid = 0;
            foreach ($array_events as $event_in_same_ics_file) {
                if ($event_in_same_ics_file->uid == $event->uid) {
                    $number_of_event_with_same_uid++;
                }
            }
            
            if ($number_of_event_with_same_uid > 1) {
                $offset_start = $new_date_start_int - $event->dtstart_array[2] - $this->time_zone_offset;
                $offset_end = $new_date_end_int - $event->dtend_array[2] - $this->time_zone_offset;
                $ics = change_date_ics(
                    $new_date_start, 
                    $new_date_end, 
                    $ics, $offset_start,
                    $offset_end
                );
            } else {
                $ics = change_date_ics($new_date_start, $new_date_end, $ics);
            }
        }

        // On remplace par le nouveau lieu dans le fichier ics
        if ($chosen_location) {
            $ics = change_location_ics($chosen_location, $ics);
        }
        return $ics;
    }

    /**
     * Format a time interval for display according to the date/time formatting specified in the roundcube config
     * If the interval starts and ends on the same day, the date will be formatted as "date time_start:time_end"
     * If the date does not contain any times, the date will be formatted as "date_start - date_end"
     * @param string $date_start
     * @param string $date_end
     * @return string
     */
    function pretty_date(string $date_start, string $date_end): string
    {
        $date_format = 'd-m-Y';

        $time_format = $this->rcmail->config->get('time_format');

        $combined_format = $date_format . ' ' . $time_format;

        $datestr = $this->rcmail->format_date($date_start, $combined_format) . ' - ';

        if (strlen($date_start) == 8 || strlen($date_end) == 8) {
            if (strcmp($date_start, $date_end) == 0) {
                $datestr = $this->rcmail->format_date($date_start, $date_format) . $this->gettext('all_day');
            } else {
                $datestr = 
                    $this->rcmail->format_date($date_start, $date_format) 
                    . ' - ' 
                    . $this->rcmail->format_date($date_end, $date_format);
            }
        } else {
            if (substr($date_start, 0, 8) === substr($date_end, 0, 8)) {
                $datestr .= $this->rcmail->format_date($date_end, $time_format);
            } else {
                $datestr .= $this->rcmail->format_date($date_end, $combined_format);
            }
        }
        
        return $datestr;
    }
}
