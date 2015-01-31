<?php

class ImageProxy{
	const VERSION = '0.0.1';
	protected $ua = 'Mozilla/5.0 (Windows NT 6.0; rv:35.0) Gecko/20100101 Firefox/35.0';
	protected $width;
	protected $format;
	protected $quality;
	protected $resource_url;
	protected $timeout = 60;
	protected $maxredirs = 5;
	protected $preset_quality;

	private $supported_formats = array("IMG");

	public function __construct(){
		//コンストラクタ

		//サポートしているフォーマットをしらべる
		$gd_info = gd_info();
		if(
			array_key_exists("GIF Read Support", $gd_info) &&
			array_key_exists("GIF Create Support", $gd_info) &&
			$gd_info["GIF Read Support"] &&
			$gd_info["GIF Create Support"]
		){
			$this->supported_formats[] = "GIF";
		}

		if(
			array_key_exists("JPEG Support", $gd_info) &&
			$gd_info["JPEG Support"]){
			$this->supported_formats[] = "JPEG";
			$this->supported_formats[] = "JPG";
		}

		if(
			array_key_exists("PNG Support", $gd_info) &&
			$gd_info["PNG Support"]
		){
			$this->supported_formats[] = "PNG";
		}

		if(
			array_key_exists("WebP Support", $gd_info) &&
			$gd_info["WebP Support"]
		){
			$this->supported_formats[] = "WEBP";
		}

		$this->preset_quality = array(
			"low" => array(
				"JPEG" => 30,
				"PNG" => 9,
			),

			"mid" => array(
				"JPEG" => 75,
				"PNG" => 6
			),

			"high" => array(
				"JPEG" => 90,
				"PNG" => 5
			)
		);	

	}

	public function __set($key, $val){

		//$this->ua
		if($key === "ua"){
			if(is_string($val)){
				$this->ua = $val;
			}else{
				throw new Exception('ユーザエージェントは文字列でなければならない');
			}

			return;
		}

		//$this->width
		if($key === "width"){
			if(is_int($val) && $val > 0){
				$this->width = $val;
			}else{
				throw new Exception('widthは自然数でなければならない');
			}

			return;
		}

		//$this->format
		if($key === "format"){
			$normalized_format = $val;
			if($normalized_format === "JPG"){
				$normalized_format = "JPEG";
			}

			if(in_array($normalized_format, $this->supported_formats, TRUE)){
				$this->format = $normalized_format;
			}else{
				throw new Exception('サポートされていない画像フォーマット('.$val.')');
			}

			return;
		}

		//$this->resource_url
		if($key === "resource_url"){
			if(
				in_array("validate_url", filter_list()) &&
				filter_var($val, FILTER_VALIDATE_URL)
			){
				$s = parse_url($val, PHP_URL_SCHEME);
				if($s === "http" || $s === "https"){
					$this->resource_url = $val;
				}else{
					throw new Exception('サポートしていないスキーム');
				}
			}else{
				throw new Exception('不正なURL');
			}

			return;
		}


		//$this->timeout
		if($key === "timeout"){
			if(is_int($val) && $val > 0){
				$this->timeout = $val;
			}else{
				throw new Exception('timeoutは0より大きいintでなければならない');
			}

			return;
		}

		//$this->maxredirs
		if($key === "maxredirs"){
			if(is_int($val) && $val > 0){
				$this->maxredirs = $val;
			}else{
				throw new Exception('maxredirsは0より大きいintでなければならない');
			}

			return;
		}


		if($key === "quality"){

			if(in_array($val, array("low", "mid", "high"), TRUE)){
				$this->quality = $val;
				return;
			}else{
				if(ctype_digit($val)){
					$val = (int) $val;
				}
			}
				

			if(is_int($val) && $val > 0){
				$this->quality = $val;
			}else{
				throw new Exception('qualityは0より大きいintかpresetでなければならない');
			}

			return;
		}


	}

