<?php
/**
 * ***** BEGIN LICENSE BLOCK *****
 * This file is part of Quiz.
 * Copyright (c) 2007 Louis-Rémi BABE. All rights reserved.
 *
 * Quiz is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * Quiz is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Quiz; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * ***** END LICENSE BLOCK *****
 *
 * Quiz is a quiz tool for mediawiki.
 * 
 * To activate this extension :
 * * Create a new directory named quiz into the directory "extensions" of mediawiki.
 * * Place this file and the files Quiz.i18n.php and quiz.js there.
 * * Add this line at the end of your LocalSettings.php file :
 * require_once 'extensions/quiz/Quiz.php';
 * 
 * @version 0.8.1
 * @link http://www.mediawiki.org/wiki/Extension:Quiz
 * @author BABE Louis-Remi <lrbabe@gmail.com>
 */
 
/**
 * Extension's parameters.
 */
$wgExtensionCredits['parserhook'][] = array(
    'name'=>'Quiz',
    'version'=>'0.8.1',
    'author'=>'lrbabe',
    'url'=>'http://www.mediawiki.org/wiki/Extension:Quiz',
    'description' => 'Quiz tool for MediaWiki'
);
    
/**
 * Add this extension to the mediawiki's extensions list.
 */
$wgExtensionFunctions[] = "wfQuizExtension";

$wgHooks['ParserClearState'][] = 'Quiz::resetQuizID';
$wgHooks['LoadAllMessages'][] = 'Quiz::loadMessages';


/**
 * Register the extension with the WikiText parser.
 * The tag used is <quiz> * 
 */
function wfQuizExtension() {
    global $wgParser;    
    $wgParser->setHook("quiz", "renderQuiz");
}

/**
 * Call the quiz parser on an input text.
 * 
 * @param  $input				Text between <quiz> and </quiz> tags, in quiz syntax.
 * @param  $argv				An array containing any arguments passed to the extension
 * @param  &$parser				The wikitext parser.
 * 
 * @return 						An HTML quiz.
 */
function renderQuiz($input, $argv, &$parser) {    
	$parser->disableCache();
	$quiz = new Quiz($argv, $parser);	
	return $quiz->parseQuiz($input);
}

/**
 * Processes quiz markup
 */
class Quiz {	
	/**#@+
	 * @protected
	 */
	var $mQuizId, $mQuestionId, $mBeingCorrected, $mScore, $mTotal, $mAddedPoints, $mCutoffPoints, $mIgnoringCoef;
	# Quiz parameters
	var $mMessages;
	static $mColors = array(
		'right' 		=> "#1FF72D",
		'wrong' 		=> "#F74245",
		'correction' 	=> "#F9F9F9",
		'NA' 			=> "#2834FF",
		'error' 		=> "#D700D7"
	);
	var $mParser, $mRequest;
	/**#@- */
	static $sQuizId = 0;
	 
