/*
 * CQPweb: a user-friendly interface to the IMS Corpus Query Processor
 * Copyright (C) 2008-today Andrew Hardie and contributors
 *
 * See http://cwb.sourceforge.net/cqpweb.php
 *
 * This file is part of CQPweb.
 * 
 * CQPweb is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * CQPweb is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */



/**
 * This file contains stuff that always happens. It is automatically included,
 * and does not need to be explicitly passed to the PHP header-printing function.
 *
 * It should be included in every single CQPweb page served.
 * It assumes that jQuery has already been included.
 * 
 * Currently it contains functions supporting modal dialogs (i.e. centred popup boxes, with the window behind greyed out.)
 * 
 * Also the redirector function for the submit dropdowns. 
 */






/*
 * ================================
 * ALWAYS-INCLUDED FUNCTION LIBRARY
 * ================================
 */


/**
 * Debug functions should always have short names :)
 */
function clog()
{
	for (var i = 0; i < arguments.length; i++)
		console.log(arguments[i]);
}


///* stop this from beign submitted, but re-enable in case of navigation back here. */
// TODO use this instead of rep[eatig everytwherel.; 
function disable_input_and_schedule_re_enable(form_node, input_name)
{
	var i = $(form_node).find('[name="' + input_name + '"]');
	i.prop("disabled", true);
	$(window).on("pagehide", function () { i.prop("disabled", false); });
}



function adjust_form_on_submit(e)
{
	var form = $(e.target); 
	var choice_selector = form.find('[name="menuChoice"]');
	var menu_choice = choice_selector.val();
//	
//	/* stop this from beign submitted, but re-enable in case of navigation back here. */
//	choice_selector.prop("disabled", true);
//	$(window).on("pagehide", function () { choice_selector.prop("disabled", false); });
	
	disable_input_and_schedule_re_enable(e.target, "menuChoice");

	/* same everywhere */
	if ("newQuery" == menu_choice)
	{
		e.preventDefault();
		window.location.href = "index.php";
	}

	/* there is one function for each form that takes an adjust on submit action. */
	switch(e.target.id)
	{
	case 'contextMainDropdown':
		/* if "textInfo", change destination to textmeta.php; otherwise remove text input. */
		if (menu_choice == 'textInfo')
		{
			e.preventDefault();
			window.location.href = "textmeta.php?text="+ form.find('[name="text"]').val();
		}
		else
		{
			form.find('[name="text"]').prop("disabled", true);
			$(window).on("pagehide", function () { form.find('[name="text"]').prop("disabled", false); });
		}

		/* increase or decrease context */
		if (menu_choice == 'moreContext' || menu_choice == 'lessContext')
		{
			var size = parseInt(form.find('[name="contextSize"]').val());
			if (menu_choice == "moreContext")
				size += 100;
			if (menu_choice == "lessContext")
				size -= 100;
			form.find('[name="contextSize"]').val( size );
		}

		/* go back to the concordance */
		if (menu_choice == 'backFromContext')
		{
			form.attr("action", "concordance.php");
			form.find("input").not('[name="qname"]').remove();
		}

		break;
		/* end of actions for extended-context dropdown. */


	case 'concordanceMainDropdown':
		/* majority of options need this, so always do it. */
		e.preventDefault();

		switch(menu_choice)
		{
		case 'thin':
			//popup thgin form.
			greyout_and_popup_form("thin-control");
			break;
		case 'breakdown':
			// set location to breakdown w/ parameters.
			break;
		case 'distribution':
			//set location to distribution w/ paramters
			break;
		case 'dispersion':
			// TODO set location to dispersion w/ parameters.
			break;
		case 'sort':
		//	$_GET['program'] = 'sort';
		//	$_GET['newPostP'] = 'sort';
		//	$_GET['newPostP_sortPosition'] = 1;
		//	$_GET['newPostP_sortThinTag'] = '';
		//	$_GET['newPostP_sortThinTagInvert'] = 0;
		//	$_GET['newPostP_sortThinString'] = '';
		//	$_GET['newPostP_sortThinStringInvert'] = 0;
		//	unset($_GET['pageNo']);
			// build url from here, send to concordance,php 
			break;

		case 'collocations':
			//popup colloc form
			greyout_and_popup_form("colloc-control");
			break;
		case 'download-conc':
			// set parameters, and then go to download-conc
			break;

		case 'categorise':
		//	if (empty($_GET['sqAction']))
		//		$_GET['sqAction'] = 'enterCategories';
			// set parameters in get, thne fo to savequery?sqAction=enterCategories
			break;
		case 'categorise-do':
			// set parameters, go to savequery-act
			break;

		case 'savequery': 
// TODO, it's currently saveHits. But that's not good. So change it. 
			/* we use a separate UI, rather than a popup as for thin / colloc, because the same UI 
			 * is re-used for errors e.g. in the specification of a save name, AND for catqueries.
			 */
			// set parameters in get, thne fo to savequery?sqAction=
			break;

		case 'customPostprocess':
		//	$_GET['newPostP'] = $custom_pp_parameter;
		//	unset($_GET['pageNo']);
		//	require("../lib/concordance-ui.php");
			break;
		}

		break;
		/* end of actions for main concordance dropdown. */


//	case '':
//	case '':
	}

	return true;
}


