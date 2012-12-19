<?php

function rgb2hsv($r, $g, $b) {
	$r = $r/255;
	$g = $g/255;
	$b = $b/255;
	$max = max($r, $g, $b);
	$min = min($r, $g, $b);

	if ($min === $max) {
		$h = 0;
	} elseif ($max == $r && $g >= $b) {
		$h = 60*($g - $b)/($max - $min) + 0;
	} elseif ($max == $r && $g < $b) {
		$h = 60*($g - $b)/($max - $min) + 360;
	} elseif ($max == $g) {
		$h = 60*($b - $r)/($max - $min) + 120;
	} elseif ($max == $b) {
		$h = 60*($r - $g)/($max - $min) + 240;
	}

	if ($max == 0)
		$s = 0;
	else
		$s = 1 - $min/$max;

	$v = $max;

	return Array('h' => $h/360, 's' => $s, 'v' => $v);
}


function hsv2rgb($hsv) {
	$h = $hsv['h'];
	$s = $hsv['s'];
	$v = $hsv['v'];
	$h = $h*360;
	if($s == 0) {
		$r = $g = $b = $v*255;
	} else {
		$var_h = $h*6;
		$var_i = floor($var_h);

		$Hi = floor($h/60) % 6;
		$f = $h/60 - floor($h/60);


		$p = $v*(1 - $s);
		$q = $v*(1 - $f*$s);
		$t = $v*(1 - (1 - $f)*$s);

		if ($Hi == 0) {
			$r = $v;
			$g = $t;
			$b = $p;
		} elseif ($Hi == 1) {
			$r = $q;
			$g = $v;
			$b = $p;
		} elseif ($Hi == 2) {
			$r = $p;
			$g = $v;
			$b = $t;
		} elseif ($Hi == 3) {
			$r = $p;
			$g = $q;
			$b = $v;
		} elseif ($Hi == 4) {
			$r = $t;
			$g = $p;
			$b = $v ;
		} else {
			$r = $v;
			$g = $p;
			$b = $q;
		}

		$r = $r*255;
		$g = $g*255;
		$b = $b*255;
	}
    return array(round($r), round($g), round($b));
}

function image_dump($im) {
	$width = imagesx($im);
	$height = imagesy($im);
	$data = array();
	for ($y = 0; $y < $height; $y++) {
		$data[$y] = array();
		for ($x = 0; $x < $width; $x++) {
			$index = imagecolorat($im, $x, $y);
			$color = imagecolorsforindex($im, $index);
			$r = $color['red'];
			$g = $color['green'];
			$b = $color['blue'];
			$hsv = rgb2hsv($r, $g, $b);
			$data[$y][$x] = $hsv;
		}
	}

	imagedestroy($im);
	return $data;
}


function image_apply($dump, $base, $s = 0) {
	$height = count($dump);
	$width = count($dump[0]);

	$im = imagecreatetruecolor($width, $height);
	$bg = imagecolorallocatealpha($im, 255, 255, 255, 127);
	imagefilledrectangle($im, 0, 0, $width, $height, $bg);
	$tr = imagecolorallocatealpha($im, 0, 0, 0, 127);
	imagecolortransparent($im, $tr);

	$base_r = hexdec(substr($base, 0, 2));
	$base_g = hexdec(substr($base, 2, 2));
	$base_b = hexdec(substr($base, 4, 2));
	$base_hsv = rgb2hsv($base_r, $base_g, $base_b);

	for ($y = 0; $y < count($dump); $y++) {
		for ($x = 0; $x < count($dump[$y]); $x++) {
			$hsv = $dump[$y][$x];
			$hsv['s'] += $s/100;
			$hsv['h'] = $base_hsv['h'];
			list($r, $g, $b) = hsv2rgb($hsv);
			if ($r == 0 && $g == 0 && $b == 0) continue;
			$color = imagecolorallocate($im, $r, $g, $b);
			imagesetpixel($im, $x, $y, $color);
		}
	}
	return $im;
}

function toString($dumps) {
	$result = array();
	foreach ($dumps as $name => $dump) {
		$result[] = $name."\n".minimize($dump);
	}
	return implode("\n\n", $result);
}

function minimize($dump) {
	$sep = ',';
	$lines = array();
	for($y = 0; $y < count($dump); $y++) {
		$line = array();
		for($x = 0; $x < count($dump[$y]); $x++) {
			$hsv = $dump[$y][$x];
			$line[] = format($hsv['h']).$sep.format($hsv['s']).$sep.format($hsv['v']);
		}
		$lines[] = implode(";", $line);
	}
	return implode("\n", $lines);
}

function format($fl) {
	return sprintf("%.3f", $fl);
}


function fromString($text) {
	$dumps = explode("\n\n", $text);
	$result = array();
	for ($i = 0; $i < count($dumps); $i++) {
		maximize($dumps[$i], $result);
	}
	return $result;
}

function maximize($text, &$final) {
	$lines = explode("\n", $text);
	$name = trim($lines[0]);
	$data = array();
	for ($y = 1; $y < count($lines); $y++) {
		$line = explode(";", $lines[$y]);
		for ($x = 0; $x < count($line); $x++) {
			$p = explode(",", $line[$x]);
			$hsv = array(
				'h' => (float) $p[0],
				's' => (float) $p[1],
				'v' => (float) $p[2]
			);
			$data[$y - 1][$x] = $hsv;
		}
	}
	$final[$name] = $data;
}




class Color {
	
	public $r = 0;
	public $g = 0;
	public $b = 0;
	public $gray = 15;
	
	public function __construct($text) {
		if ($text[0] == "#") $text = substr($text, 1);
		$this->colorHexToDec($text);
		$this->hex = $text;
	}

	protected function colorHexToDec($color) {
		$this->r = hexdec(substr($color, 0, 2));
		$this->g = hexdec(substr($color, 2, 2));
		$this->b = hexdec(substr($color, 4, 2));
	}

	public function debug($same_line = false) {
		$color = '#'.$this->colorDecToHex();
		$span = '<div style="width:20px; height: 20px; float: left; border: 1px solid; background-color: '.$color.'">&nbsp;&nbsp;&nbsp;</div>';
		echo $span." <div style='float: left; padding-left: 4px; padding-top: 4px; font-size: 12px; font-family: Tahoma;'>".$color."</div>";
		if ($same_line === false) echo "<div style='clear: both;'></div>";
	}

	protected function colorDecToHex() {
		$red = dechex($this->r);
		$green = dechex($this->g);
		$blue = dechex($this->b);
		if (strlen($red) == 1) $red = "0".$red;
		if (strlen($green) == 1) $green = "0".$green;
		if (strlen($blue) == 1) $blue = "0".$blue;
		return strtoupper($red.$green.$blue);
	}

	public function isGray() {
		$d = max(abs($this->r - $this->g), abs($this->r - $this->b), abs($this->g - $this->b))."<br>";
		if ($d < $this->gray)
			return true;
		else
			return false;
		
	}

	public function add($color, $opacity) {
		$this->r = round($this->r + ($color->r - $this->r)*$opacity/100);
		$this->g = round($this->g + ($color->r - $this->g)*$opacity/100);
		$this->b = round($this->b + ($color->r - $this->b)*$opacity/100);
	}

	public function toString($short = false) {
		return ($short ? '' : '#').$this->colorDecToHex();
	}

}

?>