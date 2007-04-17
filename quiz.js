/**
 * ***** BEGIN LICENSE BLOCK *****
 * This file is part of Quiz.
 * Copyright (c) 2007 Louis-R�mi BABE. All rights reserved.
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
 * @version 0.8.1
 * @link http://www.mediawiki.org/wiki/Extension:Quiz
 * 
 * @author BABE Louis-Remi <lrbabe@gmail.com>
 */
 
(function() {
	/**
	 * Shuffle questions
	 */
	function shuffle(input) {
		var quiz = input.parentNode.parentNode.parentNode.parentNode.parentNode;
		var div = quiz.getElementsByTagName('div');
		var questions = new Array();
		var k = 0;
		for(var i=0; i<div.length; ++i) {
			if(div[i].className == "quizQuestions") {
				var quizQuestions = div[i];
			} 
			if(div[i].className == "question") {
				questions[k] = div[i];
				k++;
			}
		}
		var quizHTML = "";
		for(var l, x, m = questions.length; m; l = parseInt(Math.random() * m), x = questions[--m], questions[m] = questions[l], questions[l] = x);
		for(var o=0; o<questions.length; ++o) {
			quizHTML += "<div class='question'>" + questions[o].innerHTML + "</div>";		
		}
		quizQuestions.innerHTML = quizHTML;
	}
	
	/**
	 * Prepare the quiz for "javascriptable" browsers
	 */
	function prepareQuiz() {
		var bodyContentDiv = document.getElementById('bodyContent').getElementsByTagName('div');
		for(var i=0; i<bodyContentDiv.length; ++i) {
			if(bodyContentDiv[i].className == 'quiz') {
				var input = bodyContentDiv[i].getElementsByTagName('input');			
				for(var j=0; j<input.length; ++j) {
					// Add the possibility of unchecking radio buttons
					if(input[j].type == "radio") {
						input[j].ondblclick = function() {
							this.checked = false;
						};
					}
					// Displays the shuffle buttons.
					else if(input[j].className == "scriptshow") {
						input[j].style.display = "inline";
						input[j].onclick = function() { shuffle(this); };
					} 
					// Correct the bug of ie6 on textfields
					else if((input[j].className == "numbers" || input[j].className == "words") 
					&& typeof document.body.style.maxHeight == "undefined" ) {
						input[j].parentNode.onclick = function() { 
							this.parentNode.firstChild.style.display = "inline";
							this.parentNode.firstChild.style.position = "absolute";
							this.parentNode.firstChild.style.marginTop = "1.7em";
						};
						input[j].parentNode.onmouseout = function() {
							this.parentNode.firstChild.style.display = "none";
						};
					}
				}
			}
		}
	}

	function addLoadListener(func) {
		if (window.addEventListener) {
			window.addEventListener("load", func, false);
		} else if (document.addEventListener) {
			document.addEventListener("load", func, false);
		} else if (window.attachEvent) {
			window.attachEvent("onload", func);
		}
	}

	if (document.getElementById && document.createTextNode) {
		addLoadListener(prepareQuiz);
	}
})();