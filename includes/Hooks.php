<?php

namespace MediaWiki\Extension\Quiz;

use MediaWiki\Hook\ParserAfterTidyHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Parser\Parser;

class Hooks implements
	ParserFirstCallInitHook,
	ParserAfterTidyHook
{

	/**
	 * Register the extension with the WikiText parser.
	 * The tag used is <quiz>
	 * @param Parser $parser the wikitext parser
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setHook( 'quiz', [ self::class, 'renderQuiz' ] );
	}

	/**
	 * Call the quiz parser on an input text.
	 *
	 * @param string $input text between <quiz> and </quiz> tags, in quiz syntax.
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

	/**
	 * @param Parser $parser
	 * @param string &$text
	 */
	public function onParserAfterTidy( $parser, &$text ) {
		Quiz::resetQuizID();
	}
}
