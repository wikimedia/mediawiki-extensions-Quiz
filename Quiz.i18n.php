<?php
/**
 * ***** BEGIN LICENSE BLOCK *****
 * This file is part of Quiz.
 * Copyright (c) 2007 Louis-Rémi BABE. All rights reserved.
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
 * * Create a new directory named quiz into the directory "extensions" of mediawiki.
 * * Place this file and the files Quiz.i18n.php and quiz.js there.
 * * Add this line at the end of your LocalSettings.php file :
 * require_once 'extensions/quiz/Quiz.php';
 * 
 * @version 0.7b
 * @link http://www.mediawiki.org/wiki/Extension:Quiz
 * 
 * @author BABE Louis-Rémi <lrbabe@gmail.com>
 */

/**
 * Messages list.
 */
$wgQuizMessages = array(
 	'en' => array(
 		'quiz_addedPoints' 		=> "Point(s) added for a correct answer",
		'quiz_cutoffPoints'		=> "Point(s) subtracted for a wrong answer",
		'quiz_ignoreCoef'		=> "Ignore the questions' coefficients",
		'quiz_shuffle'			=> "Shuffling the questions",
		'quiz_colorRight'		=> "Right",
		'quiz_colorWrong'		=> "Wrong",
		'quiz_colorNA'			=> "Not answered",
		'quiz_colorError'		=> "Syntax error",
		'quiz_correction'		=> "Correction",
		'quiz_score'			=> "Your score is $1 / $2"
 	),
 	'de' => array(
 		'quiz_addedPoints' 		=> "Pluspunkte für eine richtige Antwort",
		'quiz_cutoffPoints'		=> "Minuspunkte für eine falsche Antwort",
		'quiz_ignoreCoef'		=> "Ignoriere den Fragen-Koeffizienten",
		'quiz_shuffle'			=> "Fragen mischen",
		'quiz_colorRight'		=> "Richtig",
		'quiz_colorWrong'		=> "Falsch",
		'quiz_colorNA'			=> "Nicht beantwortet",
		'quiz_colorError'		=> "Syntaxfehler",
		'quiz_correction'		=> "Korrektur",
		'quiz_score'			=> "Punkte: $1 / $2"
 	),
 	'fr' => array(
 		'quiz_addedPoints' 		=> "Point(s) ajouté(s) pour une réponse juste",
		'quiz_cutoffPoints'		=> "Point(s) retiré(s) pour une réponse erronée",
		'quiz_ignoreCoef'		=> "Ignorer les coefficients des questions",
		'quiz_shuffle'			=> "Mélanger les questions",
		'quiz_colorRight'		=> "Juste",
		'quiz_colorWrong'		=> "Faux",
		'quiz_colorNA'			=> "Non répondu",
		'quiz_colorError'		=> "Erreur de syntaxe",
		'quiz_correction'		=> "Correction",
		'quiz_score'			=> "Votre score est $1 / $2"
 	),
 );
 ?>