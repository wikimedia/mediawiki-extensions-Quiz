<?php

namespace MediaWiki\Extension\Quiz;

use Exception;
use MediaWiki\Html\TemplateParser;
use MediaWiki\Request\WebRequest;
use Parser;
use Xml;

class Question {

	/** @var WebRequest */
	private $mRequest;

	/** @var int */
	private $mQuestionId;

	/** @var bool */
	private $mBeingCorrected;

	/** @var bool */
	private $mCaseSensitive;

	/** @var bool */
	private $shuffleAnswers;

	/** @var Parser */
	private $mParser;

	/** @var string */
	private $mState;

	/** @var string */
	private $mProposalPattern = '`^([+-]) ?(.*)`';

	/** @var string */
	private $mCorrectionPattern = '`^\|\|(.*)`';

	/** @var string */
	private $mCategoryPattern = '`^\|(\n|[^\|].*\n)`';

	/** @var string */
	private $mTextFieldPattern = '`\{ ([^\}]*?)(_([\d]*) ?| )\}`';

	/** @var string */
	public $mType = 'multipleChoice';

	/** @var int */
	public $mCoef = 1;

	/**
	 * @param bool $beingCorrected Identifier for quiz being corrected.
	 * @param bool $caseSensitive Identifier for case sensitive inputs.
	 * @param int $questionId the Identifier of the question used to generate input names.
	 * @param bool $shufAns Identifier if answers are supposed to be shuffled.
	 * @param Parser $parser Parser the wikitext parser.
	 */
	public function __construct( $beingCorrected, $caseSensitive, $questionId, $shufAns, $parser ) {
		global $wgRequest;
		$this->mRequest = $wgRequest;
		$this->mQuestionId = $questionId;
		$this->mBeingCorrected = $beingCorrected;
		$this->mCaseSensitive = $caseSensitive;
		$this->shuffleAnswers = $shufAns;
		$this->mParser = $parser;
		$this->mState = ( $beingCorrected ) ? 'NA' : '';
	}

	/**
	 * Mutator of the question state
	 *
	 * @param string $pState
	 */
	private function setState( $pState ) {
		if ( $pState == 'error' || ( $pState == 'incorrect' && $this->mState != 'error' ) ||
			( $pState == 'correct' && ( $this->mState == 'NA' || $this->mState == 'na_correct' ) ) ||
			( $pState == 'na_incorrect' && ( $this->mState == 'NA' || $this->mState == 'na_correct' ) ) ||
			( $pState == 'na_correct' && ( $this->mState == 'NA' ) ) ||
			( $pState == 'new_NA' && ( $this->mState == 'NA' || $this->mState == 'correct' ) )
		) {
			$this->mState = $pState;
		}

		// Special cases
		if ( ( $pState == 'na_incorrect' && $this->mState == 'correct' ) ||
			( $pState == 'correct' && $this->mState == 'na_incorrect' )
		) {
			$this->mState = 'incorrect';
		}
	}

	/**
	 * Accessor of the question state.
	 *
	 * @return string
	 */
	public function getState() {
		if ( $this->mState == 'na_correct' ) {
			return 'correct';
		} elseif ( $this->mState == 'na_incorrect' || $this->mState == 'new_NA' ) {
			return 'NA';
		} else {
			return $this->mState;
		}
	}

	/**
	 * Convert the question's header into HTML.
	 *
	 * @param string $input the quiz header in quiz syntax.
	 * @return string
	 */
	public function parseHeader( $input ) {
		$parametersPattern = '`\n\|([^\|].*)\s*$`';
		$input = preg_replace_callback(
			$parametersPattern,
			[ $this, 'parseParameters' ],
			$input
		);
		$splitHeaderPattern = '`\n\|\|`';
		$unparsedHeader = preg_split( $splitHeaderPattern, $input );
		$output = $this->mParser->recursiveTagParse( trim( $unparsedHeader[0] ) );
		if ( array_key_exists( 1, $unparsedHeader ) && $this->mBeingCorrected ) {
			$output .= "\n";
			$output .= '<table class="correction"><tr>';
			$output .= '<td>&#x2192;</td><td>';
			$output .= $this->mParser->recursiveTagParse( trim( $unparsedHeader[1] ) );
			$output .= '</td>';
			$output .= '</tr></table>';
		}
		return $output;
	}

