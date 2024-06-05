<?php
// This file is part of Moodle - https://moodle.org/.
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
 * Plugin administration pages are defined here.
 *
 * @package     local_aiquestions
 * @category    admin
 * @copyright   2023 Ruthy Salomon <ruthy.salomon@gmail.com> , Yedidia Klein <yedidia@openapp.co.il>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
defined('MOODLE_INTERNAL') || die();

$courseid = optional_param('courseid', 0, PARAM_INT);

if ($courseid == 0) {
    redirect(new moodle_url('/local/aiquestions/index.php'));
}

require_login($courseid);

$context = context_course::instance($courseid);
require_capability('moodle/question:add', $context);

require_once("$CFG->libdir/formslib.php");
require_once(__DIR__ . '/locallib.php');

$PAGE->set_context(context_system::instance());
$PAGE->set_heading(get_string('pluginname', 'local_aiquestions'));
$PAGE->set_title(get_string('pluginname', 'local_aiquestions'));
$PAGE->set_url('/local/aiquestions/story.php?courseid=' . $courseid);
$PAGE->set_pagelayout('standard');
$PAGE->navbar->add(get_string('pluginname', 'local_aiquestions'), new moodle_url('/local/aiquestions/'));
$PAGE->navbar->add(get_string('story', 'local_aiquestions'),
                    new moodle_url('/local/aiquestions/story.php?courseid=' . $courseid));
$PAGE->requires->js_call_amd('local_aiquestions/state');

echo $OUTPUT->header();

$mform = new local_aiquestions_story_form();

if ($mform->is_cancelled()) {
    redirect($CFG->wwwroot . '/course/view.php?id=' . $courseid);
} else if ($data = $mform->get_data()) {
    echo "data: " . $data->category . "<br>";
    $task = new \local_aiquestions\task\questions();
    if ($task) {
        $uniqid = uniqid($USER->id, true);
        $preset = $data->preset;
        $primer = 'primer' . $preset;
        $instructions = 'instructions' . $preset;
        $example = 'example' . $preset;

            // Handle file upload and extract text content
        //$extractedText = $mform->handle_file_upload($data, $_FILES);
        //if (!empty($extractedText)) {
        //    $data->textinput = $extractedText;
        //}
        $data = (object) [
            'category' => $data->category,
            'numofopenquestions' => $data->numofopenquestions,
            'numofmultiplechoicequestions' => $data->numofmultiplechoicequestions,
            'courseid' => $data->courseid,
            'userid' => $USER->id,
            'uniqid' => $uniqid,
            'questionLevel' => $data->questionLevel,
            'examLanguage' => $data->examLanguage,
            'field' => $data->field,
            'examFocus' => $data->examFocus,
            'skills' => $data->skills,
            'textinput' => $data->textinput
        ];

        // $questions = \local_aiquestions_get_questions($data);
        $task->execute($data);
        if (isset($questions->text)) {
            $created = \local_aiquestions_create_questions(
                $data->courseid,
                $data->category,
                $questions->text,
                $data->numofopenquestions + $data->numofmultiplechoicequestions,
                $data->userid
            );

            if ($created) {
                echo "[local_aiquestions] Successfully created questions!";
            } else {
                echo "[local_aiquestions] Error: Failed to create questions.";
            }
        } else {
            echo "[local_aiquestions] Error: No question text returned from API.";
        }

    } else {
        echo get_string('taskerror', 'local_aiquestions');
    }

    $datafortemplate = [
        'courseid' => $courseid,
        'wwwroot' => $CFG->wwwroot,
        'uniqid' => $uniqid,
        'userid' => $USER->id,
    ];
    echo $OUTPUT->render_from_template('local_aiquestions/loading', $datafortemplate);
} else {
    $mform->display();
}

echo $OUTPUT->footer();
?>
