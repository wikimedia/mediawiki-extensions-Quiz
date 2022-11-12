<?php

namespace MediaWiki\Extension\Quiz\Tests;

use MediaWikiLangTestCase;
use Parser;
use ParserOptions;
use SpecialPage;

abstract class QuizTestCase extends MediaWikiLangTestCase {
	/** @var Parser */
	protected Parser $parser;

	protected function setUp(): void {
		parent::setUp();

		$this->setMwGlobals( 'wgUsePigLatinVariant', false );

		$options = new ParserOptions( $this->getTestUser()->getUser() );
		$title = SpecialPage::getTitleFor( 'Blankpage', '/dummy by Quiz' );
		$this->parser = $this->getServiceContainer()->getParser();
		$this->parser->startExternalParse( $title, $options, Parser::OT_PLAIN, true );
	}
}