	/**
	 * Determine the question's parameters.
	 *
	 * @param array $matches elements matching $parametersPattern
	 * 						$matches[0] are the potential question parameters.
	 */
	private function parseParameters( $matches ) {
		$typePattern = '`t[yi]p[eo]?="(.*?)"`';
		if ( preg_match( $typePattern, $matches[1], $type ) ) {
			// List of all object type code and the correspondant question type.
			switch ( $type[1] ) {
				case '{}':
					$this->mType = 'textField';
					break;
				case '()':
					$this->mType = 'singleChoice';
					break;
				case '[]':
					$this->mType = 'multipleChoice';
					break;
			}
		}
		$coefPattern = '`[ck]oef="(.*?)"`';
		if ( preg_match( $coefPattern, $matches[1], $coef ) &&
			is_numeric( $coef[1] ) && $coef[1] > 0
		) {
			$this->mCoef = (int)$coef[1];
		}
	}

	/**
	 * Check order obtained from request
	 *
	 * @param string $order The order obtained from request
	 * @param int $proposalIndex Contains the index of last Proposal
	 *
	 * @return int
	 */
	private function checkRequestOrder( $order, $proposalIndex ) {
		$tempOrder = explode( ' ', $order );

		// Check the number of order matches number of proposals
		if ( count( $tempOrder ) !== $proposalIndex + 1 ) {
			return 1;
		}

		// Check if each value is numeric and is within 0 to proposalIndex rannge
		foreach ( $tempOrder as $orderVal ) {
			if ( !is_numeric( $orderVal ) ) {
				return 1;
			}
			if ( $orderVal < 0 || $orderVal > $proposalIndex ) {
				return 1;
			}
		}
		'@phan-var int[] $tempOrder';

		// Check for repeated values
		$orderChecker = array_fill( 0, $proposalIndex + 1, 0 );
		foreach ( $tempOrder as $orderVal ) {
			if ( $orderChecker[ $orderVal ] !== 1 ) {
				$orderChecker[ $orderVal ] = 1;
			} else {
				return 1;
			}
		}
		return 0;
	}

	/**
	 * Transmit a single choice object to the basic type parser.
	 *
	 * @param string $input A question object in quiz syntax.
	 *
	 * @return string A question object in HTML.
	 */
	public function singleChoiceParseObject( $input ) {
		return $this->basicTypeParseObject( $input, 'radio' );
	}

	/**
	 * Transmit a multiple choice object to the basic type parser.
	 *
	 * @param string $input A question object in quiz syntax.
	 *
	 * @return string A question object in HTML.
	 */
	public function multipleChoiceParseObject( $input ) {
		return $this->basicTypeParseObject( $input, 'checkbox' );
	}

