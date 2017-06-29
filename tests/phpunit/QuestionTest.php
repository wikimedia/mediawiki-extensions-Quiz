<?php

class QuestionTest extends MediaWikiLangTestCase {

	private $parser;
	private $question;
	private $inputId;
	private $request;

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
		return new Question( $beingCorrected, $caseSensitive, $questionId, $this->parser );
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
			[ '1', 'Option A | Option B | Option C',
			 [ '<tr class="categories">'. "\n"
			 . '<th>Option A </th><th> Option B </th><th> Option C</th></tr>' . "\n",
			 '`^([+-]) ?([+-])? ?([+-])? ?(.*)`',
			 'NA'
			 ]
			],
			[ '0', 'Option A | Option B | Option C',
			 [ '<tr class="categories">'. "\n"
			 . '<th>Option A </th><th> Option B </th><th> Option C</th></tr>' . "\n",
			 '`^([+-]) ?([+-])? ?([+-])? ?(.*)`',
			 ''
			 ]
			],
			[ '1', '| B | C',
			 [ '<tr class="categories">'. "\n"
			 . '<th>???</th><th> B </th><th> C</th></tr>' . "\n",
			 '`^([+-]) ?([+-])? ?([+-])? ?(.*)`',
			 'error'
			 ]
			],
			[ '0', '| B | C',
			 [ '<tr class="categories">'. "\n"
			 . '<th>???</th><th> B </th><th> C</th></tr>' . "\n",
			 '`^([+-]) ?([+-])? ?([+-])? ?(.*)`',
			 'error'
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
			[ '1', '1', [ '{ Stageira | Plato | Greece }', 'Stageira | Plato | Greece', '' ],
			 '<div style="display:inline-block">' . "\n\t\n\t\t" . '<a class="input" href="#nogo">'
			 . "\n\t\t\t" . '<span class="correction"> Stageira<br />Plato<br />Greece<br />'
			 . "\n\t\t\t" . '</span>' . "\n\t\t\t" . '<span style="border-left:3px solid #2834FF;">'
			 . "\n\t\t\t\t"
			 . '<input type="text" name="1" title="Not answered"  size="" '
			 . 'maxlength="" autocomplete="off" value="" />'
			 . "\n\t\t\t\t\t" . '<em style=" ">▼' . "\n\t\t\t\t\t" . '</em>' . "\n\t\t\t"
			 . '</span>' . "\n\t\n" . '</div>' . "\n",
			 ''
			],
			[
			 '2', '0', [ '{ Stageira | Plato | Greece }', 'Stageira | Plato | Greece', '' ],
			 '<div style="display:inline-block">' . "\n\t\n\t\t" . '<a class="input" href="#nogo">'
			 . "\n\t\t\t" . '<span class="correction">' . " \n\t\t\t" . '</span>' . "\n\t\t\t"
			 . '<span style="">' . "\n\t\t\t\t"
			 . '<input type="text" name="2" title=""  size="" maxlength="" autocomplete="off" value="" />'
			 . "\n\t\t\t\t\t" . '<em style="display: none">' . "\n\t\t\t\t\t" . '</em>' . "\n\t\t\t"
			 . '</span>' . "\n\t\n" . '</div>' . "\n",
			 ''
			],
			[
			 '3', '1', [ '{ Plato (i) _6 }', 'Plato (i)', '_6', '6' ],
			  '<div style="display:inline-block">' . "\n\t\n\t\t" . '<a class="input" href="#nogo">'
			 . "\n\t\t\t" . '<span class="correction"> Plato<br />' . "\n\t\t\t" . '</span>' . "\n\t\t\t"
			 . '<span style="border-left:3px solid #2834FF;">' . "\n\t\t\t\t"
			 . '<input type="text" name="3" title="Not answered"  size="4" '
			 . 'maxlength="6" autocomplete="off" value="" />'
			 . "\n\t\t\t\t\t" . '<em style=" ">▼' . "\n\t\t\t\t\t" . '</em>'
			 . "\n\t\t\t" . '</span>' . "\n\t\n" . '</div>' . "\n",
			 ''
			],
			[ '4', '1', [ '{ Stageira | Plato | Greece }', 'Stageira | Plato | Greece', '' ],
			 '<div style="display:inline-block">' . "\n\t\n\t\t" . '<a class="input" href="#nogo">'
			 . "\n\t\t\t" . '<span class="correction"> Stageira<br />Plato<br />Greece<br />'. "\n\t\t\t"
			 . '</span>' . "\n\t\t\t" . '<span style="border-left:3px solid #1FF72D;">' . "\n\t\t\t\t"
			 . '<input type="text" name="4" title="Right"  size="" '
			 . 'maxlength="" autocomplete="off" value="Greece" />'
			 . "\n\t\t\t\t\t" . '<em style=" ">▼' . "\n\t\t\t\t\t" . '</em>' . "\n\t\t\t" . '</span>'
			 . "\n\t\n" . '</div>' . "\n",
			 'Greece'
			],
			[
			 '5', '1', [ '{ Plato (i) _6 }', 'Plato (i)', '_6', '6' ],
			 '<div style="display:inline-block">' . "\n\t\n\t\t" . '<a class="input" href="#nogo">'
			 . "\n\t\t\t" . '<span class="correction"> Plato<br />' . "\n\t\t\t" . '</span>' . "\n\t\t\t"
			 . '<span style="border-left:3px solid #F74245;">' . "\n\t\t\t\t"
			 . '<input type="text" name="5" title="Wrong"  size="4" '
			 . 'maxlength="6" autocomplete="off" value="morethansix" />'
			 . "\n\t\t\t\t\t" . '<em style=" ">▼' . "\n\t\t\t\t\t" . '</em>'
			 . "\n\t\t\t" . '</span>' . "\n\t\n" . '</div>' . "\n",
			 'morethansix'
			],

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

}
