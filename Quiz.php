<?php
/**
 * ***** BEGIN LICENSE BLOCK *****
 * This file is part of Quiz.
 * Copyright © 2007 Louis-Rémi BABE. All rights reserved.
 *
 * Quiz is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Quiz is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Quiz; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * ***** END LICENSE BLOCK *****
 *
 * Quiz is a quiz tool for MediaWiki.
 *
 * To activate this extension, add the following to your LocalSettings.php :
 * require_once( "$IP/extensions/Quiz/Quiz.php" );
 *
 * @file
 * @ingroup Extensions
 * @version 1.1.0
 * @author Louis-Rémi Babe <lrbabe@gmail.com>
 * @link https://www.mediawiki.org/wiki/Extension:Quiz Documentation
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This is not a valid entry point to MediaWiki.' );
}
if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'Quiz' );
	$wgMessagesDirs['QuizExtension'] = __DIR__ . '/i18n';
	/* wfWarn(
		'Deprecated PHP entry point used for Quiz extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return;
} else {
	die( 'This version of the Quiz extension requires MediaWiki 1.25+' );
}
