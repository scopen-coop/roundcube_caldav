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
            $date_start = $event->dtstart_array[2];
            $date_end = $event->dtend_array[2];


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


            $html .= '<li>' . $this->connect_server($event) . '<li>';
            $html .= '</ul>';
            $html .= '</div>';
            array_push($content, $html);
        }
    }

    function connect_server($curr_event)
    {
        $server = $this->rcube->config->get('server_caldav');
        $_login = $server['_login'];
        $_password = $server['_password'];
        $_url_base = $server['_url_base'];
        $_main_calendar = $server['_main_calendar'];
        $_used_calendars = $server['_used_calendars'];
        $client = new SimpleCalDAVClient();


        try {

            $cipher = new password_encryption();
            $plain_password = $cipher->decrypt($_password, $this->rcube->config->get('des_key'), true);
            $client->connect($_url_base, $_login, $plain_password);
            $arrayOfCalendars = $client->findCalendars();

            $meeting_collision = $this->meeting_collision($arrayOfCalendars, $_main_calendar, $_used_calendars, $client, $curr_event);
            $previous_meeting = $this->get_previous_meeting($arrayOfCalendars, $_main_calendar, $_used_calendars, $client, $curr_event);

        } catch (Exception $e) {
            echo $e;
        }
        return $meeting_collision . '<br/>' . $previous_meeting;
    }

    public function get_previous_meeting($arrayOfCalendars, $_main_calendar, $_used_calendars, $client, $curr_event)
    {
        $test_rdv_low= false;
        $test_rdv_high= false;
        $meeting_low='';
        $meeting_high='';
        foreach ($arrayOfCalendars as $cal) {
            // On récupère les calendriers et on travaille qu'avec ceux définit dans les paramètres
            if ($cal->getCalendarID() == $_main_calendar || array_key_exists($cal->getCalendarID(), $_used_calendars)) {

                $client->setCalendar($arrayOfCalendars[$cal->getCalendarID()]);


                $curr_date_m1 = date("Ymd\THis\Z", $curr_event->dtstart_array[2] - 86400);
                $curr_date_p1 = date("Ymd\THis\Z", $curr_event->dtend_array[2] + 86400);


                //On preselectionne les fichiers ics en collision avec la date du fichier reçu
                $filter_low = new CalDAVFilter("VEVENT");
                $filter_low->mustOverlapWithTimerange($curr_date_m1, $curr_event->dtstart_array[1] . 'Z');
                $filter_high = new CalDAVFilter("VEVENT");
                $filter_high->mustOverlapWithTimerange($curr_event->dtend_array[1] . 'Z', $curr_date_p1);


                $rapport_low = $client->getCustomReport($filter_low->toXML());
                $rapport_high = $client->getCustomReport($filter_high->toXML());

                // On affiche le nom du calendrier
                $temp_low = '<b>' . $cal->getDisplayName() . '</b>' . ': <br/>';
                $temp_high = '<b>' . $cal->getDisplayName() . '</b>' . ': <br/>';

                $test_cal_low = false;
                $test_cal_high = false;
                foreach ($rapport_low as $event_found_ics) {
                    // On recupère uniquement le fichier ics qui est dans la partie data pour le parser
                    $event_found_ical = new ICal\ICal($event_found_ics->getData());

                    // On regarde event par event, un fichier ics peut en contenir plusieurs (en cas de répétition)
                    foreach ($event_found_ical->events() as &$event_found) {
                        $test_rdv_low = true;
                        $test_cal_low = true;

                        if ($this->compare_time($curr_date_m1, $curr_event->dtstart_array[1], $event_found->dtstart_array[1], $event_found->dtend_array[1])) {
                            $temp_low .= $event_found->summary . ': ' . $this->pretty_date($event_found->dtstart_array[1], $event_found->dtend_array[1]) . '<br/>';
                        }

                    }
                }
                if ($test_cal_low) {
                    $meeting_low.= $temp_low;
                }
                foreach ($rapport_high as $event_found_ics) {
                    // On recupère uniquement le fichier ics qui est dans la partie data pour le parser
                    $event_found_ical = new ICal\ICal($event_found_ics->getData());

                    // On regarde event par event, un fichier ics peut en contenir plusieurs (en cas de répétition)
                    foreach ($event_found_ical->events() as &$event_found) {
                        $test_rdv_high = true;
                        $test_cal_high = true;

                        if ($this->compare_time($curr_event->dtend_array[1], $curr_date_p1, $event_found->dtstart_array[1], $event_found->dtend_array[1])) {
                            $temp_low .= $event_found->summary . ': ' . $this->pretty_date($event_found->dtstart_array[1], $event_found->dtend_array[1]) . '<br/>';
                        }

                    }
                }
                if ($test_cal_high) {
                    $meeting_high .= $temp_high;
                }

            }

        }
        if ($test_rdv_low) {
            $meeting_low = $this->gettext('meeting_low') . '<br/>' . $meeting_low;
        }
        if ($test_rdv_high) {
            $meeting_high = $this->gettext('meeting_high') . '<br/>' . $meeting_high;
        }
        return $meeting_high .$meeting_low ;
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
        if (date($df, $date_start) == date($df, $date_end)) {
            $datestr .= $this->rcmail->format_date($date_end, $time_format);
        } else {
            $datestr .= $this->rcmail->format_date($date_end, $combined_format);
        }
        return $datestr;
    }

    public function compare_time($currdate_start, $currdate_end, $date_start, $date_end)
    {
        return (((strtotime($date_start) <= strtotime($currdate_start))
                && (strtotime($date_end) >= strtotime($currdate_start)))
            || ((strtotime($date_start) <= strtotime($currdate_end))
                && (strtotime($date_end) >= strtotime($currdate_end)))
            || ((strtotime($date_start) >= strtotime($currdate_start))
                && (strtotime($date_end) <= strtotime($currdate_end))));
    }

    /**
     * @param $arrayOfCalendars
     * @param $_main_calendar
     * @param $_used_calendars
     * @param $client
     * @param $curr_event
     * @return string
     * @throws CalDAVException
     */
    public function meeting_collision($arrayOfCalendars, $_main_calendar, $_used_calendars, $client, $curr_event)
    {
        $meeting_collision = '';
        $test_rdv = false;
        foreach ($arrayOfCalendars as $cal) {

            // On récupère les calendriers et on travaille qu'avec ceux définit dans les paramètres
            if ($cal->getCalendarID() == $_main_calendar || array_key_exists($cal->getCalendarID(), $_used_calendars)) {

                $client->setCalendar($arrayOfCalendars[$cal->getCalendarID()]);


                //On preselectionne les fichiers ics en collision avec la date du fichier reçu
                $filter = new CalDAVFilter("VEVENT");
                $filter->mustOverlapWithTimerange($curr_event->dtstart_array[1] . 'Z', $curr_event->dtend_array[1] . 'Z');
                $rapport = $client->getCustomReport($filter->toXML());

                // On affiche le nom du calendrier
                $temp = '<b>' . $cal->getDisplayName() . '</b>' . ': <br/>';

                $test_cal = false;
                foreach ($rapport as $event_found_ics) {
                    // On recupère uniquement le fichier ics qui est dans la partie data pour le parser
                    $event_found_ical = new ICal\ICal($event_found_ics->getData());

                    // On regarde event par event, un fichier ics peut en contenir plusieurs (en cas de répétition)
                    foreach ($event_found_ical->events() as &$event_found) {
                        $test_rdv = true;
                        $test_cal = true;

                        if ($this->compare_time($curr_event->dtstart_array[1], $curr_event->dtend_array[1], $event_found->dtstart_array[1], $event_found->dtend_array[1])) {
                            $temp .= $event_found->summary . ': ' . $this->pretty_date($event_found->dtstart_array[1], $event_found->dtend_array[1]) . '<br/>';
                        }

                    }
                }
                if ($test_cal) {
                    $meeting_collision .= $temp;
                }
            }

        }
        if ($test_rdv) {
            $meeting_collision = $this->gettext('same_hour_meeting') . '<br/>' . $meeting_collision;
        }
        return $meeting_collision;
    }
}