	/** 
	 * Constructor
	 * 
	 * @public
	 */
	function Quiz($argv, &$parser) {
		global $wgRequest, $wgLanguageCode;
		$this->mParser = $parser;
		$this->mRequest = &$wgRequest;
		# Determine which messages will be used, according to the language.
		self::loadMessages();
		# Allot a unique identifier to the quiz.
		$this->mQuizId = self::$sQuizId;
		self::$sQuizId++;
		# Reset the unique identifier of the questions.
		$this->mQuestionId = 0;
		# Reset the counter of div "shuffle" or "noshuffle" inside the quiz.
		$this->mShuffleDiv = 0;
		# Determine if this quiz is being corrected or not, according to the quizId
	    $this->mBeingCorrected = ($wgRequest->getVal('quizId') == "$this->mQuizId")? true : false;
	    # Initialize various parameters used for the score calculation
	    $this->mScore = 0;
	    $this->mTotal = 0;
	    $this->mAddedPoints = 1;
	    $this->mCutoffPoints = 0;
	    $this->mIgnoringCoef = false;
	    if($this->mBeingCorrected) {
	    	if(is_numeric($this->mRequest->getVal('addedPoints'))) {
	    		$this->mAddedPoints = $wgRequest->getVal('addedPoints');
	    	}
	    	if(is_numeric($this->mRequest->getVal('cutoffPoints'))) {
	    		$this->mCutoffPoints = $wgRequest->getVal('cutoffPoints');
	    	}
	    	if($this->mRequest->getVal('ignoringCoef') == "on") {
	    		$this->mIgnoringCoef = true;
	    	}
	    } elseif (array_key_exists('points',$argv) && preg_match('`([\d\.,]*)/?([\d\.,]*)(!)?`', $argv['points'], $matches)) {	
	    	if(is_numeric($matches[1])) {
	    		$this->mAddedPoints = $matches[1];
	    	}
	    	if(is_numeric($matches[2])) {
	    		$this->mCutoffPoints = $matches[2];
	    	}
	    	if(array_key_exists(3,$matches)) {
	    		$this->mIgnoringCoef = true;
	    	}
	    }
	    $this->mDisplaySettings = (array_key_exists('display', $argv) && $argv['display'] == "simple")? false : true;
	    $this->mIncludePattern = '`^\{\{:?(.*)\}\}[ \t]*`m';		    
	}
	
	static function resetQuizID() {
		self::$sQuizId = 0;
	}
	
	function loadMessages() {
	    static $messagesLoaded = false;
	    global $wgMessageCache;
	    if ( $messagesLoaded ) return;
	    $messagesLoaded = true;
	    require( dirname( __FILE__ ) . '/Quiz.i18n.php' );
	    foreach ($wgQuizMessages as $lang => $langMessages ) {
	    	$wgMessageCache->addMessages( $langMessages, $lang );
	    }
	}	
	
	/**
	 * Accessor for the color array
	 * Dispalys an error message if the colorId doesn't exists.
	 * 
	 * @public
	 * @param  $colorId
	 */
	static function getColor($colorId) {
		try {
			if(array_key_exists($colorId, self::$mColors)) {
				return self::$mColors[$colorId];
			} else {
				throw new Exception($colorId);
			}
			
		} catch(Exception $e) {
			echo "Invalid color ID : ".$e->getMessage()."\n";
		}
	}
	
