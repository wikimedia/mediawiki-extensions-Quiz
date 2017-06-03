<?php
/**
 * Processes quiz markup
 */
class Quiz {
	// Quiz colors
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
		// Allot a unique identifier to the quiz.
		$this->mQuizId = self::$sQuizId;
		self::$sQuizId++;
		// Reset the unique identifier of the questions.
		$this->mQuestionId = 0;
		// Reset the counter of div "shuffle" or "noshuffle" inside the quiz.
		$this->mShuffleDiv = 0;
		// Determine if this quiz is being corrected or not, according to the quizId
		$this->mBeingCorrected = ( $wgRequest->getVal( 'quizId' ) == strval( $this->mQuizId ) );
		// Initialize various parameters used for the score calculation
		$this->mState = 'NA';
		$this->mTotal = $this->mScore = 0;
		$this->mAddedPoints = 1;
		$this->mCutoffPoints = 0;
		$this->mIgnoringCoef = false;
		$this->mDisplaySimple = ( array_key_exists( 'display', $argv ) && $argv['display'] == 'simple' );

		if ( $this->mBeingCorrected ) {
			$lAddedPoints = str_replace( ',', '.',
				$this->mRequest->getVal( 'addedPoints' )
			);
			if ( is_numeric( $lAddedPoints ) ) {
				$this->mAddedPoints = $lAddedPoints;
			}

			$lCutoffPoints = str_replace( ',', '.',
				$this->mRequest->getVal( 'cutoffPoints' )
			);
			if ( is_numeric( $lCutoffPoints ) ) {
				$this->mCutoffPoints = $lCutoffPoints;
			}
			if ( $this->mRequest->getVal( 'ignoringCoef' ) == 'on' ) {
				$this->mIgnoringCoef = true;
			}
		}

		if ( array_key_exists( 'points', $argv ) &&
			( !$this->mBeingCorrected || $this->mDisplaySimple ) &&
			preg_match( '`([\d\.]*)/?([\d\.]*)(!)?`', str_replace( ',', '.', $argv['points'] ), $matches )
		) {
			if ( is_numeric( $matches[1] ) ) {
				$this->mAddedPoints = $matches[1];
			}
			if ( is_numeric( $matches[2] ) ) {
				$this->mCutoffPoints = $matches[2];
			}
			if ( array_key_exists( 3, $matches ) ) {
				$this->mIgnoringCoef = true;
			}
		}
		$this->mShuffle = !( array_key_exists( 'shuffle', $argv ) && $argv['shuffle'] == 'none' );
		$this->mCaseSensitive = !( array_key_exists( 'case', $argv ) &&	$argv['case'] == '(i)' );