	/**
	 * Convert a basic type object from quiz syntax to HTML.
	 *
	 * @param string $input A question object in quiz syntax
	 * @param string $inputType
	 *
	 * @return string A question object in HTML.
	 */
	private function basicTypeParseObject( $input, $inputType ) {
		$output = preg_match( $this->mCategoryPattern, $input, $matches )
			? $this->parseCategories( $matches[1] )
			: '';
		$raws = preg_split( '`\n`s', $input, -1, PREG_SPLIT_NO_EMPTY );
		// Parameters used in some special cases.
		$expectOn = 0;
		$attemptChecker = 0;
		$lines = [];
		$proposalIndex = -1;
		foreach ( $raws as $proposalId => $raw ) {
			$text = null;
			$colSpan = '';
			$rawClass = '';
			$signesOutput = '';
			if ( preg_match( $this->mProposalPattern, $raw, $matches ) ) {
				$proposalIndex++;
				$rawClass = 'proposal';
				// Insulate the proposal signes.
				$text = array_pop( $matches );
				array_shift( $matches );
				// Determine a type ID, according to the questionType and the number of signes.
				$typeId  = substr( $this->mType, 0, 1 );
				$typeId .= array_key_exists( 1, $matches ) ? 'c' : 'n';
				foreach ( $matches as $signId => $sign ) {
					$attribs = [];
					$attribs['type'] = $inputType;
					$attribs['class'] = 'check';
					// Determine the input's name and value.
					switch ( $typeId ) {
						case 'mn':
							$name = 'q' . $this->mQuestionId . 'p' . $proposalId;
							$value = 'p' . $proposalId;
							break;
						case 'sn':
							$name = 'q' . $this->mQuestionId;
							$value = 'p' . $proposalId;
							break;
						case 'mc':
							$name = 'q' . $this->mQuestionId . 'p' . $proposalId . 's' . $signId;
							$value = 's' . $signId;
							break;
						case 'sc':
							$name = 'q' . $this->mQuestionId . 'p' . $proposalId;
							$value = 's' . $signId;
							break;
						default:
							throw new Exception( 'unknown typeId' );
					}
					// Determine if the input had to be checked.
					if ( $this->mBeingCorrected && $this->mRequest->getVal( $name ) == $value ) {
						$attribs['checked'] = 'checked';
					}
					// Determine if the proposal has been attempted
					$attemptChecker = ( $this->mBeingCorrected && $this->mRequest->getVal( $name ) === $value )
						? 1
						: 0;
					// Determine the color of the cell and modify the state of the question.
					switch ( $sign ) {
						case '+':
							$expectOn++;
							// A single choice object with many correct proposal is a syntax error.
							if ( $this->mType == 'singleChoice' && $expectOn > 1 ) {
								$this->setState( 'error' );
								$attribs['class'] .= ' error';
								$attribs['title'] = wfMessage( 'quiz_legend_error' )->text();
								$attribs['disabled'] = 'disabled';
							}
							if ( $this->mBeingCorrected ) {
								if ( array_key_exists( 'checked', $attribs ) ) {
									$this->setState( 'correct' );
									$attribs['class'] .= ' correct';
									$attribs['title'] = wfMessage( 'quiz_legend_correct' )->text();
								} else {
									$this->setState( 'na_incorrect' );
									$attribs['class'] .= ' incorrect';
									$attribs['title'] = wfMessage( 'quiz_legend_incorrect' )->text();
								}
							}
							break;
						case '-':
							if ( $this->mBeingCorrected ) {
								if ( array_key_exists( 'checked', $attribs ) ) {
									$this->setState( 'incorrect' );
									$attribs['class'] .= ' incorrect';
									$attribs['title'] = wfMessage( 'quiz_legend_incorrect' )->text();
								} else {
									$this->setState( 'na_correct' );
								}
							}
							break;
						default:
							$this->setState( 'error' );
							$attribs['class'] .= ' error';
							$attribs['title'] = wfMessage( 'quiz_legend_error' )->text();
							$attribs['disabled'] = 'disabled';
							break;
					}
					$signesOutput .= '<td class="sign">';
					$signesOutput .= Xml::input( $name, false, $value, $attribs );
					$signesOutput .= '</td>';
				}
				if ( $typeId == 'sc' ) {
					// A single choice object with no correct proposal is a syntax error.
					if ( $expectOn == 0 ) {
						$this->setState( 'error' );
					}
					$expectOn = 0;
				}
				// If the proposal text is empty, the question has a syntax error.
				if ( trim( $text ) == '' ) {
					$text = '???';
					$this->setState( 'error' );
				}
			} elseif ( preg_match( $this->mCorrectionPattern, $raw, $matches ) &&
				$this->mBeingCorrected
			) {
				$rawClass = $attemptChecker ? 'correction selected' : 'correction unselected';
				$text = array_pop( $matches );
				$signesOutput = '<td>&#x2192;</td>';
				// Hacks to avoid counting the number of signes.
				$colSpan = ' colspan="13"';
			}
			if ( $text !== null ) {
				$lineOutput = '<tr class="' . $rawClass . '">' . "\n";
				$lineOutput .= $signesOutput;
				$lineOutput .= '<td' . $colSpan . '>';
				$lineOutput .= $this->mParser->recursiveTagParse( $text );
				$lineOutput .= '</td>';
				$lineOutput .= '</tr>' . "\n";
				if ( $rawClass === 'correction selected' || $rawClass === 'correction unselected' ) {
					if ( $proposalIndex === -1 ) {
						// Add to output directly
						$output .= $lineOutput;
					} else {
						// Add feedback to previous proposal
						$lines[ $proposalIndex ] .= $lineOutput;
					}
				} else {
					// Add lineOutput for proposal
					$lines[ $proposalIndex ] = $lineOutput;
				}
			}
		}
		// A single choice object with no correct proposal is a syntax error.
		if ( isset( $typeId ) && $typeId == 'sn' && $expectOn == 0 ) {
			$this->setState( 'error' );
		}
		// Finding order of shuffled proposals
		$order = '';
		if ( $this->shuffleAnswers ) {
			if ( $this->mBeingCorrected ) {
				$order = $this->mRequest->getVal( $this->mQuestionId . '|order', '' );

				// Check order values
				$orderInvalid = $this->checkRequestOrder( $order, $proposalIndex );

				// If order is invalid then order is reset
				if ( $orderInvalid ) {
					$order = '';
					for ( $i = 0; $i <= $proposalIndex; $i++ ) {
						$order .= ' ' . $i;
					}
				}
			} else {
				$orderArray = range( 0, $proposalIndex );
				shuffle( $orderArray );
				$order = implode( ' ', $orderArray );
			}
		} else {
			for ( $i = 0; $i <= $proposalIndex; $i++ ) {
				$order .= ' ' . $i;
			}
		}
		// @phan-suppress-next-line PhanTypeMismatchArgumentNullableInternal
		$order = ltrim( $order );
		$tempOrder = explode( ' ', $order );
		for ( $i = 0; $i <= $proposalIndex; ++$i ) {
			$output .= $lines[ $tempOrder[ $i ] ];
		}
		$orderName = $this->mQuestionId . '|order';
		$orderValue = $order;
		$attribs['hidden'] = 'hidden';
		$attribs['checked'] = 'checked';

		return $this->shuffleAnswers
			? Xml::input( $orderName, false, $orderValue, $attribs ) . $output
			: $output;
	}