	/**
	 * Convert the input text to an HTML output.
	 * 
	 * @param  $input				Text between <quiz> and </quiz> tags, in quiz syntax.
	 */
	function parseQuiz($input) {
		# Ouput the style and the script to the header once for all.
		if($this->mQuizId == 0) {
			$head  = "<style type=\"text/css\">\n";
	    	$head .= ".quiz .settings input.numerical { width:2em; }\n";	    	
	    	$head .= ".quiz .question {margin-left: 2em }\n";
	    	$head .= ".quiz .header .questionId {font-size: 1.1em; font-weight: bold; float: left;}\n";
	    	# This fu**ing ie hiddes the questionId when it is indented...
	    	$head .= "*>.quiz .header .questionId {text-indent: -1.5em;}\n";
			$head .= ".quiz .correction { background-color: ".Quiz::getColor('correction').";}\n";
	    	$head .= ".quiz .hideCorrection .correction { display: none; }\n";
			$head .= ".quiz .settings td { padding: 0.1em 0.4em 0.1em 0.4em }\n";
			# Part for the basic types's inputs.
			$head .= ".quiz .sign {text-align:center; }\n";
			# Part for the inputfields
			$head .= ".quiz a.input, .quiz a.input:hover, .quiz a.input:active, .quiz a.input:visited { text-decoration:none; color:black; outline:0 }";
			$head .= ".quiz a.input span { outline:black solid 1px}";
			$head .= "* html .quiz a.input span { border:1px solid black }";
			$head .= ".quiz a.input big { font-weight:bold; font-family:sans-serif; }";
			$head .= ".quiz a.input input { padding-left:2px; border:0; }";
			$head .= ".quiz a.input span.correction { padding:3px; margin:0; list-style-type:none; display:none; background-color:".Quiz::getColor("correction")."; }";
			$head .= ".quiz a.input:active span.correction, .quiz a.input:focus span.correction { display:inline; position:absolute; margin:1.8em 0 0 0.1em; }";
			$head .= "</style>\n";
	    	global $wgJsMimeType, $wgScriptPath, $wgOut;
	    	$head .= "<script type=\"$wgJsMimeType\" src=\"$wgScriptPath/extensions/quiz/quiz.js\"></script>\n";
	    	$wgOut->addScript($head);
		}
		$classHide = ($this->mBeingCorrected)? "" : " class=\"hideCorrection\"";
		$output  = "<div id=\"quiz$this->mQuizId\" class=\"quiz\">";    
	    $output .= "<form $classHide method=\"post\" action=\"#quiz$this->mQuizId\" >\n";
	    if($this->mDisplaySettings) {
		    $output .= 	"<table class=\"settings\"><tbody>";
			$output .= 	"<tr><td>".wfMsgHtml('quiz_addedPoints').":</td>" .
						"<td><input class=\"numerical\" type=\"text\" name=\"addedPoints\" value=\"$this->mAddedPoints\"/></td><td></td>" .
						"<td style=\"background: ".$this->getColor('error')."\"></td>" .
						"<td>".wfMsgHtml('quiz_colorError')."</td></tr>";
			$output .= 	"<tr><td>".wfMsgHtml('quiz_cutoffPoints').":</td>" .
						"<td><input class=\"numerical\" type=\"text\" name=\"cutoffPoints\" value=\"$this->mCutoffPoints\"/></td><td></td>" .
						"<td class=\"correction\" style=\"background: ".$this->getColor('right')."\"></td>" .
						"<td class=\"correction\" style=\"background: transparent;\">".wfMsgHtml('quiz_colorRight')."</td></tr>";
			$bChecked = ($this->mIgnoringCoef)? "checked=\"checked\"" : "";
			$output .= 	"<tr><td>".wfMsgHtml('quiz_ignoreCoef').":</td>" .
						"<td><input type=\"checkbox\" name=\"ignoringCoef\" $bChecked/></td><td></td>" .
						"<td class=\"correction\" style=\"background: ".$this->getColor('wrong')."\"></td>" .
						"<td class=\"correction\" style=\"background: transparent;\">".wfMsgHtml('quiz_colorWrong')."</td></tr>";
			$output .= 	"<tr><td><input class=\"scriptshow\" type=\"button\" value=\"".wfMsgHtml('quiz_shuffle')."\" style=\"display: none;\"/></td>" .
						"<td></td><td></td>" .
						"<td class=\"correction\" style=\"background: ".$this->getColor('NA')."\"></td>" .
						"<td class=\"correction\" style=\"background: transparent;\">".wfMsgHtml('quiz_colorNA')."</td></tr>";
			$output .= 	"</tbody></table>";
	    }
	    $output .= "<input type=\"hidden\" name=\"quizId\" value=\"$this->mQuizId\"/>";
	    $input = $this->parseIncludes($input);
		$output .= "<div class=\"quizQuestions\">";
		$output .= $this->parseQuestions($input);
		$output .= "</div>";
			   
	    $output .= "<p><input type=\"submit\" value=\"" . wfMsgHtml( 'quiz_correction' ) . "\"/></p>";
		$output .= "<span class=\"correction\">";
		$output .= wfMsgHtml('quiz_score', 
			"<span class=\"score\">$this->mScore</span>",
			"<span class=\"total\">$this->mTotal</span>" );
	    $output .= "</span>";
	    $output .= "</form>\n";
	    $output .= "</div>\n";		
		return $output;
	}
	
	/**
	 * Replace inclusions from other quizzes.
	 *
	 * @param  $input				Text between <quiz> and </quiz> tags, in quiz syntax.
	 */
	function parseIncludes($input) {
		return preg_replace_callback($this->mIncludePattern, array($this, "parseInclude"), $input);				
	}
	