/**
 * This function can be put on a form as its "submit" action. 
 * It uses the "data-redirect-map" attribute to change the target of the form,
 * depending on the value of the input named in "data-redirect-input".
 */
function redirect_form_by_input(e)
{
	// temp code
	if (e.target["redirect"])
		if ("newQuery" == e.target["redirect"].val)
			window.location.href = "index.php";
	return true;
// ============= end temp code.
	
	var form = $(e.target);
	
	var str = form.attr("data-redirect-map");
	
	if (str)
	{
		var map = JSON.parse(str);
		
		if (e.target["redirect"])
		{
			var val = e.target["redirect"].val;
			
			/* we avoid bothering including newQuery in the map. */ 
			if ("newQuery" == e.target["redirect"].val)
				window.location.href = "index.php";
			
			if (map[e.target["redirect"].val])
			{
				form.prop("target", map[e.target["redirect"].val]);
	// append extra data as hidden inputs to the node?? e.g. see "sort" - it implies a bunch of other params. 
	// yup, this is needed. see redirect script.
			}
			//TODO not finshed yet...
			
			/* Dump the redirect. */
			
			form.find('input[name="redirect"]').remove();
		}
	}
	
	return true;
}


/**
 * Adds a keypress checker to an input, which affects a specified element.  
 * If desired, should be called in a setupblock: $(  function () {} );  
 * 
 * input_id = which input to check the keypresses of.
 * target_selector = element(s) to show or hide. Full selector so either #id or .class can be used.
 */
function add_check_caps_lock_to_input(input_id, target_selector)
{
	var jq = $("#" + input_id);
	
	/* on key down: check for the CapsLock key. */
	jq.keydown(
			function (e)
			{
				var notice =  $(target_selector);
				
				/* code for CapsLock is 20 */
				if (20 == e.which)
				{
					if ("none" == notice.css("display"))
						notice.css( "display", "inline-block" );
					else
						notice.css( "display", "none" ); 
				}

			}
		);

	/* on key press: check for CapsLock as modifier. */
	jq.keypress( 
			function (e)
			{
				if (e.originalEvent.getModifierState && e.originalEvent.getModifierState("CapsLock"))
					$(target_selector).css( "display", "inline-block" );
				else
					$(target_selector).css( "display", "none" ); 
				return;
			}
		);
}





/*
 * =======
 * GREYOUT
 * =======
 */



function greyout_on()
{
	var d = $("#div_for_greyout");

	if (d.length < 1)
	{
		greyout_setup();
		d = $("#div_for_greyout");
	}

	d.show();
}


function greyout_off()
{
	$("#div_for_greyout").hide();
}

function greyout_z_index()
{
	return Number($("#div_for_greyout").css("z-index"));
}


