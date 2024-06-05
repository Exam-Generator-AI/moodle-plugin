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
 * Story Form Class is defined here.
 *
 * @package     local_aiquestions
 * @category    admin
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/pdflib.php'); // For PDF handling
use PhpOffice\PhpPresentation\IOFactory;

/**
 * Form to get the story from the user.
 *
 * @package     local_aiquestions
 * @category    admin
 */
class local_aiquestions_story_form extends moodleform
{
    /**
     * Defines forms elements
     */
    public function definition()
    {
        global $courseid;
        $mform = $this->_form;

        // Add some CSS to improve the UI
        $mform->addElement('html', '
            <style>
                .mform .fitem .fitemtitle {
                    width: 20%;
                    font-weight: bold;
                }
                .mform .fitem .felement {
                    width: 75%;
                    padding: 8px;
                    border-radius: 5px;
                    border: 1px solid #ccc;
                    box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
                }
                .mform .dynamic-field-container {
                    margin-top: 10px;
                    margin-left: 20%;
                }
                .mform .fitem .felement select, 
                .mform .fitem .felement input[type="file"],
                .mform .fitem .felement textarea {
                    width: 100%;
                    padding: 8px;
                    border-radius: 5px;
                    border: 1px solid #ccc;
                    box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
                }
                .mform .fitem .felement input[type="file"] {
                    padding: 5px;
                }
                .mform .fitem .felement input[type="submit"] {
                    background-color: #0073e6;
                    color: #fff;
                    padding: 10px 20px;
                    border: none;
                    border-radius: 5px;
                    cursor: pointer;
                    font-size: 16px;
                }
                .mform .fitem .felement input[type="submit"]:hover {
                    background-color: #005bb5;
                }
                .mform .fitem .felement input[type="submit"]:active {
                    background-color: #003f7f;
                }
                .mform .fitem .felement input[type="submit"]:focus {
                    outline: none;
                }
            </style>
        ');

        // Question category.
        $contexts = [context_course::instance($courseid)];
        $mform->addElement(
            'questioncategory',
            'category',
            get_string('category', 'question'),
            ['contexts' => $contexts]
        );
        $mform->addHelpButton('category', 'category', 'local_aiquestions');

        // Number of open questions.
        $mform->addElement(
            'select',
            'numofopenquestions',
            'Number of open questions',
            range(0, 10)
        );
        $mform->setType('numofopenquestions', PARAM_INT);

        // Number of multiple choice questions.
        $mform->addElement(
            'select',
            'numofmultiplechoicequestions',
            'Number of multiple choice questions',
            range(0, 10)
        );
        $mform->setType('numofmultiplechoicequestions', PARAM_INT);

        // JavaScript for validation.
        $mform->addElement('html', '
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    var numofopenquestions = document.querySelector("select[name=\'numofopenquestions\']");
                    var numofmultiplechoicequestions = document.querySelector("select[name=\'numofmultiplechoicequestions\']");
                    var form = document.querySelector("form");

                    function validateTotalQuestions() {
                        var openQuestions = parseInt(numofopenquestions.value);
                        var multipleChoiceQuestions = parseInt(numofmultiplechoicequestions.value);
                        var totalQuestions = openQuestions + multipleChoiceQuestions;

                        if (totalQuestions > 10) {
                            if (this === numofopenquestions) {
                                numofopenquestions.value = 10 - multipleChoiceQuestions;
                            } else {
                                numofmultiplechoicequestions.value = 10 - openQuestions;
                            }
                            alert("The total number of open and multiple choice questions cannot exceed 10. Adjusted the number automatically.");
                        }
                    }

                    numofopenquestions.addEventListener("change", validateTotalQuestions);
                    numofmultiplechoicequestions.addEventListener("change", validateTotalQuestions);
                });
            </script>
        ');


        // Language.
        $languages = ['English' => "English", 'Hebrew' => "Hebrew", 'Hindi' => "Hindi", 'Spanish' => 'Spanish', 'German' => "German", 'French' => "French", 'Russian' => "Russian", 'Arabic' => "Arabic"];
        $mform->addElement(
            'select',
            'examLanguage',
            'Questions language',
            $languages
        );

        // Field (exam type).
        $fieldoptions = ["topic" => "topic", "Text" => "text", "Upload file" => "Upload file", "URL" => "url", "Math" => "math"];
        $mform->addElement(
            'select',
            'field',
            'Input field type',
            $fieldoptions
        );
        $mform->setDefault('field', 'topic'); // Set default value
        $mform->setType('field', PARAM_RAW);

        // Container for dynamically changing input field.
        $mform->addElement('html','<div class="dynamic-field-container"></div>');

    // Add a hidden input field to hold the extracted text content
    $mform->addElement('hidden', 'textinput');
    $mform->setType('textinput', PARAM_RAW);

    // Modify the JavaScript to handle file upload and extract text content
    $mform->addElement('html', '
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                var fieldSelect = document.querySelector("select[name=\'field\']");
                var fieldInputContainer = document.querySelector(".dynamic-field-container");
                var extractedTextField = document.querySelector("input[name=\'extractedtext\']");

                function updateFieldInput() {
                    var selectedValue = fieldSelect.value;
                    fieldInputContainer.innerHTML = "";

                    if (selectedValue === "Upload file") {
                        fieldInputContainer.innerHTML = "<input type=\'file\' name=\'uploadedfile\' style=\'width:100%;\' />";
                    } else {
                        fieldInputContainer.innerHTML = "<textarea name=\'textinput\' rows=\'4\' cols=\'50\' style=\'width:100%;\'></textarea>";
                    }
                }

                function handleFileUpload(event) {
                    var file = event.target.files[0];
                    var reader = new FileReader();
                    reader.onload = function(event) {
                        extractedTextField.value = event.target.result;
                    };
                    reader.readAsText(file);
                }

                fieldSelect.addEventListener("change", updateFieldInput);
                fieldInputContainer.addEventListener("change", handleFileUpload);
                updateFieldInput(); // Initial call to set the correct input
            });
        </script>
    ');

