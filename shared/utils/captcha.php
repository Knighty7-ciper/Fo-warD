<?php

class Captcha {
    public static function generate() {
        $code = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 6);

        $_SESSION['captcha_code'] = $code;
        $_SESSION['captcha_time'] = time();

        return $code;
    }

    public static function verify($user_input) {
        if (!isset($_SESSION['captcha_code']) || !isset($_SESSION['captcha_time'])) {
            return false;
        }

        if ((time() - $_SESSION['captcha_time']) > 300) {
            unset($_SESSION['captcha_code']);
            unset($_SESSION['captcha_time']);
            return false;
        }

        $is_valid = strtoupper($user_input) === strtoupper($_SESSION['captcha_code']);

        unset($_SESSION['captcha_code']);
        unset($_SESSION['captcha_time']);

        return $is_valid;
    }

    public static function createImage($code) {
        $width = 150;
        $height = 50;

        $image = imagecreatetruecolor($width, $height);

        $bg_color = imagecolorallocate($image, 240, 240, 240);
        $text_color = imagecolorallocate($image, 50, 50, 50);
        $line_color = imagecolorallocate($image, 200, 200, 200);

        imagefilledrectangle($image, 0, 0, $width, $height, $bg_color);

        for ($i = 0; $i < 3; $i++) {
            imageline($image, rand(0, $width), rand(0, $height),
                     rand(0, $width), rand(0, $height), $line_color);
        }

        $font_size = 20;
        $x = 20;
        $y = 35;

        for ($i = 0; $i < strlen($code); $i++) {
            $angle = rand(-10, 10);
            imagettftext($image, $font_size, $angle, $x, $y, $text_color,
                        __DIR__ . '/../../frontend/assets/fonts/arial.ttf', $code[$i]);
            $x += 20;
        }

        header('Content-Type: image/png');
        imagepng($image);
        imagedestroy($image);
    }

    public static function renderHTML() {
        $code = self::generate();

        $html = '<div class="captcha-container">';
        $html .= '<img src="/backend/api/captcha/image.php" alt="CAPTCHA" id="captcha-image">';
        $html .= '<button type="button" onclick="refreshCaptcha()" class="captcha-refresh">&#x21bb;</button>';
        $html .= '</div>';

        return $html;
    }
}

?>
