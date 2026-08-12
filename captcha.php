<?php

session_start();

function generateCaptcha() {
    $num1 = rand(1, 9);
    $num2 = rand(1, 9);
    $num3 = rand(1, 9);
    $code = $num1 . $num2 . $num3;
    $_SESSION['captcha'] = $code;

    // Create image
    $image = imagecreatetruecolor(100, 30);
    $bg = imagecolorallocate($image, 0, 0, 0); // Black bg
    $text = imagecolorallocate($image, 0, 255, 255); // Cyan text
    imagefill($image, 0, 0, $bg);
    imagestring($image, 5, 25, 5, $code, $text);

    // Output
    header('Content-Type: image/png');
    imagepng($image);
    imagedestroy($image);
}

if (isset($_GET['generate'])) {
    generateCaptcha();
}