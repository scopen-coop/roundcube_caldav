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

class roundcube_caldav extends rcube_plugin
{
    public $task = 'settings';
    public $rcube;
    public $rcmail;

    function init()
    {
        $this->rcmail = rcmail::get_instance();
        $this->rcube = rcube::get_instance();

        $this->load_config();
        $this->add_texts('localization/');

        $this->add_hook('preferences_sections_list', array($this, 'modify_section'));
        $this->add_hook('preferences_list', array($this, 'preferences_list'));
        $this->add_hook('preferences_save', array($this, 'preferences_save'));
    }


    function modify_section($args)
    {
        $args['list']['server_caldav'] = array(
            'id' => 'server_caldav', 'section' => $this->gettext('server_caldav'),
        );
        return $args;
    }

    function preferences_list($param_list)
    {
        if ($param_list['section'] != 'server_caldav') {
            return $param_list;
        }
        $param_list['blocks']['main']['name'] = $this->gettext('settings');
        $param_list = $this->server_caldav_form($param_list);

        $server = $this->rcube->config->get('server_caldav');

        $_url_base = $server['_url_base'];
        $_login = $server['_login'];
        $_password = $server['_password'];
        if (!empty($_url_base) && !empty($_login) && !empty($_password)) {
            $param_list = $this->connection_server_calDAV($_url_base, $_login, $_password, $param_list);
        }
        return $param_list;
    }

    function preferences_save($save_params)
    {

        if ($save_params['section'] == 'server_caldav') {

            if (!empty($_POST['_define_server_caldav']) && !empty($_POST['_define_login']) &&
                !empty($_POST['_define_password'])) {
                $this->rcmail->output->command('display_message', $this->gettext('save_msg'), 'valid');
                $save_params['prefs']['server_caldav']['_url_base'] = rcube_utils::get_input_value('_define_server_caldav', rcube_utils::INPUT_POST);
                $save_params['prefs']['server_caldav']['_login'] = rcube_utils::get_input_value('_define_login', rcube_utils::INPUT_POST);
                $save_params['prefs']['server_caldav']['_password'] = rcube_utils::get_input_value('_define_password', rcube_utils::INPUT_POST);
                $save_params['prefs']['server_caldav']['_main_calendar'] = rcube_utils::get_input_value('_define_main_calendar', rcube_utils::INPUT_POST);

                foreach (rcube_utils::get_input_value('_define_used_calendars', rcube_utils::INPUT_POST) as $cal) {
                    $save_params['prefs']['server_caldav']['_used_calendars'][$cal] = $cal;
                }

            } else {
                $this->rcmail->output->command('display_message', $this->gettext('save_error_msg'), 'error');
            }
        }
        $save_params['result'] = true;
        $save_params['abort'] = false;
        return $save_params;
    }

    /**
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
            'content' => $password->show($server['_password']),
        );
        return $param_list;
    }


    function connection_server_calDAV($_url_base, $_login, $_password, array $args)
    {


        $args['blocks']['main']['options']['calendar_choice'] = array(
            'title'   => html::label('ojk', rcube::Q($this->gettext('calendar_choice'))),
        );

        $server = $this->rcube->config->get('server_caldav');
        $client = new SimpleCalDAVClient();
        try {

            $client->connect($_url_base, $_login, $_password);
            $arrayOfCalendars = $client->findCalendars();

            foreach ($arrayOfCalendars as $cal) {
                $print=null;
                foreach ($server['_used_calendars'] as $used_calendar){
                    if($used_calendar == $cal->getCalendarID()){
                        $print = $cal->getCalendarID();
                    }
                }


                $radiobutton = new html_radiobutton(array('name' => '_define_main_calendar', 'value' => $cal->getCalendarID()));
                $checkbox = new html_checkbox(array('name' => '_define_used_calendars[]', 'value' => $cal->getCalendarID()));
                $args['blocks']['main']['options'][$cal->getCalendarID()] = array(
                    'title' => html::label($cal->getCalendarID(), $cal->getDisplayName()),
                    'content' => html::div('input-group', $radiobutton->show($server['_main_calendar']) .
                        $checkbox->show($print)
                    ));

            }
        } catch (Exception $e) {
            echo $e->__toString();
        }
        return $args;
    }
}


//$server['_main_calendar']
//$server['_used_calendars'][$cal->getCalendarID()]