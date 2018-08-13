<?php
require_once("printer.php");

define("API_KEY","REPLACE_ME");	// Your bot key
define("BASE_URL","http://api.telegram.org/bot".API_KEY."/");		// The base url for bot requests
define("FILE_BASE","http://api.telegram.org/file/bot".API_KEY."/");	// The base url for downloading send files
define("DEV_ID","REPLACE_ME"); // Your own User-ID

$cpi = new CustomPrinterInterface("COM3");	// Open the printer on serial port COM3 (Windows in this case)

while(true){	// Endless loop for recieving messages
	foreach(getNewMessages() as $msg){
		/*
		This would've been code to answer to the /start command
		
		if(isset($msg["text"]) && strtolower($msg["text"]) == "/start"){
			replyToMessage($msg["from"]["id"],$msg["message_id"],"Hello, {$msg["from"]["first_name"]}! Simply send me text or images and i'll print them!");	// Auf start antworten
			continue;
		}*/
		
		replyToMessage($msg["from"]["id"],$msg["message_id"],"Okay, {$msg["from"]["first_name"]}, will be done.");	// Reply the user that their message is being processed
		
		if($msg["from"]["id"] != DEV_ID) forwardMessage(DEV_ID,$msg["from"]["id"],$msg["message_id"]);	// Forward the message that is being printed to the dev-user-id
		
		$from = "{$msg["from"]["first_name"]} (@{$msg["from"]["username"]})";	// Format the user name for later use
		
		if(isset($msg["entities"])){											// If the recieved message has formatting, use the "printTextWithFormat" function
			$cpi->printTextWithFormat($from,$msg["text"],$msg["entities"]);
		} else if(isset($msg["text"])){											// If not, simply print the text without modifying it in any way
			$cpi->printSimpleText($from,$msg["text"]);
		}
		if(isset($msg["photo"])){												// If the message is a photo, print the photo
			$caption = "-";														// Default photo caption if none was given by the user
			if(isset($msg["caption"])) $caption = $msg["caption"];
			$cpi->printImage(getFileURL($msg["photo"][sizeof($msg["photo"])-1]["file_id"]),$from,$caption);
		}
		if(isset($msg["document"]))	$cpi->printImage(getFileURL($msg["document"]["file_id"]),$from,"-");		// Files like bmp are send as document, so try to print them anyways
		if(isset($msg["sticker"]))  $cpi->printImage(getFileURL($msg["sticker"]["file_id"]),$from,"Sticker");	// Print stickers that have been send
	}
	
	sleep(5);	// Wait 5 seconds until the next poll 
}
	
function postRequest($endpoint,$fields=array(){	// Function for doing POST-Requests on the Telegram API
	/*
		Example for optional POST parameters/arguments/fields:
			$fields = array("field_name"=>"field_value");
	*/
	
	$options = array(
		'http' => array(
			'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
			'method'  => 'POST',
			'content' => http_build_query($fields)
		)
	);
	$context = stream_context_create($options);
	return	file_get_contents(BASE_URL.$endpoint, false, $context);
}

function getRequest($endpoint,$fields=array()){	// Function for doing GET-Requests on the Telegram API
	/*
		Example for optional POST parameters/arguments/fields:
			$fields = array("field_name"=>"field_value");
	*/
	
	$field_string = "";
	foreach($fields as $field => $value){
		$field_string.="$field=$value&";
	}
	
	return file_get_contents(BASE_URL."$endpoint?$field_string");
}

function getNewMessages(){	// Check for new incoming messages (updates) and return them as array
	echo("* Looking for updates...\n");
	
	$last_upd_id = 0;
	$new_messages = array();
	
	if(is_file("last_update_id.var")) $last_upd_id = intval(file_get_contents("last_update_id.var"));	// I used a file called "last_update_id.var" to store the ID of the last processed update
	$messages_json = json_decode(getRequest("getUpdates",array("offset"=>$last_upd_id)),true);			// Do a GET request to get the latest updates and convert the JSON to an PHP array
	
	foreach($messages_json["result"] as $result){
		if(intval($result["update_id"]) > $last_upd_id){
			$new_messages[] = $result["message"];
			$last_upd_id = intval($result["update_id"]);
			
			if(isset($result["message"]["text"]))  echo("* '{$result["message"]["from"]["username"]}' has send a text message: '{$result["message"]["text"]}'\n\n");
			if(isset($result["message"]["photo"]) || isset($result["message"]["document"])) echo("* '{$result["message"]["from"]["username"]}' has send an image.\n\n");
			if(isset($result["message"]["sticker"])) echo("* '{$result["message"]["from"]["username"]}' has send a sticker: '{$result["message"]["sticker"]["emoji"]}'");
		}
	}
	
	if(sizeof($new_messages)==0) echo("* No new updates found.\n\n");
	file_put_contents("last_update_id.var",$last_upd_id);
	return $new_messages;
}

function getFileURL($fileid){	// Returns the complete URL of a file ID
	return FILE_BASE . json_decode(getRequest("getFile",array("file_id"=>$fileid)),true)["result"]["file_path"];
}

function sendMessage($chatid,$message,$parse_mode = ""){			// Send a message to a user (via their Chat-ID)
	$message = urlencode($message);
	return getRequest("sendMessage",array("chat_id"=>$chatid,"text"=>$message,"parse_mode"=>$parse_mode));
}

function replyToMessage($chatid,$msg_id,$reply,$parse_mode = ""){	// Reply to a message from a user (via their Chat-ID)
	$reply = urlencode($reply);
	return getRequest("sendMessage",array("chat_id"=>$chatid,"text"=>$reply,"parse_mode"=>$parse_mode,"reply_to_message_id"=>$msg_id));
}

function forwardMessage($tochatid,$fromchatid,$messageid){			// Forward a message from one chat to another (also via Chat-IDs)
	getRequest("forwardMessage",array("chat_id"=>$tochatid,"from_chat_id"=>$fromchatid,"message_id"=>$messageid));
}

echo("* Script successfully run\n");