	/**
	 * Include text between <quiz> and <quiz> from another page to this quiz.
	 * 
	 * @param  $matches				Elements matching $includePattern.
	 * 								$matches[1] is the page title.
	 */
	function parseInclude($matches) {
		$title = Title::makeTitleSafe( NS_MAIN, $matches[1]);
		$text = $this->mParser->fetchTemplate($title);
		$output = "";
		if(preg_match('`<quiz[^>]*>(.*)</quiz>`sU', $text, $unparsedQuiz)) {
			# Remove inclusions from included quiz.
			$output  = preg_replace($this->mIncludePattern, "", StringUtils::escapeRegexReplacement($unparsedQuiz[1]));
			$output .= "\n";
		}
		return $output;
	}
	
	/**
	 * Replace questions from quiz syntax to HTML.
	 * 
	 * @param  $input				A question in quiz syntax.
	 */
	function parseQuestions($input) {
		$splitPattern = '`(^|\n[ \t]*)\n\{`';		
		$unparsedQuestions = preg_split($splitPattern, $input, -1, PREG_SPLIT_NO_EMPTY );
		$output = "";
		$questionPattern = '`(.*?[^|\}])\}[ \t]*(\n(.*)|$)`s';
		foreach($unparsedQuestions as $unparsedQuestion) {
			# If this "unparsedQuestion" is not a full question,
			# we put the text into a buffer to add it at the beginning of the next question.
			if(!empty($buffer)) $unparsedQuestion = "$buffer\n\n{".$unparsedQuestion;
			if(preg_match($questionPattern, $unparsedQuestion, $matches)) {
				$buffer = "";
				$output.= $this->parseQuestion($matches);
			} else {
				$buffer = $unparsedQuestion;
			}
		}
		# Close unclosed "shuffle" or "noshuffle" tags.
		while($this->mShuffleDiv > 0) {
			$output.= "</div>";
			$this->mShuffleDiv--;
		}
		return $output;
	}
	
	/**
	 * Convert a question from quiz syntax to HTML
	 * 
	 * @param  $matches				Elements matching $questionPattern.
	 * 								$matches[1] is the question header.
	 * 								$matches[3] is the question object.
	 */
	function parseQuestion($matches) {
		$question = new Question($this->mBeingCorrected, $this->mQuestionId, $this->mParser);
		$buffer  = $question->parseHeader($matches[1]);
		if(!array_key_exists(3, $matches) || trim($matches[3]) == "") {
			switch($matches[1]) {
			case "X":
				$this->mShuffleDiv++;
				return "<div class=\"shuffle\">\n";
				break;
			case "!X":
				$this->mShuffleDiv++;
				return "<div class=\"noshuffle\">\n";
				break;
			case "/X":
				# Prevent closing of other tags.
				if($this->mShuffleDiv == 0) {
					return;
				} else {
					$this->mShuffleDiv--;
					return "</div>\n";
				}
				break;
			default:
				return "<div class=\"quizText\">$buffer<br/></div>";
				break;
			}
		}
		$output  = "<div class=\"question\">\n";
		$output .= "<input type=\"hidden\" name=\"questionType\" value=\"{$question->mType}\"/>";
		$output .= "<input type=\"hidden\" name=\"questionCoef\" value=\"{$question->mCoef}\"/>";
		$output .= "<div class=\"header\">\n";
		$output .= "<span class=\"questionId\">".++$this->mQuestionId.". </span>$buffer";
		$output .= "</div>\n";
		# Store the parsed object into a buffer to determine some parameters before outputing it.
		$buffer = call_user_func(array($question, "{$question->mType}ParseObject"), $matches[3]);
 		$output .= "<table class=\"object\" ";
		$lState = $question->getState();
		# Determine the border-left color, score and the total of the question.
		if($lState != "") {
			$output .= "style=\"border-left:3px solid ".$this->getColor($lState)."\"";
			if($this->mIgnoringCoef) {
				$question->mCoef = 1;
			} 
			switch($lState) {
			case "right":
				$this->mTotal += $this->mAddedPoints * $question->mCoef;
				$this->mScore += $this->mAddedPoints * $question->mCoef;
				break;
			case "wrong":
				$this->mTotal += $this->mAddedPoints * $question->mCoef;
				$this->mScore -= $this->mCutoffPoints * $question->mCoef;
				break;
			case "NA":
				$this->mTotal += $this->mAddedPoints * $question->mCoef;
				break;
			}
		}
		$output .= "><tbody>\n";
		$output .= $buffer;
		$output .= "</tbody></table>\n";
		$output .= "<br/></div>\n";
		return $output;
	}
}

