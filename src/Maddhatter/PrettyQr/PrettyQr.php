<?php namespace Maddhatter\PrettyQr;

use PHPQRCode\QRcode;
use Illuminate\Support\Facades\Response;

define('QR_MOD_SIZE', 10); //mod size in pixels
define('QR_BORDER_SIZE', 2); //border size in modules

class PrettyQr{
//**PRIVATE DATA MEMBERS**
	private $text; //text to be encoded
	private $qrText; //array of 1's and 0's representing the QR code
	private $qrSize; //# of squares wide the QR code is
	private $qrImg; //GD image of just the QR code
	private $fontImg; //GD image of just the text in the center
	private $fullImg; //GD image of everything together
	
	private $fg = array(); //foreground color R, G, B, A
	private $qrFgColor; //color resource for the qrImg
		
	private $bg = array(); //background color R, G, B, A
	private $qrBgColor; //color resource for the qrImg
	
	private $rotateAngle; //how far to rotate the image to the right
	private $hideMask=false; //array of 1's and 0's representing what squares to hide
	private $fText; //text that appears in the center of the image
	private $fSize; //font size
	private $fFile; //font file
	
//**PUBLIC METHODS**

	/**
	 * Constructor, sets default colors
	 */
	public function __construct(){
		$this->fg = array(0,0,0,0);
		$this->bg = array(255,255,255,127);
	}
	
	/**
	 * Destructor, destroys all images
	 */
	public function __destruct(){
		if(isset($this->qrImg)) imagedestroy($this->qrImg);
		if(isset($this->fontImg)) imagedestroy($this->fontImg);
		if(isset($this->fullImg)) imagedestroy($this->fullImg);
	}
	
	/**
	 * showQR(), sends Content-Type header, displays image as PNG
	 */
	public function showQR(){
		$this->createImg();
	
		// header("Content-Type: image/png", true);
		// imagepng($this->fullImg);

		ob_start();
		imagepng($this->fullImg);
		$png = ob_get_contents();
		ob_end_clean();

		return Response::make($png, 200, array('content-type' => 'image/png'));
	}
	
	/**
	 * saveQR(), not yet functioning
	 */
	public function saveQR($name=false){}

	/**
	 * setFG(), sets foreground color for image
	 * @param int $r [0-255] sets red
	 * @param int $g [0-255] sets green
	 * @param int $b [0-255] sets blue
	 * @param int $a (optional) [0-127] sets transparency: 0-opaque 127-transparent
	 * @return bool false on error, true on success
	 */
	public function setFG($r, $g, $b, $a=0){
		//store info in temp array to be validated
		$tempArray = array($r, $g, $b, $a);
		foreach($tempArray as $key=>$value){
			if(!is_numeric($value))
				throw new \InvalidArgumentException("Invalid RGBA value");
			if($key<3){
				if($value<0 ||$value>255)
					throw new \InvalidArgumentException("Invalid RGBA value");
			}
		}
		$this->fg = $tempArray;
		
		return $this;
	}

	/**
	 * setBG(), sets background color for image
	 * @param int $r [0-255] sets red
	 * @param int $g [0-255] sets green
	 * @param int $b [0-255] sets blue
	 * @param int $a (optional) [0-127] sets transparency: 0-opaque 127-transparent
	 * @return bool false on error, true on success
	 */
	public function setBG($r, $g, $b, $a=0){
		//store info in temp array to be validated
		$tempArray = array($r, $g, $b, $a);
		foreach($tempArray as $key=>$value){
			if(!is_numeric($value))
				throw new \InvalidArgumentException("Invalid RGBA value");
			if($key<3){
				if($value<0 ||$value>255)
					throw new \InvalidArgumentException("Invalid RGBA value");
			}
		}
		$this->bg = $tempArray;
		return $this;
	}

	/**
	 * addHideMask(), allows user to define custom hide mask
	 * @param array $mask must be the same size as the current QR code
	 */
	public function addHideMask($mask){
		if(count($mask)==$this->qrSize){
			$this->hideMask = $mask;
		}else{
			throw new \InvalidArgumentException("Invalid mask. Must match size of QR code");
		}

		return $this;
	}
	
	 /**
	  * setText(), sets the text to be encoded in the QR
	  *		$text is optional to allow empty contructor
	  * @param string $text (optional) text to be set, if any
	  * 
	  */
	public function setText($text=false){
		if($text!==false){
			$this->text = $text;
			$this->qrText = QRcode::text($text, false, 'H', 1, 0);
			$this->qrSize = count($this->qrText);
		}

		return $this;
	}
	
