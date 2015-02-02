<?php
ob_start();
include_once(__dir__."/ImageProxy.php");

$ip = new ImageProxy();

$ip->width = (int) $_GET["width"];
$ip->format = strtoupper($_GET["format"]);
$ip->quality = $_GET["q"];
$url = $_GET["url"];

if(preg_match('/^https?:\/\//', $url)){

$ip->resource_url = $_GET["url"];

}

try{
	$ip->getImage();
}catch(Exception $e){
	header("HTTP/1.1 500 Internal Server Error");
	echo "例外: ",  $e->getMessage(), "\n";
	die;
}
ob_end_flush();
