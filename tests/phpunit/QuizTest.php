<?php

namespace MediaWiki\Extension\Quiz\Tests;

use MediaWiki\Extension\Quiz\Quiz;
use MediaWiki\Html\TemplateParser;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\Quiz\Quiz
 * @group Database
 */
class QuizTest extends QuizTestCase {

	/**
	 * @var Quiz
	 */
	private $quiz;

	protected function setUp(): void {
		parent::setUp();
		$this->quiz = TestingAccessWrapper::newFromObject(
			new Quiz( [], $this->parser )
		);
	}

	public function testQuizHasQuizId() {
		$this->assertClassHasStaticAttribute( 'sQuizId', Quiz::class );
	}

	public function testResetQuizId() {
		$this->quiz->resetQuizId();
		$this->assertSame( 0, $this->quiz->getQuizId() );
	}

	public static function provideParseQuestion() {
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
	 * @covers \MediaWiki\Extension\Quiz\Quiz::parseQuestion
	 */
	public function testParseQuestion( $input, $expected, $injectedValue, $expectedValue ) {
		$this->quiz->mShuffleDiv = $injectedValue;
		$output = $this->quiz->parseQuestion( $input[0], $input[1] );
		$this->assertEquals( $expectedValue, $this->quiz->mShuffleDiv );
		$this->assertEquals( $expected, $output );
	}

	public static function provideGetSettingsTable() {
		return [
			// Test case when simple display is disabled, it is not being corrected, state is NA
			// added and cutoff points are 3 and -1 respectively with 4 questions in quiz
			[ [ '0', '0', 'NA', '1', '3', '-1', '1', '4' ],
			 "\t" . '<tr>' . "\n\t\t" . '<td><label for="addedPoints">' .
			 'Points added for a correct answer:</label></td>' .
			 "\n\t\t" . '<td><input class="numerical" type="number" ' .
			 'name="addedPoints" id="addedPoints" value="3"/>&#160;&#160;</td>' . "\n\t" .
			 '</tr>' . "\n\t" . '<tr>' . "\n\t\t" . '<td><label for="cutoffPoints">'
			 . 'Point for an incorrect answer:</label></td>'
			 . "\n\t\t" . '<td><input class="numerical" type="number" ' .
			 'name="cutoffPoints" id="cutoffPoints" value="-1"/></td>' . "\n\t" .
			 '</tr>' . "\n\t" . '<tr>' . "\n\t\t" . '<td><label for="ignoringCoef">'
			 . 'Ignore the questions' . "'" . ' coefficients:</label></td>'
			 . "\n\t\t" . '<td><input type="checkbox" name="ignoringCoef" id="ignoringCoef" ' .
			 'checked="checked"/></td>' . "\n\t" .
			 '</tr>' . "\n\t" . '<tr>' . "\n\t\t\t\t\t"
			 . '<td><input class="shuffle" name="shuffleButton" type="button" ' .
			 'value="Shuffle questions"/></td>'
			 . "\n\t\t\t" . '<td></td>' . "\n\t" . '</tr>' . "\n"
			],
			// Test case when simple display is disabled, it is being corrected, state is NA
			// added and cutoff points are 1 and 0 respectively with 2 questions in quiz
			[ [ '0', '1', 'NA', '1', '1', '0', '1', '2' ],
			 "\t" . '<tr>' . "\n\t\t" . '<td><label for="addedPoints">' .
			 'Point added for a correct answer:</label></td>'
			 . "\n\t\t" . '<td><input class="numerical" type="number" name="addedPoints" ' .
			 'id="addedPoints" value="1"/>&#160;&#160;</td>' . "\n\t\t" .
			 '<td class="margin correct"></td>' . "\n\t\t\t" .
			 '<td style="background: transparent;">Correct</td>' . "\n\t" . '</tr>' .
			 "\n\t" . '<tr>' . "\n\t\t" . '<td><label for="cutoffPoints">' .
			 'Points for an incorrect answer:</label></td>' . "\n\t\t" .
			 '<td><input class="numerical" type="number" name="cutoffPoints" id="cutoffPoints"' .
			 ' value="0"/></td>' . "\n\t\t" . '<td class="margin incorrect"></td>' . "\n\t\t"
			 . '<td style="background: transparent;">Incorrect</td>' . "\n\t" . '</tr>'
			 . "\n\t" . '<tr>' . "\n\t\t" .
			 '<td><label for="ignoringCoef">Ignore the questions'
			 . "'" . ' coefficients:</label></td>'
			 . "\n\t\t" . '<td><input type="checkbox" name="ignoringCoef" id="ignoringCoef"' .
			 ' checked="checked"/></td>' . "\n\t\t" .
			 '<td class="margin NA"></td>' . "\n\t\t" .
			 '<td style="background: transparent;">Not answered</td>' . "\n\t"
			 . '</tr>' . "\n\t" . '<tr>' . "\n\t" .
			 '</tr>' . "\n"
			],
			// Test case for simple display enabled and quiz is not being corrected
			[ [ '1', '0', 'NA', '1', '1', '0', '1', '3' ],
			 ""
			],
			// Test case for simple display enabled and quiz is being corrected
			[ [ '1', '1', 'NA', '1', '1', '0', '1', '2' ],
			 "\t" . '<tr>' . "\n\t\t" . '<td class="margin correct"></td>'
			 . "\n\t\t\t" . '<td style="background: transparent;">Correct</td>' . "\n\t" . '</tr>'
			 . "\n\t" . '<tr>' . "\n\t\t" . '<td class="margin incorrect"></td>'
			 . "\n\t\t" . '<td style="background: transparent;">Incorrect</td>' . "\n\t" . '</tr>'
			 . "\n\t" . '<tr>' . "\n\t\t" . '<td class="margin NA"></td>' . "\n\t\t"
			 . '<td style="background: transparent;">Not answered</td>' . "\n\t" . '</tr>' . "\n"
			],
		];
	}

	/**
	 * @dataProvider provideGetSettingsTable
	 * @covers \MediaWiki\Extension\Quiz\Quiz::getSettingsTable
	 */
	public function testGetSettingsTable( $injected, $expected ) {
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
		$this->assertEquals( $expected, $settingsTable );
	}

}
