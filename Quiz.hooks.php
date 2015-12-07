<?php

class QuizHooks {

	/**
	 * Register the extension with the WikiText parser.
	 * The tag used is <quiz>
	 * @param $parser Parser the wikitext parser
	 * @return Boolean true to continue hook processing
	*/
	public static function onParserFirstCallInit( &$parser ) {
		$parser->setHook( 'quiz', 'QuizHooks::renderQuiz' );
		return true;
	}

	/**
	 * Call the quiz parser on an input text.
	 *
	 * @param $input String text between <quiz> and </quiz> tags, in quiz syntax.
	 * @param $argv Array an array containing any arguments passed to the extension
	 * @param $parser Parser the wikitext parser.
	 *
	 * @return string An HTML quiz.
	*/
	public static function renderQuiz( $input, $argv, $parser ) {
		$parser->disableCache();
		$quiz = new Quiz( $argv, $parser );
		return $quiz->parseQuiz( $input );
	}
}
