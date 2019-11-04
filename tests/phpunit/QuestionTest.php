<?php

use MediaWiki\MediaWikiServices;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers Question
 */
class QuestionTest extends MediaWikiLangTestCase {

	/**
	 * @var Parser $parser
	 */
	private $parser;

	/**
	 * @var Question $question
	 */
	private $question;

	protected function setUp() : void {
		parent::setUp();

		$services = MediaWikiServices::getInstance();
		$options = new ParserOptions();
		$title = SpecialPage::getTitleFor( 'Blankpage', '/dummy by Quiz' );
		$this->parser = $services->getParser();
		$this->parser->startExternalParse( $title, $options, 'text', true );
	}

	private function getQuestion( $beingCorrected, $caseSensitive, $questionId ) {
		// Randomly generate shuffle parameter value
		$shuffle = rand( 0, 100 ) % 2 == 0 ? 0 : 1;
		return TestingAccessWrapper::newFromObject(
			new Question( $beingCorrected, $caseSensitive, $questionId, $shuffle, $this->parser )
		);
	}

	public function testGetState() {
		$this->question = $this->getQuestion( 1, 1, 2 );
		$state = $this->question->getState();
		$this->assertThat(
			$state,
			$this->logicalOr(
				$this->equalTo( 'correct' ),
				$this->equalTo( 'error' ),
				$this->equalTo( 'NA' ),
				$this->equalTo( 'incorrect' )
			)
		);
	}

	public function provideSetState() {
		return [
			[ 'NA', 'error', 'error' ],
			[ 'NA', 'na_incorrect', 'na_incorrect' ],
			[ 'na_correct', 'na_incorrect', 'na_incorrect' ],
			[ 'na_incorrect', 'na_correct', 'na_incorrect' ],
			[ 'correct', 'na_incorrect', 'incorrect' ],
			[ 'na_incorrect', 'correct', 'incorrect' ],
			[ 'error', 'na_incorrect', 'error' ]
		];
	}

	/**
	 * @dataProvider provideSetState
	 * covers Question::setState
	 */
	public function testSetState( $previousState, $inputState, $expected ) {
		$this->question = $this->getQuestion( 1, 1, 2 );
		$this->question->setState( $previousState );
		$this->question->setState( $inputState );
		$state = $this->question->mState;
		$this->assertEquals( $expected, $state );
	}

	public function provideParseParameters() {
		return [
			[ '0', [ '|type="{}"', 'type="{}"' ], [ 'textField', '1' ] ],
			[ '1', [ '|type="{}"', 'type="{}"' ], [ 'textField', '1' ] ],
			[ '0', [ '|type="()" coef="2"', 'type="()" coef="2"' ], [ 'singleChoice', '2' ] ],
			[ '1', [ '|type="()" coef="2"', 'type="()" coef="2"' ], [ 'singleChoice', '2' ] ]
		];
	}

	/**
	 * @dataProvider provideParseParameters
	 * covers Question::parseParameters
	 */
	public function testParseParameters( $beingCorrected, $input, $expected ) {
		$this->question = $this->getQuestion( $beingCorrected, 1, 2 );
		$this->question->parseParameters( $input );
		$this->assertEquals( $expected[0], $this->question->mType );
		$this->assertEquals( $expected[1], $this->question->mCoef );
	}

	public function provideParseHeader() {
		return [
			[ '0', 'Question ' . "\n" . '|type="[]"', 'Question' ],
			[ '1', 'Question ' . "\n" . '|type="[]"', 'Question' ],
			[ '0', 'Sample Question ' . "\n" . '|type="{}" coef="3"', 'Sample Question' ],
			[ '1', 'Sample Question ' . "\n" . '|type="{}" coef="3"', 'Sample Question' ]
		];
	}

	/**
	 * @dataProvider provideParseHeader
	 * covers Question::parseHeader
	 */
	public function testParseHeader( $beingCorrected, $input, $expected ) {
		$this->question = $this->getQuestion( $beingCorrected, 1, 2 );
		$header = $this->question->parseHeader( $input );
		$this->assertEquals( $expected, $header );
	}

