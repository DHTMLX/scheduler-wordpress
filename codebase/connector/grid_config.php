<?php	

class GridConfiguration{
	
	/*! attaching header functionality
	*/
	protected $headerDelimiter = ',';
	protected $headerNames = false;
	protected $headerAttaches = array();
	protected $footerAttaches = array();
	protected $headerWidthsUnits = 'px';
	
	protected $headerIds = false;
    protected $headerWidths = false;
    protected $headerTypes = false;
	protected $headerAlign  = false; 
	protected $headerVAlign = false;
	protected $headerSorts  = false;
	protected $headerColors = false;
	protected $headerHidden = false;
	
	function __construct($headers = false){
	 	if ($headers === false || $headers === true )
			$this->headerNames = $headers;
		else
			$this->setHeader($headers);
	}

	/*! brief convert list of parameters to an array
		@param param 
			list of values or array of values
		@return array of parameters
	*/
	private function parse_param_array($param, $check=false, $default = ""){
		if (gettype($param) == 'string')
			$param = explode($this->headerDelimiter, $param);
				
		if ($check){
			for ($i=0; $i < sizeof($param); $i++) { 
				if (!array_key_exists($param[$i],$check))
					$param[$i] = $default;
			}
		}
		return $param;
	}
	
	/*! sets delimiter for string arguments in attach header functions (default is ,)
		@param headerDelimiter
			string delimiter
	*/
	public function setHeaderDelimiter($headerDelimiter) {
		$this->headerDelimiter = $headerDelimiter;
	}

	/*! sets header
		@param names
		 array of names or string of names, delimited by headerDelimiter (default is ,)
	*/
	public function setHeader($names) {
		if ($names instanceof DataConfig){
			$out = array();
			for ($i=0; $i < sizeof($names->text); $i++)
				$out[]=$names->text[$i]["name"];
			$names = $out;
		}
				
		$this->headerNames = $this->parse_param_array($names);
	}

	/*! sets init columns width in pixels
		@param wp
			array of widths or string of widths, delimited by headerDelimiter (default is ,)
	*/
	public function setInitWidths($wp) {
		$this->headerWidths = $this->parse_param_array($wp);
		$this->headerWidthsUnits = 'px';
	}

	/*! sets init columns width in persents
		@param wp
			array of widths or string of widths, delimited by headerDelimiter (default is ,)
	*/
	public function setInitWidthsP($wp) {
		$this->setInitWidths($wp);
		$this->headerWidthsUnits = '%';
	}

	/*! sets columns align
		@param alStr
			array of aligns or string of aligns, delimited by headerDelimiter (default is ,)
	*/
	public function setColAlign($alStr) {
		$this->headerAlign = $this->parse_param_array($alStr,
			array("right"=>1, "left"=>1, "center"=>1, "justify"=>1),
			"left");
	}

	/*! sets columns vertical align
		@param alStr
			array of vertical aligns or string of vertical aligns, delimited by headerDelimiter (default is ,)
	*/
	public function setColVAlign($alStr) {
		$this->headerVAlign = $this->parse_param_array($alStr,
			array("baseline"=>1, "sub"=>1, "super"=>1, "top"=>1, "text-top"=>1, "middle"=>1, "bottom"=>1, "text-bottom"=>1),
			"top");
	}

	/*! sets column types
		@param typeStr
			array of types or string of types, delimited by headerDelimiter (default is ,)
	*/
	public function setColTypes($typeStr) {
		$this->headerTypes = $this->parse_param_array($typeStr);
	}

	/*! sets columns sorting
		@param sortStr
			array if sortings or string of sortings, delimited by headerDelimiter (default is ,)
	*/
	public function setColSorting($sortStr) {
		$this->headerSorts = $this->parse_param_array($sortStr);
	}

	/*! sets columns colors
		@param colorStr
			array of colors or string of colors, delimited by headerDelimiter (default is ,)
			if (color should not be applied it's value should be null)
	*/
	public function setColColor($colorStr) {
		$this->headerColors = $this->parse_param_array($colorStr);
	}

	/*! sets hidden columns
		@param hidStr
			array of bool values or string of bool values, delimited by headerDelimiter (default is ,)
	*/
	public function setColHidden($hidStr) {
		$this->headerHidden = $this->parse_param_array($hidStr);
	}

	/*! sets columns id
		@param idsStr
			array of ids or string of ids, delimited by headerDelimiter (default is ,)
	*/
	public function setColIds($idsStr) {
		$this->headerIds = $this->parse_param_array($idsStr);
	}

