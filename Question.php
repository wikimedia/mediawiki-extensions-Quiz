<?php

class Question {
	/**
	 * Constructor
	 *
	 * @param $beingCorrected Boolean.
	 * @param $caseSensitive Boolean.
	 * @param $questionId Integer: the Identifier of the question used to generate input names.
	 * @param $parser Parser the wikitext parser.
	 */
	public function __construct( $beingCorrected, $caseSensitive, $questionId, &$parser ) {
		global $wgRequest;
		$this->mRequest = &$wgRequest;
		$this->mQuestionId = $questionId;
		$this->mBeingCorrected = $beingCorrected;
		$this->mCaseSensitive = $caseSensitive;
		$this->mParser = $parser;
		$this->mState = ( $beingCorrected ) ? 'NA' : '';
		$this->mType = 'multipleChoice';
		$this->mCoef = 1;
		$this->mProposalPattern = '`^([+-]) ?(.*)`';
		$this->mCorrectionPattern = '`^\|\|(.*)`';
		$this->mCategoryPattern = '`^\|(\n|[^\|].*\n)`';
		$this->mTextFieldPattern = '`\{ ([^\}]*?)(_([\d]*) ?| )\}`';
	}

	/**
	 * Mutator of the question state
	 *
	 * @protected
	 * @param $pState String:
	 */
	function setState( $pState ) {
		if ( $pState == 'error' || ( $pState == 'wrong' && $this->mState != 'error' ) ||
			( $pState == 'right' && ( $this->mState == 'NA' || $this->mState == 'na_right' ) ) ||
			( $pState == 'na_wrong' && ( $this->mState == 'NA' || $this->mState == 'na_right' ) ) ||
			( $pState == 'na_right' && ( $this->mState == 'NA' ) ) ||
			( $pState == 'new_NA' && ( $this->mState == 'NA' || $this->mState == 'right' ) )
		) {
			$this->mState = $pState;
		}

		// Special cases
		if ( ( $pState == 'na_wrong' && $this->mState == 'right' ) ||
			( $pState == 'right' && $this->mState == 'na_wrong' )
		) {
			$this->mState = 'wrong';
		}
		return;
	}

	/**
	 * Accessor of the question state.
	 *
	 * @protected
	 */
	function getState() {
		if ( $this->mState == 'na_right' ) {
			return 'right';
		} elseif ( $this->mState == 'na_wrong' || $this->mState == 'new_NA' ) {
			return 'NA';
		} else {
			return $this->mState;
		}
	}

