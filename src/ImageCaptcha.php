<?php
/**
 * PHP/GD adaptation of dromara/tianai-captcha 1.5.6 (89c064a2).
 * Original authors: 天爱有情 and contributors. Apache-2.0; see LICENSE / UPSTREAM.md.
 * Changes: GD rendering, cryptographic randomness, bounded input validation, independent assets.
 */
namespace Foundation\Captcha;

final class ImageCaptcha
{
    public const TYPES = ['SLIDER', 'WORD_IMAGE_CLICK', 'ROTATE', 'CONCAT'];
    public const WIDTH = 600;
    public const HEIGHT = 360;
    private string $resources;
    private string $backgroundDirectory;
    public function __construct(?string $resources = null, ?string $backgroundDirectory = null, private array $minimumCropUnits = [], private bool $sliderDecoy = false)
    {
        $this->resources = $resources ?? dirname(__DIR__).'/resources';
        $this->backgroundDirectory = $backgroundDirectory ?? $this->resources.'/backgrounds';
        foreach ($minimumCropUnits as $name => $minimum) {
            if (!is_string($name) || !is_int($minimum) || $minimum < 120 || $minimum > 144) {
                throw new \InvalidArgumentException('Crop minimum must be an integer between 120 and 144');
            }
        }
    }

    /** 返回给浏览器的 public 与只保存在服务端的 answer 严格分开。 */
    public function generate(string $type = 'SLIDER'): array
    {
        if (!in_array($type, self::TYPES, true)) throw new \InvalidArgumentException('不支持的验证码类型');
        $background = $this->background();
        [$template, $answer] = match($type) {
            'SLIDER' => $this->slider($background), 'WORD_IMAGE_CLICK' => $this->words($background),
            'ROTATE' => $this->rotate($background), 'CONCAT' => $this->concat($background),
        };
        return ['public' => [
            'type' => $type, 'backgroundImage' => $this->encode($background, false),
            'templateImage' => $this->encode($template, true),
            'backgroundImageWidth' => self::WIDTH, 'backgroundImageHeight' => self::HEIGHT,
            'templateImageWidth' => imagesx($template), 'templateImageHeight' => imagesy($template),
        ] + ($type === 'CONCAT' ? ['data'=>['randomY'=>$answer['randomY']]] : []), 'answer' => ['type' => $type] + $answer];
    }

    /** 上游旋转协议：圆形中心裁片，滑轨走满一周；角度仅留在服务端。 */
    private function rotate(\GdImage $background): array
    {
        $diameter=240; $cut=self::transparent(360,360);
        for($y=0;$y<$diameter;$y++)for($x=0;$x<$diameter;$x++) {
            if(($x-119.5)**2+($y-119.5)**2>120**2)continue;
            imagesetpixel($cut,$x+60,$y+60,imagecolorat($background,$x+180,$y+60));
        }
        imagefilledellipse($background,300,180,240,240,imagecolorallocate($background,190,200,210));
        imageellipse($background,300,180,242,242,imagecolorallocate($background,140,150,165));
        $percentage=random_int(250,549)/600;
        // GD positive angle is counterclockwise; CSS slider adds clockwise rotation.
        $turned=imagerotate($cut,$percentage*360,imagecolorallocatealpha($cut,0,0,0,127));
        imagesavealpha($turned,true);
        $template=self::transparent(360,360);
        imagecopy($template,$turned,0,0,(int)((imagesx($turned)-360)/2),(int)((imagesy($turned)-360)/2),360,360);
        return [$template,['percentage'=>$percentage,'tolerant'=>0.02]];
    }

    /** 上游拼接协议：上部循环移位，randomY 只控制分割高度。 */
    private function concat(\GdImage $background): array
    {
        $randomY=random_int(90,269); $height=360-$randomY; $offset=random_int(75,479);
        $copy=imagecreatetruecolor(600,$height);
        imagecopy($copy,$background,0,0,0,0,600,$height);
        imagecopy($background,$copy,0,0,$offset,0,600-$offset,$height);
        imagecopy($background,$copy,600-$offset,0,0,0,$offset,$height);
        return [self::transparent(1,1),['percentage'=>$offset/600,'tolerant'=>0.05,'randomY'=>$randomY]];
    }