class Question {
	var $mQuestionId, $mType, $mCoef , $mCorrectionPattern, $mProposalPattern, $mCategoryPattern;
	/**#@+
	 * @private
	 */
	var $mState;
	/**#@-*/
	
	/**
	 * Constructor
	 * 
	 * @public
	 * @param  $beingCorrected			boolean.
	 * @param  $questionId				The Identifier of the question used to gernerate input names.
	 * @param  $parser					The wikitext parser.	 
	 */
	function Question($beingCorrected, $questionId, &$parser) {
		global $wgRequest;
		$this->mRequest = &$wgRequest;
		$this->mQuestionId = $questionId;
		$this->mBeingCorrected = ($beingCorrected)? true : false;
		$this->mParser = $parser;
		$this->mState = ($beingCorrected)? "NA" : "";
		$this->mType = "multipleChoice";
		$this->mCoef = 1;
		$this->mProposalPattern 	= '`^([+-]) ?(.*)`';
		$this->mCorrectionPattern 	= '`^\|\|(.*)`';	
		$this->mCategoryPattern 	= '`^\|([^\|].*)\s*`';
		$this->mTextFieldPattern 	= '`\{ ([^\}]*?)(_([\d]*) ?| )\}`';
	}	
	
	/**
	 * Mutator of the question state
	 * 
	 * @protected
	 * @param  $pState				
	 */
	function setState($pState) {
		if ($pState == "error" 
		|| ($pState == "wrong" && $this->mState != "error") 
		|| ($pState == "right" && ($this->mState == "right" || $this->mState == "NA" || $this->mState == "na_right"))
		|| ($pState == "na_wrong" && ($this->mState == "NA" || $this->mState == "na_right"))
		|| ($pState == "na_right" && ($this->mState == "NA"))
		) {
			$this->mState = $pState;
		}
		# Special cases
		if(($pState == "na_wrong" && $this->mState == "right") || ($pState == "right" && $this->mState == "na_wrong")) {
			$this->mState = "wrong";
		}
		return;
	}
	
	/**
	 * Accessor of the question state.
	 * 
	 * @protected
	 */
	function getState() {
		if ($this->mState == "na_right") {
			return "right";
		} elseif ($this->mState == "na_wrong") {
			return "NA";
		} else {
			return $this->mState;
		}
	}
	
	/**
	 * Accessor for the question Id.
	 * If the questionId is accessed, it is incremented.
	 */
	
	/**
	 * Convert the question's header into HTML.
	 * 
	 * @param  $input				The quiz header in quiz syntax.
	 */
	function parseHeader($input) {
		$parametersPattern = '`\n\|([^\|].*)\s*$`';
		$input = preg_replace_callback($parametersPattern, array($this, "parseParameters"), $input);	
		$splitHeaderPattern = '`\n\|\|`';
		$unparsedHeader = preg_split($splitHeaderPattern, $input);
	 	# If the header is empty, the question has a syntax error.
		if(trim($unparsedHeader[0]) == "") {
	 		$unparsedHeader[0] = "???";
	 		$this->setState("error");
	 	}
	 	$output = $this->mParser->recursiveTagParse(trim($unparsedHeader[0])."\n");
		if(array_key_exists(1,$unparsedHeader)) {
	 		$output .= "<table><tbody><tr class=\"correction\">";
			$output .= "<td>&#x2192;</td><td>".$this->mParser->recursiveTagParse(trim($unparsedHeader[1]))."</td>";
			$output .= "</tr></tbody></table>";
	 	}
	 	return $output;
	}
	
