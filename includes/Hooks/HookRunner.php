<?php

namespace MediaWiki\Extension\Quiz\Hooks;

use MediaWiki\Extension\Quiz\Question;
use MediaWiki\Extension\Quiz\Quiz;
use MediaWiki\HookContainer\HookContainer;

/**
 * This is a hook runner class, see docs/Hooks.md in core.
 * @internal
 */
class HookRunner implements
	QuizQuestionCreatedHook
{
	private HookContainer $hookContainer;

	public function __construct( HookContainer $hookContainer ) {
		$this->hookContainer = $hookContainer;
	}

	/**
	 * @inheritDoc
	 */
	public function onQuizQuestionCreated( Quiz $quiz, Question &$question ) {
		return $this->hookContainer->run(
			'QuizQuestionCreated',
			[ $quiz, &$question ]
		);
	}
}
