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

//get number of messages to load
	$number = preg_replace('{[\D]}', '', $_GET['number']);
	$contact_uuid = (is_uuid($_GET['contact_uuid'])) ? $_GET['contact_uuid'] : null;

//set refresh flag
	$refresh = $_GET['refresh'] == 'true' ? true : false;

//get messages
	if (isset($_SESSION['message']['display_last']['text']) && $_SESSION['message']['display_last']['text'] != '') {
		$array = explode(' ',$_SESSION['message']['display_last']['text']);
		if (is_array($array) && is_numeric($array[0]) && $array[0] > 0) {
			if ($array[1] == 'messages') {
				$limit = limit_offset($array[0], 0);
			}
			else {
				$since = "and message_date >= :message_date ";
				$parameters['message_date'] = date("Y-m-d H:i:s", strtotime('-'.$_SESSION['message']['display_last']['text']));
			}
		}
	}
	if ($limit == '' && $since == '') { $limit = limit_offset(25, 0); } //default (message count)
	$sql = "SELECT ";
	$sql .= "message_uuid, ";
	$sql .= "domain_uuid, ";
	$sql .= "user_uuid, ";
	$sql .= "contact_uuid, ";
	$sql .= "message_type, ";
	$sql .= "message_direction, ";
	if ($_SESSION['domain']['time_zone']['name'] != '') {
		$sql .= "message_date AT time zone :time_zone AS message_date, ";
	}
	else {
		$sql .= "message_date, ";
	}
	$sql .= "message_from, ";
	$sql .= "message_to, ";
	$sql .= "message_text ";
	$sql .= "FROM v_messages ";
	$sql .= "WHERE user_uuid = :user_uuid ";
	$sql .= "AND (domain_uuid = :domain_uuid OR domain_uuid IS NULL) ";
	$sql .= $since;
	$sql .= "AND (message_from LIKE :message_number OR message_to LIKE :message_number) ";
	$sql .= "ORDER BY message_date DESC ";
	$sql .= $limit;
	if ($_SESSION['domain']['time_zone']['name'] != '') {
		$parameters['time_zone'] = $_SESSION['domain']['time_zone']['name'];
	}
	$parameters['user_uuid'] = $_SESSION['user_uuid'];
	$parameters['domain_uuid'] = $domain_uuid;
	$parameters['message_number'] = '%'.$number;
	$database = new database;
	$messages = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

	if (is_array($messages) && @sizeof($messages) != 0) {
		$messages = array_reverse($messages);

		//get media (if any)
			$sql = "SELECT ";
			$sql .= "message_uuid, ";
			$sql .= "message_media_uuid, ";
			$sql .= "message_media_type, ";
			$sql .= "length(decode(message_media_content,'base64')) AS message_media_size ";
			$sql .= "FROM v_message_media ";
			$sql .= "WHERE user_uuid = :user_uuid ";
			$sql .= "AND (domain_uuid = :domain_uuid OR domain_uuid is null) ";
			$sql .= "AND ( ";
			foreach ($messages as $index => $message) {
				$message_uuids[] = "message_uuid = :message_uuid_".$index;
				$parameters['message_uuid_'.$index] = $message['message_uuid'];
			}
			$sql .= implode(' OR ', $message_uuids);
			$sql .= ") ";
			$sql .= "AND message_media_type <> 'txt' ";
			$parameters['user_uuid'] = $_SESSION['user_uuid'];
			$parameters['domain_uuid'] = $domain_uuid;
			$database = new database;
			$rows = $database->select($sql, $parameters, 'all');
			unset($sql, $parameters, $index);

		//prep media array
			if (is_array($rows) && @sizeof($rows) != 0) {
				foreach ($rows as $index => $row) {
					$message_media[$row['message_uuid']][$index]['uuid'] = $row['message_media_uuid'];
					$message_media[$row['message_uuid']][$index]['type'] = $row['message_media_type'];
					$message_media[$row['message_uuid']][$index]['size'] = $row['message_media_size'];
				}
			}
	}

	if (!$refresh) {
		echo "<div style='position: relative;'>";
		echo "<div id='thread_messages' style='min-height: 300px; overflow: auto; padding-right: 15px;'>\n";
	}

	//output messages
		if (is_array($messages) && @sizeof($messages) != 0) {
			foreach ($messages as $message) {
				//parse from message
				if ($message['message_direction'] == 'inbound') {
					$message_from = $message['message_to'];
					$media_source = format_phone($message['message_from']);
				}
				if ($message['message_direction'] == 'outbound') {
					$media_source = format_phone($message['message_to']);
				}

				//message when
				$message_current_date = strtotime(strstr($message['message_date'], '.', true));
				$diff = $message_current_date - $message_previous_date;
				
				if ($diff > 600) {
					$age = strtotime("now") - $message_current_date;
					
					//today
					if (date('Ymd') == date('Ymd', $message_current_date)) {
						echo "<div class='message-time'>".date('g:i a', $message_current_date)."</div>\n";
					}
					
					//yesterday
					elseif (date('Ymd', strtotime('-1 day')) == date('Ymd', $message_current_date)) {
						echo "<div class='message-time'>".$text['label-message-time-yesterday']." " .date('g:i a', $message_current_date)."</div>\n";
					}
					
					//last 2 to 3 days
					elseif (date('Ymd', strtotime('-3 days')) <= date('Ymd', $message_current_date)) {
						echo "<div class='message-time'>".date('l', $message_current_date)." " .$text['label-message-time-at']." " .date('g:i a', $message_current_date)."</div>\n";
					}
					
					//older than 3 days
					else {
						echo "<div class='message-time'>".(date('d M. Y', $message_current_date))." ".$text['label-message-time-at']." ".date('g:i a', $message_current_date)."</div>\n";
					}
				}

				$message_previous_date = strtotime(strstr($message['message_date'], '.', true));

				//message bubble
					echo "<div class='message-bubble message-bubble-".($message['message_direction'] == 'inbound' ? 'em' : 'me')."'>";
						//contact image em
							if ($message['message_direction'] == 'inbound') {
								if (is_array($_SESSION['tmp']['messages']['contact_em'][$contact_uuid]) && @sizeof($_SESSION['tmp']['messages']['contact_em'][$contact_uuid]) != 0) {
									echo "<div class='message-bubble-image-em'>\n";
									echo "	<img class='message-bubble-image-em'><br />\n";
									echo "</div>\n";
								}
							}
						//contact image me
							else {
								if (is_array($_SESSION['tmp']['messages']['contact_me']) && @sizeof($_SESSION['tmp']['messages']['contact_me']) != 0) {
									echo "<div class='message-bubble-image-me'>\n";
									echo "	<img class='message-bubble-image-me'><br />\n";
									echo "</div>\n";
								}
							}
					//message
						if ($message['message_text'] != '') {
							echo "<div class='message-text'>".str_replace("\n",'<br />',escape($message['message_text']))."</div>\n";
						}
					//attachments
						if (is_array($message_media[$message['message_uuid']]) && @sizeof($message_media[$message['message_uuid']]) != 0) {

							foreach ($message_media[$message['message_uuid']] as $media) {
								if ($media['type'] != 'txt') {
									if ($media['type'] == 'jpg' || $media['type'] == 'jpeg' || $media['type'] == 'gif' || $media['type'] == 'png') {
										echo "<a href='#' onclick=\"display_media('".$media['uuid']."','".$media_source."');\" class='message-media-link-".($message['message_direction'] == 'inbound' ? 'em' : 'me')."'>";
									}
									else {
										echo "<a href='message_media.php?id=".$media['uuid']."&src=".$media_source."&action=download' class='message-media-link-".($message['message_direction'] == 'inbound' ? 'em' : 'me')."'>";
									}
									echo "<img src='resources/images/attachment.png' style='width: 16px; height: 16px; border: none; margin-right: 10px;'>";
									echo "<span style='font-size: 85%; white-space: nowrap;'>".strtoupper($media['type']).' &middot; '.strtoupper(byte_convert($media['size']))."</span>";
									echo "</a>\n";
								}
							}
							echo "<br />\n";
						}
				echo "</div>\n";
			}
			echo "<span id='thread_bottom'></span>\n";
		}

		echo "<script>\n";
		//set current contact
			echo "	$('#contact_current_number').val('".$number."');\n";
		//set bubble contact images from src images
			echo "	$('img.message-bubble-image-em').attr('src', $('img#src_message-bubble-image-em_".$contact_uuid."').attr('src'));\n";
			echo "	$('img.message-bubble-image-me').attr('src', $('img#src_message-bubble-image-me').attr('src'));\n";
		echo "</script>\n";

	if (!$refresh) {
		echo "</div></div>\n";

		if (permission_exists('message_add')) {
			//output input form
			echo "<form id='message_compose' method='post' enctype='multipart/form-data' action='message_send.php'>\n";
			echo "<input type='hidden' name='message_from' value='".$message_from."'>\n";
			echo "<input type='hidden' name='message_to' value='".$number."'>\n";
			echo "	<textarea class='formfld' id='message_text' name='message_text' placeholder=\"".$text['description-enter_response']."\"></textarea>";
			echo "	<input type='submit' class='btn btn_send' value='".$text['button-send']."' title=\"".$text['label-ctrl_enter']."\"></div>\n";
			echo "	<div class='attachment'>\n";
			echo " 		<img src='resources/images/attachment.png' style='min-width: 20px; height: 20px; border: none; padding-right: 5px;'>\n";
			echo "		<input type='file' class='formfld' multiple='multiple' name='message_media[]' id='message_new_media'>\n";
			echo "	</div>\n";
			echo "	<span id='thread_refresh_state'><img src='resources/images/refresh_active.gif' style='width: 16px; height: 16px; border: none; cursor: pointer;' onclick=\"refresh_thread_stop('".$number."','".$contact_uuid."');\" alt=\"".$text['label-refresh_pause']."\" title=\"".$text['label-refresh_pause']."\"></span>\n";
			echo "</form>\n";


		}
	}