	/**
	 * Determine the question's parameters.
	 * 
	 * @param  $matches				Elements matching $parametersPattern
	 * 								$matches[0] are the potential question parameters.
	 */
	function parseParameters($matches) {
		$typePattern = '`t[yi]p[eo]?="(.*?)"`';
		if(preg_match($typePattern, $matches[1], $type)) {
			# List of all object type code and the correspondant question type.
			switch($type[1]) {
			case '{}':
				$this->mType = "textField";
				break;
			case '()':
				$this->mType = "singleChoice";
				break;
			case '[]':
				$this->mType = "multipleChoice";
				break;			
			}
		}
		$coefPattern = '`[ck]oef="(.*?)"`';
		if(preg_match($coefPattern, $matches[1], $coef) && is_numeric($coef[1]) && $coef[1]>0) {
			$this->mCoef = $coef[1];
		} 
		return;
	}
	
	/**
	 * Transmit a single choice object to the basic type parser.
	 * 
	 * @param  $input				A question object in quiz syntax.
	 * 
	 * @return $output				A question object in HTML.
	 */
	function singleChoiceParseObject($input) {
		return $this->basicTypeParseObject($input, "radio");
	}
	
	/**
	 * Transmit a multiple choice object to the basic type parser.
	 * 
	 * @param  $input				A question object in quiz syntax.
	 * 
	 * @return $output				A question object in HTML.
	 */
	function multipleChoiceParseObject($input) {
		return $this->basicTypeParseObject($input, "checkbox");
	}
	
	/**
	 * Convert a basic type object from quiz syntax to HTML.
	 * 
	 * @param  $input				A question object in quiz syntax
	 * @param  $inputType			
	 * 
	 * @return $output				A question object in HTML.
	 */
	function basicTypeParseObject($input, $inputType) {
		$output = preg_match($this->mCategoryPattern, $input, $matches)? $this->parseCategories($matches[1]) : "";
		$raws = preg_split('`\n`s', $input, -1, PREG_SPLIT_NO_EMPTY);
		# Parameters used in some special cases.
		$expectOn 		= 0;
		$checkedCount 	= 0;
		foreach($raws as $proposalId => $raw) {
			$text 			= NULL;
			$colSpan 		= "";
			$signesOutput 	= "";
			if(preg_match($this->mProposalPattern, $raw, $matches)) {
				$rawClass = "proposal";
				# Insulate the proposla signes.
				$text = array_pop($matches);
				array_shift($matches);
				# Determine a type ID, according to the questionType and the number of signes.
				$typeId  = substr($this->mType, 0, 1);
				$typeId .= array_key_exists(1, $matches)? "c" : "n";				
				foreach($matches as $signId => $sign) {
					$inputStyle = "";
					# Determine the input's name and value.
					switch($typeId) {
					case "mn":
						$name = "q$this->mQuestionId"."p$proposalId";
						$value = "p$proposalId";
						break;
					case "sn":
						$name = "q$this->mQuestionId";
						$value = "p$proposalId";
						break;
					case "mc":
						$name = "q$this->mQuestionId"."p$proposalId"."s$signId";
						$value = "s$signId";
						break;
					case "sc":
						$name = "q$this->mQuestionId"."p$proposalId";
						$value = "s$signId";
						break;
					}
					# Determine if the input had to be checked.
					$checked = ($this->mBeingCorrected && $this->mRequest->getVal($name) == $value)? "checked=\"checked\"" : NULL;
					# Determine the color of the cell and modify the state of the question.
					switch($sign) {
					case "+":
						$expected = "+";
						$expectOn++;
						# A single choice object with many correct proposal is a syntax error.
						if($this->mType == "singleChoice" && $expectOn > 1) {
							$expected = "=";
							$this->setState("error");
							$inputStyle = "style=\"outline: ".Quiz::getColor('error')." solid 3px; border: 3px solid ".Quiz::getColor('error').";\"";
						}
						if($this->mBeingCorrected) {
							if($checked) {
								$checkedCount++;
								$this->setState("right");
								$inputStyle = "style=\"outline: ".Quiz::getColor('right')." solid 3px; border: 3px solid ".Quiz::getColor('right').";\"";
							} else {
								$this->setState("na_wrong");
								$inputStyle = "style=\"outline: ".Quiz::getColor('wrong')." solid 3px; border: 3px solid ".Quiz::getColor('wrong').";\"";
							}
						} 
						break;
					case "-":
						$expected = "-";
						if($this->mBeingCorrected) {
							if($checked) {
								$checkedCount++;
								$this->setState("wrong");
								$inputStyle = "style=\"outline: ".Quiz::getColor('wrong')." solid 3px; border: 3px solid ".Quiz::getColor('wrong').";\"";
							} else {
								$this->setState("na_right");
							}
						} 
						break;
					default:
						$expected = "=";
						$this->setState("error");
						$inputStyle = "style=\"outline: ".Quiz::getColor('error')." solid 3px; border: 3px solid ".Quiz::getColor('error').";\"";
						break;
					}
					$signesOutput .= "<td class=\"sign\">";
					$signesOutput .= "<input class=\"$expected\" $inputStyle type=\"$inputType\" name=\"$name\" value=\"$value\" $checked/>";
					$signesOutput .= "</td>";
				}
				if($typeId == "sc") {
					# A single choice object with no correct proposal is a syntax error.
					if($expectOn == 0) {
						$this->setState("error");
					}
					$expectOn = 0;
				}
				# If the proposal text is empty, the question has a syntax error.
				if(trim($text) == "") {
					$text = "???";
					$this->setState("error");
				}
			} elseif(preg_match($this->mCorrectionPattern, $raw, $matches)) {
				$rawClass = "correction";
				$text = array_pop($matches);
				$signesOutput = "<td>&#x2192;</td>";
				# Hacks to avoid counting the number of signes.
				$colSpan = " colspan=\"11\"";
			}
			if($text) {				
				$output .= "<tr class=\"$rawClass\">\n";
				$output .= $signesOutput;
				$output .= "<td$colSpan>";				
				$output .= $this->mParser->recursiveTagParse($text);
				$output .= "</td>";
				$output .= "</tr>\n";
			}
		}
		# A single choice object with no correct proposal is a syntax error.
		if(isset($typeId) && $typeId == "sn" && $expectOn == 0) {
			$this->setState("error");			
		}	
		return $output;
	}	
	
