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

        //echo "<pre>";
        //print_r($data);
        //echo "</pre>";

        //return null;
        return $data;
    }

    public function mime_type() {
        return 'application/xml';
    }

    protected function text_field($text) {
        return array(
            'text' => htmlspecialchars(trim($text), ENT_NOQUOTES),
            'format' => FORMAT_HTML,
            'files' => array(),
        );
    }

    public function readquestion($lines) {
        //this is no longer needed but might still be called by default.php
        return;
    }

    /**
     * Read STACK questions from a string and process it to a list of question arrays
     * @param string $xmlstr STACK questions as an XML string
     * @return array containing question arrays
     */
    protected function questionstoformfrom($xmlstr) {

        // Slight hack, since SimpleXMLElement does not like these names....
        $xmlstr = str_replace('<dc:', '<dc', $xmlstr);
        $xmlstr = str_replace('</dc:', '</dc', $xmlstr);
        
        $root = new SimpleXMLElement($xmlstr);
        $result = array();

        if ($root->getName() == 'assessmentItem') {
            $result[] = $this->questiontoformfrom($root);
        } else if ($root->getName() == 'mathQuiz') {
            foreach ($root->assessmentItem as $assessmentitem) {
                $result[] = $this->questiontoformfrom($assessmentitem);
            }
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

        $question = new stdClass();
        $question->qtype                 = 'stack';
        $question->name                  = (string) $assessmentitem->MetaData->dctitle->selection;

        $question->variantsselectionseed = '';
        $question->defaultmark           = 1;
        $question->length                = 1;
        //$question->penalty               = 0;

        // Note, new tags for [[input:ans1]] and validation are converted below, after inputs
        $question->questiontext          = (string) $assessmentitem->questionCasValues->questionStem->castext;
        $question->questiontextformat    = FORMAT_HTML;
        // Always blank on import - we assume PRT feedback is embedded in the question.
        $question->specificfeedback      = array('text' => '', 'format' => FORMAT_HTML, 'files' => array());
        $question->generalfeedback       = (string) $assessmentitem->questionCasValues->workedSolution->castext;
        $question->generalfeedbackformat = FORMAT_HTML;

        $question->questionvariables     = $this->convert_keyvals((string) $assessmentitem->questionCasValues->questionVariables->rawKeyVals);
        $newnote = (string) $assessmentitem->questionCasValues->questionNote->castext;
        if (strlen($newnote)>255) {
            $question->questiontext .= "Question note too long on import:\n\n".$newnote;
            $question->questionnote  = "ERROR on import: question note too long.  See question text.";
        } else {
            $question->questionnote = $newnote;
        }

        /*********************************************************************/
        // Question level options
        $itemoptions = array();
        foreach ($assessmentitem->ItemOptions->stackoption as $stackoptionXML) {
            $name = (string) $stackoptionXML->name;
            $value = $this->convert_bools((string) $stackoptionXML->selected);
            $itemoptions[$name] = $value;
        }
        // Not all the STACK 2 options are used.  Some are thrown away.
        $question->questionsimplify      = $itemoptions['Simplify'];
        // Bug in STACK 2 exporter: Penalty is not written....
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
        $question->prtcorrect            = array('text' => $itemoptions['FeedbackGenericCorrect'], 'format' => FORMAT_HTML, 'files' => array());;
        $question->prtpartiallycorrect   = array('text' => $itemoptions['FeedbackGenericPCorrect'], 'format' => FORMAT_HTML, 'files' => array());;
        $question->prtincorrect          = array('text' => $itemoptions['FeedbackGenericIncorrect'], 'format' => FORMAT_HTML, 'files' => array());;

        /*********************************************************************/
        // Input elements
        $inputtypemapping = array(
                    'Algebraic Input'  => 'algebraic',
                    'True/False'       => 'boolean',
                    'Textarea'         => 'textarea',
                    'Single Character' => 'singlechar',
                    'Matrix'           => 'matrix',
                    //'List'             => '?',
                    //'DropDownList'     => '?',
                    //'Slider'           => '?',
                    //'Dragmath'         => '?',
        );

        $questionparts = array();
        foreach ($assessmentitem->questionparts->questionpart as $questionpartXML) {
            $questionpart = array();

            $inputtype = (string) $questionpartXML->inputType->selection;
            if (array_key_exists($inputtype, $inputtypemapping)) {
                $questionpart['type'] = $inputtypemapping[$inputtype];
            } else {
                throw new Exception('STACK 2 importer tried to set an input type named '.$inputtype.' for input '.$questionpart['studentanskey'].'.  This has not yet been implemented in STACK 3.');
            }

            $inputoptions = array();
            foreach ($questionpartXML->stackoption as $stackoptionXML) {
                $name = (string) $stackoptionXML->name;
                $value = $this->convert_bools((string) $stackoptionXML->selected);
                $inputoptions[$name] = $value;
            }

            $questionpart['modelans']               = (string) $questionpartXML->teachersAns->casString;
            if (strlen($questionpart['modelans'])>255) {
                $question->questionvariables .= "\n/*Automatically added by the importer*/";
                $question->questionvariables .= "\nlonganswer".$name.':'.$questionpart['modelans']."\n";
                $questionpart['modelans'] = 'longanswer'.$name;
            }
            $questionpart['boxsize']            = (string) $questionpartXML->boxsize;
            $questionpart['insertstars']        = $inputoptions['insertStars'];
            $questionpart['syntaxhint']         = (string) $questionpartXML->syntax;
            $questionpart['forbidwords']        = (string) $questionpartXML->forbiddenWords->Forbid;
            $questionpart['forbidfloat']        = $inputoptions['forbidFloats'];
            $questionpart['requirelowestterms'] = $inputoptions['lowestTerms'];
            $questionpart['checkanswertype']    = $inputoptions['sameType'];
            $questionpart['strictsyntax']       = $inputoptions['formalSyntax'];
            // STACK 2 exporter does not seem to export these correctly anyway!
            $questionpart['mustverify']         = 1;
            $questionpart['showvalidation']     = 1;

            $name = (string) $questionpartXML->name;
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
        $question->questiontext = $this->convert_questiontext($question->questiontext, $inputnames);

        /*********************************************************************/
        // Potential response trees
        $potentialresponsetrees = array();
        foreach ($assessmentitem->PotentialResponseTrees->PotentialResponseTree as $prtXML) {
            $name = (string) $prtXML->prtname;
            // STACK adds this for export purposes, because it can't cope with PRTs which are just a number.
            $name = str_replace('PotResTree_', '', $name);

            $prt = array();
            $prt['value'] = (int) $prtXML->questionValue;
            $prt['autosimplify'] = $this->convert_bools((string) $prtXML->autoSimplify);
            $prt['feedbackvariables'] = $this->convert_keyvals((string) $prtXML->feedbackVariables);
        
            $potentialresponses = array();
            $autonumber = 0;
            foreach ($prtXML->PotentialResponses->PR as $prxml) {

                $id = (string) $prxml['id'];

                $pr = array();
                $pr['answertest'] = (string) $prxml->answerTest;
                if ('Equal_Com_Ass' == trim($pr['answertest'])) {
                    $pr['answertest'] = 'EqualComAss';
                }
                $pr['tans'] = (string) $prxml->teachersAns;
                if (strlen($pr['tans'])>255) {
                    $prt['feedbackvariables']  .= "\n/*Automatically added by the importer*/";
                    $prt['feedbackvariables']  .= "\nlongexpr".$autonumber.':'.$pr['tans']."\n";
                    $autonumber +=1;
                    $pr['tans'] = 'longexpr'.$autonumber;
                }
                $pr['sans'] = (string) $prxml->studentAns;
                if (strlen($pr['sans'])>255) {
                    $prt['feedbackvariables']  .= "\n/*Automatically added by the importer*/";
                    $prt['feedbackvariables']  .= "\nlongexpr".$autonumber.':'.$pr['sans']."\n";
                    $autonumber +=1;
                    $pr['sans'] = 'longexpr'.$autonumber;
                }
                $pr['testoptions'] = (string) $prxml->testoptions;
                if (strlen($pr['testoptions'])>255) {
                    $prt['feedbackvariables']  .= "\n/*Automatically added by the importer*/";
                    $prt['feedbackvariables']  .= "\nlongexpr".$autonumber.':'.$pr['testoptions']."\n";
                    $autonumber +=1;
                    $pr['testoptions'] = 'longexpr'.$autonumber;
                }
                $pr['quiet'] = (string) $prxml->quietAnsTest;
                foreach (array('true', 'false') as $branchname) {
                    $branch = $this->getnextPR($branchname, $prxml);
                    foreach($branch as $key => $val) {
                        $pr[$branchname.$key] = $val;
                    }
                }
        
                $potentialresponses[$id] = $pr;
            }

            foreach($potentialresponses as $prname => $pr) {
                foreach($pr as $key => $val) {
                    $prt[$key][$prname] = $val;
                }
            }
            $potentialresponsetrees[$name] = $prt;
        }

        $prtnames = array();
        foreach($potentialresponsetrees as $name => $prt) {
            // STACK 3 (moodle forms?) can't cope with PRT names which are only a number.  So append "prt".
            $newname = $this->convert_prt_name($name);
            $prtnames[$name] = $newname;
            foreach($prt as $key=>$val) {
                $question->{$newname.$key} = $val;
            }
        }
        // Change the input tags for the new versions.
        // Single PRT questions are treated as a special case
        if (1==count($prtnames)) {
            foreach ($prtnames as $oldname => $newname) {
                $question->questiontext = str_replace('<PRTfeedback>'.$oldname.'</PRTfeedback>', '', $question->questiontext);
                $question->specificfeedback = array('text' => "<p>[[feedback:$newname]]</p>", 'format' => FORMAT_HTML, 'files' => array());
            }
        } else {
            foreach ($prtnames as $oldname => $newname) {
                $question->questiontext = str_replace('<PRTfeedback>'.$oldname.'</PRTfeedback>', "[[feedback:$newname]]", $question->questiontext);
            }
        }

        /*********************************************************************/
        // Question tests
        $itemtests = array();
        if ($assessmentitem->ItemTests) {
            $question->testcases[0] = null;
            foreach ($assessmentitem->ItemTests->test as $testxml) {
                $inputs = array();
                $prts   = array();
                foreach($testxml->children() as $col) {
                    $key = (string) $col->key;
                    $val = (string) $col->value;
                    if ('IE_' == substr($key, 0, 3)) {
                        $inputs[substr($key, 3)] = $val;
                    }
                    if ('PRT_' == substr($key, 0, 4)) {
                        // Knock off PR_PotResTree_
                        if ('NONE' == $val) {
                            $val = 'NULL';
                        }
                        $prts[$this->convert_prt_name(substr($key, 15))] = $val;
                    }
                }
                $qtest = new stack_question_test($inputs);
                foreach ($prts as $key => $val) {
                    $qtest->add_expected_result($key, new stack_potentialresponse_tree_state(
                                                            '', array(), array($val), true, 0, 0.1));
                }
                $question->testcases[] = $qtest;
            }
            unset ($question->testcases[0]);
        }
/*
        echo "<pre>";
        print_r($question);
        echo "</pre>";
*/
        return $question;
    }

    /**
     * Process the raw keyvals fields, i.e. question and answer variables
     * to convert them into the new format.
     * @param  string incoming raw keyvals.
     * @return string converted raw keyvals.
     */
    public function convert_keyvals($strin) {

        $str = str_replace(';', "\n", $strin);
        $kv_array = explode("\n", $str);

        $strout = '';
        foreach ($kv_array as $kvs) {
            $kvs = trim($kvs);
            if ('' != $kvs) {
                // Split over the first occurrence of the equals sign, turning this into normal Maxima assignment.
                $i = strpos($kvs, '=');
                if (false === $i) {
                    $val = $kvs;
                } else {
                    // Need to check we don't have a function definition...
                    if (':'===substr($kvs, $i-1, 1)) {
                        $val = $kvs;
                    } else {
                        $val = trim(trim(substr($kvs, 0, $i)).':'.trim(substr($kvs, $i+1)));
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
        return $questiontext;
    }

    /**
    * Names for PRTs cannot now be just numbers,
    * @param  string incoming name
    * @return string converted name.
    */
    public function convert_prt_name($name) {
            if (is_numeric($name)) {
                $name = 'prt'.$name;
            }
        return $name;
    }
}