	public function provideParseCategories() {
		return [
			// Test case when Question is being corrected and input has 3 Categories
			[ '1', 'Option A | Option B | Option C',
			 [ '<tr class="categories">' . "\n"
			 . '<th>Option A </th><th> Option B </th><th> Option C</th></tr>' . "\n",
			 '`^([+-]) ?([+-])? ?([+-])? ?(.*)`',
			 'NA'
			 ]
			],
			// Test case when Question is not being corrected and input has 3 Categories
			[ '0', 'Option A | Option B | Option C',
			 [ '<tr class="categories">' . "\n"
			 . '<th>Option A </th><th> Option B </th><th> Option C</th></tr>' . "\n",
			 '`^([+-]) ?([+-])? ?([+-])? ?(.*)`',
			 ''
			 ]
			],
			// Test case when Question is being corrected and input has syntax error
			[ '1', '| B | C',
			 [ '<tr class="categories">' . "\n"
			 . '<th>???</th><th> B </th><th> C</th></tr>' . "\n",
			 '`^([+-]) ?([+-])? ?([+-])? ?(.*)`',
			 'error'
			 ]
			],
			// Test case when Question is not being corrected and input has syntax error
			[ '0', '| B | C',
			 [ '<tr class="categories">' . "\n"
			 . '<th>???</th><th> B </th><th> C</th></tr>' . "\n",
			 '`^([+-]) ?([+-])? ?([+-])? ?(.*)`',
			 'error'
			 ]
			],
			// Test case when Question is not being corrected and input has Categories with link
			[ '0', 'Option A | [[Article name | Option B]] | Option C',
			 [ '<tr class="categories">' . "\n"
			 . '<th>Option A </th><th> <!--LINK\'" 0:0--> </th><th> Option C</th></tr>' . "\n",
			 '`^([+-]) ?([+-])? ?([+-])? ?(.*)`',
			 ''
			 ]
			],
			// Test case when Question is being corrected and input has Categories with link
			[ '1', 'Option A | [[Article name | Option B]] | Option C',
			 [ '<tr class="categories">' . "\n"
			 . '<th>Option A </th><th> <!--LINK\'" 0:0--> </th><th> Option C</th></tr>' . "\n",
			 '`^([+-]) ?([+-])? ?([+-])? ?(.*)`',
			 'NA'
			 ]
			],
			// Test case when Question is not being corrected and input has Categories with template
			[ '0', 'Option A | {{Template name | url=http://www.example.com}} | Option C',
			 [ '<tr class="categories">' . "\n"
			 . '<th>Option A </th><th> {{Template name | url=<a rel="nofollow" ' .
			 'class="external free" href="http://www.example.com}}">http://www.example.com}}' .
			 '</a> </th><th> Option C</th></tr>' . "\n",
			 '`^([+-]) ?([+-])? ?([+-])? ?(.*)`',
			 ''
			 ]
			],
			// Test case when Question is being corrected and input has Categories with template
			[ '1', 'Option A | {{Template name | url=http://www.example.com}} | Option C',
			 [ '<tr class="categories">' . "\n"
			 . '<th>Option A </th><th> {{Template name | url=<a rel="nofollow" ' .
			 'class="external free" href="http://www.example.com}}">http://www.example.com}}' .
			 '</a> </th><th> Option C</th></tr>' . "\n",
			 '`^([+-]) ?([+-])? ?([+-])? ?(.*)`',
			 'NA'
			 ]
			]
		];
	}

	/**
	 * @dataProvider provideParseCategories
	 * covers Question::parseCategories
	 */
	public function testParseCategories( $beingCorrected, $input, $expected = [] ) {
		$this->question = $this->getQuestion( $beingCorrected, 1, 2 );
		$output = $this->question->parseCategories( $input );
		$this->assertEquals( $expected[0], $output );
		$pattern = $this->question->mProposalPattern;
		$this->assertEquals( $expected[1], $pattern );
		$state = $this->question->getState();
		$this->assertEquals( $expected[2], $state );
	}

