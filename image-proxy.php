<?php
ob_start();
include_once("./ImageProxy.php");

$ip = new ImageProxy();

$ip->width = (int) $_GET["width"];
$ip->format = strtoupper($_GET["format"]);
$ip->quality = $_GET["q"];
$ip->resource_url = $_GET["url"];

try{
	$ip->getImage();
}catch(Exception $e){
	header("HTTP/1.1 500 Internal Server Error");
	echo "例外: ",  $e->getMessage(), "\n";
	die;
}
ob_end_flush();