	/**
	 * rotate(), sets the rotation angle of the QR code
	 * @param int $angle must be a multiple of 90
	 * @return bool true on success, false on failure
	 */
	public function rotate($angle){
		if(!is_numeric($angle))
			throw new \InvalidArgumentException("Angle must be a multiple of 90, {$angle} given.");
		if(!($angle%90)){
			$this->rotateAngle = $angle;
			return $this;
		}else{
			throw new \InvalidArgumentException("Angle must be a multiple of 90, {$angle} given.");
		}
	}

	/**
	 * getQRSize(), returns the size of the QR code
	 * @return int
	 */
	public function getQRSize(){
		return $this->qrSize;
	}
	
	/**
	 * debugQR(), displays the QR code without any modifications except rotation
	 */
	public function debugQR(){
		$this->qrImg = imagecreate($this->qrSize*QR_MOD_SIZE, $this->qrSize*QR_MOD_SIZE);
		$this->qrBgColor = imagecolorallocate($this->qrImg,255,255,255);
		$this->qrFgColor = imagecolorallocate($this->qrImg,0,0,0);
		$this->applyMask($this->qrText, false);
		header("Content-Type: image/png");
		imagepng($this->qrImg);
	}
	
	/**
	 * getEmptyMask(), returns an array of 0's the size of the QR code
	 *    useful for creating hideMasks
	 * @return array
	 */
	public function getEmptyMask(){
		$mask = array();
		for($i=0; $i<$this->qrSize; $i++){
			for($j=0; $j<$this->qrSize; $j++){
				$mask[$i][$j] = 0;
			}
		}
		return $mask;
	}
	
	/**
	 * addText(), add text to the middle of the QR code
	 * @param string $text the text to be added
	 * @param string $font the path to the TTF font to be used
	 * @param int $size the font size in points
	 */
	public function addText($text, $font, $size){
		$this->fText = $text;
		$this->fFile = $font;
		$this->fSize = $size;
	}
	
//**PRIVATE METHODS**
	
	private function createImg(){
		if(isset($this->fText)){
			$this->createFontImg();
			$this->hideMiddleRows(ceil(imagesy($this->fontImg)/QR_MOD_SIZE));
			$this->createQRImg();
			
			$width = max(imagesx($this->fontImg), (imagesx($this->qrImg)+(QR_MOD_SIZE*QR_BORDER_SIZE*2)));
			$height = max(imagesy($this->fontImg), (imagesy($this->qrImg)+(QR_MOD_SIZE*QR_BORDER_SIZE*2)));
			
			$this->fullImg = imagecreate($width, $height);
			$bgColor = imagecolorallocatealpha($this->fullImg, $this->bg[0], $this->bg[1], $this->bg[2], $this->bg[3]);
			
			$this->addLayer($this->qrImg, $this->fullImg);
			$this->addLayer($this->fontImg, $this->fullImg);
			
		}else{
			$this->createQRImg();
			
			$this->fullImg = imagecreate(imagesx($this->qrImg)+40, imagesy($this->qrImg)+40);
			$bgColor = imagecolorallocatealpha($this->fullImg, $this->bg[0], $this->bg[1], $this->bg[2], $this->bg[3]);
			
			$this->addLayer($this->qrImg, $this->fullImg);
		}
	}
	
	private function addLayer($layer, $base){
		$bW = imagesx($base);
		$bH = imagesy($base);
		$lW = imagesx($layer);
		$lH = imagesy($layer);
		
		$x = floor(($bW-$lW)/2);
		$y = floor(($bH-$lH)/2);
		imagecopy($base, $layer, $x, $y, 0, 0, $lW, $lH);
	}
	
	private function createQRImg(){
		$this->qrImg = \imagecreate($this->qrSize*QR_MOD_SIZE, $this->qrSize*QR_MOD_SIZE);

		$this->qrBgColor = \imagecolorallocatealpha($this->qrImg, $this->bg[0], $this->bg[1], $this->bg[2], $this->bg[3]);
		$this->qrFgColor = \imagecolorallocatealpha($this->qrImg, $this->fg[0], $this->fg[1], $this->fg[2], $this->fg[3]);
		
		//create base qr code
		$this->applyMask($this->qrText);
		
		//create the mask for position and alignment squares and apply it
		$mask = $this->createPosMask();
		$this->applyMask($mask, false);
		
		//apply a hide mask, if there is one
		if($this->hideMask!==false)
			$this->applyMask($this->hideMask,false,true);
	}
	
	private function rotateMask(&$input){
		$output;
		$last = $this->qrSize - 1; //last array slot ($size-1, keeps from rewriting)
		for($k=0;$k<($this->rotateAngle/90);$k++){
			for($i=0; $i<$this->qrSize; $i++){
				for($j=0; $j<$this->qrSize; $j++){
					$output[$i][$j] = $input[$last-$j][$i];
				}
			}
			$input = $output;
		}
	}
	
