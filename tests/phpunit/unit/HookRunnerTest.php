<?php

namespace MediaWiki\Extension\Quiz\Tests\Unit;

use MediaWiki\Extension\Quiz\Hooks\HookRunner;
use MediaWiki\Tests\HookContainer\HookRunnerTestBase;

/**
 * @covers \MediaWiki\Extension\Quiz\Hooks\HookRunner
 */
class HookRunnerTest extends HookRunnerTestBase {

	public static function provideHookRunners() {
		yield HookRunner::class => [ HookRunner::class ];
	}
}
