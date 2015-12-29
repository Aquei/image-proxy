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
				"JPEG" => 65,
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

	//$quotaはキャッシュdirの最大ファイル数
	protected function cacheNewFile(&$data, $filename, $dir_name, $quota){
		$dir_path = __DIR__.'/'.$dir_name;
		if(file_exists($dir_path) && is_dir($dir_path)){
		}else{
			mkdir($dir_path);
		}
		$cache_dir = dir($dir_path);
		$oldest_file;
		$count = 0;

		while(false !== ($entry = $cache_dir->read()) && is_file($cache_dir->path.'/'.$entry)){
			++$count;
			$entry_path = $cache_dir->path.'/'.$entry;
			$entry_ctime = filectime($entry_path);
			if($oldest_file){
				if($oldest_file["ctime"] > $entry_ctime){
					$oldest_file["ctime"] = $entry_ctime;
					$oldest_file["name"] = $entry;
					$oldest_file["path"] = $entry_path;
				}
			}else{
				$oldest_file = array("ctime" => $entry_ctime, "name" => $entry, "path" => $entry_path);
			}
		}

		if($count > $quota && $oldest_file){
			unlink($oldest_file["path"]);
		}

		//新しくファイルをキャッシュする
		$cache_file_path = $cache_dir->path."/".$filename;
		if(!file_exists($cache_file_path)){
			if(file_put_contents($cache_file_path, $data) === false){
				throw new Exception("file write error");
			}
		}

		return true;
	}

	protected function echoResizedImageIfExist(){

		$filename = $this->getMd5Name($this->format, $this->quality, $this->width, $this->resource_url);
		$resized_path = __dir__."/resized_cache/".$filename;
		if(file_exists($resized_path)){
			$resized = file_get_contents($resized_path);
			$image_size = getimagesizefromstring($resized);
			$this->echoHeaders($image_size[2], true);
			echo $resized;

			return true;
		}else{
			return false;
		}
	}







	protected function getOriginalImage(){
		//もしキャッシュがあったらキャッシュを返す
		$cache_file_name = md5($this->resource_url);
		$cache_file_path = __DIR__."/original_cache/".$cache_file_name;
		if(file_exists($cache_file_path) && is_file($cache_file_path)){
			return file_get_contents($cache_file_path);
		}


		//ファイルが存在しないので取得し、返す
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
				CURLOPT_SSL_VERIFYPEER => FALSE, //証明書を検証しない
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


	protected function echoHeaders($imagetype, $is_cached = false){

		$headers = array();

		//常につけてたほうが良い
		$headers[] = "X-Content-Type-Options: nosniff";
		$headers[] = "X-XSS-Protection: 1; mode=block";
		$headers[] = "Content-Security-Policy: default-src 'none';";
		$headers[] = "Access-Control-Allow-Origin: *";
		$headers[] = "Cache-Control: public, max-age=31536000";

		if($is_cached){
			$headers[] = "X-ImageProxy-Cached: 1";
		}else{
			$headers[] = "X-ImageProxy-Cached: 0";
		}

		//send content-type
		$content_type = image_type_to_mime_type($imagetype);
		if($content_type){
			$headers[] = "Content-Type: ".$content_type;
		}

		//canonical
		$headers[] = 'Link: <'.$this->resource_url.'>; rel="canonical"';

		if(count($headers)){
			foreach($headers as $header){
				header($header);
			}
		}
			
	}


	protected function checkStatus(){

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

		return true;
	}


	protected function getMd5Name($format, $quality, $width, $url){
		return md5($format.$quality.$width.$url);
	}




	public function getImage(){
		//
		//	プロキシした画像を返す
		//

		$this->checkStatus();



		//もしリサイズしたキャッシュがあったら返す
		//そしてreturn
		$echoResizedResult = $this->echoResizedImageIfExist();
		if($echoResizedResult){
			return true;
		}

		//以下キャッシュなしの場合
		$newImage = array();
		$query_format = $this->format;
		$query_quality = $this->quality;

		$original_image = $this->getOriginalImage();

		if(false === ($temp_image = imagecreatefromstring($original_image))){
			throw new Exception('画像ファイルが認識できません');
		}

		if(strlen($original_image) >= 1024*1024){ //1MB以上ならキャッシュしておく
			$cache_result = $this->cacheNewFile($original_image, md5($this->resource_url), "original_cache", 30);
			if(!$cache_result){
				throw new Exception("キャッシュが保存できませんでした");
			}
		}

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
			$scaled_image_height = floor($image_size[1] * ($this->width / $image_size[0]));

			//リサイズは横幅2000px以上ならimagescale、未満ならimagecopyresampledを利用する
			if($image_size[0] >= 2000){
				$scaled_image = imagescale($temp_image, $this->width, $scaled_image_height, IMG_BICUBIC);
			}else{
				$scaled_image = imagecreatetruecolor($this->width, $scaled_image_height);
				if(!imagecopyresampled($scaled_image, $temp_image, 0, 0, 0, 0, $this->width, $scaled_image_height, $image_size[0], $image_size[1])){
					$scaled_image = false;
				}

				//もしオリジナルがtruecolorでないなら、リサイズ画像もパレットに
				//4bitの減色は変な色になることがあるので8bitにする
				if(!imageistruecolor($temp_image) && $this->format !== "JPEG"){

					$colortotal = imagecolorstotal($temp_image);
					if($colortotal <= 16){
						$colortotal = 256;
					}

					imagetruecolortopalette($scaled_image, true, $colortotal);
				}
			}

			if($scaled_image === false){
				throw new Exception('画像のスケールに失敗しました');
			}
		}else{
			$scaled_image = $temp_image;
		}

		ob_start();
		if($this->format === "PNG"){
			$newImage["result"] = imagepng($scaled_image, null, $this->quality);
		}else if($this->format === "JPEG" || $this->format === "JPG"){
			$newImage["result"] = imagejpeg($scaled_image, null, $this->quality);
		}else if($this->format === "GIF"){
			$newImage["result"] = imagegif($scaled_image, null);
		}else if($this->format === "WEBP"){
			$newImage["result"] = imagewebp($scaled_image, null);
		}

		$newImage["data"] = ob_get_contents();
		ob_end_clean();

		if($newImage["result"] === false){
			throw new Exception("png画像の生成に失敗しました");
		}

		$newImage["mime"] = $image_size["mime"];

		//output header
		$this->echoHeaders($image_size[2]);



		echo $newImage["data"];

		//キャッシュにセーブ
		$this->cacheNewFile($newImage["data"], $this->getMd5Name($query_format, $query_quality, $this->width, $this->resource_url), "resized_cache", 100);

		


		return true;
	}
}
