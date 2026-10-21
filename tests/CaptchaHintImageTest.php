<?php
namespace Tests\Unit;

use Foundation\Captcha\ImageCaptcha;
use PHPUnit\Framework\TestCase;

final class CaptchaHintImageTest extends TestCase
{
    public function testRasterHintsRemainReadableAndKeepAnswersPrivate(): void
    {
        $engine = new ImageCaptcha();
        $hashes = [];
        for ($sample = 0; $sample < 16; $sample++) {
            $generated = $engine->generate('WORD_IMAGE_CLICK');
            $public = $generated['public'];
            $this->assertSame([286, 76], [$public['templateImageWidth'], $public['templateImageHeight']]);
            $this->assertSame(['type', 'backgroundImage', 'templateImage', 'backgroundImageWidth', 'backgroundImageHeight', 'templateImageWidth', 'templateImageHeight'], array_keys($public));
            $png = base64_decode(explode(',', $public['templateImage'], 2)[1], true);
            $this->assertNotFalse($png);
            $image = imagecreatefromstring($png);
            $this->assertInstanceOf(\GdImage::class, $image);
            $hashes[] = hash('sha256', $png);
            // A quiet outer frame catches clipping of rotated glyphs and decoration.
            $frameClean = true;
            for ($y = 0; $y < 76; $y++) for ($x = 0; $x < 286; $x++) {
                if (($x < 4 || $x >= 282 || $y < 4 || $y >= 72) && (imagecolorat($image, $x, $y) & 0xffffff) !== 0xffffff) $frameClean = false;
            }
            $this->assertTrue($frameClean, '提示字符或干扰被边缘裁切');
            for ($cell = 0; $cell < 3; $cell++) {
                $strokes = 0;
                for ($y = 5; $y < 71; $y++) for ($x = 25 + $cell * 86; $x < 89 + $cell * 86; $x++) {
                    $rgb = imagecolorsforindex($image, imagecolorat($image, $x, $y));
                    if (max($rgb['red'], $rgb['green'], $rgb['blue']) < 160) $strokes++;
                }
                $this->assertGreaterThan(180, $strokes, '三个提示字符都应有清晰笔画');
            }
            $tracks = array_map(static fn($p, $i) => ['type'=>'click', 'x'=>$p[0]*300, 'y'=>$p[1]*180, 't'=>$i*300], $generated['answer']['points'], [0,1,2]);
            $input = ['bgImageWidth'=>300, 'bgImageHeight'=>180, 'startTime'=>1000, 'stopTime'=>2200, 'trackList'=>$tracks];
            $this->assertTrue($engine->verify($generated['answer'], $input));
            $input['trackList'] = array_reverse($tracks);
            foreach ($input['trackList'] as $i => &$point) $point['t'] = $i * 300;
            unset($point);
            $this->assertFalse($engine->verify($generated['answer'], $input));
        }
        $this->assertCount(16, array_unique($hashes));
    }
    public function testEveryHintStyleKeepsGlyphsVisibleAndUnclipped(): void
    {
        $engine = new ImageCaptcha();
        $render = new \ReflectionMethod(ImageCaptcha::class, 'wordHint');
        $characters = preg_split('//u', '春夏秋冬山水风云花月星海天地森林竹石清明远近东西南北梅兰雪雨日光田园松柏江河飞鸟青红白金', -1, PREG_SPLIT_NO_EMPTY);
        foreach (range(0, 3) as $style) foreach (array_chunk($characters, 3) as $words) {
            $words = array_pad($words, 3, '春');
            $image = $render->invoke($engine, $words, $style);
            $frameClean = true;
            for ($y = 0; $y < 76; $y++) for ($x = 0; $x < 286; $x++) {
                if (($x < 4 || $x >= 282 || $y < 4 || $y >= 72) && (imagecolorat($image, $x, $y) & 0xffffff) !== 0xffffff) $frameClean = false;
            }
            $this->assertTrue($frameClean, '样式'.$style.'边缘裁切');
            foreach ($words as $cell => $word) {
                $strokes = 0;
                for ($y = 4; $y < 72; $y++) for ($x = 22 + $cell * 86; $x < 92 + $cell * 86; $x++) {
                    $rgb = imagecolorsforindex($image, imagecolorat($image, $x, $y));
                    if (max($rgb['red'], $rgb['green'], $rgb['blue']) < 160) $strokes++;
                }
                $this->assertGreaterThan(180, $strokes, '样式'.$style.'字符'.$word.'笔画不足');
            }
        }
    }

    public function testFixedWordsAndStyleStillProduceDifferentHints(): void
    {
        $engine = new ImageCaptcha();
        $render = new \ReflectionMethod(ImageCaptcha::class, 'wordHint');
        foreach (range(0, 3) as $style) {
            $hashes = [];
            for ($i = 0; $i < 12; $i++) {
                $image = $render->invoke($engine, ['春', '夏', '秋'], $style);
                ob_start();
                try {
                    imagepng($image);
                    $hashes[] = hash('sha256', ob_get_contents());
                } finally {
                    ob_end_clean();
                }
            }
            $this->assertCount(12, array_unique($hashes), '固定文字与样式仍需随机生成');
        }
    }

    public function testEveryLocalBackgroundLoadsWithChallengeVariation(): void
    {
        $engine = new ImageCaptcha();
        $render = new \ReflectionMethod(ImageCaptcha::class, 'photoBackground');
        $resourceRoot = dirname(__DIR__).'/resources/';
        $manifest = json_decode(file_get_contents($resourceRoot.'manifest.json'), true);
        foreach (['lake', 'flat-forest', 'flat-desert', 'flat-island', 'flat-city'] as $name) {
            $path = $resourceRoot.'backgrounds/'.$name.'.jpg';
            $this->assertSame([720, 432], array_slice(getimagesize($path), 0, 2));
            $this->assertSame($manifest['backgrounds/'.$name.'.jpg'], hash_file('sha256', $path));
            $hashes = [];
            for ($i = 0; $i < 3; $i++) {
                $image = $render->invoke($engine, $name);
                $this->assertSame([600, 360], [imagesx($image), imagesy($image)]);
                ob_start();
                try {
                    imagepng($image);
                    $hashes[] = hash('sha256', ob_get_contents());
                } finally {
                    ob_end_clean();
                }
            }
            $this->assertCount(3, array_unique($hashes));
        }
    }

}
