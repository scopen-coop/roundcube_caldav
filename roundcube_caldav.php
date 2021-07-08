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


class roundcube_caldav extends rcube_plugin
{
    public $task = 'settings';

    function init()
    {

        $this->rc = rcmail::get_instance();
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



        return $param_list;
    }

    function preferences_save($save_params)
    {
        if ($save_params['section'] == 'server_caldav') {
            $this->rc = rcmail::get_instance();
            $save_params['prefs'] = array();

            if (!empty($_POST['_define_server_caldav']) && !empty($_POST['_define_login']) &&
                !empty($_POST['_define_password'])) {
                $this->rc->output->command('display_message', $this->gettext('save_msg'), 'valid');
                $save_params['prefs']['url_base'] = rcube_utils::get_input_value('_define_server_caldav', rcube_utils::INPUT_POST);
                $save_params['prefs']['login'] = rcube_utils::get_input_value('_define_login', rcube_utils::INPUT_POST);
                $save_params['prefs']['password'] = rcube_utils::get_input_value('_define_password', rcube_utils::INPUT_POST);

            } else {
                $this->rc->output->command('display_message', $this->gettext('save_error_msg'), 'error');
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
        $rcube = rcube::get_instance();

        // Champs pour specifier l'url du serveur
        $field_id = 'define_server_caldav';
        $url_base = new html_inputfield(array('name' => '_' . $field_id, 'id' => $field_id));
        $param_list['blocks']['main']['options']['url_base'] = array(
            'title' => html::label($field_id, rcube::Q($this->gettext('url_base'))),
            'content' => $url_base->show($rcube->config->get('url_base', false)),
        );

        // Champs pour specifier le login
        $field_id = 'define_login';
        $login = new html_inputfield(array('name' => '_' . $field_id, 'id' => $field_id));
        $param_list['blocks']['main']['options']['login'] = array(
            'title' => html::label($field_id, rcube::Q($this->gettext('login'))),
            'content' => $login->show($rcube->config->get('login', false)),
        );

        // Champs pour specifier le mot de passe
        $field_id = 'define_password';
        $password = new html_passwordfield(array('name' => '_' . $field_id, 'id' => $field_id));
        $param_list['blocks']['main']['options']['password'] = array(
            'title' => html::label($field_id, rcube::Q($this->gettext('password'))),
            'content' => $password->show($rcube->config->get('password', false)),
        );
        return $param_list;
    }

}