	/**
	 * Determine the object's parameters and convert a list of categories from quiz syntax to HTML.
	 * 
	 * @param  $input			The various categories.
	 */
	function parseCategories($input) {
		$categories = explode('|', $input);
		# Less than two categories is a syntax error.
		if(!array_key_exists(1, $categories)) {
			$categories[1] = "???";
			$this->setState("error");
		}
		$output = "<tr class=\"categories\">\n";
		$this->mProposalPattern =  '`^';
		foreach($categories as $key => $category) {
			# If a category name is empty, the question has a syntax error.
			if(trim($category) == "") {
				$category = "???";
				$this->setState("error");
			}
			$output .= "<th>".Sanitizer::removeHTMLtags($category)."</th>";
			if($key == 0) {
				$this->mProposalPattern .= '([+-]) ?';
			} else {
				$this->mProposalPattern .= '([+-])? ?';
			}			
		}
		$output .= "<th></th>";
		$output .= "</tr>\n";
		$this->mProposalPattern .= '(.*)`';
		return $output;
	}
	
	/**
	 * Convert a "text field" object to HTML.
	 * 
	 * @param  $input				A question object in quiz syntax.
	 *
	 * @return $output				A question object in HTML.
	 */
	function textFieldParseObject($input) {
		$raws = preg_split('`\n`s', $input, -1, PREG_SPLIT_NO_EMPTY);
		global $wqInputId;
		$wqInputId = $this->mQuestionId * 100;
		$output = "";
		foreach($raws as $raw) {
			# soit c'est une correction, dans ce cas là on le parse tout court
			# soit c'est un texte à trou et dans ce cas là on parse puis on remplace les {} par des trous en faisant un 
			# callback
			if(preg_match($this->mCorrectionPattern, $raw, $matches)) {
				$rawClass = "correction";
				$text = "<td>&#x2192; ".$this->mParser->recursiveTagParse($matches[1])."</td>";
			} elseif(trim($raw) != "") {
				$rawClass = "proposal";
				$text = $this->mParser->recursiveTagParse($raw);
				$text = preg_replace_callback($this->mTextFieldPattern, array($this, "parseTextField"), $text);
				$text = "<td class=\"input\">$text</td>";
			}
			$output.= "<tr class=\"$rawClass\">\n$text</tr>\n";
		}
		return $output;
	}
	
