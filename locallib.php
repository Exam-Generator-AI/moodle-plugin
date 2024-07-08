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
/**
 * Get questions from the API.
 *
 * @param object $data Data to create questions from
 * @return object Questions of generated questions
 * @throws Exception if the request fails
 */

 require_once(__DIR__ . '/../../config.php');
 print("libdir-".$CFG->libdir);
// Directly include the filelib.php file
 require_once(__DIR__ . '/../../lib/filelib.php');


function get_auth_token($data) {
    global $USER, $SESSION;
    require_login();

    $key = get_config('local_aiquestions', 'key'); // TODO: Change this to the actual key
    echo "key: ". $key;
    $url = 'http://host.docker.internal:5000/api/v1/auth/external'; // Change this to sync route
    $authorization = "X-API-KEY:" . $key;

    $data = '{
        "email": "'. $USER->email .'"
    }';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', $authorization]);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2000);


    // Execute the request and wait for the response
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Close the cURL session
    curl_close($ch);

    // Decode the response
    $result = json_decode($response);

    echo "result from exam" . $httpCode;

    // Check if the response is valid
    if ($httpCode !== 200) {
        return False;
    }

    if (isset($result->access_token)) {
        # code...
        $SESSION->access_token = $result->access_token;
        return $result;
    }
    return false;

    }


function read_file($fileData,$access_token){
    print("send file to exam". $fileData->name ."\n");
    print("path ". $fileData->path . $fileData->name."\n");

    $url = 'http://host.docker.internal:5000/api/v1/exports'; // Change this to sync routex
    $authorization = "Authorization: Bearer " . $access_token;

    if (empty($access_token)) {
        # code...
        return (object)['response'=>'{}' , 'httpCode'=>401];
    }
    echo "mime type - $fileData->mimeType \n\n";
    if ($fileData->mimeType === 'application/vnd.openxmlformats-officedocument.presentationml.presentation') {
        echo "pptx file \n\n";
       $url = $url."/pptx/read";
       $field="pptFile";
    } else {
       echo "pdf file \n\n";
       $url = $url."/pdf/read";
       $field="pdfFile";
    }

    // Create a temporary file
    $tempDir = make_temp_directory('mytempfiles');
    $tempFilePath = tempnam($tempDir, 'tempfile_');
    file_put_contents($tempFilePath, $fileData->content);

    if (function_exists('curl_file_create')) { // php 5.5+
        $cFile = curl_file_create($tempFilePath, $fileData->mimeType, $fileData->name);
      } else { // 
        $cFile = '@' . realpath($tempFilePath);
      }
    // cURL initialization
    $ch = curl_init($url);
    
    // cURL options
    curl_setopt($ch, CURLOPT_HTTPHEADER, [ $authorization]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $postData = array(
        $field => $cFile
    );
    
    // Set cURL options for file upload
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    $result=curl_exec($ch);
    
    // Clean up the temporary file
    if (file_exists($tempFilePath)) {
        unlink($tempFilePath);
        echo "Temporary file deleted: $tempFilePath\n";
    }
    // Check for cURL errors
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        echo "<script>console.log('cURL error: $error_msg');</script>";
        throw new Exception("cURL error: $error_msg");
    }
    curl_close($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $decode = json_decode($result);
    return (object)['response'=>$decode->text , 'httpCode'=>$httpCode];
} 
function send_exam( $data,$access_token='' ){
    $url = 'http://host.docker.internal:5000/api/v1/gen/exam/sync'; // Change this to sync routex
    $authorization = "Authorization: Bearer " . $access_token;

    $levelQuestions = $data->questionLevel;
    $text = $data->textinput;
    // $examTags = $data->skills;//TODO: fix skills to send Ids
    $examTags = [];
    $numofopenquestions = $data->numofopenquestions;
    $numsofblankquestions = $data->numsofblankquestions;
    $multipleQuestions = $data->numofmultiplechoicequestions;
    $examLanguage = $data->examLanguage;
    $field = $data->field;
    $examFocus = $data->examFocus;
    $example = "";
    $payload = [];
    $isClosedContent = false;

    if (empty($access_token)) {
        # code...
        return (object)['response'=>'{}' , 'httpCode'=>401];
    }
    $exam_data = '{
        "text": "'. $text .'", "field": "'. $field .'","examTags": [],"exampleQuestion": "'.$example.'",
        "examFocus": "'. $examFocus .'","examLanguage": "'. $examLanguage .'","payload": {},"isClosedContent": "'.$isClosedContent.'",
        "questions": {"multiple_choice": "'. $multipleQuestions .'","open_questions": "'. $numofopenquestions .'","fill_in_the_blank": '. $numsofblankquestions. '},"levelQuestions": "'. $levelQuestions .'"
    }';

    // Initialize cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', $authorization]);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $exam_data);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2000);


    // Print message before sending the request
    mtrace("Sending request to exam server...");

    // Check for cURL errors
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        echo "<script>console.log('cURL error: $error_msg');</script>";
        throw new Exception("cURL error: $error_msg");
    }
    // Execute the request and wait for the response
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    echo $httpCode;
    curl_close($ch);

    return (object)['response'=>json_decode($response) , 'httpCode'=>$httpCode];
}