function greyout_setup()
{
	if (window.cqpweb_greyout_has_been_set_up)
		return;

	/* this styling makes a div grey-out the whole browser window. */
	var stylecode = "style=\"" +
		/* its size */
		"display:none; position:fixed; z-index:1000; top:0; left:0; height:100%; width:100%;" + 
		/* its coloring (note use of rgba so as not to affect children; note also #888888 fallback for old browsers) */
		" background-color:#888888; background-color: rgba(0, 0, 0, 0.5)" +
		"\""
		;

	/* create and insert.... note that it starts off as hidden, see style above. */
	$("<div id=\"div_for_greyout\" " + stylecode + "></div>").appendTo($("body"));

	window.cqpweb_greyout_has_been_set_up = true;
}

function greyout_and_throbber(msg)
{
	greyout_on();

	var pfx = $("#CQPwebGlobalApplicationData").attr("data-urlPrefix");

	var g_content = $(`
		<table id="table_for_greyout_content" class="concordtable greyoutContent">
			<tr>
				<td class="concordgeneral" align="center">
					<p>
						${msg ? msg : "CQPweb is processing your request. Please wait."}
					</p>
					<p>
						<img src="${pfx}css/img/throbber.gif">
					</p>
				</td>
			</tr>
		</table>
		`);

	g_content.css({
			position: 'absolute',
			left: '50%',
			top: '50%',
			transform: 'translate(-50%, -50%)',
			'background-color': 'white'   /* i.e. provide a white backdrop over the greyout, like the base layer of the page. */
		});

	g_content.appendTo($("#div_for_greyout"));

//	window.onpagehide = function () { greyout_and_throbber_off(); }
	$(window).on("pagehide", function () { greyout_and_throbber_off(); });
}

function greyout_and_throbber_off()
{
	$("#table_for_greyout_content").remove();
	greyout_off();
}


function greyout_and_acknowledge(msg, close_url)
{
	greyout_and_throbber_off();
	greyout_on();
	
	var g_content = $(`
		<table id="table_for_greyout_content" class="concordtable greyoutContent">
			<tr>
				<td class="concordgeneral" align="center">
					<p class="spacer">&nbsp;</p>
					<p>${msg}</p>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
			<tr>
				<td class="concordgrey" align="center">
					<p>
						<a class="menuItem" href="" id="link_to_close_greyout">[Close]</a>
					</p>
				</td>
			</tr> 
		</table>
		`);

	g_content.css({
			position: 'absolute',
			left: '50%',
			top: '50%',
			transform: 'translate(-50%, -50%)',
			'background-color': 'white'  
		});
	//TODO have this as a style for class "greyoutContent" so as not to repeat it here/ 
	
	g_content.appendTo($("#div_for_greyout"));
	
	var l = $("#link_to_close_greyout");
	
	l.css({ cursor: 'pointer' });
	
	l.get(0).focus();
	
	/* EITHER remove the greyout, if no "goto" url was supplied; OR navigate to that url. */ 
	if (undefined == close_url || null === close_url || '' === close_url || false === close_url)
		l.click(function (e) {greyout_off(); e.stopImmediatePropagation(); $("#table_for_greyout_content").remove(); return false; });
	else
		l.click(function () {window.location.href = close_url; $("#table_for_greyout_content").remove(); return false; });
}


/**
 * 
 * @param question
 * @param yes_word
 * @param no_word
 * @param answer       Can be: (a) nothing, (b) string id of an element whose value will be set true/false depending on yes/no;
 *                     (c) a callback to which will be passed true or false.
 */
