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
 * @version 0.5b
 * @link http://www.mediawiki.org/wiki/Extension:Quiz
 * 
 * @author BABE Louis-Rémi <lrbabe@gmail.com>
 */
 
/**
 * Extension's parameters.
 */
$wgExtensionCredits['parserhook'][] = array(
    'name'=>'Quiz',
    'version'=>'0.5b',
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
	var $mQuizId, $mBeingCorrected, $mScore, $mTotal, $mAddedPoints, $mCutoffPoints, $mIgnoringCoef;
	# Quiz parameters
	var $mMessages, $mColors;
	var $mParser, $mRequest;
	/**#@- */
	static $sQuizId = 0;
	 
	/** 
	 * Constructor
	 * 
	 * @public
	 */
	function Quiz($argv, &$parser) {
		global $wgRequest, $wgLanguageCode, $wgQuizMessages, $wgQuizColors, $gQuestionId;
		$this->mParser = $parser;
		$this->mRequest = &$wgRequest;
		# Determine which messages will be used, according to the language.
		self::loadMessages();
		$this->mColors = array(
			'right' 		=> "#1FF72D",
			'wrong' 		=> "#F74245",
			'correction' 	=> "#F9F9F9",
			'NA' 			=> "#2834FF",
			'error' 		=> "#D700D7"
		);
		# Allot a unique identifier to the quiz.
		$this->mQuizId = self::$sQuizId;
		self::$sQuizId++;
		# Reset the unique identifier of the questions.
		$gQuestionId = 0;
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
	    		echo "checked";
	    	}
	    } elseif (array_key_exists('points',$argv) && preg_match('`(.*?)/?(.*?)(!)?`', $argv['points'], $matches)) {	
	    	if(array_key_exists(1,$matches)) {
	    		$this->mAddedPoints = $matches[1];
	    	}
	    	if(array_key_exists(3,$matches)) {
	    		$this->mCutOffPoints = $matches[3];
	    	}
	    	if(array_key_exists(4,$matches)) {
	    		$this->mIgnoringCoef = true;
	    	}
	    }
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
	function getColor($colorId) {
		try {
			if(array_key_exists($colorId, $this->mColors)) {
				return $this->mColors[$colorId];
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
	    	$head .= ".quiz input.text { width:2em; }\n";	    	
	    	$head .= ".quiz .question {margin-left: 2em }\n";
	    	$head .= ".quiz .header>p:first-child {text-indent: -1.5em;}\n";
			$head .= ".quiz .header>p:first-child:first-letter {font-size: 1.2em;}\n";
			$head .= ".quiz .correction { background-color: ".$this->getColor('correction').";}\n";
	    	$head .= ".quiz .hideCorrection .correction { display: none; }\n";
			$head .= ".quiz .settings td { padding: 0.1em 0.4em 0.1em 0.4em }\n";
	    	$head .= "</style>\n";
	    	global $wgJsMimeType, $wgScriptPath, $wgOut;
	    	$head .= "<script type=\"$wgJsMimeType\" src=\"$wgScriptPath/extensions/quiz/quiz.js\"></script>\n";
	    	$wgOut->addScript($head);
		}
		$classHide = ($this->mBeingCorrected)? "" : " class=\"hideCorrection\"";
		$output  = "<div id=\"quiz$this->mQuizId\" class=\"quiz\">";    
	    $output .= "<form $classHide method=\"post\" action=\"#quiz$this->mQuizId\" onsubmit=\"return(correctIt());\">\n";
	    $output .= 	"<table class=\"settings\"><tbody>";
		$output .= 	"<tr><td>".wfMsgHtml('quiz_addedPoints')." : </td>" .
					"<td><input class=\"text\" type=\"text\" name=\"addedPoints\" value=\"$this->mAddedPoints\"/></td><td></td>" .
					"<td>".wfMsgHtml('quiz_colorError')." : </td>" .
					"<td style=\"background: ".$this->getColor('error')."\"></td></tr>";
		$output .= 	"<tr><td>".wfMsgHtml('quiz_cutoffPoints')." : </td>" .
					"<td><input class=\"text\" type=\"text\" name=\"cutoffPoints\" value=\"$this->mCutoffPoints\"/></td><td></td>" .
					"<td class=\"correction\" style=\"background: transparent;\">".wfMsgHtml('quiz_colorRight')." : </td>" .
					"<td class=\"correction\" style=\"background: ".$this->getColor('right')."\"></td></tr>";
		$bChecked = ($this->mIgnoringCoef)? "checked" : "";
		$output .= 	"<tr><td>".wfMsgHtml('quiz_ignoreCoef')." : </td>" .
					"<td><input type=\"checkbox\" name=\"ignoringCoef\" $bChecked/></td><td></td>" .
					"<td class=\"correction\" style=\"background: transparent;\">".wfMsgHtml('quiz_colorWrong')." : </td>" .
					"<td class=\"correction\" style=\"background: ".$this->getColor('wrong')."\"></td></tr>";
		$output .= 	"<tr><td></td>" .
					"<td><input type=\"hidden\" name=\"quizId\" value=\"$this->mQuizId\"/></td><td></td>" .
					"<td class=\"correction\" style=\"background: transparent;\">".wfMsgHtml('quiz_colorNA')." : </td>" .
					"<td class=\"correction\" style=\"background: ".$this->getColor('NA')."\"></td></tr>";
		$output .= 	"</tbody></table>";
		
	    $input = $this->parseIncludes($input);
		$output .= $this->parseQuestions($input);
			   
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
	 * Replace inclusions from other quiz.
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
		$questionPattern = '`(.*?)\n\{(.*?)\}[ \t]*\n(.*?)(\n[ \t]*\n|\s*$)`s';
		return preg_replace_callback($questionPattern, array($this, "parseQuestion" ), $input);
	}
	
	/**
	 * Convert a question from quiz syntax to HTML
	 * 
	 * @param  $matches				Elements matching $questionPattern.
	 * 								$matches[1] is the text before the question.
	 * 								$matches[2] is the question header.
	 * 								$matches[3] is the question object.
	 */
	function parseQuestion($matches) {
		$question = new Question($this->mBeingCorrected);
		$output  = $this->mParser->recursiveTagParse($matches[1]);
		$buffer  = $question->parseHeader($matches[2], $this->mParser);
		$output .= "<div class=\"question\">\n";
		$output .= "<input type=\"hidden\" name=\"questionType\" value=\"{$question->mType}\"/>";
		$output .= "<input type=\"hidden\" name=\"questionCoef\" value=\"{$question->mCoef}\"/>";
		$output .= "<div class=\"header\">\n";
		$output .= $buffer;
		$output .= "</div>\n";
		# Store the parsed object into a buffer to determine some parameters before outputing it.
		$buffer = call_user_func(array($question, "{$question->mType}ParseObject"), $matches[3], $this);
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
	 */
	function Question($beingCorrected) {
		# Determine a unique questionId
		global $gQuestionId;
		$this->mQuestionId = $gQuestionId;
		$gQuestionId++;
		$this->mState = ($beingCorrected)? "NA" : "";
		$this->mType = "multipleChoice";
		$this->mCoef = 1;
		$this->mProposalPattern = '`^([+-]) ?(.*)`';
		$this->mCorrectionPattern = '`^\|\|(.*)`';	
		$this->mCategoryPattern = '`^\|([^\|].*)\s*`';
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
	 * Convert the question's header into HTML.
	 * 
	 * @param  $input				The quiz header in quiz syntax.
	 * @param  $parser				The wikitext parser.
	 */
	function parseHeader($input, $parser) {
		$parametersPattern = '`\n\|(.*)\s*$`';
		$input = preg_replace_callback($parametersPattern, array($this, "parseParameters"), $input);		
		$splitHeaderPattern = '`\n\|\|`';
		$unparsedHeader = preg_split($splitHeaderPattern, $input);
	 	$output = ($this->mQuestionId+1).".&nbsp;&nbsp;";
		# If the header is empty, the question has a syntax error.
		if(trim($unparsedHeader[0]) == "") {
	 		$unparsedHeader[0] = "???";
	 		$this->setState("error");
	 	}		
		$output .= $parser->recursiveTagParse(trim($unparsedHeader[0])."\n");
		if(array_key_exists(1,$unparsedHeader)) {
	 		$output .= "<table><tbody><tr class=\"correction\">";
			$output .= "<td>&#151;&#155;</td><td>".$parser->recursiveTagParse(trim($unparsedHeader[1]))."</td>";
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
			case '()':
				$this->mType = "singleChoice";
				break;
			case '[]':
				$this->mType = "multipleChoice";
				break;			
			}
		}
		$coefPattern = '`[ck]oef="(.*?)"`';
		if(preg_match($coefPattern, $matches[1], $coef) && is_numeric($coef[1])) {
			$this->mCoef = $coef[1];
		} 
		return;
	}
	
	/**
	 * Transmit a single choice object to the basic type parser.
	 * 
	 * @param  $input				A question object in quiz syntax.
	 * @param  $quiz				The current quiz.
	 * @see    class Quiz
	 * 
	 * @return $output				A question object in HTML.
	 */
	function singleChoiceParseObject($input, $quiz) {
		return $this->basicTypeParseObject($input, "radio", $quiz);
	}
	
	/**
	 * Transmit a multiple choice object to the basic type parser.
	 * 
	 * @param  $input				A question object in quiz syntax.
	 * @param  $quiz				The current quiz.
	 * @see    class Quiz
	 * 
	 * @return $output				A question object in HTML.
	 */
	function multipleChoiceParseObject($input, $quiz) {
		return $this->basicTypeParseObject($input, "checkbox", $quiz);
	}
	
	/**
	 * Convert a basic type object from quiz syntax to HTML.
	 * 
	 * @param  $input				A question object in quiz syntax
	 * @param  $inputType			
	 * @param  $quiz				The current quiz
	 * @see    class Quiz
	 * 
	 * @return $output				A question object in HTML.
	 */
	function basicTypeParseObject($input, $inputType, $quiz) {
		$output = preg_match($this->mCategoryPattern, $input, $matches)? $this->parseCategories($matches[1]) : "";
		$raws = preg_split('`\n`s', $input);
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
					$cellStyle = "";
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
					$checked = ($quiz->mBeingCorrected && $quiz->mRequest->getVal($name) == $value)? "checked" : NULL;
					# Determine the color of the cell and modify the state of the question.
					switch($sign) {
					case "+":
						$expected = "+";
						$expectOn++;
						# A single choice object with many correct proposal is a syntax error.
						if($this->mType == "singleChoice" && $expectOn > 1) {
							$expected = "=";
							$this->setState("error");
							$cellStyle = " style=\"background: ".$quiz->getColor('error').";\"";
						}
						if($quiz->mBeingCorrected) {
							if($checked) {
								$checkedCount++;
								$this->setState("right");
								$cellStyle = " style=\"background: ".$quiz->getColor('right').";\"";
							} else {
								$this->setState("na_wrong");
								$cellStyle = " style=\"background: ".$quiz->getColor('wrong').";\"";
							}
						} 
						break;
					case "-":
						$expected = "-";
						if($quiz->mBeingCorrected) {
							if($checked) {
								$checkedCount++;
								$this->setState("wrong");
								$cellStyle = " style=\"background: ".$quiz->getColor('wrong').";\"";
							} else {
								$this->setState("na_right");
							}
						} 
						break;
					default:
						$expected = "=";
						$this->setState("error");
						$cellStyle = " style=\"background: ".$quiz->getColor('error').";\"";
						break;
					}
					$signesOutput  .= "<td$cellStyle>";
					$signesOutput .= "<input class=\"$expected\" type=\"$inputType\" name=\"$name\" value=\"$value\" $checked/>";
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
				$signesOutput = "<td>&#151;&#155;</td>";
				# Hacks to avoid counting the number of signes.
				$colSpan = " colspan=\"11\"";
			}
			if($text) {				
				$output .= "<tr class=\"$rawClass\">\n";
				$output .= $signesOutput;
				$output .= "<td$colSpan>";				
				$output .= $quiz->mParser->recursiveTagParse($text);
				$output .= "</td>";
				$output .= "</tr>\n";
			}
		}
		# A single choice object with no correct proposal is a syntax error.
		if($typeId == "sn" && $expectOn == 0) {
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
			$output .= "<th>$category</th>";
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
}

/**
 *
 * TODO :
 * 
 * BUG :
 * 
 * Questions : 
 * * Is it better to make $wgRequest member of Quiz or is it better to use global $wgRequest in each function that use it ?
 * * Is it better to make the parser global as well...
 * 
 */
?>