        // Add CSS to style the elements
        $mform->addElement('html', '
            <style>
                .mform .dynamic-field-container {
                    margin-top: 10px;
                    margin-left: 27%;
                    width: 75%; /* Set width to match the dropdown */
                }
            </style>
        ');

        // Question level.
        $mform->addElement(
            'select',
            'questionLevel',
            'Questions Level',
            ["Academic" => "Academic"]
        );

        // Exam focus.
        $mform->addElement(
            'textarea',
            'examFocus',
            'Questions focus',
            ['rows' => 6, 'cols' => 50]
        );
        $mform->setDefault('examFocus', ''); // Set default value
        $mform->setType('examFocus', PARAM_RAW);

        // Exam tags.
        $skills = ["Cognitive literacy" => "Cognitive literacy", "Mathematical literacy" => "Mathematical literacy", "Scientific literacy" => "Scientific literacy", "Critical Thinking" => "Critical Thinking"];
        $select = $mform->addElement(
            'select',
            'skills',
            'Skills',
            $skills,
            ['multiple' => true, 'size' => 3]
        );
        $select->setMultiple(true);
        
        // Courseid.
        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        // Buttons.
        $buttonarray = [];
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('generate', 'local_aiquestions'));
        $buttonarray[] = &$mform->createElement('cancel', 'cancel', get_string('backtocourse', 'local_aiquestions'));
        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);
    }

    /**
     * Handle file upload and extract text content
     *
     * @param array $data
     * @param array $files
     * @return string|null
     */
    public function handle_file_upload($data, $files)
    {
        if (isset($files['uploadedfile']) && $files['uploadedfile']['error'] === UPLOAD_ERR_OK) {
            $file = $files['uploadedfile'];
            $fileext = pathinfo($file['name'], PATHINFO_EXTENSION);

            if ($fileext === 'pdf') {
                return $this->read_pdf($file['tmp_name']);
            } elseif ($fileext === 'pptx') {
                return $this->read_pptx($file['tmp_name']);
            }
        }
        return null;
    }

    /**
     * Placeholder function to read PDF content
     *
     * @param string $filepath
     * @return string
     */
    private function read_pdf($filepath)
    {
        require_once($CFG->libdir . '/pdflib.php');
    
        $pdf = new \TCPDF();
        $text = '';
    
        $pageCount = $pdf->setSourceFile($filepath);
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $pageId = $pdf->importPage($pageNo);
            $pdf->useTemplate($pageId);
            $text .= $pdf->getPageContent($pageNo);
        }
    
        return $text;
    }

    /**
     * Function to read PPTX content using PhpPresentation
     *
     * @param string $filepath
     * @return string
     */
    private function read_pptx($filepath)
    {
        $pptReader = IOFactory::createReader('PowerPoint2007');
        $presentation = $pptReader->load($filepath);
        $text = '';

        foreach ($presentation->getAllSlides() as $slide) {
            foreach ($slide->getShapeCollection() as $shape) {
                if ($shape instanceof \PhpOffice\PhpPresentation\Shape\RichText) {
                    foreach ($shape->getParagraphs() as $paragraph) {
                        $text .= $paragraph->getText() . ' ';
                    }
                }
            }
        }
        return $text;
    }
    /**
     * Form validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files)
    {
        $errors = [];
        $totalquestions = $data['numofopenquestions'] + $data['numofmultiplechoicequestions'];
        if ($totalquestions > 10) {
            $errors['numofopenquestions'] = get_string('exceedstotalquestions', 'local_aiquestions');
            $errors['numofmultiplechoicequestions'] = get_string('exceedstotalquestions', 'local_aiquestions');
        }
        return $errors;
    }
    public function InputValidation($data, $files)
    {   
        $errors = parent::InputValidation($data, $files);

        // Check if the extracted text content is not null
        if (empty($data['textinput']) && empty($data['extractedtext'])) {
            $errors['textinput'] = get_string('missingtext', 'local_aiquestions');
        }

        return $errors;
    }
}