	private function applyMask($mask, $gap=true, $inverted=false){
		//set the color being used, based on whether or not it is inverted
		if($inverted){
			$color = $this->qrBgColor;
		}else{
			$color = $this->qrFgColor;
			$this->rotateMask($mask);
		}

		for($i=0;$i<$this->qrSize;$i++){
			for($j=0;$j<$this->qrSize;$j++){
				if($mask[$i][$j]){
					if($gap){
						$x1 = ($j*QR_MOD_SIZE)+1;
						$x2 = $x1+(QR_MOD_SIZE-2);
						$y1 = ($i*QR_MOD_SIZE)+1;
						$y2 = $y1+(QR_MOD_SIZE-2);
					}else{
						$x1 = ($j*QR_MOD_SIZE);
						$x2 = $x1+(QR_MOD_SIZE-1);
						$y1 = ($i*QR_MOD_SIZE);
						$y2 = $y1+(QR_MOD_SIZE-1);
					}
					imagefilledrectangle($this->qrImg, $x1, $y1, $x2, $y2, $color);
				}
			}
		}
	}
	
	private function hideMiddleRows($rowCount){
		$mask = $this->getEmptyMask();
		$middle = floor($this->qrSize/2);
		$firstRow = $middle-(floor($rowCount/2));
		
		for($i=0;$i<$rowCount;$i++){
			for($j=0;$j<$this->qrSize;$j++){
				$mask[$firstRow+$i][$j]=1;
			}
		}
		
		$this->hideMask=$mask;
		// $this->dumpQR($mask);
	}
	
	private function createPosMask(){
		$mask = $this->getEmptyMask();
		
		$this->addSquare($mask, 0, 0, 'P');
		$this->addSquare($mask, $this->qrSize-7, 0, 'P');
		$this->addSquare($mask, 0, $this->qrSize-7, 'P');
		$this->addSquare($mask, $this->qrSize-9, $this->qrSize-9, 'A');
			
		return $mask;
	}
	
	private function addSquare(&$mask, $x, $y, $type){
		$pSquare = array(
			array(1,1,1,1,1,1,1),
			array(1,0,0,0,0,0,1),
			array(1,0,1,1,1,0,1),
			array(1,0,1,1,1,0,1),
			array(1,0,1,1,1,0,1),
			array(1,0,0,0,0,0,1),
			array(1,1,1,1,1,1,1)
		);

		$aSquare = array(
			array(1,1,1,1,1),
			array(1,0,0,0,1),
			array(1,0,1,0,1),
			array(1,0,0,0,1),
			array(1,1,1,1,1)
		);

		if($type=='P'){
			for($i=0; $i<7; $i++){
				for($j=0; $j<7; $j++){
					$mask[$x+$j][$y+$i] = $pSquare[$i][$j];

				}
			}
		}
		if($type=='A'){
			for($i=0; $i<5; $i++){
				for($j=0; $j<5; $j++){
					$mask[$x+$j][$y+$i] = $aSquare[$i][$j];
				}
			}
		}
	}
	
	private function dumpQR($turned, $debug=false){
		// /*
		echo "<pre>";
		if(!$debug){
			for($i=0;$i<count($turned);$i++){
				for($j=0;$j<count($turned);$j++){
					echo $turned[$i][$j];
				}
				echo "\n";
			}
		}else
			var_dump($turned);
		echo "</pre>";
		//*/
	}
	
	private function createFontImg(){
		$textBox = $this->calculateTextBox();
		$this->fontImg = imagecreate($textBox['width'], $textBox['height']);
		
		$bg = imagecolorallocatealpha($this->fontImg, $this->bg[0], $this->bg[1], $this->bg[2], $this->bg[3]);
		$fg = imagecolorallocatealpha($this->fontImg, $this->fg[0], $this->fg[1], $this->fg[2], $this->fg[3]);
		
		imagettftext($this->fontImg, $this->fSize, 0, $textBox['left'], $textBox['top'], $fg, $this->fFile, $this->fText);
	}

	
	private function calculateTextBox() {
		/************
		simple function that calculates the *exact* bounding box (single pixel precision).
		The function returns an associative array with these keys:
		left, top:  coordinates you will pass to imagettftext
		width, height: dimension of the image you have to create
		*************/
		$rect = imagettfbbox($this->fSize,0,$this->fFile,$this->fText);
		$minX = min(array($rect[0],$rect[2],$rect[4],$rect[6]));
		$maxX = max(array($rect[0],$rect[2],$rect[4],$rect[6]));
		$minY = min(array($rect[1],$rect[3],$rect[5],$rect[7]));
		$maxY = max(array($rect[1],$rect[3],$rect[5],$rect[7]));
	   
		return array(
		 "left"   => abs($minX) - 1,
		 "top"    => abs($minY) - 1,
		 "width"  => $maxX - $minX,
		 "height" => $maxY - $minY,
		 "box"    => $rect
		);
	}
}