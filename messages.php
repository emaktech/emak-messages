<?php
/*
	FusionPBX
	Version: MPL 1.1

	The contents of this file are subject to the Mozilla Public License Version
	1.1 (the "License"); you may not use this file except in compliance with
	the License. You may obtain a copy of the License at
	http://www.mozilla.org/MPL/

	Software distributed under the License is distributed on an "AS IS" basis,
	WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
	for the specific language governing rights and limitations under the
	License.

	The Original Code is FusionPBX

	The Initial Developer of the Original Code is
	Mark J Crane <markjcrane@fusionpbx.com>
	Portions created by the Initial Developer are Copyright (C) 2016-2019
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/
?>
<link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700|Roboto&display=swap" rel="stylesheet">

<?php
//includes
	require_once "root.php";
	require_once "resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('message_view')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//get (from) destinations
	$sql = "SELECT destination_number FROM v_destinations ";
	$sql .= "WHERE domain_uuid = :domain_uuid ";
	$sql .= "AND destination_type_text = 1 ";
	$sql .= "AND destination_enabled = 'true' ";
	$sql .= "ORDER BY destination_number asc ";
	$parameters['domain_uuid'] = $domain_uuid;
	$database = new database;
	$rows = $database->select($sql, $parameters, 'all');
	if (is_array($rows) && @sizeof($rows)) {
		foreach ($rows as $row) {
			$destinations[] = $row['destination_number'];
		}
	}
	unset($sql, $parameters, $rows, $row);

//get self (primary contact attachment) image
	if (!is_array($_SESSION['tmp']['messages']['contact_me'])) {
		$sql = "SELECT attachment_filename AS filename, attachment_content AS image ";
		$sql .= "FROM v_contact_attachments ";
		$sql .= "WHERE domain_uuid = :domain_uuid ";
		$sql .= "AND contact_uuid = :contact_uuid ";
		$sql .= "AND attachment_primary = 1 ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
		$parameters['contact_uuid'] = $_SESSION['user']['contact_uuid'];
		$database = new database;
		$row = $database->select($sql, $parameters, 'row');
		$_SESSION['tmp']['messages']['contact_me'] = $row;
		unset($sql, $parameters, $row);
	}

//additional includes
	require_once "resources/header.php";
	require_once "resources/css/messages.css";?>
	<link href="resources/css/emoji.css" rel="stylesheet">
	<link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css" rel="stylesheet">
<?php
//launch in fullscreen when added to homescreen
echo "<meta name='mobile-web-app-capable' content='yes'>\n";

//cache self (primary contact attachment) image
	if (is_array($_SESSION['tmp']['messages']['contact_me']) && sizeof($_SESSION['tmp']['messages']['contact_me']) != 0) {
		$attachment_type = strtolower(pathinfo($_SESSION['tmp']['messages']['contact_me']['filename'], PATHINFO_EXTENSION));
		echo "<img id='src_message-bubble-image-me' style='display: none;' src='data:image/".$attachment_type.";base64,".$_SESSION['tmp']['messages']['contact_me']['image']."'>\n";
	}

//new message layer
	if (permission_exists('message_add')) {
		echo "<div id='message_new_layer' style='display: none;'>\n";
		echo "	<table cellpadding='0' cellspacing='0' border='0' width='100%' height='100%'>\n";
		echo "		<tr>\n";
		echo "			<td align='center' valign='middle'>\n";
		echo "				<form id='message_new' method='post' enctype='multipart/form-data' action='message_send.php'>\n";
		echo "				<span id='message_new_container'>\n";
		echo "					<b>".$text['label-new_message']."</b><br /><br />\n";
		echo "					<table width='100%'>\n";
		echo "						<tr>\n";
		echo "							<td class='vncell'>".$text['label-message_from']."</td>\n";
		echo "							<td class='vtable'>\n";
		if (is_array($destinations) && sizeof($destinations) != 0) {
			echo "							<select class='formfld' name='message_from' id='message_new_from' onchange=\"$('#message_new_to').trigger('focus');\">\n";
			foreach ($destinations as $destination) {
				echo "							<option value='".$destination."'>".format_phone($destination)."</option>\n";
			}
			echo "							</select>\n";
		}
		else {
			echo "							<input type='text' class='formfld' name='message_from' id='message_new_from'>\n";
		}
		echo "							</td>\n";
		echo "						</tr>\n";
		echo "						<tr>\n";
		echo "							<td class='vncell'>".$text['label-message_to']."</td>\n";
		echo "							<td class='vtable'>\n";
		echo "								<input type='text' class='formfld' name='message_to' id='message_new_to'>\n";
		echo "							</td>\n";
		echo "						</tr>\n";
		echo "						<tr>\n";
		echo "							<td class='vncell'>".$text['label-message_text']."</td>\n";
		echo "							<td class='vtable'>\n";
		echo "								<textarea class='formfld' style='width: 100%; height: 80px;' name='message_text' name='message_new_text'></textarea>\n";
		echo "							</td>\n";
		echo "						</tr>\n";
		echo "						<tr>\n";
		echo "							<td class='vncell'>".$text['label-message_media']."</td>\n";
		echo "							<td class='vtable'>\n";
		echo "								<input type='file' class='formfld' multiple='multiple' name='message_media[]' id='message_new_media'>\n";
		echo "							</td>\n";
		echo "						</tr>\n";
		echo "					</table>\n";
		echo "					<center>\n";
		echo "						<input type='reset' class='btn' style='float: left; margin-top: 15px;' value='".$text['button-clear']."' onclick=\"$('#message_new').reset();\">\n";
		echo "						<input type='button' class='btn' style='margin-top: 15px;' value='".$text['button-close']."' onclick=\"$('#message_new_layer').fadeOut(200);\">\n";
		echo "						<input type='submit' class='btn' style='float: right; margin-top: 15px;' value='".$text['button-send']."'>\n";
		echo "					</center>\n";
		echo "				</span>\n";
		echo "				</form>\n";
		echo "			</td>\n";
		echo "		</tr>\n";
		echo "	</table>\n";
		echo "</div>\n";
	}

// Message Media Layer
	echo "<div id='message_media_layer' style='display: none;'></div>\n";

// Show the content
	// Contacts Section
	echo "<div id='main-container'>\n";
	echo "	<div id='contacts-container'>\n";
	echo "		<div class='title-contacts'>\n";
	echo "			<b>".$text['title-messages']."</b>\n";
	echo "		</div>\n";
	echo "		<div class='new-message'>\n";
	if (permission_exists('message_add')) {
	echo "				<input type='button' class='btn btn_new' name='' alt='".$text['label-new_message']."' onclick=\"$('#message_new_layer').fadeIn(200); unload_thread();\" value='".$text['label-new_message']."'>\n";
	}
	echo "		</div>\n";
	echo "		<div id='contacts' class='contacts'>&middot;&middot;&middot;</div>\n";
	echo "	</div>\n";
	
	// Messages Section
	echo "	<div id='messages-container'>\n";
	echo "		<div class='title-messages'>\n";

	echo "			<div id='contact_current_name'>".$text['label-messages']."</div>\n";
	echo "			<input type='hidden' id='contact_current_number' value=''>\n";
	echo "				<a href='messages_log.php'><input type='button' class='btn btn_log' alt=\"".$text['label-log']."\" value=\"".$text['label-log']."\"></a>\n";

	echo "		</div>\n";
	echo "		<div class='thread_container'>\n";
	echo "			<div id='thread'>&middot;&middot;&middot;</div>\n";
	echo "		</div>\n";
	echo "	</div>\n";
	echo "</div>\n";

//js to load messages for clicked number
	$refresh_contacts = is_numeric($_SESSION['message']['refresh_contacts']['numeric']) && $_SESSION['message']['refresh_contacts']['numeric'] > 0 ? $_SESSION['message']['refresh_contacts']['numeric'] : 10; //default (seconds)
	$refresh_thread = is_numeric($_SESSION['message']['refresh_thread']['numeric']) && $_SESSION['message']['refresh_thread']['numeric'] > 0 ? $_SESSION['message']['refresh_thread']['numeric'] : 5; //default (seconds)
?>
<script>
	var contacts_refresh = (<?php echo $refresh_contacts; ?> * 1000);
	var thread_refresh = (<?php echo $refresh_thread; ?> * 1000);
	var timer_contacts;
	var timer_thread;

	function refresh_contacts() {
		clearTimeout(timer_contacts);
		$('#contacts').load('messages_contacts.php?sel=' + $('#contact_current_number').val(), function(){
			timer_contacts = setTimeout(refresh_contacts, contacts_refresh);
		});
	}

	function load_thread(number, contact_uuid) {
		clearTimeout(timer_thread);
		$('#thread').load('messages_thread.php?number=' + encodeURIComponent(number) + '&contact_uuid=' + encodeURIComponent(contact_uuid), function(){
			$('div#thread_messages').animate({}, 200, function() {
				$('#thread_messages').scrollTop(Number.MAX_SAFE_INTEGER); //chrome
				$('span#thread_bottom')[0].scrollIntoView(true); //others
						//note: the order of the above two lines matters!
		if( /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ) {
			if ($('#message_new_layer').is(':hidden')) {
				$('#message_text').trigger('focus');
		}
	}
				refresh_contacts();
				timer_thread = setTimeout(refresh_thread_start, thread_refresh, number, contact_uuid);
			});
		});
	}

	function unload_thread() {
		clearTimeout(timer_thread);
		$('#thread').html('<center>&middot;&middot;&middot;</center>');
		$('#contact_current_number').val('');
		$('#contact_current_name').html('');
		refresh_contacts();
	}

	function refresh_thread(number, contact_uuid, onsent) {
		$('#thread_messages').load('messages_thread.php?refresh=true&number=' + encodeURIComponent(number) + '&contact_uuid=' + encodeURIComponent(contact_uuid), function(){
			$('div#thread_messages').animate({ }, 200, function() {
				$('#thread_messages').scrollTop(Number.MAX_SAFE_INTEGER); //chrome
				$('span#thread_bottom')[0].scrollIntoView(true); //others
						//note: the order of the above two lines matters!
		if( /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ) {
			if ($('#message_new_layer').is(':hidden')) {
				$('#message_text').trigger('focus');
		}
	}
				if (onsent != 'true') {
					timer_thread = setTimeout(refresh_thread, thread_refresh, number, contact_uuid);
				}
			});
		});
	}

	function refresh_contacts_stop() {
		clearTimeout(timer_contacts);
		document.getElementById('contacts_refresh_state').innerHTML = '<img src="resources/images/refresh_paused.png" class="refresh" onclick="refresh_contacts_start();" alt="<?php echo $text['label-refresh_enable']; ?>" title="<?php echo $text['label-refresh_enable']; ?>">';
	}

	function refresh_contacts_start() {
		if (document.getElementById('contacts_refresh_state')) {
			document.getElementById('contacts_refresh_state').innerHTML = '<img src="resources/images/refresh_active.gif" class="refresh" onclick="refresh_contacts_stop();" alt="<?php echo $text['label-refresh_pause']; ?>" title="<?php echo $text['label-refresh_pause']; ?>';
			refresh_contacts();
		}
	}

	function refresh_thread_stop(number, contact_uuid) {
		clearTimeout(timer_thread);
		document.getElementById('thread_refresh_state').innerHTML = '<img src="resources/images/refresh_paused.png" class="refresh" onclick="refresh_thread_start(' + number + ', ' + contact_uuid + ');" alt="<?php echo $text['label-refresh_enable']; ?>" title="<?php echo $text['label-refresh_enable']; ?>">';
	}

	function refresh_thread_start(number, contact_uuid) {
		if (document.getElementById('thread_refresh_state')) {
			document.getElementById('thread_refresh_state').innerHTML = '<img src="resources/images/refresh_active.gif" class="refresh" onclick="refresh_thread_stop(' + number + ', ' + contact_uuid + ');" alt="<?php echo $text['label-refresh_pause']; ?>" title="<?php echo $text['label-refresh_pause']; ?>">';
			refresh_thread(number, contact_uuid);
		}
	}
	
<?php
//define form submit function
	if (permission_exists('message_add')) {
		echo "	$('#message_new').submit(function(event) {\n";
		echo "		event.preventDefault();\n";
		echo "		$.ajax({\n";
		echo "			url: $(this).attr('action'),\n";
		echo "			type: $(this).attr('method'),\n";
		echo "			data: new FormData(this),\n";
		echo "			processData: false,\n";
		echo "			contentType: false,\n";
		echo "			cache: false,\n";
		echo "			success: function(){\n";
		echo "				if ($.isNumeric($('#message_new_to').val())) {\n";
		echo "					$('#contact_current_number').val($('#message_new_to').val());\n";
		echo "					load_thread($('#message_new_to').val());\n";
		echo "				}\n";
		echo "				$('#message_new_layer').fadeOut(400);\n";
		echo "				document.getElementById('message_new').reset();\n";
		echo "				refresh_contacts();\n";
		echo "			}\n";
		echo "		});\n";
		echo "	});\n";
	}
?>
//open message media in layer
	function display_media(id, src) {
		$('#message_media_layer').load('message_media.php?id=' + id + '&src=' + src + '&action=display', function(){
			$('#message_media_layer').fadeIn(200);
		});
	}

	refresh_contacts();

</script>
<?php
	unset($messages, $message, $numbers, $number);

//include the footer
	require_once "resources/footer.php";
?>