	/**
	 * Determine the object's parameters and convert a list of categories from
	 * quiz syntax to HTML.
	 *
	 * @param string $input pipe-separated list of the various categories.
	 * @return string
	 */
	private function parseCategories( $input ) {
		$linkPattern = '`(\[\[.*?\]\](*SKIP)(*FAIL)|\|)|({{.*?}}(*SKIP)(*FAIL)|\|)`';
		$categories = preg_split( $linkPattern, $input );
		// Less than two categories is a syntax error.
		if ( !array_key_exists( 1, $categories ) ) {
			$categories[1] = '???';
			$this->setState( 'error' );
		}
		$output = '<tr class="categories">' . "\n";
		$this->mProposalPattern = '`^';
		foreach ( $categories as $key => $category ) {
			// If a category name is empty, the question has a syntax error.
			if ( trim( $category ) == '' ) {
				$category = '???';
				$this->setState( 'error' );
			}
			$output .= '<th>' . $this->mParser->recursiveTagParse( $category ) . '</th>';
			if ( $key == 0 ) {
				$this->mProposalPattern .= '([+-]) ?';
			} else {
				$this->mProposalPattern .= '([+-])? ?';
			}
		}
		$output .= '</tr>' . "\n";
		$this->mProposalPattern .= '(.*)`';
		return $output;
	}

	/**
	 * Convert a "text field" object to HTML.
	 *
	 * @param string $input A question object in quiz syntax.
	 *
	 * @return string A question object in HTML.
	 */
	public function textFieldParseObject( $input ) {
		$raws = preg_split( '`\n`s', $input, -1, PREG_SPLIT_NO_EMPTY );
		global $wqInputId;
		$wqInputId = $this->mQuestionId * 100;
		$output = '';
		foreach ( $raws as $raw ) {
			if ( preg_match( $this->mCorrectionPattern, $raw, $matches ) ) {
				if ( $this->mBeingCorrected ) {
					$rawClass = 'correction';
					$text = '<td>&#x2192; ' . $this->mParser->recursiveTagParse( $matches[1] ) .
						'</td>';
				} else {
					continue;
				}
			} elseif ( trim( $raw ) != '' ) {
				$rawClass = 'proposal';
				$text = $this->mParser->recursiveTagParse( $raw );
				$text = preg_replace_callback(
					$this->mTextFieldPattern,
					[ $this, 'parseTextField' ],
					$text
				);
				$text = '<td class="input">' . $text . '</td>';
			} else {
				continue;
			}
			$output .= '<tr class="' . $rawClass . '">' . "\n";
			$output .= $text . "\n";
			$output .= '</tr>' . "\n";
		}
		return $output;
	}