	function parseTextField($input) {
		global $wqInputId;
		$wqInputId ++;
		$state = $size = $maxlength = $class = $style = $value = $disabled = $a_inputBeg = $a_inputEnd = $big = "";
		# determine size and maxlength of the input.
		if(array_key_exists(3, $input)) {
			$size = $input[3];
			if($size<3) $size = "size=\"1\"";
			elseif($size<12) $size = "size=\"".($size-2)."\"";
			else $size = "size=\"".($size-1)."\"";
			$maxlength = "maxlength=\"".$input[3]."\"";
		}
		$input[1] = trim($input[1]);
		# Syntax error if there is no input text.
		if(empty($input[1])) {
			$value = "value=\"???\"";
		} else {
			if($this->mBeingCorrected) {
				$value = trim($this->mRequest->getVal($wqInputId));
				$a_inputBeg = "<a class=\"input\" href=\"#nogo\"><span class=\"correction\">";				
				$state = "NA";			
			}
			$class = "class=\"numbers\"";
			foreach(preg_split('` *\| *`', $input[1], -1, PREG_SPLIT_NO_EMPTY) as $possibility) {
				if( $state == "" || $state == "NA" || $state == "wrong") {
					if(preg_match('`^(-?\d+\.?\d*)(-(-?\d+\.?\d*)| (\d+\.?\d*)(%))?$`', $possibility, $matches)) {
						if(array_key_exists(5, $matches)) $strlen = $size = $maxlength = "";
						elseif(array_key_exists(3, $matches)) $strlen = strlen($matches[1]) > strlen($matches[3])? strlen($matches[1]) : strlen($matches[3]);
						else $strlen = strlen($matches[1]);						
						if($this->mBeingCorrected && $value != "") {
							$state = (is_numeric($value) &&
							(  (array_key_exists(5, $matches) && $value >= ($matches[1]-($matches[1]*$matches[4])/100) && $value <= ($matches[1]+($matches[1]*$matches[4])/100) )
							|| (array_key_exists(3, $matches) && $value >= $matches[1] && $value <= $matches[3])
							|| $value == $possibility ))? "right" : "wrong";
						}
					} else {
						$strlen = strlen($possibility);
						$class = "class=\"words\"";
						if($this->mBeingCorrected && $value != "") $state = ($value == $possibility)? "right" : "wrong";
					}
					if(array_key_exists(3, $input) && $strlen > $input[3]) {
						# The textfield is too short for the answer
						$state = "error";
						$value = "<_{$possibility}_>";
					}
				}
				if($this->mBeingCorrected) $a_inputBeg.= "$possibility<br/>";					
			}
			$value = "value=\"".Sanitizer::removeHTMLtags($value)."\"";
			if($this->mBeingCorrected) {
				$a_inputBeg.= "</span>";
				$a_inputEnd = "</a>";				
				$big = "<big>v</big>";
			}
		}
		if($state == "error" || $this->mBeingCorrected) {
			$style = "style=\"border-left:3px solid ".Quiz::getColor($state)."; \"";
			$this->setState($state);
			if($state == "error") {
				$size = "";
				$maxlength = "";
				$disabled = "disabled=\"disabled\"";
			}						
		}
		return $output = "$a_inputBeg<span $style><input $class type=\"text\" name=\"$wqInputId\" $size $maxlength $value $disabled />$big</span>$a_inputEnd";		
	}
}
?>
