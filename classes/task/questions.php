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
 * Adhoc task for questions generation.
 *
 * @package     local_aiquestions
 * @category    admin
 * @copyright   2023 Ruthy Salomon <ruthy.salomon@gmail.com> , Yedidia Klein <yedidia@openapp.co.il>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aiquestions\task;

defined('MOODLE_INTERNAL') || die();

/**
 * The question generator adhoc task.
 *
 * @package     local_aiquestions
 * @category    admin
 */
class questions extends \core\task\adhoc_task
{

    public static function instance($data): self {
        $task = new self();
        $task->set_custom_data($data);

        return $task;
    }
    /**
     * Execute the task.
     *
     * @return void
     */
    public function execute()
    {
        global $DB, $CFG, $_FILES, $_USER;
        require_once(__DIR__ . '/../../locallib.php');
        // Read numoftries from settings.
        $numoftries = get_config('local_aiquestions', 'numoftries');

        $data = $this->get_custom_data();
        // Get the data from the task.
        $courseid = $data->courseid;
        $category = $data->category;
        $userid = $data->userid;
        $uniqid = $data->uniqid;
        //exam section
        $numofquestions = $data->numofopenquestions;
        $text = $data->text;
        $skills = $data->skills;
        $questionLevel = $data->questionLevel;
        $examLanguage = $data->examLanguage;
        $field = $data->field;
        $examFocus = $data->examFocus;


        // Create the DB entry.
        $dbrecord = new \stdClass();
        $dbrecord->course = $courseid;
        $dbrecord->numoftries = $numoftries;
        $dbrecord->userid = $userid;
        $dbrecord->timecreated = time();
        $dbrecord->timemodified = time();
        $dbrecord->tries = 0;
        $dbrecord->numoftries = $numoftries;
        $dbrecord->uniqid = $uniqid;
        $dbrecord->gift = '';
        $dbrecord->success = '';
        $inserted = $DB->insert_record('local_aiquestions', $dbrecord);

        // Create questions.
        $created = false;
        $i = 1;
        $error = ''; // Error message.
        $update = new \stdClass();

        $api_key = get_config('local_aiquestions', 'key');
        echo "api_key:" . $api_key;
        echo "[local_aiquestions] Creating Questions via OpenAI...\n";
        echo "[local_aiquestions] Try $i of $numoftries...\n";

        while (!$created && $i <= 1) {

            // First update DB on tries.
            $update->id = $inserted;
            $update->tries = $i;
            $update->datemodified = time();
            $DB->update_record('local_aiquestions', $update);

            echo "starting to get questions from OpenAI...\n";
            // Get questions from ChatGPT API.
            $questions = \local_aiquestions_get_questions($data);
            echo "[local_aiquestions] Questions received from OpenAI...\n";
            // Print error message of ChatGPT API (if there are).
            // if (isset($questions->error->message)) {
            //     $error .= $questions->error->message;

                // Print error message to cron/adhoc output.
            //     echo "[local_aiquestions] Error : $error.\n";
            // }
            // Check gift format.
            if (property_exists($questions, 'text')) {
                // if (\local_aiquestions_check_gift($questions->text)) {

                    // Create the questions, return an array of objetcs of the created questions.
                    $created = \local_aiquestions_create_questions($courseid, $category, $questions->text, $data->numofmultiplechoicequestions + $data->multipleQuestions + $data->numsofblankquestions, $userid);
                    if ($created === false){ 
                         echo "something went wrong cannot create all questions";
                    } else { //all questions inserted to the question bank
                        $j = 0;
                        foreach ($created as $question) {
                            $success[$j]['id'] = $question->id;
                            $success[$j]['questiontext'] = $question->questiontext;
                            $j++;
                        }

                        echo "[local_aiquestions] Successfully created $j questions!\n";

                        // Insert success creation info to DB.
                        $update->id = $inserted;
                        $update->gift = $questions->text;
                        $update->tries = $i;
                        $update->success = json_encode(array_values($success));
                        $update->datemodified = time();
                        $DB->update_record('local_aiquestions', $update);
                }
                // }
            } else {
                echo "[local_aiquestions] Error: No question text returned \n";
            }
            $i++;
        }

        // If questions were not created.
        if (!$created) {
            // Insert error info to DB.
            $update = new \stdClass();
            $update->id = $inserted;
            $update->tries = $i - 1;
            $update->timemodified = time();
            $update->success = 0;
            $DB->update_record('local_aiquestions', $update);
        }

        // Print error message.
        // It will be shown on cron/adhoc output (file/whatever).
        if ($error != '') {
            echo '[local_aiquestions adhoc_task]' . $error;
        }
    }
}
