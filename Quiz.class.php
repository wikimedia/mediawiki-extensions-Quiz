<?php
/**
 * Processes quiz markup
 */
class Quiz {
	# Quiz colors
	static $mColors = array(
		'right' 		=> '#1FF72D',
		'wrong' 		=> '#F74245',
		'correction' 	=> '#F9F9F9',
		'NA' 			=> '#2834FF',
		'error' 		=> '#D700D7'
	);
	static $sQuizId = 0;

	protected $mScore;

	/**
	 * Constructor
	 *
	 * @param $argv array
	 * @param $parser Parser
	 */
	public function __construct( $argv, &$parser ) {
		global $wgRequest;
		$this->mParser = $parser;
		$this->mRequest = &$wgRequest;
		# Allot a unique identifier to the quiz.
		$this->mQuizId = self::$sQuizId;
		self::$sQuizId++;
		# Reset the unique identifier of the questions.
		$this->mQuestionId = 0;
		# Reset the counter of div "shuffle" or "noshuffle" inside the quiz.
		$this->mShuffleDiv = 0;
		# Determine if this quiz is being corrected or not, according to the quizId
		$this->mBeingCorrected = ( $wgRequest->getVal( 'quizId' ) == "$this->mQuizId" )? true : false;
		# Initialize various parameters used for the score calculation
		$this->mState = 'NA';
		$this->mTotal = $this->mScore = 0;
		$this->mAddedPoints = 1;
		$this->mCutoffPoints = 0;
		$this->mIgnoringCoef = false;
		$this->mDisplaySimple = ( array_key_exists( 'display', $argv ) && $argv['display'] == 'simple' ) ? true : false;
		if( $this->mBeingCorrected ) {
			$lAddedPoints = str_replace( ',', '.', $this->mRequest->getVal( 'addedPoints' ) );
			if( is_numeric( $lAddedPoints ) ) {
				$this->mAddedPoints = $lAddedPoints;
			}
			$lCutoffPoints = str_replace( ',', '.', $this->mRequest->getVal( 'cutoffPoints' ) );
			if( is_numeric( $lCutoffPoints ) ) {
				$this->mCutoffPoints = $lCutoffPoints;
			}
			if( $this->mRequest->getVal( 'ignoringCoef' ) == 'on' ) {
				$this->mIgnoringCoef = true;
			}
		}
		if (
			array_key_exists( 'points', $argv ) &&
			( !$this->mBeingCorrected || $this->mDisplaySimple ) &&
			preg_match( '`([\d\.]*)/?([\d\.]*)(!)?`', str_replace( ',', '.', $argv['points'] ), $matches )
		)
		{
			if( is_numeric( $matches[1] ) ) {
				$this->mAddedPoints = $matches[1];
			}
			if( is_numeric( $matches[2] ) ) {
				$this->mCutoffPoints = $matches[2];
			}
			if( array_key_exists( 3, $matches ) ) {
				$this->mIgnoringCoef = true;
			}
		}
		$this->mShuffle = ( array_key_exists( 'shuffle', $argv ) && $argv['shuffle'] == 'none' ) ? false : true;
		$this->mCaseSensitive = ( array_key_exists( 'case', $argv ) && $argv['case'] == '(i)' ) ? false : true;
		# Patterns used in several places
		$this->mIncludePattern = '`^\{\{:?(.*)\}\}[ \t]*`m';
	}

	/**
	 * @return bool
	 */
	static function resetQuizID() {
		self::$sQuizId = 0;
		return true;
	}

	/**
	 * Accessor for the color array
	 * Displays an error message if the colorId doesn't exists.
	 *
	 * @param $colorId Integer: color hex code
	 * @return string
	 * @throws Exception
	 */
	public static function getColor( $colorId ) {
		if( array_key_exists( $colorId, self::$mColors ) ) {
			return self::$mColors[$colorId];
		}

		throw new Exception( 'Invalid color ID: ' . $colorId );
	}

