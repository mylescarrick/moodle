<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Web services admin UI forms
 *
 * @package   webservice
 * @copyright 2009 Moodle Pty Ltd (http://moodle.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once $CFG->libdir.'/formslib.php';

class external_service_form extends moodleform {
    function definition() {
        global $CFG, $USER;

        $mform = $this->_form;
        $service = $this->_customdata;

        $mform->addElement('header', 'extservice', get_string('externalservice', 'webservice'));

        $mform->addElement('text', 'name', get_string('name'));
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->addElement('advcheckbox', 'enabled', get_string('enabled', 'webservice'));


        /// needed to select automatically the 'No required capability" option
        $currentcapabilityexist = false;
        if (empty($service->requiredcapability))
        {
          $service->requiredcapability = "norequiredcapability";
          $currentcapabilityexist = true;
        }

        // Prepare the list of capabilites to choose from
        $systemcontext = get_context_instance(CONTEXT_SYSTEM);
        $allcapabilities = fetch_context_capabilities($systemcontext);
        $capabilitychoices = array();
        $capabilitychoices['norequiredcapability'] = get_string('norequiredcapability', 'webservice');
        foreach ($allcapabilities as $cap) {
            $capabilitychoices[$cap->name] = $cap->name . ': ' . get_capability_string($cap->name);
            if (!empty($service->requiredcapability) && $service->requiredcapability == $cap->name) {
                $currentcapabilityexist = true;
            }
        }

        $mform->addElement('searchableselector', 'requiredcapability', get_string('requiredcapability', 'webservice'), $capabilitychoices);

        /// display notification error if the current requiredcapability doesn't exist anymore
        if(empty($currentcapabilityexist)) {
            global $OUTPUT;
            $mform->addElement('static', 'capabilityerror', '', $OUTPUT->notification(get_string('selectedcapabilitydoesntexit','webservice', $service->requiredcapability)));
            $service->requiredcapability = "norequiredcapability";
        }
        $mform->addElement('advcheckbox', 'restrictedusers', get_string('restrictedusers', 'webservice'));

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons(true);

        $this->set_data($service);
    }

    function definition_after_data() {
        $mform = $this->_form;
        $service = $this->_customdata;

        if (!empty($service->component)) {
            // built-in components must not be modified except the enabled flag!!
            $mform->hardFreeze('name,requiredcapability,restrictedusers');
        }
    }

    function validation($data, $files) {
        $errors = parent::validation($data, $files);
        return $errors;
    }
}


class external_service_functions_form extends moodleform {
    function definition() {
        global $CFG, $USER, $DB;

        $mform = $this->_form;
        $data = $this->_customdata;

        $mform->addElement('header', 'addfunction', get_string('addfunction', 'webservice'));

        $select = "name NOT IN (SELECT s.functionname
                                  FROM {external_services_functions} s
                                 WHERE s.externalserviceid = :sid
                               )";

        $functions = $DB->get_records_select_menu('external_functions', $select, array('sid'=>$data['id']), 'name', 'id, name');

        //we add the descriptions to the functions
        foreach ($functions as $functionid => $functionname) {
            $function = external_function_info($functionname); //retrieve full function information (including the description)
            $functions[$functionid] = $function->name.':'.$function->description;
        }

        $mform->addElement('searchableselector', 'fid', get_string('name'), $functions, array('multiple' => true));

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_ACTION);

        $this->add_action_buttons(true);

        $this->set_data($data);
    }
}


class web_service_token_form extends moodleform {
    function definition() {
        global $CFG, $USER, $DB;

        $mform = $this->_form;
        $data = $this->_customdata;

        $mform->addElement('header', 'token', get_string('token', 'webservice'));

        if (empty($data->nouserselection)) {
            //user searchable selector
            $sql = "SELECT u.id, u.firstname, u.lastname
            FROM {user} u
            WHERE NOT EXISTS ( SELECT 1
                                    FROM {user} au, {role_assignments} r
                                    WHERE au.id=u.id AND r.roleid = 1 AND r.userid = au.id)
            ORDER BY u.lastname";
            $users = $DB->get_records_sql($sql,array());
            $options = array();
            foreach ($users as $userid => $user) {
                    $options[$userid] = $user->firstname. " " . $user->lastname;
            }
            $mform->addElement('searchableselector', 'user', get_string('user'),$options);
            $mform->addRule('user', get_string('required'), 'required', null, 'client');
        }

        //service selector
        $services = $DB->get_records('external_services');
        $options = array();
        $systemcontext = get_context_instance(CONTEXT_SYSTEM);
        foreach ($services as $serviceid => $service) {
            //check that the user has the required capability (only for generation by the profil page)
            if (empty($data->nouserselection) 
                || empty($service->requiredcapability)
                || has_capability($service->requiredcapability, $systemcontext, $USER->id)) {
                $options[$serviceid] = $service->name;
            }
        }
        $mform->addElement('select', 'service', get_string('service', 'webservice'),$options);
        $mform->addRule('service', get_string('required'), 'required', null, 'client');
       
        
        $mform->addElement('text', 'iprestriction', get_string('iprestriction', 'webservice'));

        $mform->addElement('date_selector', 'validuntil', get_string('validuntil', 'webservice'), array('optional'=>true));

        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_ACTION);

        $this->add_action_buttons(true);

        $this->set_data($data);
    }

    function validation($data, $files) {
        $errors = parent::validation($data, $files);
        return $errors;
    }
}