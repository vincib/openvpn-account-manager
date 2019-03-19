<?php

require_once("../head.php");

if (!isset($_COOKIE["OTP"])) {
    echo "ERROR";
    exit();
    }

use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

$renderer = new ImageRenderer(
    new RendererStyle(200),
    new ImagickImageBackEnd()
);
$writer = new Writer($renderer);
header("Content-Type: image/png");
setcookie("OTP","",time()-3600);
echo $writer->writeString($_COOKIE["OTP"]);


