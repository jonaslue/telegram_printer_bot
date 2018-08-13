<?php
require __DIR__ . '/vendor/mike42/escpos-php/autoload.php';
	
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;

class CustomPrinterInterface{	
	private $printer = null;
	
	public function __construct($port){
		global $printer;
		if(PHP_OS == "WINNT" || PHP_OS == "Windows" || PHP_OS == "WIN32") exec("mode $port:9600,N,8,1"); 	// Set port options for Windows
		if(PHP_OS == "Linux") exec("stty -F $port 9600 cs8 -cstopb -parenb");								// Set port options for Linux
		
		$printer = new Printer(new FilePrintConnector($port));
		if($printer === null) die("! The printer could not be opened.");
		echo("* The printer is being used on port '$port'\n");
	}
	
	function __destruct(){
		global $printer;
		$printer->close();	// Close the printer to avoid warnings in console
	}
	
	public function printSimpleText($from,$text){
		global $printer;
	
		echo("* '$from' is printing text: '$text'\n\n");
		$printer->setEmphasis(true);		// Bold
		$printer->text("\n\nSender: ");
		$printer->setEmphasis(false);		// Bold off
		$printer->text($from);
		$printer->setEmphasis(true);		// Bold
		$printer->text("\nMessage: ");
		$printer->setEmphasis(false);		// Bold off
		$printer->text("$text\n\n");		// The \n\n part is the padding at the end of the paper
		$printer->cut();					// Cut the paper
	}
	
	public function printTextWithFormat($from,$text,$entities){
		// $entities in this case is the "entities" array in a Message object (https://core.telegram.org/bots/api#message)
		
		global $printer;
		
		$formats = array();
		foreach($entities as $ent){	// Convert the entities-Array from Telegram to an easier-to-use array
			$formats["{$ent["offset"]}"] = array("length"=>$ent["length"],"type"=>$ent["type"]);
		}
		
		echo("* '$from' is printing formatted text: '$text'\n\n");
		$printer->setEmphasis(true);
		$printer->text("\n\nSender: ");
		$printer->setEmphasis(false);
		$printer->text($from);
		$printer->setEmphasis(true);
		$printer->text("\nMessage: ");
		$printer->setEmphasis(false);
		
		$next_reset = 0;
		for($pt = 0;$pt < strlen($text);$pt++){				// Go through each character of the message string an set formatting options
			if($next_reset == $pt){							// Reset previously set format options of the printer
				$printer -> setEmphasis(false);
				$printer -> setUnderline(false);
				$printer -> setReverseColors(false);
			}
			
			if(isset($formats[$pt])){						// Set printer formatting options
				switch($formats[$pt]["type"]){
					case "italic":
						$printer -> setUnderline(true);		// Replacement for Italic
						break;
					case "bold";
						$printer -> setEmphasis(true);		// Bold
						break;
					case "code";
						$printer -> setReverseColors(true); // Replacement for Code
						break;
				}
				$next_reset = $pt+$formats[$pt]["length"];	// Set the string position at which the current format setting ends
			}
			$printer->text($text[$pt]);						// Print the current character of the string to the printer
		}
		
		$printer -> setEmphasis(false);			// Reset all printer settings we've possibly changed above
		$printer -> setUnderline(false);		// ^
		$printer -> setReverseColors(false);	// ^
		
		$printer->text("\n\n");
		$printer->cut();
	}
	
	public function printImage($path,$from,$caption){
		// $path is the URL of the image
		
		global $printer;
		$file = "NUL";
		
		echo("* The image '$caption' from '$from' is now being printed.\n* Image filepath: '$path'\n\n");
		
		if(!file_exists("imgs")) mkdir("imgs");
		
		if(strtolower(substr($path,strlen($path)-4))=="webp"){
			/*
				If the image is most likely a sticker (WEBP format) then create a
				new GD-image, fill it with a white background and scale the
				sticker image so it fits nicely on the paper, then save the GD-image
				as a JPEG file since WEBP is currently not being supported by escpos-php
			*/
			
			$file       = "imgs/".urlencode(str_replace(FILE_BASE,"",$path).".jpg");
			$sticker    = imagecreatefromwebp($path);	// Sticker GD image
			$scsticker  = imagescale($sticker,512);		// Scaled sticker (512px * 512px)
			$canvas  		= imagecreatetruecolor(imagesx($scsticker), imagesy($scsticker));	// Create blank GD image
			
			imagefill($canvas,0,0,imagecolorallocate($canvas,255,255,255));						// Fill the blank GD image with white 
			imagecopy($canvas,$scsticker,0,0,0,0,imagesx($scsticker),imagesy($scsticker));		// Copy the scaled sticker onto the white background
			imagejpeg($canvas,$file,100);														// Save the final image as JPEG
		} else {
			$file = "imgs/".urlencode(str_replace(FILE_BASE,"",$path));
			$im = imagecreatefromstring(file_get_contents($path));
			
			imagejpeg(imagescale($im,512),$file,100);	// Load the image, scale it to 512px width and save it as JPEG
		}
		
		$printer->setEmphasis(true);
		$printer->text("\n\nSender: ");
		$printer->setEmphasis(false);
		$printer->text($from);
		$printer->setEmphasis(true);
		$printer->text("\nImage title: ");
		$printer->setEmphasis(false);
		$printer->text("$caption\n");
		$printer -> setJustification(Printer::JUSTIFY_CENTER);	// Set the printers align to center
		$printer -> bitImage(EscposImage::load($file,false));	// Load and print the image file
		$printer -> setJustification(Printer::JUSTIFY_LEFT);	// Set the printers align to left (default)
		$printer->text("\n\n");
		$printer->cut();
		
		unlink($file);	// Delete the temporary image file from the file system
	}
}