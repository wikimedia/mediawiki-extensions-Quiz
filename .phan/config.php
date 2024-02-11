<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

// To migrate later
$cfg['suppress_issue_types'][] = 'MediaWikiNoBaseException';

return $cfg;