	/*! attaches header
		@param values
			array of header names or string of header names, delimited by headerDelimiter (default is ,)
		@param styles
			array of header styles or string of header styles, delimited by headerDelimiter (default is ,)
	*/
	public function attachHeader($values, $styles = null, $footer = false) {
		$header = array();
		$header['values'] = $this->parse_param_array($values);
		if ($styles != null) {
			$header['styles'] = $this->parse_param_array($styles);
		} else {
			$header['styles'] = null;
		}
		if ($footer)
			$this->footerAttaches[] = $header;
		else
			$this->headerAttaches[] = $header;
	}

	/*! attaches footer
		@param values
			array of footer names or string of footer names, delimited by headerDelimiter (default is ,)
		@param styles
			array of footer styles or string of footer styles, delimited by headerDelimiter (default is ,)
	*/
	public function attachFooter($values, $styles = null) {
		$this->attachHeader($values, $styles, true);
	}
	
	private function auto_fill($mode){
		$headerWidths = array();
		$headerTypes = array();
		$headerSorts = array();
		$headerAttaches = array();
		
		for ($i=0; $i < sizeof($this->headerNames); $i++) { 
			$headerWidths[] = 100;
			$headerTypes[] = "ro";
			$headerSorts[] = "connector";
			$headerAttaches[] = "#connector_text_filter";
		}
		if ($this->headerWidths == false)
			$this->setInitWidths($headerWidths);
		if ($this->headerTypes == false)
			$this->setColTypes($headerTypes);
			
		if ($mode){
			if ($this->headerSorts == false)
				$this->setColSorting($headerSorts);
			$this->attachHeader($headerAttaches);
		}
	}

	/*! adds header configuration in output XML
	*/
	public function attachHeaderToXML($conn, $out) {
		$config = $conn->get_config();
		
		if (!$conn->is_first_call()) return; //render head only for first call
		$full_header = ($this->headerNames === true);
		if (gettype($this->headerNames) == 'boolean') //auto-config
			$this->setHeader($config);
		$this->auto_fill($full_header);
		
		$str = '<head>';
		for ($i = 0; $i < count($this->headerNames); $i++) {
			$str .= '<column';
			$str .= ' type="'. $this->headerTypes[$i].'"';
			$str .= ' width="'.$this->headerWidths[$i].'"';
			$str .= $this->headerIds  ? ' id="'.$this->headerIds[$i].'"' : '';
			$str .= $this->headerAlign[$i]  ? ' align="'.$this->headerAlign[$i].'"' : '';
			$str .= $this->headerVAlign[$i] ? ' valign="'.$this->headerVAlign[$i].'"' : '';
			$str .= $this->headerSorts[$i]  ? ' sort="'.$this->headerSorts[$i].'"' : '';
			$str .= $this->headerColors[$i] ? ' color="'.$this->headerColors[$i].'"' : '';
			$str .= $this->headerHidden[$i] ? ' hidden="'.$this->headerHidden[$i].'"' : '';
			$str .= '>'.$this->headerNames[$i].'</column>';
		}
		$str .= '<settings><colwidth>'.$this->headerWidthsUnits.'</colwidth></settings>';
		if ((count($this->headerAttaches) > 0)||(count($this->footerAttaches) > 0)) {
			$str .= '<afterInit>';
		}
		for ($i = 0; $i < count($this->headerAttaches); $i++) {
			$str .= '<call command="attachHeader">';
			$str .= '<param>'.implode(",",$this->headerAttaches[$i]['values']).'</param>';
			if ($this->headerAttaches[$i]['styles'] != null) {
				$str .= '<param>'.implode(",",$this->headerAttaches[$i]['styles']).'</param>';
			}
			$str .= '</call>';
		}
		for ($i = 0; $i < count($this->footerAttaches); $i++) {
			$str .= '<call command="attachFooter">';
			$str .= '<param>'.implode(",",$this->footerAttaches[$i]['values']).'</param>';
			if ($this->footerAttaches[$i]['styles'] != null) {
				$str .= '<param>'.implode(",",$this->footerAttaches[$i]['styles']).'</param>';
			}
			$str .= '</call>';
		}
		if ((count($this->headerAttaches) > 0)||(count($this->footerAttaches) > 0)) {
			$str .= '</afterInit>';
		}
		$str .= '</head>';
		
		$out->add($str);
	}
}
	
?>