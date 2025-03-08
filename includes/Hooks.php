<?php

namespace MediaWiki\Extension\Quiz;

use Parser;

class Hooks {

	/**
	 * Register the extension with the WikiText parser.
	 * The tag used is <quiz>
	 * @param Parser $parser the wikitext parser
	 * @return bool true to continue hook processing
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setHook( 'quiz', [ self::class, 'renderQuiz' ] );
		return true;
	}

	/**
	 * Call the quiz parser on an input text.
	 *
	 * @param string|null $input text between <quiz> and </quiz> tags, in quiz syntax.
	 * @param array $argv an array containing any arguments passed to the extension
	 * @param Parser $parser the wikitext parser.
	 *
	 * @return string An HTML quiz.
	 */
	public static function renderQuiz( $input, $argv, $parser ) {
		$parser->getOutput()->updateCacheExpiry( 0 );
		$quiz = new Quiz( $argv, $parser );
		return $quiz->parseQuiz( $input );
	}
}
