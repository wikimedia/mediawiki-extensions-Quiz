<?php

class QuizTest extends MediaWikiLangTestCase {

	private $quiz;
	private $parser;

	protected function setUp() {
		parent::setUp();
		global $wgParser;
		$wgParser = $this->getParser();
		$this->parser = &$wgParser;
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

}
