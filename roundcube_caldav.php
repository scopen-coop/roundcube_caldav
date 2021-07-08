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

        $_url_base = $this->rcube->config->get('url_base', false);
        $_login = $this->rcube->config->get('login', false);
        $_password = $this->rcube->config->get('password', false);
        if (!empty($_url_base) && !empty($_login) && !empty($_password)) {
            $param_list = $this->connection_server_calDAV($_url_base, $_login, $_password,$param_list);
        }
        return $param_list;
    }

    function preferences_save($save_params)
    {
        if ($save_params['section'] == 'server_caldav') {
            $save_params['prefs'] = array();

            if (!empty($_POST['_define_server_caldav']) && !empty($_POST['_define_login']) &&
                !empty($_POST['_define_password'])) {
                $this->rcmail->output->command('display_message', $this->gettext('save_msg'), 'valid');
                $save_params['prefs']['url_base'] = rcube_utils::get_input_value('_define_server_caldav', rcube_utils::INPUT_POST);
                $save_params['prefs']['login'] = rcube_utils::get_input_value('_define_login', rcube_utils::INPUT_POST);
                $save_params['prefs']['password'] = rcube_utils::get_input_value('_define_password', rcube_utils::INPUT_POST);

            } else {
                $this->rcmail->output->command('display_message', $this->gettext('save_error_msg'), 'error');
            }
        }
        return $save_params;
    }

    /**
     * @param array $param_list
     * @return array
     */
    public function server_caldav_form(array $param_list)
    {

        // Champs pour specifier l'url du serveur
        $field_id = 'define_server_caldav';
        $url_base = new html_inputfield(array('name' => '_' . $field_id, 'id' => $field_id));
        $param_list['blocks']['main']['options']['url_base'] = array(
            'title' => html::label($field_id, rcube::Q($this->gettext('url_base'))),
            'content' => $url_base->show($this->rcube->config->get('url_base', false)),
        );

        // Champs pour specifier le login
        $field_id = 'define_login';
        $login = new html_inputfield(array('name' => '_' . $field_id, 'id' => $field_id));
        $param_list['blocks']['main']['options']['login'] = array(
            'title' => html::label($field_id, rcube::Q($this->gettext('login'))),
            'content' => $login->show($this->rcube->config->get('login', false)),
        );

        // Champs pour specifier le mot de passe
        $field_id = 'define_password';
        $password = new html_passwordfield(array('name' => '_' . $field_id, 'id' => $field_id));
        $param_list['blocks']['main']['options']['password'] = array(
            'title' => html::label($field_id, rcube::Q($this->gettext('password'))),
            'content' => $password->show($this->rcube->config->get('password', false)),
        );
        return $param_list;
    }

    function connection_server_calDAV($_url_base, $_login, $_password,array $args)
    {
        //$args['blocks']['main']['name'] = $this->gettext('settings');
        $client = new SimpleCalDAVClient();
        try {

            $client->connect($_url_base, $_login, $_password);
            $arrayOfCalendars = array_keys($client->findCalendars());

            foreach ($arrayOfCalendars as $cal) {
                $field_id = $cal;
                $checkbox = new html_checkbox(array('name' => '_' . $field_id, 'id' => $field_id, 'value' => 1));

                $args['blocks']['main']['options'][$cal] = array(
                    'title'   => html::label($field_id, $cal),
                    'content' => $checkbox->show(intval($this->rcube->config->get($cal, false)))
                );
            }
        } catch (Exception $e) {
            echo $e->__toString();
        }
        return $args;
    }
}