function greyout_and_yes_no_question(question, yes_word, no_word, answer = false)
{
	greyout_on();
	
	var g_content = $(`
		<table id="table_for_greyout_content" class="concordtable greyoutContent">
			<tr>
				<td class="concordgeneral" colspan="2" align="center">
					<p class="spacer">&nbsp;</p>
					<p>${question}</p>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral" align="center">
					<p>
						<a class="menuItem" href="#" id="greyout_link_to_say_yes">${yes_word}</a>
					</p>
				</td>
				<td class="concordgeneral" align="center">
					<p>
						<a class="menuItem" href="#" id="greyout_link_to_say_no">${no_word}</a>
					</p>
				</td>
			</tr>
		</table>
		`);

	g_content.css({
		position: 'absolute',
		left: '50%',
		top: '50%',
		transform: 'translate(-50%, -50%)',
		'background-color': 'white'
	} );
	//TODO have this as a style for class "greyoutContent" so as not to repeat it here/ 
	
	g_content.appendTo($("#div_for_greyout"));
	
	if (false === answer)
		;
	else if ("string" == typeof(answer))
	{
		$('#greyout_link_to_say_no' ).click( function () { $("#"+answer).val(false); $("#table_for_greyout_content").remove(); greyout_off(); } );
		$('#greyout_link_to_say_yes').click( function () { $("#"+answer).val(true);  $("#table_for_greyout_content").remove(); greyout_off(); } );
	}
	else
	{
		$('#greyout_link_to_say_no' ).click( function () { answer(false); } );
		$('#greyout_link_to_say_yes').click( function () { answer(true);  } );
	}
}

function greyout_are_you_sure(e)
{
	e.preventDefault();
	
	var question = $(e.target).attr('data-areYouSureQ');
	
	if (undefined === question)
		question = "Are you sure you want to do this?";
	
	greyout_and_yes_no_question(
			question, 
			"Yes", 
			"No",
			function (answer)
			{
				$("#table_for_greyout_content").remove();
				
				if (answer)
				{
					greyout_and_throbber();
					switch (e.target.tagName)
					{
					case "FORM":
						e.target.submit();
						break;
					case "A":
						window.location.href = e.target.href;
						break;
					default:
						clog("Bad tag name in greyout_are_you_sure() answer callback!");
						break;
					}
				}
				else
					greyout_off();
				
				return false;
			}
	);
	return false;
}



/* for collocation control, thin contorl, &c. */
function greyout_and_popup_form(popup_div_id)
{
	greyout_on();

	var g_content = $("#" + popup_div_id);

	g_content.css( {
		position: 'absolute',
		left: '50%',
		top: '50%',
		transform: 'translate(-50%, -50%)',
		'background-color': 'white'
		} );
	//TODO have this as a style for class "greyoutContent" so as not to repeat it here/  
	// see note above re: duplocation. Here, we also need:
	g_content.css( { display: 'block' } );

	g_content.appendTo($("#div_for_greyout"));

	// TODO is it necessary to add a fucntion to the "cancel" button? OR can it simply be given onclick="greyout_off();"?
}






/*
 * ======================================
 * ALWAYS-RUN STARTUP CODE (on jQ launch)
 * ======================================
 */

$( function() {

// note - the following preloads the throbber gif, cos otherwise Firefox doesn't load on need. So, it should be only used if the user hasMozilla/ Gecko/ Firefox/. 
$("body").append($('<img style="display:none;" src="../css/img/throbber.gif">'));
// TODO integrate this a bit better... e.g. by looking in the data-store obvject for
// throbber-preload. 

	
	/* apply bog-standard greyout and throbber to any form that has class greyoutOnSubmit */  
	$("form.greyoutOnSubmit").submit(function () { greyout_and_throbber(); return true; });
	$("a.greyoutOnSubmit"   ).click( function () { greyout_and_throbber(); return true; });
	/* elements that get something specific via an id or class should NOT also be greyoutOnSubmit. */
	
	/* apply standard greyout and "are you sure" to any form that has class greyoutAreYouSure */  
	$("form.greyoutAreYouSure").submit(function (e) { greyout_are_you_sure(e); return true; });
	$("a.greyoutAreYouSure"   ).click( function (e) { greyout_are_you_sure(e); return true; });
	
	/* put the redirector onto forms that declare they need it. */
	$("form.hasRedirection").submit(redirect_form_by_input);

	/*
	 * This sets up all URL-type inputs to change to type text if an internal embedded page (ui=embed) is the content.
	 * That makes these be allowed as URLs, even though browsers won;t recongnise them as URLs.
	 */
	 $("input[type=url]").keyup(
		function (e)
		{
			if (/^index.php\?ui=embed&id=\d+$/.test(e.target.value))
				e.target.type = "text" ; 
			else
				e.target.type = "url" ;
			return true;
		}
	);
	
} );