function local_aiquestions_get_questions($data,$tries = 3) {
    global $CFG, $USER,$SESSION;

    if ($tries == 0) {
        throw new Exception("Cant authenticate to exam server");
    }
    $retrieved_token='';
    if (isset($SESSION->access_token)) {
        $retrieved_token = $SESSION->access_token;
    } else {
        $res = get_auth_token($data);
        if ($res === false) {
            # code...
            throw new Exception("Cant authenticate to exam server");
        }
        local_aiquestions_get_questions($data,$tries-1);
    }
    if ($data->field == 'file' && isset($data->fileDetails)) {
        $res= read_file($data->fileDetails,$retrieved_token);
        if ($res->httpCode == 200) {
            # code...
            $data->textinput = $res->response;
            $res = send_exam($data,$retrieved_token);
        }
    } else {
        $res = send_exam($data,$retrieved_token);
    }

    $httpCode = $res->httpCode;
    $response = $res->response;


    // Print message after receiving the response
    mtrace("<script>console.log('Received response from exam server.');</script>");

    echo "status" . $httpCode;
    // Check if the response is valid
    if ($httpCode === 401 || $httpCode === 422){ //check why exam server return 422
        return local_aiquestions_get_questions($data,$tries-1);
    }
    elseif ($httpCode !== 200 || $response === null) {
        echo "<script>console.log('Invalid response received from exam server: $response');</script>";
        throw new Exception("Invalid response received from exam server");
    }

    $questions = new stdClass(); // The questions object.
    print_r($response);
    if (isset($response->gift)) { // TODO: return from exam server 
        $questions->text = $response->gift;
    } else {
        $questions = $response;
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

    // currently fill in the blank is multichoice instead gapselect
    $qtypeMap = array("multiple_choice"=>"multichoice","fill_in_the_blank"=>"multichoice","open_questions"=>"essay");

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

    echo "question number from exam- ". count($questions)."num of question". $numofquestions;
    

    // if (count($questions) != $numofquestions) {
    //     return false;
    // }
    $createdquestions = []; // Array of objects of created questions.
    foreach ($questions as $question) {
        $questionSections = explode("\n", $question);
        $qtype = $qtypeMap[trim(str_replace("//","",$questionSections[0]))];
        if (empty($qtype)) {
            echo "fail finding qtype\n";
            return false;
        }
    
        echo "qtype".$qtype."\n\n";
            
        $questiontext = trim(preg_replace('/^.*::/', '', $questionSections[1]));

        print_r($USER);
        
        $q = $qformat->readquestion($questionSections);    
        // Check if question is valid.
        if (!$q) {
            echo "in question read fail";
            return false;
        }
        $q->category = $category->id;
        $q->createdby = $userid;
        $q->modifiedby = $userid;
        $q->timecreated = time();
        $q->timemodified = time();
        $q->qtype = $qtype;
        $q->questiontext = ['text' => "<p>" . $questiontext . "</p>"];
        $q->questiontextformat = 1;

        if ($qtype == "e") {
            $q->questiontext = ['text' => "<p>" . $questionSections[2] . "</p>"];
            // $q->correctanswer = $questionSections[3];
            if (preg_match('/{=([^~]+)~([^}]*)}/', $questionSections[2], $matches)) {
                $q->questiontext = ['text' => "<p>" . str_replace($matches[0], '[[2]]', $questionSections[2]) . "</p>"];
                // $options = explode('~', trim($matches[2]));
                // $i = 1;
                // foreach ($options as $key=>$option){
                //     $q->choices[] = array(
                //         'answer' => array('[', $option, $i, ']'),
                //         'choicegroup' => array('[', 'group', 0, ']'),
                //     );
                //     $i++;
                // }
                // $q->choices[] = array(
                //     'answer' => array('[', $matches[1], 4, ']'),
                //     'choicegroup' => array('[', 'group', 0, ']'),
                // );

                
                // Create choice for the correct answer
                $correctAnswer = trim($matches[1]);
                $choice = new qtype_gapselect_choice();
                $choice->id = 1; // Example ID, this should be unique
                $choice->text = $correctAnswer;
                $choice->fraction = 1.0;
                $q->choices[] = $choice;

                // Create choices for incorrect answers
                $incorrectAnswers = explode('~', trim($matches[2]));
                foreach ($incorrectAnswers as $answer) {
                    $choice = new qtype_gapselect_choice();
                    $choice->id = count($question->choices) + 1;
                    $choice->text = trim($answer);
                    $choice->fraction = 0.0;
                    $q->choices[] = $choice;

                echo "\n\nthis is quesion:\n\n";
                print_r(explode('~', trim($matches[2])));
                print_r($q);
                echo "\n\n";

            }
        }
    }

        echo "Question Title: " . $q->name . "\n";
        echo "Question Type: " . $q->qtype . "\n";
        
        // Set default values for essay question type fields
        if ($qtype == 'essay') {
            $q->responseformat = 'editor';
            $q->responserequired = 1;
            $q->responsefieldlines = 15;
            $q->minwordlimit = 0;
            $q->maxwordlimit = 1000;
            $q->attachments = 0;
            $q->attachmentsrequired = 0;
            $q->filetypeslist = '';
            $q->maxbytes = 10000;
            $q->graderinfo = array('text' => '', 'format' => FORMAT_HTML);
            $q->responsetemplate = array('text' => '', 'format' => FORMAT_HTML);
        } 


        $created = question_bank::get_qtype($qtype)->save_question($q, $q);
        echo "created obj\n";
        $createdquestions[] = $created;
    }
    echo "done create\n";
    print_r($createdquestions);
    if (count($createdquestions) > 0) {
        echo "success\n";
        return $createdquestions;
    } else {
        echo "fail to create questions\n";
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

use Smalot\PdfParser\Parser;
use PhpOffice\PhpPresentation\IOFactory;

function extract_text_from_pdf($filepath) {
    try {
        $parser = new Parser();
        $pdf = $parser->parseFile($filepath);
        $text = $pdf->getText();
        $text = str_replace("\n", " ", $text);
        
        $numPages = count($pdf->getPages());
        error_log("PDF file processed with $numPages pages. Text length: " . strlen($text));

        return $text;

    } catch (Exception $e) {
        error_log("Error processing PDF file: " . $e->getMessage());
        return null;
    }
}

function extract_text_from_pptx($filepath) {
    try {
        $pptReader = IOFactory::createReader('PowerPoint2007');
        $presentation = $pptReader->load($filepath);

        $textContent = '';
        $numSlides = 0;

        foreach ($presentation->getAllSlides() as $slide) {
            $numSlides++;
            foreach ($slide->getShapeCollection() as $shape) {
                if ($shape instanceof \PhpOffice\PhpPresentation\Shape\RichText) {
                    foreach ($shape->getParagraphs() as $paragraph) {
                        foreach ($paragraph->getLines() as $line) {
                            $textContent .= $line->getText() . ' ';
                        }
                    }
                }
            }
        }

        $textContent = str_replace("\n", " ", $textContent);
        error_log("PPTX file processed with $numSlides slides. Text length: " . strlen($textContent));

        return $textContent;

    } catch (Exception $e) {
        error_log("Error processing PPTX file: " . $e->getMessage());
        return null;
    }
}
?>