?>

<script>
//Load messages for clicked number
//Define form submit function
	$('#message_compose').submit(function(event) {
		event.preventDefault();
		$.ajax({
			url: $(this).attr('action'),
			type: $(this).attr('method'),
			data: new FormData(this),
			processData: false,
			contentType: false,
			cache: false,
			success: function(){
					document.getElementById('message_compose').reset();
					if (!http_user_agent('mobile')) {
						if ($('#message_new_layer').is(':hidden')) {
							$('#message_text').trigger('focus');
						}
					}
					refresh_thread('".$number."', '".$contact_uuid."', 'true');
			}
		})
	})
//Enable ctrl+enter to send
	$('#message_text').keydown(function (event) {
		if ((event.keyCode == 10 || event.keyCode == 13) && event.ctrlKey) {
			$('#message_compose').submit();
		}
	})
</script>

<script>
//Restyle messages divs which contain only an emoji
var messageTextDiv = document.getElementsByClassName('message-text');
for (var i=0; i < messageTextDiv.length; i++) {
  if (messageTextDiv[i].innerHTML.match(/^(\u00a9|\u00ae|[\u2000-\u3300]|\ud83c[\ud000-\udfff]|\ud83d[\ud000-\udfff]|\ud83e[\ud000-\udfff])$/)) {
	messageTextDiv[i].classList.add('emoji');
  }
}
</script>




























