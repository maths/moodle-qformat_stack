<?php
// This file is part of Stack - http://stack.bham.ac.uk/
//
// Stack is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Stack is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Stack.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file defines the main qformat_stack plugin.
 *
 * @package   qformat_stack
 * @copyright 2012 Matti Pauna
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/stack/questiontype.php');

/**
 * This question importer class will import the Stack 2.0 XML format.
 *
 * This makes it possible to import existing stack questions into Moodle.
 *
 * @copyright 2012 Matti Pauna
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qformat_stack extends qformat_default {

    public function provide_import() {
        return true;
    }

    public function provide_export() {
        return false;
    }

    public function readquestions($lines) {
        $data = $this->questionstoformfrom(implode($lines));
        return $data;
    }

    public function mime_type() {
        return 'application/xml';
    }

    protected function text_field($text) {
        return array(
            'text'   => htmlspecialchars(trim($text), ENT_NOQUOTES),
            'format' => FORMAT_HTML,
            'files'  => array(),
        );
    }

    public function readquestion($lines) {
        // This is no longer needed but might still be called by default.php.
        return;
    }

    /**
     * Read STACK questions from a string and process it to a list of question arrays
     * @param string $xmlstr STACK questions as an XML string
     * @return array containing question arrays
     */
    protected function questionstoformfrom($xmlstr) {

        // Slight hack, since SimpleXMLElement does not like these names...
        $xmlstr = str_replace('<dc:', '<dc', $xmlstr);
        $xmlstr = str_replace('</dc:', '</dc', $xmlstr);

        $root = new SimpleXMLElement($xmlstr);
        $result = array();
        $errors = array();

        if ($root->getName() == 'assessmentItem') {
            list($question, $err) = $this->questiontoformfrom($root);
            $result[] = $question;
            $errors = array_merge($errors, $err);
        } else if ($root->getName() == 'mathQuiz') {
            foreach ($root->assessmentItem as $assessmentitem) {
                list($question, $err) = $this->questiontoformfrom($assessmentitem);
                $result[] = $question;
                $errors = array_merge($errors, $err);
            }
        }

        if (!empty($errors)) {
            throw new stack_exception(implode('<br />', $errors));
        }
        return $result;
    }

    /**
     * Utility function for processing next potential response tree elements
     * @param string $tf which node, true or false
     * @param SimpleXMLElement $prxml potential response element
     * @return subelements as an array
     */
    protected function getnextpr($tf, $prxml) {
        $result = array();

        $node = $prxml->$tf;

        $result['scoremode'] = (string) $node->rawModMark;
        if ('+AT' == $result['scoremode'] || '=AT' == $result['scoremode']) {
            $result['scoremode'] = '=';
        }
        $result['score'] = (string) $node->rawMark;
        $result['penalty'] = (string) $node->penalty;
        $result['feedback'] = array('text' => (string) $node->feedback, 'format' => FORMAT_HTML, 'files' => array());
        $result['answernote'] = (string) $node->ansnote;
        $result['nextnode'] = (string) $node->nextPR;

        return $result;
    }

    /**
     * Process a single question into an array
     * @param SimpleXMLElement $assessmentitem
     * @return the question as an array
     */
    protected function questiontoformfrom($assessmentitem) {

        // Set this to false to enable a range of conversions.
        $strictimport = true;

        $errors = array();
        $question = new stdClass();
        $question->qtype                 = 'stack';
        $question->name                  = (string) $assessmentitem->MetaData->dctitle->selection;

        $question->variantsselectionseed = '';
        $question->defaultmark           = 1;
        $question->length                = 1;

        // Note, new tags for [[input:ans1]] and validation are converted below, after inputs.
        $question->questiontext          = (string) $assessmentitem->questionCasValues->questionStem->castext;
        $question->questiontextformat    = FORMAT_HTML;
        // Always blank on import - we assume PRT feedback is embedded in the question.
        $question->specificfeedback      = array('text' => '', 'format' => FORMAT_HTML, 'files' => array());
        $question->generalfeedback       = (string) $assessmentitem->questionCasValues->workedSolution->castext;
        $question->generalfeedbackformat = FORMAT_HTML;

        $question->questionvariables     = $this->convert_keyvals((string)
                                                $assessmentitem->questionCasValues->questionVariables->rawKeyVals);
        $newnote = (string) $assessmentitem->questionCasValues->questionNote->castext;
        if (strlen($newnote) > 255) {
            $question->questiontext .= "Question note too long on import:\n\n".$newnote;
            $question->questionnote  = "ERROR on import: question note too long.  See question text.";
        } else {
            $question->questionnote = $newnote;
        }

        // Question level options.
        $itemoptions = array();
        foreach ($assessmentitem->ItemOptions->stackoption as $stackoptionxml) {
            $name = (string) $stackoptionxml->name;
            $value = $this->convert_bools((string) $stackoptionxml->selected);
            $itemoptions[$name] = $value;
        }
        // Not all the STACK 2 options are used.  Some are thrown away.
        $question->questionsimplify      = $itemoptions['Simplify'];
        // Bug in STACK 2 exporter: Penalty is not written...
        if (array_key_exists('Penalty', $itemoptions)) {
            $question->penalty           = $itemoptions['Penalty'];
        } else {
            $question->penalty           = 0.1;
        }

        $question->assumepositive        = $itemoptions['AssumePos'];
        if ('(none)' == $itemoptions['MultiplicationSign']) {
            $itemoptions['MultiplicationSign'] = 'none';
        }
        $question->multiplicationsign    = $itemoptions['MultiplicationSign'];
        $question->sqrtsign              = $itemoptions['SqrtSign'];
        $question->complexno             = $itemoptions['ComplexNo'];
        $question->inversetrig           = 'cos-1';
        $question->matrixparens          = '[';
        $question->prtcorrect            = array('text' => $itemoptions['FeedbackGenericCorrect'],
                                                                'format' => FORMAT_HTML, 'files' => array());
        $question->prtpartiallycorrect   = array('text' => $itemoptions['FeedbackGenericPCorrect'],
                                                                'format' => FORMAT_HTML, 'files' => array());;
        $question->prtincorrect          = array('text' => $itemoptions['FeedbackGenericIncorrect'],
                                                                'format' => FORMAT_HTML, 'files' => array());;

        // Input elements.
        $inputtypemapping = array(
                    'Algebraic Input'  => 'algebraic',
                    'True/False'       => 'boolean',
                    'Textarea'         => 'textarea',
                    'Single Character' => 'singlechar',
                    'Matrix'           => 'matrix',
                    // TODO 'List'             => '?',
                    // TODO 'DropDownList'     => '?',
                    // TODO 'Slider'           => '?',
                    // TODO 'Dragmath'         => '?',
                    // Add these back once these input elements exist.
        );

        $questionparts = array();
        foreach ($assessmentitem->questionparts->questionpart as $questionpartxml) {
            $questionpart = array();

            $inputtype = (string) $questionpartxml->inputType->selection;
            if (array_key_exists($inputtype, $inputtypemapping)) {
                $questionpart['type'] = $inputtypemapping[$inputtype];
            } else {
                if ($strictimport) {
                    $errors[] = 'Tried to set an input type named ' .
                            $inputtype.' for input ' . (string) $questionpartxml->name .
                            ' in question '.$question->name.'.  This has not yet been implemented in STACK 3.';
                } else {
                    $questionpart['type'] = 'algebraic';
                }
            }

            $inputoptions = array();
            foreach ($questionpartxml->stackoption as $stackoptionxml) {
                $name = (string) $stackoptionxml->name;
                $value = $this->convert_bools((string) $stackoptionxml->selected);
                $inputoptions[$name] = $value;
            }

            $questionpart['modelans']               = (string) $questionpartxml->teachersAns->casString;
            if (strlen($questionpart['modelans']) > 255) {
                $question->questionvariables .= "\n/*Automatically added by the importer*/";
                $question->questionvariables .= "\nlonganswer".$name.':'.$questionpart['modelans']."\n";
                $questionpart['modelans'] = 'longanswer'.$name;
            }
            $questionpart['boxsize']            = (string) $questionpartxml->boxsize;
            $questionpart['insertstars']        = $inputoptions['insertStars'];
            $questionpart['syntaxhint']         = (string) $questionpartxml->syntax;
            $questionpart['forbidwords']        = (string) $questionpartxml->forbiddenWords->Forbid;
            $questionpart['allowwords']         = '';
            $questionpart['forbidfloat']        = $inputoptions['forbidFloats'];
            // Export error: STACK 2 exporter does not seem to export these correctly.
            if (array_key_exists('lowestTerms', $inputoptions)) {
                $questionpart['requirelowestterms'] = (bool) $inputoptions['lowestTerms'];
            } else {
                $questionpart['requirelowestterms'] = true;
            }
            $questionpart['checkanswertype']    = $inputoptions['sameType'];
            $questionpart['strictsyntax']       = $inputoptions['formalSyntax'];
            // STACK 2 exporter does not seem to export these correctly anyway!
            $questionpart['mustverify']         = 1;
            $questionpart['showvalidation']     = 1;
            $questionpart['options']            = '';

            $name = (string) $questionpartxml->name;
            $questionparts[$name] = $questionpart;
        }
        $inputnames = array();
        foreach ($questionparts as $anskey => $questionpart) {
            $inputnames[] = $anskey;
            foreach ($questionpart as $key => $val) {
                $questionkey = $anskey.$key;
                $question->$questionkey = $val;
            }
        }

        // Change the input tags for the new versions.
        $question->questiontext                = $this->convert_questiontext($question->questiontext, $inputnames);
        $question->specificfeedback['text']    = $this->fix_maths_delimiters($question->specificfeedback['text']);
        $question->generalfeedback             = $this->fix_maths_delimiters($question->generalfeedback);
        $question->prtcorrect['text']          = $this->fix_maths_delimiters($question->prtcorrect['text']);
        $question->prtpartiallycorrect['text'] = $this->fix_maths_delimiters($question->prtpartiallycorrect['text']);
        $question->prtincorrect['text']        = $this->fix_maths_delimiters($question->prtincorrect['text']);

        // Potential response trees.
        $potentialresponsetrees = array();
        foreach ($assessmentitem->PotentialResponseTrees->PotentialResponseTree as $prtxml) {
            $name = (string) $prtxml->prtname;
            // STACK adds this for export purposes, because it can't cope with PRTs which are just a number.
            $name = str_replace('PotResTree_', '', $name);

            if (strlen($name) > 32) {
                if ($strictimport) {
                    $errors[] = 'The PRT name "' . $name . '" exceeds 32 characters and is too long.';
                }
                $name = substr($name, 0, 31);
            }

            $prt = array();
            $prt['value'] = (int) $prtxml->questionValue;
            $prt['autosimplify'] = $this->convert_bools((string) $prtxml->autoSimplify);
            $prt['feedbackvariables'] = $this->convert_keyvals((string) $prtxml->feedbackVariables);

            $potentialresponses = array();
            $autonumber = 0;
            foreach ($prtxml->PotentialResponses->PR as $prxml) {

                $id = (string) $prxml['id'];

                $pr = array();
                $pr['answertest'] = (string) $prxml->answerTest;
                if ('Equal_Com_Ass' == trim($pr['answertest'])) {
                    $pr['answertest'] = 'EqualComAss';
                }
                $pr['tans'] = (string) $prxml->teachersAns;
                if (strlen($pr['tans']) > 255) {
                    $prt['feedbackvariables']  .= "\n/*Automatically added by the importer*/";
                    $prt['feedbackvariables']  .= "\nlongexpr".$autonumber.':'.$pr['tans']."\n";
                    $autonumber += 1;
                    $pr['tans'] = 'longexpr'.$autonumber;
                }
                $pr['sans'] = (string) $prxml->studentAns;
                if (strlen($pr['sans']) > 255) {
                    $prt['feedbackvariables']  .= "\n/*Automatically added by the importer*/";
                    $prt['feedbackvariables']  .= "\nlongexpr".$autonumber.':'.$pr['sans']."\n";
                    $autonumber += 1;
                    $pr['sans'] = 'longexpr'.$autonumber;
                }
                $pr['testoptions'] = (string) $prxml->testoptions;
                if (strlen($pr['testoptions']) > 255) {
                    $prt['feedbackvariables']  .= "\n/*Automatically added by the importer*/";
                    $prt['feedbackvariables']  .= "\nlongexpr".$autonumber.':'.$pr['testoptions']."\n";
                    $autonumber += 1;
                    $pr['testoptions'] = 'longexpr'.$autonumber;
                }
                $pr['quiet'] = (string) $prxml->quietAnsTest;
                foreach (array('true', 'false') as $branchname) {
                    $branch = $this->getnextPR($branchname, $prxml);
                    foreach ($branch as $key => $val) {
                        if ('answernote' == $key and '' == trim($val)) {
                            $ids = (string) ($id + 1);
                            $val = $name.'-'.$ids.'-';
                            if ('true' == $branchname) {
                                $val .= 'T';
                            } else {
                                $val .= 'F';
                            }
                        }
                        $pr[$branchname . $key] = $val;
                    }
                }

                $potentialresponses[$id] = $pr;
            }

            $numericalfields = array('truescore', 'falsescore', 'truepenalty', 'falsepenalty');
            foreach ($potentialresponses as $prname => $pr) {
                foreach ($pr as $key => $val) {
                    $prt[$key][$prname] = $val;
                    if (in_array($key, $numericalfields) and '' != $val) {
                        // Tidy up numerical values.
                        $val = trim($val);
                        if (substr($val, 0, 1) == '.') {
                            $val = '0'.$val;
                        }
                        if (!($this->convert_floats($val) === (string) $val)) {
                            if ($strictimport) {
                                $errors[] = 'Tried to set a numerical field "'.$key.'" in potential response tree "' . $prname .
                                '" in question "'.$question->name.'". with the illegal value "'.$val.'". This must be a float. ';
                            } else {
                                $prt[$key][$prname] = $this->convert_floats($val);
                            }
                        }
                    }
                }
            }
            $potentialresponsetrees[$name] = $prt;
        }

        $prtnames = array();
        foreach ($potentialresponsetrees as $name => $prt) {
            // STACK 3 (moodle forms?) can't cope with PRT names which are only a number.  So prepend "prt".
            $newname = $this->convert_prt_name($name);
            $prtnames[$name] = $newname;
            foreach ($prt as $key => $val) {
                $question->{$newname . $key} = $val;
            }
        }
        // Change the input tags for the new versions.
        // Single PRT questions are treated as a special case.
        if (1 == count($prtnames)) {
            foreach ($prtnames as $oldname => $newname) {
                $question->questiontext = str_replace('<PRTfeedback>'.$oldname.'</PRTfeedback>', '',
                                                        $question->questiontext);
                $question->specificfeedback = array('text' => "<p>[[feedback:$newname]]</p>",
                                                        'format' => FORMAT_HTML, 'files' => array());
            }
        } else {
            foreach ($prtnames as $oldname => $newname) {
                $question->questiontext = str_replace('<PRTfeedback>'.$oldname.'</PRTfeedback>', "[[feedback:$newname]]",
                                                        $question->questiontext);
            }
        }

        // Question tests.
        $itemtests = array();
        if ($assessmentitem->ItemTests) {
            $question->testcases[0] = null;
            foreach ($assessmentitem->ItemTests->test as $testxml) {
                $inputs = array();
                $prts   = array();
                foreach ($testxml->children() as $col) {
                    $key = (string) $col->key;
                    $val = (string) $col->value;
                    if ('IE_' == substr($key, 0, 3)) {
                        $inputs[substr($key, 3)] = $val;
                    }
                    if ('PRT_' == substr($key, 0, 4)) {
                        // Knock off PR_PotResTree_.
                        if ('NONE' == $val) {
                            $val = 'NULL';
                        }
                        $key = $this->convert_prt_name(substr($key, 15));
                        if (strlen($key) > 32) {
                            $key = substr($key, 0, 31);
                        }
                        $prts[$key] = $val;
                    }
                }
                $qtest = new stack_question_test($inputs);
                foreach ($prts as $key => $val) {
                    $qtest->add_expected_result($key, new stack_potentialresponse_tree_state(
                                                            1, true, 0, 0.1, '', array($val)));
                }
                $question->testcases[] = $qtest;
            }
            unset ($question->testcases[0]);
        }

        return array($question, $errors);
    }

    /**
     * Process the raw keyvals fields, i.e. question and answer variables
     * to convert them into the new format.
     * @param  string incoming raw keyvals.
     * @return string converted raw keyvals.
     */
    public function convert_keyvals($strin) {

        $str = str_replace(';', "\n", $strin);
        $kvarray = explode("\n", $str);

        $strout = '';
        foreach ($kvarray as $kvs) {
            $kvs = trim($kvs);
            if ('' != $kvs) {
                // Split over the first occurrence of the equals sign, turning this into normal Maxima assignment.
                $i = strpos($kvs, '=');
                if (false === $i) {
                    $val = $kvs;
                } else {
                    // Need to check we don't have a function definition...
                    if (':' === substr($kvs, $i - 1, 1)) {
                        $val = $kvs;
                    } else {
                        $val = trim(trim(substr($kvs, 0, $i)) . ':' . trim(substr($kvs, $i + 1)));
                    }
                }

                $strout .= $val.";\n";
            }
        }

        $strout = trim($strout);
        return $strout;
    }

    /**
     * Convert Boolean values into 1 & 0.
     * @param  string string.
     * @return string converted boolean value.
     */
    public function convert_bools($strin) {
        $val = $strin;
        if ('true' == $strin) {
            $val = 1;
        }
        if ('false' == $strin) {
            $val = 0;
        }
        return $val;
    }

    /**
     * Replace STACK 2 tags in the question text with new ones.
     * @param  string incoming question text
     * @param  array input names
     * @return string converted raw keyvals.
     */
    public function convert_questiontext($questiontext, $inputnames) {
        foreach ($inputnames as $name) {
            $questiontext = str_replace('#'.$name.'#', "[[input:$name]]", $questiontext);
            $questiontext = str_replace('<IEfeedback>'.$name.'</IEfeedback>', "[[validation:$name]]", $questiontext);
        }

        $questiontext = stack_fact_sheets::convert_legacy_tags($questiontext);

        return $this->fix_maths_delimiters($questiontext);
    }

    public function fix_maths_delimiters($text) {
        return stack_maths::replace_dollars($text);
    }

    /**
     * Names for PRTs cannot now be just numbers,
     * @param  string incoming name
     * @return string converted name.
     */
    public function convert_prt_name($name) {
        if (is_numeric($name)) {
            $name = 'prt' . $name;
        }
        return $name;
    }

    /**
     * Make sure numerical values really are floats
     * @param  string incoming value
     * @return string converted float.
     */
    public function convert_floats($val) {
            return $val = (string) floatval($val);
    }
}
