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
		unset( $this->quiz );
	}

	private function getParser() {
		return new StubObject(
			'wgParser', $GLOBALS['wgParserConf']['class'],
			[ $GLOBALS['wgParserConf'] ]
		);
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
	 * @covers Quiz::parseQuestion
	 */
	public function testParseQuestion( $input, $expected, $injectedValue, $expectedValue ) {
		$this->quiz->mShuffleDiv = $injectedValue;
		$output = $this->quiz->parseQuestion( $input[0], $input[1] );
		$this->assertEquals( $this->quiz->mShuffleDiv, $expectedValue );
		$this->assertEquals( $output, $expected );
	}

	public function provideGetSettingsTable() {
		return [
			// Test case when simple display is disabled, it is not being corrected, state is NA
			// added and cutoff points are 3 and -1 respectively with 4 questions in quiz
			[ [ '0', '0', 'NA', '1', '3', '-1', '1', '4' ],
			 "\n\t" . '<tr>' . "\n\t\n\t\t" . '<td><label for="addedPoints">' .
			 'Points added for a correct answer:</label></td>' .
			 "\n\t\t" . '<td><input class="numerical" type="number" ' .
			 'name="addedPoints" id="addedPoints" value="3"/>&#160;&#160;</td>' . "\n\t\n\t\n\t\n\t" .
			 '</tr>' . "\n\n\n\t" . '<tr>' . "\n\t\n\t\t" . '<td><label for="cutoffPoints">'
			 . 'Point for a wrong answer:</label></td>'
			 . "\n\t\t" . '<td><input class="numerical" type="number" ' .
			 'name="cutoffPoints" id="cutoffPoints" value="-1"/></td>' . "\n\t\n\t\n\t" .
			 '</tr>' . "\n\n\n\t" . '<tr>' . "\n\t\n\t\t" . '<td><label for="ignoringCoef">'
			 . 'Ignore the questions' . "'" . ' coefficients:</label></td>'
			 . "\n\t\t" . '<td><input type="checkbox" name="ignoringCoef" id="ignoringCoef" ' .
			 'checked="checked"/></td>' . "\n\t\n\t\n\t" .
			 '</tr>' . "\n\n\n\t\n\t" . '<tr>' . "\n\t\t\n\t\t\t\n\t\t\t\t\n\t\t\t\t\t"
			 . '<td><input class="shuffle" name="shuffleButton" type="button" ' .
			 'value="Shuffle questions"/></td>' . "\n\t\t\t\t\n\t\t\t\n\t\t\n\t\t"
			 . "\n\t\t\n\t\t\t" . '<td></td>' ."\n\t\t\n\t\t\n\t" . '</tr>' . "\n\t\n\n"
			],
			// Test case when simple display is disabled, it is being corrected, state is NA
			// added and cutoff points are 1 and 0 respectively with 2 questions in quiz
			[ [ '0', '1', 'NA', '1', '1', '0', '1', '2' ],
			 "\n\t" . '<tr>' . "\n\t\n\t\t" . '<td><label for="addedPoints">' .
			 'Point added for a correct answer:</label></td>'
			 . "\n\t\t" . '<td><input class="numerical" type="number" name="addedPoints" ' .
			 'id="addedPoints" value="1"/>&#160;&#160;</td>' . "\n\t\n\t\n\t\t" .
			 '<td class="margin right"></td>' . "\n\t\t\t" .
			 '<td style="background: transparent;">Right</td>' . "\n\t\n\t\n\t" . '</tr>' .
			 "\n\n\n\t" . '<tr>' . "\n\t\n\t\t" . '<td><label for="cutoffPoints">' .
			 'Points for a wrong answer:</label></td>' . "\n\t\t" .
			 '<td><input class="numerical" type="number" name="cutoffPoints" id="cutoffPoints"' .
			 ' value="0"/></td>' . "\n\t\n\t\n\t\t" . '<td class="margin wrong"></td>' . "\n\t\t"
			 . '<td style="background: transparent;">Wrong</td>' . "\n\t\n\t" . '</tr>'
			 . "\n\n\n\t" . '<tr>' . "\n\t\n\t\t" .
			 '<td><label for="ignoringCoef">Ignore the questions'
			 . "'" . ' coefficients:</label></td>'
			 . "\n\t\t" . '<td><input type="checkbox" name="ignoringCoef" id="ignoringCoef"' .
			 ' checked="checked"/></td>' . "\n\t\n\t\n\t\t" .
			 '<td class="margin NA"></td>' . "\n\t\t" .
			 '<td style="background: transparent;">Not answered</td>' . "\n\t\n\t"
			 . '</tr>' . "\n\n\n\t\n\t" . '<tr>' . "\n\t\t\n\t\t\t\n\t\t\n\t\t\n\t\t\n\t\t\n\t" .
			 '</tr>' . "\n\t\n\n"
			],
			// Test case for simple display enabled and quiz is not being corrected
			[ [ '1', '0', 'NA', '1', '1', '0', '1', '3' ],
			 "\n\n\n\n"
			],
			// Test case for simple display enabled and quiz is being corrected
			[ [ '1', '1', 'NA', '1', '1', '0', '1', '2' ],
			 "\n\t" . '<tr>' . "\n\t\n\t\n\t\t" . '<td class="margin right"></td>'
			 . "\n\t\t\t" . '<td style="background: transparent;">Right</td>' . "\n\t\n\t\n\t" . '</tr>'
			 . "\n\n\n\t" . '<tr>' . "\n\t\n\t\n\t\t" . '<td class="margin wrong"></td>'
			 . "\n\t\t" . '<td style="background: transparent;">Wrong</td>' . "\n\t\n\t" . '</tr>'
			 . "\n\n\n\t" . '<tr>' . "\n\t\n\t\n\t\t" . '<td class="margin NA"></td>' . "\n\t\t"
			 . '<td style="background: transparent;">Not answered</td>' . "\n\t\n\t" . '</tr>' . "\n\n\n"
			],
		];
	}

	/**
	 * @dataProvider provideGetSettingsTable
	 * @covers Quiz::getSettingsTable
	 */
	public function testGetSettingsTable( $injected = [], $expected ) {
		// Setting quiz variables
		$this->quiz->mDisplaySimple = $injected[0];
		$this->quiz->mBeingCorrected = $injected[1];
		$this->quiz->mState = $injected[2];
		$this->quiz->mShuffle = $injected[3];
		$this->quiz->mAddedPoints = $injected[4];
		$this->quiz->mCutoffPoints = $injected[5];
		$this->quiz->mIgnoringCoef = $injected[6];
		$this->quiz->numberQuestions = $injected[7];
		$templateParser = new TemplateParser( __DIR__ . '/../../templates' );
		$settingsTable = $this->quiz->getSettingsTable( $templateParser );
		$this->assertEquals( $settingsTable, $expected );
	}

}
