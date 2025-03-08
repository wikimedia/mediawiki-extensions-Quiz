<?php

namespace MediaWiki\Extension\Quiz;

use Hooks as MWHooks;
use Parser;
use StringUtils;
use TemplateParser;
use Title;
use WebRequest;

/**
 * Processes quiz markup
 */
class Quiz {

	/** @var int */
	private static $sQuizId = 0;

	/** @var Parser */
	private $mParser;

	/** @var WebRequest */
	private $mRequest;

	/** @var int */
	private $mQuizId;

	/** @var int */
	private $mQuestionId;

	/** @var int */
	private $mShuffleDiv;

	/** @var bool */
	private $mBeingCorrected;

	/** @var string */
	private $mState;

	/** @var int */
	private $numberQuestions;

	/** @var int */
	private $mTotal;

	/** @var int */
	private $mScore;

	/** @var int */
	private $mAddedPoints;

	/** @var int */
	private $mCutoffPoints;

	/** @var bool */
	private $mIgnoringCoef;

	/** @var bool */
	private $mDisplaySimple;

	/** @var bool */
	private $shuffleAnswers;

	/** @var bool */
	private $mShuffle;

	/** @var bool */
	private $mCaseSensitive;

	/** @var string */
	private $mIncludePattern;

	/**
	 * @param array $argv
	 * @param Parser $parser
	 */
	public function __construct( $argv, Parser $parser ) {
		global $wgRequest;
		$this->mParser = $parser;
		$this->mRequest = $wgRequest;
		// Allot a unique identifier to the quiz.
		$this->mQuizId = $this->getQuizId();
		self::$sQuizId++;
		// Reset the unique identifier of the questions.
		$this->mQuestionId = 0;
		// Reset the counter of div "shuffle" or "noshuffle" inside the quiz.
		$this->mShuffleDiv = 0;
		// Determine if this quiz is being corrected or not, according to the quizId
		$this->mBeingCorrected = ( $wgRequest->getVal( 'quizId' ) == strval( $this->mQuizId ) );
		// Initialize various parameters used for the score calculation
		$this->mState = 'NA';
		$this->numberQuestions = 0;
		$this->mTotal = $this->mScore = 0;
		$this->mAddedPoints = 1;
		$this->mCutoffPoints = 0;
		$this->mIgnoringCoef = false;
		$this->mDisplaySimple = ( array_key_exists( 'display', $argv ) &&
			$argv['display'] == 'simple' );
		$this->shuffleAnswers = ( array_key_exists( 'shuffleanswers', $argv ) &&
			$argv['shuffleanswers'] == 'true' );

		if ( $this->mBeingCorrected ) {
			$lAddedPoints = str_replace( ',', '.',
				$this->mRequest->getVal( 'addedPoints', '' )
			);
			if ( is_numeric( $lAddedPoints ) ) {
				$this->mAddedPoints = (int)$lAddedPoints;
			}

			$lCutoffPoints = str_replace( ',', '.',
				$this->mRequest->getVal( 'cutoffPoints', '' )
			);
			if ( is_numeric( $lCutoffPoints ) ) {
				$this->mCutoffPoints = (int)$lCutoffPoints;
			}
			if ( $this->mRequest->getVal( 'ignoringCoef' ) == 'on' ) {
				$this->mIgnoringCoef = true;
			}
		}

		if ( array_key_exists( 'points', $argv ) &&
			( !$this->mBeingCorrected || $this->mDisplaySimple ) &&
			preg_match(
				'`([\d\.]*)/?([\d\.]*)(!)?`', str_replace( ',', '.', $argv['points'] ), $matches
			)
		) {
			if ( is_numeric( $matches[1] ) ) {
				$this->mAddedPoints = (int)$matches[1];
			}
			if ( is_numeric( $matches[2] ) ) {
				$this->mCutoffPoints = (int)$matches[2];
			}
			if ( array_key_exists( 3, $matches ) ) {
				$this->mIgnoringCoef = true;
			}
		}
		$this->mShuffle = !( array_key_exists( 'shuffle', $argv ) && $argv['shuffle'] == 'none' );
		$this->mCaseSensitive = !( array_key_exists( 'case', $argv ) && $argv['case'] == '(i)' );

		// Patterns used in several places
		$this->mIncludePattern = '`^\{\{:?(.*)\}\}[ \t]*`m';
	}

	/**
	 * @return bool
	 */
	public static function resetQuizID() {
		self::$sQuizId = 0;
		return true;
	}

