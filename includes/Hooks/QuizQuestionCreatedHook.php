<?php

namespace MediaWiki\Extension\Quiz\Hooks;

use MediaWiki\Extension\Quiz\Question;
use MediaWiki\Extension\Quiz\Quiz;

/**
 * This is a hook handler interface, see docs/Hooks.md in core.
 * Use the hook name "QuizQuestionCreated" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface QuizQuestionCreatedHook {
	/**
	 * @param Quiz $quiz
	 * @param Question &$question
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onQuizQuestionCreated( Quiz $quiz, Question &$question );
}
