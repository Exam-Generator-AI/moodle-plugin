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
    $task = new \local_aiquestions\task\questions();
    if ($task) {
        $uniqid = uniqid($USER->id, true);       
        $text=$data->textinput;
        $fileDetails = null; 
        if ($data->field == "file") {
            if ($draftitemid = file_get_submitted_draft_itemid('userfile')) {
                // Prepare file storage
                $context = context_system::instance();
                $fs = get_file_storage();
        
                // Save the file permanently from the draft area
                file_save_draft_area_files($draftitemid, $context->id, 'user', 'private', 0, array('subdirs' => 0, 'maxbytes' => 0, 'areamaxbytes' => 10485760, 'maxfiles' => 50));
        
                // Retrieve the files from the permanent area
                $files = $fs->get_area_files($context->id, 'user', 'private', 0, 'id', false);
        
                // Debug: Check permanent area files after saving
                if (count($files) > 0) {
                    foreach ($files as $file) {
                        if (isset($file)) {
                            $fileDetails = array('name' => $file->get_filename() , 'mimeType' => $file->get_mimetype()) ;
                            break;
                        }
                        // echo "Filecontent: $filecontent\n";
                        // $fileDetails->content = $filecontent;
                        // $fileDetails->name = $filename;
                        // $fileDetails->mimeType = $mimeType;
                        // $fileDetails->path = $filepath;


                    }
                } else {
                    echo "No files found in the permanent area.";
                }
            } else {
                echo "No draft item ID found.";
            }
        }
        // return
        $examData = (object) [
            'category' => $data->category,
            'numofopenquestions' => $data->numofopenquestions,
            'numofmultiplechoicequestions' => $data->numofmultiplechoicequestions,
            'numsofblankquestions' => $data->numofblankquestions,
            'courseid' => $data->courseid,
            'userid' => $USER->id,
            'uniqid' => $uniqid,
            'difficulty' => $data->difficulty,
            'language' => $data->language,
            'field' => $data->field,
            'examFocus' => $data->examFocus,
            'skills' => $data->skills,
            'textinput' =>  $text,
            'fileDetails'=>$fileDetails

            // 'fileDetails' => $fileDetails
        ];
        $task = \local_aiquestions\task\questions::instance($examData);
        $task->execute($data);
        // $task->set_attempts_available(3);
        // \core\task\manager::queue_adhoc_task($task);
            // Check if the cron is overdue.
        $lastcron = get_config('tool_task', 'lastcronstart');
        $cronoverdue = ($lastcron < time() - 3600 * 24);

    } else {
        echo get_string('taskerror', 'local_aiquestions');
    }

    $datafortemplate = [
        'courseid' => $courseid,
        'wwwroot' => $CFG->wwwroot,
        'uniqid' => $uniqid,
        'userid' => $USER->id,
        'cron' => $cronoverdue
    ];
    echo $OUTPUT->render_from_template('local_aiquestions/loading', $datafortemplate);
} else {
    $mform->display();
}

echo $OUTPUT->footer();
?>
