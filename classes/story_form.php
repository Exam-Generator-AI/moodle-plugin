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
use Smalot\PdfParser\Parser;
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

        // Include necessary scripts and styles for the form.
        $mform->addElement('html', '
            <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.11.338/pdf.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.7.1/jszip.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/pptxgenjs/3.6.1/pptxgen.min.js"></script>
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
        $mform->setDefault('numofmultiplechoicequestions', 3); // Set default value

        // JavaScript for validation and auto-adjustment.
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
                        } else if (totalQuestions == 0) {
                            if (this === numofopenquestions) {
                                numofopenquestions.value = 1;
                            } else {
                                numofmultiplechoicequestions.value = 1;
                            }
                            alert("The total number of open and multiple choice questions cannot be 0. Adjusted the number automatically.");
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
        $mform->addElement('html', '<div class="dynamic-field-container"></div>');

        // Add a hidden input field to hold the extracted text content
        $mform->addElement('hidden', 'textinput');
        $mform->setType('textinput', PARAM_RAW);

        // JavaScript to handle file upload and extract text content
        $mform->addElement('html', '
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    var fieldSelect = document.querySelector("select[name=\'field\']");
                    var fieldInputContainer = document.querySelector(".dynamic-field-container");
                    var textAreaField = document.createElement("textarea");
                    textAreaField.name = "textinput";
                    textAreaField.id = "textinput";
                    textAreaField.rows = 4;
                    textAreaField.cols = 50;
                    textAreaField.style.width = "100%";
                    
                    // Create a hidden form to handle the file upload
                    var uploadForm = document.createElement("form");
                    uploadForm.method = "post";
                    uploadForm.enctype = "multipart/form-data";
                    uploadForm.style.display = "none";
                    document.body.appendChild(uploadForm);

                    function updateFieldInput() {
                        var selectedValue = fieldSelect.value;
                        fieldInputContainer.innerHTML = "";

                        if (selectedValue === "Upload file") {
                            var fileInput = document.createElement("input");
                            fileInput.type = "file";
                            fileInput.name = "uploadedfile";
                            fileInput.style.width = "100%";
                            fileInput.addEventListener("change", handleFileUpload);
                            fieldInputContainer.appendChild(fileInput);

                            // Append file input to hidden form
                            uploadForm.appendChild(fileInput);
                        } else {
                            fieldInputContainer.appendChild(textAreaField);
                        }
                    }

                    async function handleFileUpload(event) {
                        var file = event.target.files[0];

                        if (!file) return;

                        var reader = new FileReader();

                        reader.onload = function(e) {
                            var fileContent = e.target.result;

                            // Process the file content based on the file type
                            var fileType = file.type;

                            if (fileType === "application/pdf") {
                                extractTextFromPDF(fileContent);
                            } else if (fileType.includes("powerpoint")) {
                                extractTextFromPPT(fileContent);
                            } else if (fileType.includes("text")) {
                                textAreaField.value = fileContent;
                            } else {
                                alert("Unsupported file type");
                            }
                        };

                        if (file.type.includes("pdf")) {
                            reader.readAsArrayBuffer(file);
                        } else {
                            reader.readAsText(file);
                        }
                    }

                    function extractTextFromPDF(pdfContent) {
                        pdfjsLib.getDocument({data: pdfContent}).promise.then(function(pdf) {
                            var totalText = "";
                            var loadPagePromises = [];

                            for (var i = 1; i <= pdf.numPages; i++) {
                                loadPagePromises.push(
                                    pdf.getPage(i).then(function(page) {
                                        return page.getTextContent().then(function(textContent) {
                                            var pageText = textContent.items.map(function(item) {
                                                return item.str;
                                            }).join(" ");
                                            totalText += pageText + "\n";
                                        });
                                    })
                                );
                            }

                            Promise.all(loadPagePromises).then(function() {
                                textAreaField.value = totalText;
                            });
                        });
                    }

                    function extractTextFromPPT(pptContent) {
                        var zip = new JSZip();
                        zip.loadAsync(pptContent).then(function(zip) {
                            var totalText = "";
                            var loadSlidePromises = [];

                            zip.folder("ppt/slides").forEach(function(relativePath, file) {
                                loadSlidePromises.push(
                                    file.async("text").then(function(textContent) {
                                        totalText += textContent + "\n";
                                    })
                                );
                            });

                            Promise.all(loadSlidePromises).then(function() {
                                textAreaField.value = totalText;
                            });
                        });
                    }

                    updateFieldInput();
                    fieldSelect.addEventListener("change", updateFieldInput);
                });
            </script>
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

        // Buttons to submit or cancel the form.
        $this->add_action_buttons(true, 'Generate Questions');
    }

    /**
     * Custom validation should be added here
     * @param array $data, array $files
     * @return array
     */
    public function validation($data, $files)
    {
        return array();
    }
}
