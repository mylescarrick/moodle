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
 * @package moodlecore
 * @subpackage backup-plan
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Implementable class defining the needed stuf for one restore plan
 *
 * TODO: Finish phpdocs
 */
class restore_plan extends base_plan implements loggable {

    protected $controller; // The restore controller building/executing this plan
    protected $basepath;   // Fullpath to dir where backup is available

    /**
     * Constructor - instantiates one object of this class
     */
    public function __construct($controller) {
        global $CFG;

        if (! $controller instanceof restore_controller) {
            throw new restore_plan_exception('wrong_restore_controller_specified');
        }
        $this->controller = $controller;
        $this->basepath   = $CFG->dataroot . '/temp/backup/' . $controller->get_tempdir();
        parent::__construct('restore_plan');
    }

    public function build() {
        restore_plan_builder::build_plan($this->controller); // We are moodle2 always, go straight to builder
        $this->built = true;
    }

    public function get_restoreid() {
        return $this->controller->get_restoreid();
    }

    public function get_courseid() {
        return $this->controller->get_courseid();
    }

    public function get_basepath() {
        return $this->basepath;
    }

    public function get_logger() {
        return $this->controller->get_logger();
    }

    public function get_info() {
        return $this->controller->get_info();
    }

    public function log($message, $level, $a = null, $depth = null, $display = false) {
        backup_helper::log($message, $level, $a, $depth, $display, $this->get_logger());
    }

    /**
     * Function responsible for executing the tasks of any plan
     */
    public function execute() {
        if ($this->controller->get_status() != backup::STATUS_AWAITING) {
            throw new restore_controller_exception('restore_not_executable_awaiting_required', $this->controller->get_status());
        }
        $this->controller->set_status(backup::STATUS_EXECUTING);
        parent::execute();
        $this->controller->set_status(backup::STATUS_FINISHED_OK);
    }
}

/*
 * Exception class used by all the @restore_plan stuff
 */
class restore_plan_exception extends base_plan_exception {

    public function __construct($errorcode, $a=NULL, $debuginfo=null) {
        parent::__construct($errorcode, $a, $debuginfo);
    }
}