    private function slider(\GdImage $background): array
    {
        $path = $this->resources.'/templates/slider_'.random_int(1, 2);
        $fixed = imagecreatefrompng($path.'/fixed.png');
        $active = imagecreatefrompng($path.'/active.png');
        $width = imagesx($fixed); $height = imagesy($fixed);
        // StandardSliderImageCaptchaGenerator: exclusive Java upper bounds converted to inclusive PHP.
        $x = random_int($width + 5, self::WIDTH - $width - 11);
        $y = random_int(0, self::HEIGHT - $height - 1);
        $cut = self::cut($background, imagecreatefrompng($path.'/mask.png'), $x, $y);
        imagecopy($background, $fixed, $x, $y, 0, 0, $width, $height);
        if ($this->sliderDecoy) $this->drawSliderDecoy($background, $fixed, $x, $y);
        imagecopy($cut, $active, 0, 0, 0, 0, imagesx($active), imagesy($active));
        $template = self::transparent(imagesx($active), self::HEIGHT);
        imagecopy($template, $cut, 0, $y, 0, 0, imagesx($cut), imagesy($cut));
        return [$template, ['percentage' => $x / self::WIDTH, 'tolerant' => 0.02]];
    }

    /** Optional upstream interference, disabled by default for human readability. */
    private function drawSliderDecoy(\GdImage $background, \GdImage $fixed, int $x, int $y): void
    {
        $width = imagesx($fixed); $height = imagesy($fixed);
        // StandardSliderImageCaptchaGenerator obfuscate: one weaker, slightly scaled decoy.
        $decoyWidth = $width; $decoyHeight = $height;
        $variation = random_int(-3, 3) * 5;
        if (random_int(0, 1)) $decoyWidth += $variation; else $decoyHeight += $variation;
        $decoy = self::transparent($decoyWidth, $decoyHeight);
        imagealphablending($decoy, false);
        imagecopyresampled($decoy, $fixed, 0, 0, 0, 0, $decoyWidth, $decoyHeight, $width, $height);
        $opacity = random_int(50, 79) / 100;
        for ($dy = 0; $dy < $decoyHeight; $dy++) for ($dx = 0; $dx < $decoyWidth; $dx++) {
            $pixel = imagecolorat($decoy, $dx, $dy);
            $alpha = 127 - (int) round((127 - (($pixel >> 24) & 127)) * $opacity);
            imagesetpixel($decoy, $dx, $dy, ($alpha << 24) | ($pixel & 0xffffff));
        }
        $decoyX = self::sliderDecoyX($x, $width, $decoyWidth);
        // Clamp scaled height to the canvas; never alter the real target or extracted piece.
        $decoyY = min($y, self::HEIGHT - $decoyHeight);
        imagecopy($background, $decoy, $decoyX, $decoyY, 0, 0, $decoyWidth, $decoyHeight);
    }

    /** Official opposite-side placement, using the actual scaled width to prevent overlap. */
    private static function sliderDecoyX(int $targetX, int $targetWidth, int $decoyWidth): int
    {
        return self::WIDTH / 2 > $targetX + $targetWidth / 2
            ? random_int($targetX + $targetWidth, self::WIDTH - $decoyWidth - 1)
            : random_int($targetWidth, $targetX - $decoyWidth - 1);
    }

    /** CaptchaImageUtils.cutImage：只复制模板 alpha > 100 的像素。 */
    public static function cut(\GdImage $background, \GdImage $mask, int $left, int $top): \GdImage
    {
        $out = self::transparent(imagesx($mask), imagesy($mask));
        for ($y = 0; $y < imagesy($mask); $y++) {
            for ($x = 0; $x < imagesx($mask); $x++) {
                $rgba = imagecolorsforindex($mask, imagecolorat($mask, $x, $y));
                if ((127 - $rgba['alpha']) * 255 / 127 > 100) {
                    imagesetpixel($out, $x, $y, imagecolorat($background, $left + $x, $top + $y));
                }
            }
        }
        return $out;
    }

    private function words(\GdImage $background): array
    {
        // Same nonoverlapping block placement and ordered four targets + two distractors as Java.
        $pool = preg_split('//u', '春夏秋冬山水风云花月星海天地森林竹石清明远近东西南北梅兰雪雨日光田园松柏江河飞鸟青红白金', -1, PREG_SPLIT_NO_EMPTY);
        $blocks = [];
        for ($x = 0; $x < 4; $x++) for ($y = 0; $y < 2; $y++) $blocks[] = [$x * 150 + 50, $y * 150 + 50];
        $targets = []; $selected = [];
        for ($i = 0; $i < 6; $i++) {
            $word = array_splice($pool, random_int(0, count($pool)-1), 1)[0];
            [$x, $y] = array_splice($blocks, random_int(0, count($blocks)-1), 1)[0];
            $tile = self::transparent(100, 100);
            // Sample the target patch before drawing: dark scenes need light strokes.
            $luminance = 0;
            foreach ([-24, 0, 24] as $dx) foreach ([-24, 0, 24] as $dy) {
                $rgb = imagecolorsforindex($background, imagecolorat($background, $x + 50 + $dx, $y + 50 + $dy));
                $luminance += 0.2126 * $rgb['red'] + 0.7152 * $rgb['green'] + 0.0722 * $rgb['blue'];
            }
            $inks = $luminance / 9 < 140
                ? [[244, 224, 133], [181, 234, 213], [209, 215, 255], [251, 195, 217]]
                : [[39, 61, 99], [49, 85, 61], [101, 42, 86], [112, 58, 31]];
            $this->text($tile, $word, 52, random_int(0, 84), $inks[random_int(0, 3)]);
            imagecopy($background, $tile, $x, $y, 0, 0, 100, 100);
            if ($i < 4) { $selected[] = $word; $targets[] = [($x+50)/600, ($y+50)/360]; }
        }
        return [$this->wordHint($selected, random_int(0, 3)), ['points' => $targets, 'tolerant' => 50/600]];
    }

