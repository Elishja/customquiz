<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * The main mod_customquiz configuration form.
 *
 * @package     mod_customquiz
 * @copyright   2025 Ingrid Vladimisky <ingrid.vladimisky@northius.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

/**
 * Module instance settings form.
 *
 * @package     mod_customquiz
 * @copyright   2025 Ingrid Vladimisky
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_customquiz_mod_form extends moodleform_mod {

    public function definition() {
        global $CFG, $DB, $COURSE;

        $mform = $this->_form;

        // === General ===
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('customquizname', 'mod_customquiz'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'customquizname', 'mod_customquiz');

        $this->standard_intro_elements();

        // === Configuración personalizada ===
        $mform->addElement('header', 'customquizfieldset', get_string('customquizfieldset', 'mod_customquiz'));

        // Categorías permitidas (desde el banco de preguntas del curso)
        $context = context_course::instance($COURSE->id);
        $categories = $DB->get_records('question_categories', ['contextid' => $context->id], 'name ASC');
        $options = [];

        foreach ($categories as $cat) {
            $catcontext = context::instance_by_id($cat->contextid);
            $contextname = $catcontext->get_context_name(false);
            $options[$cat->id] = $cat->name . " ($contextname)";
        }

        $select = $mform->addElement('select', 'allowedcategories', get_string('allowedcategories', 'mod_customquiz'), $options);
        $select->setMultiple(true); // ✅ correcto

        $mform->setType('allowedcategories', PARAM_RAW);
        $mform->addRule('allowedcategories', get_string('required'), 'required', null, 'client');

        // Mínimo y máximo de preguntas
        $mform->addElement('text', 'minquestions', get_string('minquestions', 'mod_customquiz'));
        $mform->setType('minquestions', PARAM_INT);
        $mform->setDefault('minquestions', 5);

        $mform->addElement('text', 'maxquestions', get_string('maxquestions', 'mod_customquiz'));
        $mform->setType('maxquestions', PARAM_INT);
        $mform->setDefault('maxquestions', 20);

        // Tiempo por pregunta
        $mform->addElement('duration', 'timeperquestion', get_string('timeperquestion', 'mod_customquiz'));
        $mform->setDefault('timeperquestion', 0);

        // Filtros de pregunta
        $mform->addElement('advcheckbox', 'onlyunanswered', get_string('onlyunanswered', 'mod_customquiz'));
        $mform->setDefault('onlyunanswered', 0);

        $mform->addElement('advcheckbox', 'onlyincorrect', get_string('onlyincorrect', 'mod_customquiz'));
        $mform->setDefault('onlyincorrect', 0);

        // Comportamiento del intento
        $behaviours = [
            'deferredfeedback' => get_string('deferredfeedback', 'question'),
            'immediatefeedback' => get_string('immediatefeedback', 'question'),
            'interactive' => get_string('interactive', 'question')
        ];
        $mform->addElement('select', 'attemptbehaviour', get_string('attemptbehaviour', 'mod_customquiz'), $behaviours);
        $mform->setDefault('attemptbehaviour', 'deferredfeedback');

        // === Calificaciones y ajustes del curso ===
        $this->standard_grading_coursemodule_elements();
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
}