	/**
	 * @param array $input
	 * @return string
	 */
	private function parseTextField( $input ) {
		global $wqInputId;
		$wqInputId++;
		$title = '';
		$state = '';
		$spanClass = '';
		$size = '';
		$maxlength = '';
		$class = '';
		$value = '';
		$disabled = '';
		$big = '';
		$poss = '';
		$bigDisplay = '';
		// determine size and maxlength of the input.
		if ( array_key_exists( 3, $input ) ) {
			$size = $input[3];
			if ( $size < 3 ) {
				$size = 1;
			} elseif ( $size < 12 ) {
				$size -= 2;
			} else {
				$size--;
			}
			$maxlength = $input[3];
		}
		$templateParser = new TemplateParser( __DIR__ . '/../templates' );
		// Syntax error if there is no input text.
		if ( $input[1] === "" ) {
			$value = 'value="???"';
			$state = 'error';
		} else {
			// For hiding down arrow
			$bigDisplay = 'display: none';
			if ( $this->mBeingCorrected ) {
				// @phan-suppress-next-line PhanTypeMismatchArgument
				$value = trim( $this->mRequest->getVal( $wqInputId, '' ) );
				$state = 'NA';
				$title = wfMessage( 'quiz_legend_unanswered' )->escaped();
			}
			$class = 'numbers';
			$poss = ' ';
			foreach (
				preg_split( '` *\| *`', trim( $input[1] ), -1, PREG_SPLIT_NO_EMPTY ) as $possibility
			) {
				if ( $state == '' || $state == 'NA' || $state == 'incorrect' ) {
					if ( preg_match(
						'`^(-?\d+\.?\d*)(-(-?\d+\.?\d*)| (\d+\.?\d*)(%))?$`',
						str_replace( ',', '.', $possibility ),
						$matches )
					) {
						if ( array_key_exists( 5, $matches ) ) {
							$strlen = $size = $maxlength = '';
						} elseif ( array_key_exists( 3, $matches ) ) {
							$strlen = strlen( $matches[1] ) > strlen( $matches[3] )
								? strlen( $matches[1] )
								: strlen( $matches[3] );
						} else {
							$strlen = strlen( $matches[1] );
						}
						if ( $this->mBeingCorrected && $value !== "" ) {
							$value = str_replace( ',', '.', $value );
							if ( is_numeric( $value ) && (
								(
									array_key_exists( 5, $matches )
									&& $value >=
										( (int)$matches[1] - ( (int)$matches[1] * (int)$matches[4] ) / 100 )
									&& $value <=
										( (int)$matches[1] + ( (int)$matches[1] * (int)$matches[4] ) / 100 )
								) || (
									array_key_exists( 3, $matches ) &&
									$value >= $matches[1] && $value <= $matches[3]
								) || $value == $possibility )
							) {
								$state = 'correct';
								$title = wfMessage( 'quiz_legend_correct' )->escaped();
							} else {
								$state = 'incorrect';
								$title = wfMessage( 'quiz_legend_incorrect' )->escaped();
							}
						}
					} else {
						$strlen = preg_match( '` \(i\)$`', $possibility )
							? mb_strlen( $possibility ) - 4
							: mb_strlen( $possibility );
						$class = 'words';
						if ( $this->mBeingCorrected && $value !== "" ) {
							if ( $value == $possibility ||
								( preg_match(
									'`^' . preg_quote( $value, '`' ) . ' \(i\)$`i', $possibility
								) ) ||
								( !$this->mCaseSensitive && preg_match(
									'`^' . preg_quote( $value, '`' ) . '$`i', $possibility
								) )
							) {
								$state = 'correct';
								$title = wfMessage( 'quiz_legend_correct' )->escaped();
							} else {
								$state = 'incorrect';
								$title = wfMessage( 'quiz_legend_incorrect' )->escaped();
							}
						}
					}
					if ( array_key_exists( 3, $input ) && $strlen > $input[3] ) {
						// The textfield is too short for the answer
						$state = 'error';
						$value = '<_' . $possibility . '_>';
					}
				}
				if ( $this->mBeingCorrected ) {
					$strlen = preg_match( '` \(i\)$`', $possibility )
						? mb_strlen( $possibility ) - 4
						: mb_strlen( $possibility );
					$possibility = mb_substr( $possibility, 0, $strlen );
					$poss .= $possibility . '<br />';
				}
			}
			if ( $this->mBeingCorrected ) {
				$big = '▼';
				$bigDisplay = ' ';
			}
		}
		if ( $state == 'error' || $this->mBeingCorrected ) {
			$spanClass .= " border $state";
			$this->setState( $value === "" ? 'new_NA' : $state );
			if ( $state == 'error' ) {
				$size = '';
				$maxlength = '';
				$disabled = 'disabled';
				$title = wfMessage( 'quiz_legend_error' )->escaped();
			}
		}
		$name = $wqInputId;
		return $templateParser->processTemplate(
			'Answer',
			[
				'title' => $title,
				'class' => $class,
				'spanClass' => trim( $spanClass ),
				'value' => $value,
				'correction' => $this->mBeingCorrected,
				'possibility' => $poss,
				'disabled' => $disabled,
				'size' => $size,
				'big' => $big,
				'maxlength' => $maxlength,
				'name' => $name,
				'bigDisplay' => $bigDisplay,
			]
		);
	}
}