	/**
	 * Convert the input text to an HTML output.
	 *
	 * @param $input String: text between <quiz> and </quiz> tags, in quiz syntax.
	 * @return string
	 */
	function parseQuiz( $input ) {
		# Ouput the style and the script to the header once for all.
		if( $this->mQuizId == 0 ) {
			global $wgOut;

			$wgOut->addModules( 'ext.quiz' );
		}

		# Process the input
		$input = $this->parseQuestions( $this->parseIncludes( $input ) );

		# Generates the output.
		$classHide = ( $this->mBeingCorrected ) ? '' : ' class="hideCorrection"';
		$output  = '<div class="quiz">';
		$output .= "<form id=\"quiz$this->mQuizId\" $classHide method=\"post\" action=\"#quiz$this->mQuizId\">\n";
		# Determine the content of the settings table.
		$settings = array_fill( 0, 4, '' );
		if( !$this->mDisplaySimple ) {
			$settings[0] .=	'<td>' . wfMessage( 'quiz_addedPoints', $this->mAddedPoints )->escaped() . '</td>' .
							"<td><input class=\"numerical\" type=\"text\" name=\"addedPoints\" value=\"$this->mAddedPoints\"/>&#160;&#160;</td>";
			$settings[1] .=	'<td>' . wfMessage( 'quiz_cutoffPoints', $this->mCutoffPoints )->escaped() . '</td>' .
							"<td><input class=\"numerical\" type=\"text\" name=\"cutoffPoints\" value=\"$this->mCutoffPoints\"/></td>";
			$bChecked = ( $this->mIgnoringCoef ) ? ' checked="checked"' : '';
			$settings[2] .=	'<td>' . wfMessage( 'quiz_ignoreCoef' )->escaped() . '</td>' .
							"<td><input type=\"checkbox\" name=\"ignoringCoef\"$bChecked/></td>";
			if( $this->mShuffle && !$this->mBeingCorrected ) {
				$settings[3] .=	'<td><input class="shuffle" name="shuffleButton" type="button" value="' . wfMessage( 'quiz_shuffle' )->escaped() . '" style="display: none;"/></td>' .
								'<td></td>';
			} else {
				$settings[3] .=	'<td></td><td></td>';
			}
		}
		if( $this->mBeingCorrected ) {
			$settings[0] .= '<td class="margin" style="background: ' . self::getColor( 'right' ) . '"></td>' .
							'<td style="background: transparent;">' . wfMessage( 'quiz_colorRight' )->escaped() . '</td>';
			$settings[1] .= '<td class="margin" style="background: ' . self::getColor( 'wrong' ) . '"></td>' .
							'<td style="background: transparent;">' . wfMessage( 'quiz_colorWrong' )->escaped() . '</td>';
			$settings[2] .= '<td class="margin" style="background: ' . self::getColor( 'NA' ) . '"></td>' .
							'<td style="background: transparent;">' . wfMessage( 'quiz_colorNA' )->escaped() . '</td>';
		}
		if( $this->mState == 'error' ) {
			$errorKey = $this->mBeingCorrected ? 3 : 0;
			$settings[$errorKey] .=	'<td class="margin" style=\"background: ' . self::getColor( 'error' ) . '"></td>' .
									'<td>' . wfMessage( 'quiz_colorError' )->escaped() . '</td>';
		}
		# Build the settings table.
		$settingsTable = '';
		foreach( $settings as $settingsTr ) {
			if( !empty( $settingsTr ) ) {
				$settingsTable .= "<tr>\n$settingsTr</tr>\n";
			}
		}
		if( !empty( $settingsTable ) ) {
			$output .= "<table class=\"settings\">\n$settingsTable</table>\n";
		}
		$output .= "<input type=\"hidden\" name=\"quizId\" value=\"$this->mQuizId\" />";

		$output .= '<div class="quizQuestions">';
		$output .= $input;
		$output .= '</div>';

		$output .= '<p><input type="submit" value="' . wfMessage( 'quiz_correction' )->escaped() . '"/>';
		if( $this->mBeingCorrected ) {
			$output .= '<input class="reset" type="submit" value="' .
				wfMessage( 'quiz_reset' )->escaped() . '" style="display: none;" />';
		}
		$output .= '</p>';
		$output .= '<span class="correction">';
		$output .= wfMessage( 'quiz_score' )->rawParams(
			"<span class=\"score\">$this->mScore</span>",
			"<span class=\"total\">$this->mTotal</span>"
		)->escaped();
		$output .= '</span>';
		$output .= "</form>\n";
		$output .= "</div>\n";
		return $output;
	}

	/**
	 * Replace inclusions from other quizzes.
	 *
	 * @param $input String: text between <quiz> and </quiz> tags, in quiz syntax.
	 * @return string
	 */
	function parseIncludes( $input ) {
		return preg_replace_callback(
			$this->mIncludePattern,
			array( $this, 'parseInclude' ),
			$input
		);
	}

	/**
	 * Include text between <quiz> and <quiz> from another page to this quiz.
	 *
	 * @param $matches array: elements matching $includePattern.
	 * 							$matches[1] is the page title.
	 * @return mixed|string
	 */
	function parseInclude( $matches ) {
		$title = Title::makeTitleSafe( NS_MAIN, $matches[1] );
		$text = $this->mParser->fetchTemplate( $title );
		$output = '';
		if( preg_match( '`<quiz[^>]*>(.*?)</quiz>`sU', $text, $unparsedQuiz ) ) {
			# Remove inclusions from included quiz.
			$output = preg_replace(
				$this->mIncludePattern,
				'',
				StringUtils::escapeRegexReplacement( $unparsedQuiz[1] )
			);
			$output .= "\n";
		}
		return $output;
	}

