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
 * Plugin administration pages are defined here.
 *
 * @package     local_aiquestions
 * @category    admin
 * @copyright   2023 Ruthy Salomon <ruthy.salomon@gmail.com> , Yedidia Klein <yedidia@openapp.co.il>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Get questions from the API.
 *
 * @param object $data Data to create questions from
 * @return object Questions of generated questions
 */
function local_aiquestions_get_questions($data) {
    global $CFG;

    // Build primer.
    $primer = $data->primer;
    $primer .= "Write {$data->numofquestions} questions.";

    $key = get_config('local_aiquestions', 'key');
    $model = get_config('local_aiquestions', 'model');
    $url = 'http://127.0.0.1:5000/generate/exam/sync'; // Change this to sync route
    $authorization = "Authorization: Bearer " . $key;

    // Remove new lines and carriage returns.
    $story = str_replace(["\n", "\r"], " ", $data->story);
    $instructions = str_replace(["\n", "\r"], " ", $data->instructions);
    $example = str_replace(["\n", "\r"], " ", $data->example);

    $numofquestions = $data->numofquestions;
    $text = $data->text;
    $examTags = $data->examTags;
    $questionLevel = $data->questionLevel;
    $examLanguage = $data->examLanguage;
    $field = $data->field;
    $examFocus = $data->examFocus;

    $postData = json_encode([
        'text' => $text,
        'field' => $field,
        'examFocus' => $examFocus,
        'examTags' => $examTags,
        'examLanguage' => $examLanguage,
        'questions' => ['multiple_choice' => $numofquestions],
        'levelQuestions' => $questionLevel
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', $authorization]);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2000);
    $result = json_decode(curl_exec($ch));
    curl_close($ch);

    $questions = new stdClass(); // The questions object.
    if (isset($result->gift)) { // TODO: return from exam server 
        $questions->text = $result->gift;
        $questions->prompt = $story;
    } else {
        $questions = $result;
        $questions->prompt = $story;
    }
    return $questions;
}

/**
 * Create questions from data got from ChatGPT output.
 *
 * @param int $courseid Course ID
 * @param int $category Course category
 * @param string $gift Questions in GIFT format
 * @param int $numofquestions Number of questions to generate
 * @param int $userid User ID
 * @return array Array of objects of created questions
 */
function local_aiquestions_create_questions($courseid, $category, $gift, $numofquestions, $userid) {
    global $CFG, $USER, $DB;

    require_once($CFG->libdir . '/questionlib.php');
    require_once($CFG->dirroot . '/question/format.php');
    require_once($CFG->dirroot . '/question/format/gift/format.php');

    $qformat = new \qformat_gift();
    $coursecontext = \context_course::instance($courseid);

    // Get question category TODO: there is probably a better way to do this.
    if ($category) {
        $categoryids = explode(',', $category);
        $categoryid = $categoryids[0];
        $categorycontextid = $categoryids[1];
        $category = $DB->get_record('question_categories', ['id' => $categoryid, 'contextid' => $categorycontextid]);
    }

    // Use existing questions category for quiz or create the defaults.
    if (!$category) {
        $contexts = new core_question\local\bank\question_edit_contexts($coursecontext);
        if (!$category = $DB->get_record('question_categories', ['contextid' => $coursecontext->id, 'sortorder' => 999])) {
            $category = question_make_default_categories($contexts->all());
        }
    }

    // Split questions based on blank lines.
    // Then loop through each question and create it.
    $questions = explode("\n\n", $gift);

    if (count($questions) != $numofquestions) {
        return false;
    }
    $createdquestions = []; // Array of objects of created questions.
    foreach ($questions as $question) {
        $singlequestion = explode("\n", $question);

        // Manipulating question text manually for question text field.
        $questiontext = explode('{', $singlequestion[0]);
        $questiontext = trim(preg_replace('/^.*::/', '', $questiontext[0]));
        $qtype = 'multichoice';
        $q = $qformat->readquestion($singlequestion);

        // Check if question is valid.
        if (!$q) {
            return false;
        }
        $q->category = $category->id;
        $q->createdby = $userid;
        $q->modifiedby = $userid;
        $q->timecreated = time();
        $q->timemodified = time();
        $q->questiontext = ['text' => "<p>" . $questiontext . "</p>"];
        $q->questiontextformat = 1;

        $created = question_bank::get_qtype($qtype)->save_question($q, $q);
        $createdquestions[] = $created;
    }
    if ($created) {
        return $createdquestions;
    } else {
        return false;
    }
}

/**
 * Escape JSON.
 *
 * @param string $value JSON to escape
 * @return string Result escaped JSON
 */
function local_aiquestions_escape_json($value) {
    $escapers = ["\\", "/", "\"", "\n", "\r", "\t", "\x08", "\x0c"];
    $replacements = ["\\\\", "\\/", "\\\"", "\\n", "\\r", "\\t", "\\f", "\\b"];
    $result = str_replace($escapers, $replacements, $value);
    return $result;
}

/**
 * Check if the GIFT format is valid.
 *
 * @param string $gift Questions in GIFT format
 * @return bool True if valid, false if not
 */
function local_aiquestions_check_gift($gift) {
    $questions = explode("\n\n", $gift);

    foreach ($questions as $question) {
        $qa = str_replace("\n", "", $question);
        preg_match('/::(.*)\{/', $qa, $matches);
        if (isset($matches[1])) {
            $qlength = strlen($matches[1]);
        } else {
            return false; // Error: Question title not found.
        }
        if ($qlength < 10) {
            return false; // Error: Question length too short.
        }
        preg_match('/\{(.*)\}/', $qa, $matches);
        if (isset($matches[1])) {
            $wrongs = substr_count($matches[1], "~");
            $right = substr_count($matches[1], "=");
        } else {
            return false; // Error: Answers not found.
        }
        if ($wrongs != 3 || $right != 1) {
            return false; // Error: There is no single right answer or no 3 wrong answers.
        }
    }
    return true;
}
?>
