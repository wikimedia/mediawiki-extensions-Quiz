/**
 * ***** BEGIN LICENSE BLOCK *****
 * This file is part of Quiz.
 * Copyright © 2007 Louis-Rémi Babe. All rights reserved.
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
 * Quiz is a quiz tool for mediawiki.
 *
 * To activate this extension :
 * * Create a new directory named Quiz into the "extensions" directory of MediaWiki.
 * * Place this file and the files Quiz.i18n.php and Quiz.php there.
 * * Add this line at the end of your LocalSettings.php file :
 * wfLoadExtension( 'Quiz' );
 *
 * @file
 * @version 1.0
 * @link https://www.mediawiki.org/wiki/Extension:Quiz Documentation
 * @author Louis-Rémi Babe <lrbabe@gmail.com>
 */

( function ( mw, $ ) {
	'use strict';

	/**
	 * Shuffle questions
	 *
	 * @private
	 *
	 * @param {jQuery} $area The quiz area to shuffle.
	 */
	function shuffle( $area ) {
		var l, x, m, i, $div = $area.children(),
			questions = [];

		$div.each( function () {
			// Leave quiz text in place
			if ( questions.length > 0 || !$( this ).hasClass( 'quizText' ) ) {
				questions.push( this );
				if ( $( this ).hasClass( 'shuffle' ) || $( this ).hasClass( 'noshuffle' ) ) {
					shuffle( $( this ) );
				}
				$( this ).detach();
			}
		} );

		if ( !$area.hasClass( 'noshuffle' ) ) {
			for ( m = questions.length - 1; m >= 0; m-- ) {
				l = parseInt( Math.random() * m );
				x = questions[ m ];
				questions[ m ] = questions[ l ];
				questions[ l ] = x;
			}
		}
		for ( i = 0; i < questions.length; i++ ) {
			$area.append( questions[ i ] );
		}
	}

	/**
	 * Reassign numbering to shuffled questions
	 *
	 * @param {jQuery} $area The shuffled quiz area.
	 */
	function shuffleNumbering( $area ) {
		$area.find( '.questionId' ).each( function ( i ) {
			$( this ).text( i + 1 );
		} );
	}

	/** Prepare the quiz for "javascriptable" browsers
	 *
	 * @param {jQuery} $content The content area of the wiki page,
	 *  passed by the `wikipage.content` hook
	 */
	function prepareQuiz( $content ) {
		var $input = $content.find( 'div.quiz input' );
		// Add the possibility of unchecking radio buttons
		$input.filter( ':radio' ).dblclick( function () {
			this.checked = false;
		} );
		// Display the shuffle buttons
		$input.filter( '.shuffle' ).click( function () {
			shuffle( $( this.form ).find( 'div.quizQuestions' ) );
			shuffleNumbering( $( this.form ).find( 'div.quizQuestions' ) );
		} );
		// Display the reset button
		$input.filter( '.reset' ).click( function () {
			this.form.quizId.value = '';
			this.form.submit();
		} );
		// Correct the bug of IE6 on textfields
		// TODO: Is it still necessary? (IE<10 has no JS at all)
		if ( document.body.style.maxHeight === undefined ) {
			$input.filter( '.numbers, .words' ).parent()
				.click( function () {
					$( this ).parent().children( ':first' ).css( {
						display: 'inline',
						position: 'absolute',
						marginTop: '1.7em'
					} );
				} )
				.mouseout( function () {
					$( this ).parent().children( ':first' ).css( 'display', 'inline' );
				} );
		}
		$input.filter( '.numbers, .words' ).keydown( function () {
			if ( this.form.shuffleButton ) {
				this.form.shuffleButton.disabled = true;
			}
		} );
		$input.filter( '.check' ).click( function () {
			if ( this.form.shuffleButton ) {
				this.form.shuffleButton.disabled = true;
			}
		} );
		// Disable submit button while previewing the quiz as submitting would start a POST request
		// without the edit data.
		// This should be done after full page load as #editform may be outside $content.
		// TODO: Use AJAX for quiz correction so that it can be corrected also while editing
		$( function () {
			if ( $( '#editform' ).length ) {
				$input.filter( ':submit' ).prop( 'disabled', true );
			}
		} );
	}

	mw.hook( 'wikipage.content' ).add( prepareQuiz );
}( mediaWiki, jQuery ) );