	public function provideParseTextField() {
		return [
			// test case when quiz no input is provided for multiple answers
			[ '1', '1', [ '{ Stageira | Plato | Greece }', 'Stageira | Plato | Greece', '' ],
			 '<div style="display:inline-block">' . "\n\t\n\t\t" . '<a class="input" href="#nogo">'
			 . "\n\t\t\t" . '<span class="correction"> Stageira<br />Plato<br />Greece<br />'
			 . "\n\t\t\t" . '</span>' . "\n\t\t\t" . '<span class="border NA">'
			 . "\n\t\t\t\t"
			 . '<input type="text" name="1" class="words" title="Not answered"  size="" '
			 . 'maxlength="" autocomplete="off" value="" />'
			 . "\n\t\t\t\t\t" . '<em style=" ">▼' . "\n\t\t\t\t\t" . '</em>' . "\n\t\t\t"
			 . '</span>' . "\n\t\n" . '</div>' . "\n",
			 ''
			],
			// test case when the quiz is not being corrected
			[
			 '2', '0', [ '{ Stageira | Plato | Greece }', 'Stageira | Plato | Greece', '' ],
			 '<div style="display:inline-block">' . "\n\t\n\t\t" . '<a class="input" href="#nogo">'
			 . "\n\t\t\t" . '<span class="correction">' . " \n\t\t\t" . '</span>' . "\n\t\t\t"
			 . '<span class="">' . "\n\t\t\t\t"
			 . '<input type="text" name="2" class="words" title=""  size="" maxlength="" '
			 . 'autocomplete="off" value="" />' . "\n\t\t\t\t\t" . '<em style="display: none">'
			 . "\n\t\t\t\t\t" . '</em>' . "\n\t\t\t" . '</span>' . "\n\t\n" . '</div>' . "\n"
			 ,
			 ''
			],
			// test case when no user input is provided to case insensitive answer
			[
			 '3', '1', [ '{ Plato (i) _6 }', 'Plato (i)', '_6', '6' ],
			  '<div style="display:inline-block">' . "\n\t\n\t\t" . '<a class="input" href="#nogo">'
			 . "\n\t\t\t" . '<span class="correction"> Plato<br />' . "\n\t\t\t" . '</span>' . "\n\t\t\t"
			 . '<span class="border NA">' . "\n\t\t\t\t"
			 . '<input type="text" name="3" class="words" title="Not answered"  size="4" '
			 . 'maxlength="6" autocomplete="off" value="" />'
			 . "\n\t\t\t\t\t" . '<em style=" ">▼' . "\n\t\t\t\t\t" . '</em>'
			 . "\n\t\t\t" . '</span>' . "\n\t\n" . '</div>' . "\n",
			 ''
			],
			// test case when mulitple answers are provided and user input is one of them
			[ '4', '1', [ '{ Stageira | Plato | Greece }', 'Stageira | Plato | Greece', '' ],
			 '<div style="display:inline-block">' . "\n\t\n\t\t" . '<a class="input" href="#nogo">'
			 . "\n\t\t\t" . '<span class="correction"> Stageira<br />Plato<br />Greece<br />' . "\n\t\t\t"
			 . '</span>' . "\n\t\t\t" . '<span class="border correct">' . "\n\t\t\t\t"
			 . '<input type="text" name="4" class="words" title="Correct"  size="" '
			 . 'maxlength="" autocomplete="off" value="Greece" />'
			 . "\n\t\t\t\t\t" . '<em style=" ">▼' . "\n\t\t\t\t\t" . '</em>' . "\n\t\t\t" . '</span>'
			 . "\n\t\n" . '</div>' . "\n",
			 'Greece'
			],
			// test case using maxlength=6 but user input is more than more than maxlength
			[
			 '5', '1', [ '{ Plato (i) _6 }', 'Plato (i)', '_6', '6' ],
			 '<div style="display:inline-block">' . "\n\t\n\t\t" . '<a class="input" href="#nogo">'
			 . "\n\t\t\t" . '<span class="correction"> Plato<br />' . "\n\t\t\t" . '</span>' . "\n\t\t\t"
			 . '<span class="border incorrect">' . "\n\t\t\t\t"
			 . '<input type="text" name="5" class="words" title="Incorrect"  size="4" '
			 . 'maxlength="6" autocomplete="off" value="morethansix" />'
			 . "\n\t\t\t\t\t" . '<em style=" ">▼' . "\n\t\t\t\t\t" . '</em>'
			 . "\n\t\t\t" . '</span>' . "\n\t\n" . '</div>' . "\n",
			 'morethansix'
			],
			// test case for empty answer when user input is empty
			[
			 '6', '1', [ '{ }', '', '' ],
			 '<div style="display:inline-block">' . "\n\t\n\t\t" . '<a class="input" href="#nogo">'
			 . "\n\t\t\t" . '<span class="correction">' . "\n\t\t\t" . '</span>' . "\n\t\t\t"
			 . '<span class="border error">' . "\n\t\t\t\t"
			 . '<input type="text" name="6" class="" title="Syntax error" disabled size="" '
			 . 'maxlength="" autocomplete="off" value="value=&quot;???&quot;" />'
			 . "\n\t\t\t\t\t" . '<em style="">' . "\n\t\t\t\t\t" . '</em>'
			 . "\n\t\t\t" . '</span>' . "\n\t\n" . '</div>' . "\n",
			 ''
			],
			// test case when answer is 0 and user input is zero
			[
			 '7', '1', [ '{ 0 }', '0', '' ],
			 '<div style="display:inline-block">' . "\n\t\n\t\t" . '<a class="input" href="#nogo">'
			 . "\n\t\t\t" . '<span class="correction"> 0<br />' . "\n\t\t\t" . '</span>' . "\n\t\t\t"
			 . '<span class="border correct">' . "\n\t\t\t\t"
			 . '<input type="text" name="7" class="numbers" title="Correct"  size="" '
			 . 'maxlength="" autocomplete="off" value="0" />'
			 . "\n\t\t\t\t\t" . '<em style=" ">▼' . "\n\t\t\t\t\t" . '</em>'
			 . "\n\t\t\t" . '</span>' . "\n\t\n" . '</div>' . "\n",
			 '0'
			]
		];
	}