    /** Four bounded raster styles; the selected style and characters never become response fields. */
    private function wordHint(array $selected, int $style): \GdImage
    {
        $tip = imagecreatetruecolor(286, 76);
        imagefill($tip, 0, 0, imagecolorallocate($tip, 255, 255, 255));
        $palette = [[39, 100, 78], [49, 82, 143], [117, 62, 111], [131, 81, 38]];
        $colourOffset = random_int(0, 3);
        // Bounded background and foreground layers keep the quiet frame intact.
        imagesetthickness($tip, 2);
        $backgroundPattern = random_int(0, 3);
        if ($backgroundPattern === 0) {
            for ($i = 0; $i < 3; $i++) {
                $phase = random_int(0, 628) / 100;
                $base = random_int(25, 49);
                $ink = imagecolorallocatealpha($tip, 80, 105, 120, 50);
                $previous = $base + (int)(9 * sin($phase));
                for ($x = 9; $x <= 276; $x++) {
                    $y = $base + (int)(9 * sin($x / 47 + $phase));
                    imageline($tip, $x - 1, $previous, $x, $y, $ink);
                    $previous = $y;
                }
            }
        } elseif ($backgroundPattern === 1) {
            for ($i = 0; $i < 4; $i++) {
                $x = 12 + $i * 64 + random_int(0, 12);
                imageline($tip, $x, random_int(14, 28), $x + random_int(35, 56), random_int(47, 62), imagecolorallocatealpha($tip, 70, 95, 125, 45));
            }
        } elseif ($backgroundPattern === 2) {
            for ($i = 0; $i < 6; $i++) {
                imageellipse($tip, random_int(20, 265), random_int(20, 55), random_int(10, 22), random_int(8, 18), imagecolorallocatealpha($tip, 85, 105, 120, 60));
            }
        } else {
            for ($i = 0; $i < 3; $i++) {
                imagearc($tip, 55 + $i * 82, 38, 70, random_int(24, 48), random_int(5, 45), random_int(135, 175), imagecolorallocatealpha($tip, 70, 105, 100, 45));
            }
        }
        foreach ($selected as $i => $word) {
            $glyph = self::transparent(64, 60);
            $angle = match ($style) {
                1 => random_int(-13, 13),
                2 => random_int(-14, 14),
                3 => ($i % 2 ? -1 : 1) * random_int(8, 16),
                default => random_int(-16, 16),
            };
            $colour = $palette[$style === 1 ? $colourOffset : ($i + $colourOffset) % 4];
            $this->text($glyph, $word, random_int(35, 37), $angle, $colour);
            $left = 15 + $i * 64 + random_int(-4, 4);
            $top = $style === 3 ? 8 + ($i % 2 ? 4 : -4) : 8 + random_int(-1, 1);
            if ($style === 2) {
                $phase = random_int(0, 628) / 100;
                for ($x = 0; $x < 64; $x++) {
                    imagecopy($tip, $glyph, $left + $x, $top + (int)round(3 * sin($x / 13 + $phase)), $x, 0, 1, 60);
                }
            } else {
                imagecopy($tip, $glyph, $left, $top, 0, 0, 64, 60);
            }
        }
        // Independently sample foreground/background patterns; no fixed four-pattern cycle.
        $foregroundPattern = random_int(0, 3);
        imagesetthickness($tip, random_int(2, 3));
        for ($line = 0; $line < 2; $line++) {
            $rgb = $palette[($colourOffset + $line) % 4];
            $ink = imagecolorallocatealpha($tip, ...[...$rgb, random_int(38, 52)]);
            if ($foregroundPattern === 0) {
                $phase = random_int(0, 628) / 100;
                $base = ($line ? 49 : 27) + random_int(-3, 3);
                $previous = $base + (int)(9 * sin($phase));
                for ($x = 10; $x <= 275; $x++) {
                    $y = $base + (int)(9 * sin($x / 50 + $phase));
                    imageline($tip, $x - 1, $previous, $x, $y, $ink);
                    $previous = $y;
                }
            } elseif ($foregroundPattern === 1) {
                imageline($tip, 10, ($line ? 57 : 19) + random_int(-4, 4), 275, ($line ? 21 : 59) + random_int(-4, 4), $ink);
            } elseif ($foregroundPattern === 2) {
                $previousX = 10;
                $previousY = $line ? 51 : 24;
                for ($x = 54; $x <= 274; $x += 44) {
                    $y = ($line ? 51 : 24) + random_int(-7, 7);
                    imageline($tip, $previousX, $previousY, $x, $y, $ink);
                    $previousX = $x; $previousY = $y;
                }
            } else {
                for ($x = 10; $x < 262; $x += 26) {
                    $y = ($line ? 53 : 23) + random_int(-5, 5);
                    imageline($tip, $x, $y, $x + 14, $y + random_int(-3, 3), $ink);
                }
            }
        }
        imagesetthickness($tip, 1);
        for ($i = 0, $count = random_int(35, 75); $i < $count; $i++) {
            imagefilledellipse($tip, random_int(10, 275), random_int(10, 65), 2, 2, imagecolorallocatealpha($tip, 75, 95, 110, 55));
        }
        return $tip;
    }

