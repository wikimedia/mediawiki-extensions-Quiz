<?php

class QuizTest extends MediaWikiLangTestCase {

	/**
	 * @var Quiz $quiz
	 */
	private $quiz;

	/**
	 * @var Parser $parser
	 */
	private $parser;

	protected function setUp() {
		parent::setUp();
		global $wgParser;
		$wgParser = $this->getParser();
		$options = new ParserOptions();
		$title = $wgParser->getTitle();
		$this->parser = &$wgParser;
		$this->parser->startExternalParse( $title, $options, 'text', true );
		$this->quiz = new Quiz( [], $this->parser );
	}

	protected function tearDown() {
		parent::tearDown();
		unset( $this->parser );
	}

	private function getParser() {
		return new StubObject(
			'wgParser', $GLOBALS['wgParserConf']['class'],
			[ $GLOBALS['wgParserConf'] ]
		);
	}

	public function provideGetColor() {
		return [
			[ 'right', '#1FF72D' ],
			[ 'wrong', '#F74245' ],
			[ 'correction', '#F9F9F9' ],
			[ 'NA', '#2834FF' ],
			[ 'error', '#D700D7' ],
		];
	}

	/**
	 * @dataProvider provideGetColor
	 * @covers Question::getColor
	 */
	public function testGetColor( $colorId, $expected ) {
		$color = $this->quiz->getColor( $colorId );
		$this->assertEquals( $color, $expected );
	}

	public function testQuizHasQuizId() {
		$this->assertClassHasStaticAttribute( 'sQuizId', Quiz::class );
	}

	public function testResetQuizId() {
		$this->quiz->resetQuizId();
		$this->assertEquals( 0, $this->quiz->getQuizId() );
	}

	public function provideParseQuestion() {
		return [
			// Test for {X} tag
			[
			 [ [ 'X}', 'X', '' ], '1' ],
			 '<div class="shuffle">' . "\n", '0', '1'
			],
			// Test for {!X} tag
			[
			 [ [ '!X}', '!X', '' ], '1' ],
			 '<div class="noshuffle">' . "\n", '0', '1'
			],
			// Test for {/X} tag when {X} or {!X} has been used previously
			[
			 [ [ '/X}', '/X', '' ], '1' ],
			 '', '0', '0'
			],
			// Test for {/X} tag when {X} or {!X} has not been used previously
			[
			 [ [ '/X}', '/X', '' ], '1' ],
			 '</div>' . "\n", '1', '0'
			],
			// Test when random value is passed inside {} tag
			[
			 [ [ 'abc}', 'abc', '' ], '1' ],
			 '<div class="quizText">abc<br /></div>' . "\n", '0', '0'
			]
		];
	}

	/**
	 * @dataProvider provideParseQuestion
	 * @covers Question::parseQuestion
	 */
	public function testParseQuestion( $input, $expected, $injectedValue, $expectedValue ) {
		$this->quiz->mShuffleDiv = $injectedValue;
		$output = $this->quiz->parseQuestion( $input[0], $input[1] );
		$this->assertEquals( $this->quiz->mShuffleDiv, $expectedValue );
		$this->assertEquals( $output, $expected );
	}

}