	/**
	 * Replace questions from quiz syntax to HTML.
	 *
	 * @param $input String: a question in quiz syntax.
	 * @return string
	 */
	function parseQuestions( $input ) {
		$splitPattern = '`(^|\n[ \t]*)\n\{`';
		$unparsedQuestions = preg_split(
			$splitPattern,
			$input,
			-1,
			PREG_SPLIT_NO_EMPTY
		);
		$output = '';
		$questionPattern = '`(.*?[^|\}])\}[ \t]*(\n(.*)|$)`s';
		foreach( $unparsedQuestions as $unparsedQuestion ) {
			# If this "unparsedQuestion" is not a full question,
			# we put the text into a buffer to add it at the beginning of the next question.
			if( !empty( $buffer ) ) {
				$unparsedQuestion = "$buffer\n\n{" . $unparsedQuestion;
			}
			if( preg_match( $questionPattern, $unparsedQuestion, $matches ) ) {
				$buffer = '';
				$output .= $this->parseQuestion( $matches );
			} else {
				$buffer = $unparsedQuestion;
			}
		}
		# Close unclosed "shuffle" or "noshuffle" tags.
		while( $this->mShuffleDiv > 0 ) {
			$output .= '</div>';
			$this->mShuffleDiv--;
		}
		return $output;
	}

	/**
	 * Convert a question from quiz syntax to HTML
	 *
	 * @param $matches array: elements matching $questionPattern.
	 * 						$matches[1] is the question header.
	 * 						$matches[3] is the question object.
	 * @return string
	 */
	function parseQuestion( $matches ) {
		$question = new Question(
			$this->mBeingCorrected,
			$this->mCaseSensitive,
			$this->mQuestionId,
			$this->mParser
		);
		Hooks::run( 'QuizQuestionCreated', array( $this, &$question ) );
		$buffer = $question->parseHeader( $matches[1] );
		if( !array_key_exists( 3, $matches ) || trim( $matches[3] ) == '' ) {
			switch( $matches[1] ) {
				case 'X':
					$this->mShuffleDiv++;
					return "<div class=\"shuffle\">\n";
					break;
				case '!X':
					$this->mShuffleDiv++;
					return "<div class=\"noshuffle\">\n";
					break;
				case '/X':
					# Prevent closing of other tags.
					if( $this->mShuffleDiv == 0 ) {
						return '';
					} else {
						$this->mShuffleDiv--;
						return "</div>\n";
					}
					break;
				default:
					return "<div class=\"quizText\">$buffer<br /></div>";
					break;
			}
		}
		$output  = "<div class=\"question\">\n";
		$output .= "<div class=\"header\">\n";
		$output .= '<span class="questionId">' . ++$this->mQuestionId . ". </span>$buffer";
		$output .= "</div>\n";
		# Store the parsed object into a buffer to determine some parameters before outputing it.
		$buffer = call_user_func( array( $question, "{$question->mType}ParseObject" ), $matches[3] );
 		$output .= '<table class="object" ';
		$lState = $question->getState();
		# Determine the side border color, title, score and the total of the question.
		if( $lState != '' ) {
			global $wgContLang;
			$border = $wgContLang->isRTL() ? 'border-right' : 'border-left';
			$output .= "style=\"$border:3px solid " . self::getColor( $lState ) . '"';
			if( $this->mIgnoringCoef ) {
				$question->mCoef = 1;
			}
			switch( $lState ) {
				case 'right':
					$this->mTotal += $this->mAddedPoints * $question->mCoef;
					$this->mScore += $this->mAddedPoints * $question->mCoef;
					$output .= 'title="' . wfMessage(
						'quiz_points',
						wfMessage( 'quiz_colorRight' )->text(),
						$this->mAddedPoints * $question->mCoef
					)->escaped() . '"';
					break;
				case 'wrong':
					$this->mTotal += $this->mAddedPoints * $question->mCoef;
					$this->mScore -= $this->mCutoffPoints * $question->mCoef;
					$output .= 'title="' . wfMessage(
						'quiz_points',
						wfMessage( 'quiz_colorWrong' )->text(),
						-$this->mCutoffPoints * $question->mCoef
					)->escaped() . '"';
					break;
				case 'NA':
					$this->mTotal += $this->mAddedPoints * $question->mCoef;
					$output .= 'title="' . wfMessage(
						'quiz_points',
						wfMessage( 'quiz_colorNA' )->text(), 0
					)->escaped() . '"';
					break;
				case 'error':
					$this->mState = 'error';
					break;
			}
		}
		$output .= "><tbody>\n";
		$output .= $buffer;
		$output .= "</tbody></table>\n";
		$output .= "<br /></div>\n";
		return $output;
	}
}
