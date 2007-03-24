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
 * * Place this file and the files quizParameters and quiz.js there.
 * * Add this line at the end of your LocalSettings.php file :
 * require_once 'extensions/quiz/quiz.php';
 * 
 * @version 0.4b
 * @link http://www.mediawiki.org/wiki/Extension:Quiz
 * 
 * @author BABE Louis-Rémi <lrbabe@gmail.com>
 */
 
window.onload = prepareQuiz;

/**
 * Prépare le questionnaire en cachant certains éléments.
 */
function prepareQuiz() {
	// Ajoute la possibilité de décocher les boutons radio
	var bodyContentDiv = document.getElementById('bodyContent').getElementsByTagName('div');
	for(var i=0; i<bodyContentDiv.length; ++i) {
		if(bodyContentDiv[i].className == 'quiz') {
			var radioButton = bodyContentDiv[i].getElementsByTagName('input');
			for(var j=0; j<radioButton.length; ++j) {
				radioButton[j].ondblclick = function() {
					this.checked = false;
				};
			}
		}
	}	
}