	/**
	 * @return int Quiz Id
	 */
	public function getQuizId() {
		return self::$sQuizId;
	}

	/**
	 * Get HTML from template using TemplateParser
	 *
	 * @param TemplateParser $templateParser
	 * @return string
	 */
	public function getSettingsTable( $templateParser ) {
		$checked = $this->mIgnoringCoef ? 'checked="checked"' : '';
		$settingsTable = $templateParser->processTemplate(
			'Setting',
			[
				'isSettingFirstRow' => ( !$this->mDisplaySimple || $this->mBeingCorrected ||
					$this->mState === 'error' ),
				'isSettingOtherRow' => ( !$this->mDisplaySimple || $this->mBeingCorrected ),
				'notSimple' => !$this->mDisplaySimple,
				/** @phan-suppress-next-line PhanPluginDuplicateExpressionBinaryOp FIXME */
				'corrected' => ( $this->mBeingCorrected && $this->mBeingCorrected ),
				'shuffle' => $this->mShuffle,
				'shuffleOrError' => ( $this->mShuffle && $this->numberQuestions > 1 ) ||
					$this->mState === 'error',
				'error' => $this->mState === 'error',
				'wfMessage' => [
					'quiz_added' => wfMessage( 'quiz_addedPoints', $this->mAddedPoints )->text(),
					'quiz_cutoff' => wfMessage( 'quiz_cutoffPoints', $this->mCutoffPoints )->text(),
					'quiz_ignoreCoef' => wfMessage( 'quiz_ignoreCoef' )->text(),
					'quiz_legend_correct' => wfMessage( 'quiz_legend_correct' )->text(),
					'quiz_legend_incorrect' => wfMessage( 'quiz_legend_incorrect' )->text(),
					'quiz_legend_unanswered' => wfMessage( 'quiz_legend_unanswered' )->text(),
					'quiz_legend_error' => wfMessage( 'quiz_legend_error' )->text(),
					'quiz_shuffle' => wfMessage( 'quiz_shuffle' )->text()
				],
				'mAddedPoints' => $this->mAddedPoints,
				'mCutoffPoints' => $this->mCutoffPoints,
				'checked' => $checked,
				'shuffleDisplay' => $this->numberQuestions > 1
			]
		);
		return $settingsTable;
	}

	/**
	 * Convert the input text to an HTML output.
	 *
	 * @param string|null $input text between <quiz> and </quiz> tags, in quiz syntax.
	 * @return string
	 */
	public function parseQuiz( $input ) {
		// Output the style and the script to the header once for all.
		if ( $this->mQuizId == 0 ) {
			$this->mParser->getOutput()->addModules( [ 'ext.quiz' ] );
			$this->mParser->getOutput()->addModuleStyles( [ 'ext.quiz.styles' ] );
		}

		if ( $input === null ) {
			return '';
		}

		// Process the input
		$input = $this->parseQuestions( $this->parseIncludes( $input ) );

		// Generates the output.
		$templateParser = new TemplateParser( __DIR__ . '/../templates' );
		// Determine the content of the settings table.
		$settingsTable = $this->getSettingsTable( $templateParser );

		$quiz_score = wfMessage( 'quiz_score' )->rawParams(
			'<span class="score">' . $this->mScore . '</span>',
			'<span class="total">' . $this->mTotal . '</span>' )->escaped();

		return $templateParser->processTemplate(
			'Quiz',
			[
				'quiz' => [
					'id' => $this->mQuizId,
					'beingCorrected' => $this->mBeingCorrected,
					'questions' => $input
				],
				'settingsTable' => $settingsTable,
				'wfMessage' => [
					'quiz_correction' => wfMessage( 'quiz_correction' )->escaped(),
					'quiz_reset' => wfMessage( 'quiz_reset' )->escaped(),
					'quiz_score' => $quiz_score
				]
			]
		);
	}

	/**
	 * Replace inclusions from other quizzes.
	 *
	 * @param string $input text between <quiz> and </quiz> tags, in quiz syntax.
	 * @return string
	 */
	private function parseIncludes( $input ) {
		return preg_replace_callback(
			$this->mIncludePattern,
			[ $this, 'parseInclude' ],
			$input
		);
	}

