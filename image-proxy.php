<?php
ob_start();
include_once("./ImageProxy.php");

$ip = new ImageProxy();

$ip->width = (int) $_GET["width"];
$ip->format = strtoupper($_GET["format"]);
$ip->quality = $_GET["q"];
$ip->resource_url = $_GET["url"];

$ip->getImage();
ob_end_flush();