	/**
	 * Convert the question's header into HTML.
	 *
	 * @param $input String: the quiz header in quiz syntax.
	 * @return string
	 */
	function parseHeader( $input ) {
		$parametersPattern = '`\n\|([^\|].*)\s*$`';
		$input = preg_replace_callback(
			$parametersPattern,
			[ $this, 'parseParameters' ],
			$input
		);
		$splitHeaderPattern = '`\n\|\|`';
		$unparsedHeader = preg_split( $splitHeaderPattern, $input );
	 	$output = $this->mParser->recursiveTagParse( trim( $unparsedHeader[0] ) . "\n" );
		if ( array_key_exists( 1, $unparsedHeader ) && $this->mBeingCorrected ) {
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
	 * @param $matches array: elements matching $parametersPattern
	 * 						$matches[0] are the potential question parameters.
	 */
	function parseParameters( $matches ) {
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
			$this->mCoef = $coef[1];
		}
		return;
	}

	/**
	 * Transmit a single choice object to the basic type parser.
	 *
	 * @param $input string A question object in quiz syntax.
	 *
	 * @return string A question object in HTML.
	 */
	function singleChoiceParseObject( $input ) {
		return $this->basicTypeParseObject( $input, 'radio' );
	}

	/**
	 * Transmit a multiple choice object to the basic type parser.
	 *
	 * @param $input string A question object in quiz syntax.
	 *
	 * @return string A question object in HTML.
	 */
	function multipleChoiceParseObject( $input ) {
		return $this->basicTypeParseObject( $input, 'checkbox' );
	}

	/**
	 * Convert a basic type object from quiz syntax to HTML.
	 *
	 * @param $input string A question object in quiz syntax
	 * @param $inputType string
	 *
	 * @return string A question object in HTML.
	 */
	function basicTypeParseObject( $input, $inputType ) {
		$output = preg_match( $this->mCategoryPattern, $input, $matches )
			? $this->parseCategories( $matches[1] )
			: '';
		$raws = preg_split( '`\n`s', $input, -1, PREG_SPLIT_NO_EMPTY );
		// Parameters used in some special cases.
		$expectOn = 0;
		$checkedCount = 0;
		foreach ( $raws as $proposalId => $raw ) {
			$text = null;
			$colSpan = '';
			$signesOutput = '';
			if ( preg_match( $this->mProposalPattern, $raw, $matches ) ) {
				$rawClass = 'proposal';
				// Insulate the proposal signes.
				$text = array_pop( $matches );
				array_shift( $matches );
				// Determine a type ID, according to the questionType and the number of signes.
				$typeId  = substr( $this->mType, 0, 1 );
				$typeId .= array_key_exists( 1, $matches ) ? 'c' : 'n';
				foreach ( $matches as $signId => $sign ) {
					$title = $disabled = $inputStyle = '';
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
					}
					// Determine if the input had to be checked.
					$checked = $this->mBeingCorrected && $this->mRequest->getVal( $name ) == $value
						? 'checked="checked"'
						: null;
					// Determine the color of the cell and modify the state of the question.
					switch ( $sign ) {
						case '+':
							$expectOn++;
							// A single choice object with many correct proposal is a syntax error.
							if ( $this->mType == 'singleChoice' && $expectOn > 1 ) {
								$this->setState( 'error' );
								$inputStyle = 'style="outline: ' . Quiz::getColor( 'error' ) . ' solid 3px; *border: 3px solid ' . Quiz::getColor( 'error' ) . ';"';
								$title = 'title="' . wfMessage( 'quiz_colorError' )->escaped() . '"';
								$disabled = 'disabled="disabled"';
							}
							if ( $this->mBeingCorrected ) {
								if ( $checked ) {
									$checkedCount++;
									$this->setState( 'right' );
									$inputStyle = 'style="outline: ' . Quiz::getColor( 'right' ) . ' solid 3px; *border: 3px solid ' . Quiz::getColor( 'right' ) . ';"';
									$title = 'title="' . wfMessage( 'quiz_colorRight' )->escaped() . '"';
								} else {
									$this->setState( 'na_wrong' );
									$inputStyle = 'style="outline: ' . Quiz::getColor( 'wrong' ) . ' solid 3px; *border: 3px solid ' . Quiz::getColor( 'wrong' ) . ';"';
									$title = 'title="' . wfMessage( 'quiz_colorWrong' )->escaped() . '"';
								}
							}
							break;
						case '-':
							if ( $this->mBeingCorrected ) {
								if ( $checked ) {
									$checkedCount++;
									$this->setState( 'wrong' );
									$inputStyle = 'style="outline: ' . Quiz::getColor( 'wrong' ) . ' solid 3px; *border: 3px solid ' . Quiz::getColor( 'wrong' ) . ';"';
									$title = 'title="' . wfMessage( 'quiz_colorWrong' )->escaped() . '"';
								} else {
									$this->setState( 'na_right' );
								}
							}
							break;
						default:
							$this->setState( 'error' );
							$inputStyle = 'style="outline: ' . Quiz::getColor( 'error' ) . ' solid 3px; *border: 3px solid ' . Quiz::getColor( 'error' ) . ';"';
							$title = 'title="' . wfMessage( 'quiz_colorError' )->escaped() . "\"";
							$disabled = 'disabled="disabled"';
							break;
					}
					$signesOutput .= '<td class="sign">';
					$signesOutput .= '<input class="check" ' . $inputStyle . ' type="' . $inputType . '" ' . $title . ' name="' . $name . '" value="' . $value . '" ' . $checked . ' ' . $disabled . ' />';
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
			} elseif ( preg_match( $this->mCorrectionPattern, $raw, $matches ) && $this->mBeingCorrected ) {
				$rawClass = 'correction';
				$text = array_pop( $matches );
				$signesOutput = '<td>&#x2192;</td>';
				// Hacks to avoid counting the number of signes.
				$colSpan = ' colspan="13"';
			}
			if ( $text ) {
				$output .= '<tr class="' . $rawClass . '">' . "\n";
				$output .= $signesOutput;
				$output .= '<td' . $colSpan . '>';
				$output .= $this->mParser->recursiveTagParse( $text );
				$output .= '</td>';
				$output .= '</tr>' . "\n";
			}
		}
		// A single choice object with no correct proposal is a syntax error.
		if ( isset( $typeId ) && $typeId == 'sn' && $expectOn == 0 ) {
			$this->setState( 'error' );
		}
		return $output;
	}

	/**
	 * Determine the object's parameters and convert a list of categories from
	 * quiz syntax to HTML.
	 *
	 * @param $input String: pipe-separated list of the various categories.
	 * @return string
	 */
	function parseCategories( $input ) {
		$categories = explode( '|', $input );
		// Less than two categories is a syntax error.
		if ( !array_key_exists( 1, $categories ) ) {
			$categories[1] = '???';
			$this->setState( 'error' );
		}
		$output = '<tr class="categories">' . "\n";
		$this->mProposalPattern =  '`^';
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
		$output .= '<th></th>';
		$output .= '</tr>' . "\n";
		$this->mProposalPattern .= '(.*)`';
		return $output;
	}

	/**
	 * Convert a "text field" object to HTML.
	 *
	 * @param $input string A question object in quiz syntax.
	 *
	 * @return string A question object in HTML.
	 */
	function textFieldParseObject( $input ) {
		$raws = preg_split( '`\n`s', $input, -1, PREG_SPLIT_NO_EMPTY );
		global $wqInputId;
		$wqInputId = $this->mQuestionId * 100;
		$output = '';
		foreach ( $raws as $raw ) {
			if ( preg_match( $this->mCorrectionPattern, $raw, $matches ) ) {
				if ( $this->mBeingCorrected )  {
					$rawClass = 'correction';
					$text = '<td>&#x2192; ' . $this->mParser->recursiveTagParse( $matches[1] ) . '</td>';
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
			}
			$output .= '<tr class="' . $rawClass . '">' . "\n";
			$output .= $text . "\n";
			$output .= '</tr>' . "\n";
		}
		return $output;
	}

	/**
	 * @param $input array
	 * @return string
	 */
	function parseTextField( $input ) {
		global $wqInputId;
		$wqInputId++;
		$title = $state = $size = $maxlength = $class = $style = $value = $disabled = $big = $poss = $name = $bigDisplay = '';
		// determine size and maxlength of the input.
		if ( array_key_exists( 3, $input ) ) {
			$size = $input[3];
			if ( $size < 3 ) {
				$size = 1;
			} elseif ( $size < 12 ) {
				$size = $size - 2;
			} else {
				$size = $size - 1;
			}
			$maxlength = $input[3];
		}
		// Syntax error if there is no input text.
		if ( empty( $input[1] ) ) {
			$value = 'value="???"';
			$state = 'error';
		} else {
			$templateParser = new TemplateParser( __DIR__ . '/templates' );
			// For hiding down arrow
			$bigDisplay = 'display: none';
			if ( $this->mBeingCorrected ) {
				$value = trim( $this->mRequest->getVal( $wqInputId ) );
				$state = 'NA';
				$title = wfMessage( 'quiz_colorNA' )->escaped();
			}
			$class = 'numbers';
			$poss = ' ';
			foreach ( preg_split( '` *\| *`', trim( $input[1] ), -1, PREG_SPLIT_NO_EMPTY ) as $possibility ) {
				if ( $state == '' || $state == 'NA' || $state == 'wrong' ) {
					if ( preg_match( '`^(-?\d+\.?\d*)(-(-?\d+\.?\d*)| (\d+\.?\d*)(%))?$`', str_replace( ',', '.', $possibility ), $matches ) ) {
						if ( array_key_exists( 5, $matches ) ) {
							$strlen = $size = $maxlength = '';
						} elseif ( array_key_exists( 3, $matches ) ) {
							$strlen = strlen( $matches[1] ) > strlen( $matches[3] ) ? strlen( $matches[1] ) : strlen( $matches[3] );
						} else {
							$strlen = strlen( $matches[1] );
						}
						if ( $this->mBeingCorrected && !empty( $value ) ) {
							$value = str_replace( ',', '.', $value );
							if ( is_numeric( $value ) && (
								( array_key_exists( 5, $matches )
									&& $value >= ( $matches[1] - ( $matches[1] * $matches[4] ) / 100 )
									&& $value <= ( $matches[1] + ( $matches[1] * $matches[4] ) / 100 )
								) || ( array_key_exists( 3, $matches ) && $value >= $matches[1] && $value <= $matches[3]
								) || $value == $possibility )
							) {
								$state = 'right';
								$title = wfMessage( 'quiz_colorRight' )->escaped();
							} else {
								$state = 'wrong';
								$title = wfMessage( 'quiz_colorWrong' )->escaped();
							}
						}
					} else {
						$strlen = preg_match( '` \(i\)$`', $possibility ) ? mb_strlen( $possibility ) - 4 : mb_strlen( $possibility );
						$class = 'words';
						if ( $this->mBeingCorrected && !empty( $value ) ) {
							if ( $value == $possibility ||
								( preg_match( '`^' . preg_quote( $value, '`' ) . ' \(i\)$`i', $possibility ) ) ||
								( !$this->mCaseSensitive && preg_match( '`^' . preg_quote( $value, '`' ) . '$`i', $possibility ) )
							) {
								$state = 'right';
								$title = wfMessage( 'quiz_colorRight' )->escaped();
							} else {
								$state = 'wrong';
								$title = wfMessage( 'quiz_colorWrong' )->escaped();
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
					$strlen = preg_match( '` \(i\)$`', $possibility ) ? mb_strlen( $possibility ) - 4 : mb_strlen( $possibility );
					$possibility = substr( $possibility, 0, $strlen );
					$poss .= $possibility . '<br />';
				}
			}
			$value = empty( $value ) ? '' : str_replace( '"', '&quot;', $value );
			if ( $this->mBeingCorrected ) {
				$big = '▼';
				$bigDisplay = ' ';
			}
		}
		if ( $state == 'error' || $this->mBeingCorrected ) {
			global $wgContLang;
			$border = $wgContLang->isRTL() ? 'border-right' : 'border-left';
			$style = $border . ':3px solid ' . Quiz::getColor( $state ) . ';';
			$this->setState( empty( $value ) ? 'new_NA' : $state );
			if ( $state == 'error' ) {
				$size = '';
				$maxlength = '';
				$disabled = 'disabled';
				$title = wfMessage( 'quiz_colorError' )->escaped();
			}
		}
		$name = $wqInputId;

		$temp = $templateParser->processTemplate(
			'Answer',
			[
				'style' => $style,
				'title' => $title,
				'class' => $class,
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
		return $temp;
	}
}