	protected function getImageMime($format){
		//フォーマットの適切なmime typeを返す

		if($format === "JPEG" || $format === "JPG"){
			return "image/jpeg";
		}elseif($format === "PNG"){
			return "image/png";
		}elseif($format === "GIF"){
			return "image/gif";
		}elseif($format === "WEBP"){
			return "image/webp";
		}else{
			throw new Exception("サポートしていないフォーマットなのでmimeがわかりません");
		}
	}

	protected function getOriginalImage(){
		$ch = curl_init();
		$hh = array('Accept-Language:ja,en-US;q=0.8,en;q=0.6');
		if(in_array('WEBP', $this->supported_formats, TRUE)){
			$hh[] = 'Accept:text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8';
		}else{
			$hh[] = 'Accept:text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
		}

		curl_setopt_array(
			$ch,
			array(
				CURLOPT_FAILONERROR => TRUE, //4XXで失敗扱い
				CURLOPT_FOLLOWLOCATION => TRUE, //Location: を辿る
				CURLOPT_RETURNTRANSFER => TRUE, //結果を出力しない
				CURLOPT_CONNECTTIMEOUT => $this->timeout, //接続タイムアウトまでの秒数
				CURLOPT_MAXREDIRS => $this->maxredirs, //最大リダイレクト回数
				CURLOPT_TIMEOUT => $this->timeout, //CURLの最大実行時間
				CURLOPT_USERAGENT => $this->ua, //useragent
				CURLOPT_URL => $this->resource_url, //url
				CURLOPT_HTTPHEADER => $hh 
			)
		);

		$result = curl_exec($ch);
		curl_close($ch);

		if($result === false){
			throw new Exception('ファイルの取得に失敗しました');
		}

		return $result;
	}




	public function getImage(){
		//
		//	プロキシした画像を返す
		//

		$newImage = array();

		if(!$this->width){
			throw new Exception("widthが設定されていません");
		}

		if(!$this->format){
			throw new Exception('formatが設定されていません');
		}

		if(!$this->resource_url){
			throw new Exception('resoure urlが設定されていません');
		}

		if(!$this->quality){
			throw new Exception('qualityが設定されていません');
		}

		$original_image = $this->getOriginalImage();
		$temp_image = imagecreatefromstring($original_image);

		$image_size = getimagesizefromstring($original_image);
		
		//指定フォーマットがIMGの場合は$this->formatにオリジナルのフォーマットを指定する
		if($this->format === "IMG"){
			$original_format = strtoupper( image_type_to_extension($image_size[2], false) );

			if(in_array($original_format, $this->supported_formats, true)){
				$this->format = $original_format;
			}else{
				//もしオリジナルフォーマットがサポートされていない場合は"JPEG"を指定する
				$this->format = "JPEG";
			}
		}

		//qualityがpresetだったら設定
		if(array_key_exists($this->quality, $this->preset_quality)){
			if(array_key_exists($this->format, $this->preset_quality[$this->quality])){
				$this->quality = $this->preset_quality[$this->quality][$this->format];
			}else{
				//presetに定義されてない？
				$this->quality = 1;
			}
		}else{
		}


		if($image_size[0] > $this->width){
			$scaled_image = imagescale($temp_image, $this->width);

			if($scaled_image === false){
				throw new Exception('画像のスケールに失敗しました');
			}
		}else{
			$scaled_image = $temp_image;
		}

		if($this->format === "PNG"){
			$newImage["data"] = imagepng($scaled_image, null, $this->quality);
		}else if($this->format === "JPEG" || $this->format === "JPG"){
			$newImage["data"] = imagejpeg($scaled_image, null, $this->quality);
		}else if($this->format === "GIF"){
			$newImage["data"] = imagegif($scaled_image, null);
		}else if($this->format === "WEBP"){
			$newImage["data"] = imagewebp($scaled_image, null);
		}

		if($newImage["data"] === false){
			throw new Exception("png画像の生成に失敗しました");
		}

		$newImage["mime"] = $image_size["mime"];

		header('content-type: '.$this->getImageMime($this->format));
		header('Link: <'.$this->resource_url.'>; rel="canonical"');
		echo $newImage["data"];






	}
}