	/**
	 * @dataProvider provideParseTextField
	 * @covers Question::parseTextField
	 */
	public function testParseTextField( $number, $beingCorrected, $input, $expected, $requestValue ) {
		$this->question = $this->getQuestion( $beingCorrected, 1, 2 );
		$this->question->mRequest->setVal( $number, $requestValue );
		$output = $this->question->parseTextField( $input );
		$this->assertEquals( $expected, $output );
	}

	public function provideCheckRequestOrder() {
		return [
			// Test for correct order
			[ '1 0 2 3', 3, 0 ],
			// Test for order having repeated values
			[ '3 3 2 2', 3, 1 ],
			// Test for order having more values than the number of proposals
			[ '0 1 4 3 5', 3, 1 ],
			// Test for order having less values than the number of proposals
			[ '0 1 3', 3, 1 ],
			// Test for order having value more than proposalIndex
			[ '0 1 2 4', 3, 1 ],
			// Test for order having value less than proposalIndex
			[ '0 -1 1 2', 3, 1 ]
		];
	}

	/**
	 * @dataProvider provideCheckRequestOrder
	 * @covers Question::checkRequestOrder
	 */
	public function testCheckRequestOrder( $order, $proposalIndex, $expected ) {
		$this->question = $this->getQuestion( $beingCorrected = 1, 1, 2 );
		$output = $this->question->checkRequestOrder( $order, $proposalIndex );
		$this->assertEquals( $expected, $output );
	}

}