	/**
	 * Include text between <quiz> and <quiz> from another page to this quiz.
	 *
	 * @param array $matches elements matching $includePattern
	 * 							$matches[1] is the page title.
	 * @return mixed|string
	 */
	private function parseInclude( $matches ) {
		$title = Title::makeTitleSafe( NS_MAIN, $matches[1] );
		if ( $title === null ) {
			// Not a valid title for this include; replace w/ empty string.
			return '';
		}
		$text = $this->mParser->fetchTemplateAndTitle( $title )[0];
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
	 * @param string $input a question in quiz syntax.
	 * @return string
	 */
	private function parseQuestions( $input ) {
		$splitPattern = '`(^|\n[ \t]*)\n\{`';
		$unparsedQuestions = preg_split(
			$splitPattern,
			$input,
			-1,
			PREG_SPLIT_NO_EMPTY
		);

		$output = '';
		$questionPattern = '`(.*?[^|\}])\}[ \t]*(\n(.*)|$)`s';
		$this->numberQuestions = count( $unparsedQuestions );
		$numDisplay = $this->numberQuestions > 1;
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
	 * @param array $matches elements matching $questionPattern
	 * 						$matches[1] is the question header.
	 * 						$matches[3] is the question object.
	 * @param bool $numDisplay specifies whether to display question number.
	 * @return string
	 */
	public function parseQuestion( $matches, $numDisplay ) {
		$question = new Question(
			$this->mBeingCorrected,
			$this->mCaseSensitive,
			$this->mQuestionId,
			$this->shuffleAnswers,
			$this->mParser
		);
		MWHooks::run( 'QuizQuestionCreated', [ $this, &$question ] );

		// gets the question text
		$questionText = $question->parseHeader( $matches[1] );

		/*
			What is this block of code?
			The only place X !X and /X are spoken about is here
			https://en.wikiversity.org/wiki/Help:Quiz
			"A few exotic features are not yet covered,
			such as shuffle control using {X} {!X} {/X} tags."
			These were added in commit fb53a3b0 back in 2007,
			without any explanation and/or documentation. The commit message is actually unrelated.
		*/
		if ( !array_key_exists( 3, $matches ) || trim( $matches[3] ) == '' ) {
			switch ( $matches[1] ) {
				case 'X':
					$this->mShuffleDiv++;
					return '<div class="shuffle">' . "\n";
				case '!X':
					$this->mShuffleDiv++;
					return '<div class="noshuffle">' . "\n";
				case '/X':
					// Prevent closing of other tags.
					if ( $this->mShuffleDiv == 0 ) {
						return '';
					} else {
						$this->mShuffleDiv--;
						return '</div>' . "\n";
					}
				default:
					return '<div class="quizText">' . $questionText . '<br /></div>' . "\n";
			}
		}

		$templateParser = new TemplateParser( __DIR__ . '/../templates' );

		$this->mQuestionId++;

		// This will generate the answers HTML code
		$answers = call_user_func(
			// Calling singleChoiceParseObject, multipleChoiceParseObject and textFieldParseObject
			[ $question, $question->mType . 'ParseObject' ],
			$matches[3]
		);

		// Set default table title and style

		$tableTitle = "";

		$lState = $question->getState(); // correct, incorrect or unanswered?

		if ( $lState != '' ) {
			// if the question is of type=simple
			if ( $this->mIgnoringCoef ) {
				$question->mCoef = 1;
			}
			switch ( $lState ) {
				case 'correct':
					$this->mTotal += $this->mAddedPoints * $question->mCoef;
					$this->mScore += $this->mAddedPoints * $question->mCoef;

					$tableTitle = wfMessage(
						'quiz_points',
						wfMessage( 'quiz_legend_correct' )->text(),
						$this->mAddedPoints * $question->mCoef
					)->escaped();
					break;

				case 'incorrect':
					$this->mTotal += $this->mAddedPoints * $question->mCoef;
					$this->mScore -= $this->mCutoffPoints * $question->mCoef;

					$tableTitle = wfMessage(
						'quiz_points',
						wfMessage( 'quiz_legend_incorrect' )->text(),
						-$this->mCutoffPoints * $question->mCoef
					)->escaped();
					break;

				case 'NA':
					$this->mTotal += $this->mAddedPoints * $question->mCoef;

					$tableTitle = wfMessage(
						'quiz_points',
						wfMessage( 'quiz_legend_unanswered' )->text(),
						0
					)->escaped();
					break;

				case 'error':
					$this->mState = 'error';
					break;
			}
		}

		$stateObject = [
			'state' => $lState,
			'tableTitle' => $tableTitle
		];

		return $templateParser->processTemplate(
			'Question',
			[
				'question' => [
					'id' => $this->mQuestionId,
					'numdis' => $numDisplay,
					'text' => $questionText,
					'answers' => $answers
				],
				'state' => $stateObject
			]
		);
	}
}
