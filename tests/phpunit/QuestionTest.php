<?php

class QuestionTest extends MediaWikiLangTestCase {

	/**
	 * @var Parser $parser
	 */
	private $parser;

	/**
	 * @var Question $question
	 */
	private $question;

	protected function setUp() {
		parent::setUp();
		global $wgParser;
		$options = new ParserOptions();
		$wgParser = $this->getParser();
		$title = $wgParser->getTitle();
		$this->parser = &$wgParser;
		$this->parser->startExternalParse( $title, $options, 'text', true );
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

	private function getQuestion( $beingCorrected, $caseSensitive, $questionId ) {
		// Randomly generate shuffle parameter value
		$shuffle = rand( 0, 100 ) % 2 == 0 ? 0 : 1;
		return new Question( $beingCorrected, $caseSensitive, $questionId, $shuffle, $this->parser );
	}

	private function getRequest() {
		global $wgRequest;
		return $wgRequest;
	}

	public function testGetState() {
		$this->question = $this->getQuestion( 1, 1, 2 );
		$state = $this->question->getState();
		$this->assertThat(
			$state,
			$this->logicalOr(
				$this->equalTo( 'right' ),
				$this->equalTo( 'error' ),
				$this->equalTo( 'NA' ),
				$this->equalTo( 'wrong' )
			)
		);
	}

	public function provideSetState() {
		return [
			[ 'NA', 'error', 'error' ],
			[ 'NA', 'na_wrong', 'na_wrong' ],
			[ 'na_right', 'na_wrong', 'na_wrong' ],
			[ 'na_wrong', 'na_right', 'na_wrong' ],
			[ 'right', 'na_wrong', 'wrong' ],
			[ 'na_wrong', 'right', 'wrong' ],
			[ 'error', 'na_wrong', 'error' ]
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
		$this->assertEquals( $state, $expected );
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
		$this->assertEquals( $this->question->mType, $expected[0] );
		$this->assertEquals( $this->question->mCoef, $expected[1] );
	}

	public function provideParseHeader() {
		return [
			[ '0', 'Question '."\n".'|type="[]"', 'Question' ],
			[ '1', 'Question '."\n".'|type="[]"', 'Question' ],
			[ '0', 'Sample Question '."\n".'|type="{}" coef="3"', 'Sample Question' ],
			[ '1', 'Sample Question '."\n".'|type="{}" coef="3"', 'Sample Question' ]
		];
	}

	/**
	 * @dataProvider provideParseHeader
	 * covers Question::parseHeader
	 */
	public function testParseHeader( $beingCorrected, $input, $expected ) {
		$this->question = $this->getQuestion( $beingCorrected, 1, 2 );
		$header = $this->question->parseHeader( $input );
		$this->assertEquals( $header, $expected );
	}

	public function provideParseCategories() {
		return [
			// Test case when Question is being corrected and input has 3 Categories
			[ '1', 'Option A | Option B | Option C',
			 [ '<tr class="categories">'. "\n"
			 . '<th>Option A </th><th> Option B </th><th> Option C</th></tr>' . "\n",
			 '`^([+-]) ?([+-])? ?([+-])? ?(.*)`',
			 'NA'
			 ]
			],
			// Test case when Question is not being corrected and input has 3 Categories
			[ '0', 'Option A | Option B | Option C',
			 [ '<tr class="categories">'. "\n"
			 . '<th>Option A </th><th> Option B </th><th> Option C</th></tr>' . "\n",
			 '`^([+-]) ?([+-])? ?([+-])? ?(.*)`',
			 ''
			 ]
			],
			// Test case when Question is being corrected and input has syntax error
			[ '1', '| B | C',
			 [ '<tr class="categories">'. "\n"
			 . '<th>???</th><th> B </th><th> C</th></tr>' . "\n",
			 '`^([+-]) ?([+-])? ?([+-])? ?(.*)`',
			 'error'
			 ]
			],
			// Test case when Question is not being corrected and input has syntax error
			[ '0', '| B | C',
			 [ '<tr class="categories">'. "\n"
			 . '<th>???</th><th> B </th><th> C</th></tr>' . "\n",
			 '`^([+-]) ?([+-])? ?([+-])? ?(.*)`',
			 'error'
			 ]
			],
			// Test case when Question is not being corrected and input has Categories with link
			[ '0', 'Option A | [[Article name | Option B]] | Option C',
			 [ '<tr class="categories">'. "\n"
			 . '<th>Option A </th><th> <!--LINK\'" 0:0--> </th><th> Option C</th></tr>' . "\n",
			 '`^([+-]) ?([+-])? ?([+-])? ?(.*)`',
			 ''
			 ]
			],
			// Test case when Question is being corrected and input has Categories with link
			[ '1', 'Option A | [[Article name | Option B]] | Option C',
			 [ '<tr class="categories">'. "\n"
			 . '<th>Option A </th><th> <!--LINK\'" 0:0--> </th><th> Option C</th></tr>' . "\n",
			 '`^([+-]) ?([+-])? ?([+-])? ?(.*)`',
			 'NA'
			 ]
			],
			// Test case when Question is not being corrected and input has Categories with template
			[ '0', 'Option A | {{Template name | url=http://www.example.com}} | Option C',
			 [ '<tr class="categories">'. "\n"
			 . '<th>Option A </th><th> {{Template name | url=<a rel="nofollow" ' .
			 'class="external free" href="http://www.example.com}}">http://www.example.com}}' .
			 '</a> </th><th> Option C</th></tr>' . "\n",
			 '`^([+-]) ?([+-])? ?([+-])? ?(.*)`',
			 ''
			 ]
			],
			// Test case when Question is being corrected and input has Categories with template
			[ '1', 'Option A | {{Template name | url=http://www.example.com}} | Option C',
			 [ '<tr class="categories">'. "\n"
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
		$this->assertEquals( $output, $expected[0] );
		$pattern = $this->question->mProposalPattern;
		$this->assertEquals( $pattern, $expected[1] );
		$state = $this->question->getState();
		$this->assertEquals( $state, $expected[2] );
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
			 . "\n\t\t\t" . '<span class="correction"> Stageira<br />Plato<br />Greece<br />'. "\n\t\t\t"
			 . '</span>' . "\n\t\t\t" . '<span class="border right">' . "\n\t\t\t\t"
			 . '<input type="text" name="4" class="words" title="Right"  size="" '
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
			 . '<span class="border wrong">' . "\n\t\t\t\t"
			 . '<input type="text" name="5" class="words" title="Wrong"  size="4" '
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
			 . '<span class="border right">' . "\n\t\t\t\t"
			 . '<input type="text" name="7" class="numbers" title="Right"  size="" '
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
		$this->assertEquals( $output, $expected );
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
		$this->assertEquals( $output, $expected );
	}

}