		// Patterns used in several places
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
		if ( array_key_exists( $colorId, self::$mColors ) ) {
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

		// Ouput the style and the script to the header once for all.
		if ( $this->mQuizId == 0 ) {
			global $wgOut;
			$wgOut->addModules( 'ext.quiz' );
		}

		// Process the input
		$input = $this->parseQuestions( $this->parseIncludes( $input ) );

		// Generates the output.

		$templateParser = new TemplateParser( __DIR__ . '/templates' );

		// Determine the content of the settings table.
		$settings = array_fill( 0, 4, '' );
		if ( !$this->mDisplaySimple ) {
			$settings[0] .= '<td>' . wfMessage( 'quiz_addedPoints', $this->mAddedPoints )->escaped() . '</td>' . "\n";
			$settings[0] .= '<td><input class="numerical" type="text" name="addedPoints" value="' . $this->mAddedPoints .'"/>&#160;&#160;</td>' . "\n";

			$settings[1] .= '<td>' . wfMessage( 'quiz_cutoffPoints', $this->mCutoffPoints )->escaped() . '</td>' . "\n";
			$settings[1] .= '<td><input class="numerical" type="text" name="cutoffPoints" value="' . $this->mCutoffPoints . '"/></td>' . "\n";

			$checked = ( $this->mIgnoringCoef ) ? ' checked="checked"' : '';
			$settings[2] .= '<td>' . wfMessage( 'quiz_ignoreCoef' )->escaped() . '</td>' . "\n";
			$settings[2] .= '<td><input type="checkbox" name="ignoringCoef"' . $checked . '/></td>' . "\n";

			if ( $this->mShuffle && !$this->mBeingCorrected ) {
				$settings[3] .= '<td><input class="shuffle" name="shuffleButton" type="button" value="' . wfMessage( 'quiz_shuffle' )->escaped() . '" style="display: none;"/></td>' . "\n";
			} else {
				$settings[3] .= '<td></td>' . "\n";
			}
			$settings[3] .= '<td></td>' . "\n";
		}
		if ( $this->mBeingCorrected ) {
			$settings[0] .= '<td class="margin" style="background: ' . self::getColor( 'right' ) . '"></td>' . "\n";
			$settings[0] .= '<td style="background: transparent;">' . wfMessage( 'quiz_colorRight' )->escaped() . '</td>' . "\n";

			$settings[1] .= '<td class="margin" style="background: ' . self::getColor( 'wrong' ) . '"></td>' . "\n";
			$settings[1] .= '<td style="background: transparent;">' . wfMessage( 'quiz_colorWrong' )->escaped() . '</td>' . "\n";

			$settings[2] .= '<td class="margin" style="background: ' . self::getColor( 'NA' ) . '"></td>' . "\n";
			$settings[2] .= '<td style="background: transparent;">' . wfMessage( 'quiz_colorNA' )->escaped() . '</td>' . "\n";
		}
		if ( $this->mState === 'error' ) {
			$errorKey = $this->mBeingCorrected ? 3 : 0;
			$settings[$errorKey] .= '<td class="margin" style="background: ' . self::getColor( 'error' ) . '"></td>' . "\n";
			$settings[$errorKey] .= '<td>' . wfMessage( 'quiz_colorError' )->escaped() . '</td>' . "\n";
		}

		// Build the settings table.
		$settingsTable = '';
		foreach ( $settings as $settingsTr ) {
			if ( !empty( $settingsTr ) ) {
				$settingsTable .= '<tr>' . "\n";
				$settingsTable .= $settingsTr . "\n";
				$settingsTable .= '</tr>' . "\n";
			}
		}

		$quiz_score = wfMessage( 'quiz_score' )->rawParams(
			'<span class="score">' . $this->mScore . '</span>',
			'<span class="total">' . $this->mTotal . '</span>' )->escaped();

		return $templateParser->processTemplate(
			'Quiz',
			array(
				'quiz' => array(
					'id' => $this->mQuizId,
					'beingCorrected' => $this->mBeingCorrected,
					'questions' => $input
				),
				'settingsTable' => $settingsTable,
				'wfMessage' => array(
					'quiz_correction' => wfMessage( 'quiz_correction' )->escaped(),
					'quiz_reset' => wfMessage( 'quiz_reset' )->escaped(),
					'quiz_score' => $quiz_score
				)
			)
		);

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
		if ( preg_match( '`<quiz[^>]*>(.*?)</quiz>`sU', $text, $unparsedQuiz ) ) {
			// Remove inclusions from included quiz.
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
		$numQuestion = count( $unparsedQuestions );
		$numDisplay = $numQuestion > 1;
		foreach ( $unparsedQuestions as $unparsedQuestion ) {
			// If this "unparsedQuestion" is not a full question,
			// we put the text into a buffer to add it at the beginning of the next question.
			if ( !empty( $buffer ) ) {
				$unparsedQuestion = $buffer . "\n\n" . '{' . $unparsedQuestion;
			}

			if ( preg_match( $questionPattern, $unparsedQuestion, $matches ) ) {
				$buffer = '';
				$output .= $this->parseQuestion( $matches, $numDisplay );
			} else {
				$buffer = $unparsedQuestion;
			}
		}

		// Close unclosed "shuffle" or "noshuffle" tags.
		while ( $this->mShuffleDiv > 0 ) {
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
	 * @param $numDisplay Boolean: specifies whether to display question number.
	 * @return string
	 */
	function parseQuestion( $matches, $numDisplay ) {
		$question = new Question(
			$this->mBeingCorrected,
			$this->mCaseSensitive,
			$this->mQuestionId,
			$this->mParser
		);
		Hooks::run( 'QuizQuestionCreated', array( $this, &$question ) );

		// gets the question text
		$questionText = $question->parseHeader( $matches[1] );

		/*
			What is this block of code?
			The only place X !X and /X are spoken about is here
			https://en.wikiversity.org/wiki/Help:Quiz
			"A few exotic features are not yet covered, such as shuffle control using {X} {!X} {/X} tags."
			These were added in commit fb53a3b0 back in 2007, without any explanation and/or documentation. The commit message is actually unrelated.
		*/
		if ( !array_key_exists( 3, $matches ) || trim( $matches[3] ) == '' ) {
			switch ( $matches[1] ) {
				case 'X':
					$this->mShuffleDiv++;
					return '<div class="shuffle">' . "\n";
					break;
				case '!X':
					$this->mShuffleDiv++;
					return '<div class="noshuffle">' . "\n";
					break;
				case '/X':
					// Prevent closing of other tags.
					if ( $this->mShuffleDiv == 0 ) {
						return '';
					} else {
						$this->mShuffleDiv--;
						return '</div>' . "\n";
					}
					break;
				default:
					return '<div class="quizText">' . $questionText . '<br /></div>' . "\n";
					break;
			}
		}

		$templateParser = new TemplateParser( __DIR__ . '/templates' );

		$this->mQuestionId++;

		// This will generate the answers HTML code
		$answers = call_user_func(
			array( $question, $question->mType . 'ParseObject' ),
			$matches[3]
		);

		// Set default table title and style

		$tableTitle = "";
		$tableStyle = "";

		$lState = $question->getState(); // right wrong or unanswered?

		if ( $lState != '' ) {
			// TODO: convert to CSS classes
			global $wgContLang;
			$border = $wgContLang->isRTL() ? 'border-right' : 'border-left';
			$tableStyle = $border . ': 3px solid ' . self::getColor( $lState ) . ';';

			// if the question is of type=simple
			if ( $this->mIgnoringCoef ) {
				$question->mCoef = 1;
			}
			switch ( $lState ) {
				case 'right':
					$this->mTotal += $this->mAddedPoints * $question->mCoef;
					$this->mScore += $this->mAddedPoints * $question->mCoef;

					$tableTitle = wfMessage(
						'quiz_points',
						wfMessage( 'quiz_colorRight' )->text(),
						$this->mAddedPoints * $question->mCoef
					)->escaped();
					break;

				case 'wrong':
					$this->mTotal += $this->mAddedPoints * $question->mCoef;
					$this->mScore -= $this->mCutoffPoints * $question->mCoef;

					$tableTitle = wfMessage(
						'quiz_points',
						wfMessage( 'quiz_colorWrong' )->text(),
						-$this->mCutoffPoints * $question->mCoef
					)->escaped();
					break;

				case 'NA':
					$this->mTotal += $this->mAddedPoints * $question->mCoef;

					$tableTitle = wfMessage(
						'quiz_points',
						wfMessage( 'quiz_colorNA' )->text(),
						0
					)->escaped();
					break;

				case 'error':
					$this->mState = 'error';
					break;
			}
		}

		$stateObject = array(
			'tableStyle' => $tableStyle,
			'tableTitle' => $tableTitle
		);

		return $templateParser->processTemplate(
			'Question',
			array(
				'question' => array(
					'id' => $this->mQuestionId,
					'numdis' => $numDisplay,
					'text' => $questionText,
					'answers' => $answers
				),
				'state' => $stateObject
			)
		);
	}
}