    private function text(\GdImage $image, string $text, int $size, int $angle, array $rgb): void
    {
        $font = $this->resources.'/fonts/FoundationCaptchaSans-Medium.ttf';
        $box = imagettfbbox($size, $angle, $font, $text);
        if (!$box) throw new \RuntimeException('验证码字体不可用');
        $xs=[$box[0],$box[2],$box[4],$box[6]]; $ys=[$box[1],$box[3],$box[5],$box[7]];
        $x=(int)((imagesx($image)-max($xs)+min($xs))/2-min($xs));
        $y=(int)((imagesy($image)-max($ys)+min($ys))/2-min($ys));
        imagettftext($image, $size, $angle, $x, $y, imagecolorallocate($image, ...$rgb), $font, $text);
    }

    /** Local AI-generated assets only: no network access while creating a challenge. */
    private function background(): \GdImage
    {
        $files = array_map(static fn($file) => basename($file, '.jpg'), glob($this->backgroundDirectory.'/*.jpg') ?: []);
        if (!$files) throw new \RuntimeException('No CAPTCHA backgrounds available');
        return $this->photoBackground($files[random_int(0, count($files) - 1)]);
    }

    private function photoBackground(string $name): \GdImage
    {
        $path = $this->backgroundDirectory.'/'.$name.'.jpg';
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('验证码背景资源缺失');
        }
        // Inspect dimensions before allocating a decoded bitmap, including for custom assets.
        $dimensions = @getimagesize($path);
        if (!$dimensions || $dimensions[0] !== 720 || $dimensions[1] !== 432 || $dimensions[2] !== IMAGETYPE_JPEG) {
            throw new \RuntimeException('验证码背景资源格式不正确');
        }
        $source = @imagecreatefromjpeg($path);
        if (!$source) throw new \RuntimeException('验证码背景资源解码失败');
        // Crop/offset before cutting the puzzle so image and answer always use the same pixels.
        $unit = random_int($this->minimumCropUnits[$name] ?? 120, 144);
        $width = $unit * 5; $height = $unit * 3;
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagecopyresampled($image, $source, 0, 0,
            random_int(0, 720 - $width), random_int(0, 432 - $height),
            self::WIDTH, self::HEIGHT, $width, $height);
        if (random_int(0, 1)) imageflip($image, IMG_FLIP_HORIZONTAL);
        imagefilter($image, IMG_FILTER_BRIGHTNESS, random_int(-3, 3));
        // Restrained per-challenge texture: avoid the former conspicuous white speckles.
        for ($i = 0; $i < 70; $i++) {
            imagesetpixel($image, random_int(0, 599), random_int(0, 359),
                imagecolorallocatealpha($image, 255, 255, 255, random_int(108, 122)));
        }
        return $image;
    }

    private static function transparent(int $width, int $height): \GdImage
    {
        $image=imagecreatetruecolor($width,$height); imagealphablending($image,false);
        imagefill($image,0,0,imagecolorallocatealpha($image,0,0,0,127)); imagesavealpha($image,true);
        imagealphablending($image,true); return $image;
    }
    private function encode(\GdImage $image, bool $png): string
    {
        ob_start();
        try { $png ? imagepng($image) : imagejpeg($image,null,88); return 'data:image/'.($png?'png':'jpeg').';base64,'.base64_encode(ob_get_contents()); }
        finally { ob_end_clean(); }
    }

    // Java validator uses IEEE754 float32, including inclusive tolerance bounds.
    private static function f32(float|int|string $value): float { return unpack('f',pack('f',(float)$value))[1]; }
    private static function within(float $value,float $expected,float $tolerance):bool
    {
        $expected=self::f32($expected);$tolerance=self::f32($tolerance);
        return $value>=self::f32($expected-$tolerance) && $value<=self::f32($expected+$tolerance);
    }

    private static function number(mixed $value): bool
    {
        return (is_int($value) || is_float($value)) && is_finite((float)$value);
    }

    /** Stored answers are trusted, but corruption/version mismatches must fail without warnings. */
    private static function validAnswer(array $answer): bool
    {
        $type = $answer['type'] ?? null;
        if (!in_array($type, self::TYPES, true)) return false;
        $maximum = match ($type) { 'WORD_IMAGE_CLICK' => 50/600, 'CONCAT' => 0.05, default => 0.02 };
        if (!self::number($answer['tolerant'] ?? null) || $answer['tolerant'] <= 0 || $answer['tolerant'] > $maximum) return false;
        if ($type !== 'WORD_IMAGE_CLICK') {
            return self::number($answer['percentage'] ?? null) && $answer['percentage'] >= 0 && $answer['percentage'] <= 1;
        }
        $points = $answer['points'] ?? null;
        if (!is_array($points) || !array_is_list($points) || count($points) !== 4) return false;
        foreach ($points as $point) {
            if (!is_array($point) || !array_is_list($point) || count($point) !== 2) return false;
            foreach ($point as $coordinate) if (!self::number($coordinate) || $coordinate < 0 || $coordinate > 1) return false;
        }
        return count(array_unique(array_map(static fn($point) => json_encode($point), $points))) === 4;
    }

    /** SimpleImageCaptchaValidator 的归一化位置/顺序判断，附加不可信输入边界。 */
    public function verify(array $answer, array $data): bool
    {
        if (!self::validAnswer($answer)) return false;
        // Official SDK renders 600x360 at 300x180. Keep its native canvas size even on mobile.
        if (($data['bgImageWidth']??null)!==($answer['type']==='ROTATE'?242:300) || ($data['bgImageHeight']??null)!==180) return false;
        $tracks=$data['trackList']??null; $start=$data['startTime']??null; $stop=$data['stopTime']??null;
        if (!is_array($tracks)||!array_is_list($tracks)||count($tracks)<2||count($tracks)>1000) return false;
        if (!is_int($start)||!is_int($stop)||$start<0||$stop-$start<100||$stop-$start>120000) return false;
        $lastTime=-1;
        foreach($tracks as $track) {
            if(!is_array($track)||!in_array($track['type']??null,['down','move','up','click'],true)) return false;
            foreach(['x','y','t'] as $key) if(!self::number($track[$key]??null)||abs($track[$key])>100000) return false;
            if($track['t']<$lastTime||$track['t']<0||$track['t']>$stop-$start+1000) return false;
            $lastTime=$track['t'];
        }
        $tolerance=$answer['tolerant'];
        if(in_array($answer['type'],['SLIDER','ROTATE','CONCAT'],true)) {
            $first=$tracks[0]; $last=$tracks[count($tracks)-1];
            if($first['type']!=='down'||$last['type']!=='up') return false;
            foreach (array_slice($tracks, 1, -1) as $point) if ($point['type'] !== 'move') return false;
            $percentage=self::f32(self::f32(self::f32($last['x'])-self::f32($first['x']))/($answer['type']==='ROTATE'?242:300));
            return self::within($percentage,$answer['percentage'],$tolerance);
        }
        if($answer['type']!=='WORD_IMAGE_CLICK') return false;
        foreach ($tracks as $point) if (!in_array($point['type'], ['click','move'], true)) return false;
        $clicks=array_values(array_filter($tracks,static fn($p)=>$p['type']==='click'));
        if(count($clicks)!==count($answer['points'])) return false;
        foreach($clicks as $i=>$point) {
            if($point['x']<0||$point['x']>300||$point['y']<0||$point['y']>180) return false;
            if(!self::within(self::f32(self::f32($point['x'])/300),$answer['points'][$i][0],$tolerance) || !self::within(self::f32(self::f32($point['y'])/180),$answer['points'][$i][1],$tolerance)) return false;
        }
        return true;
    }
}
