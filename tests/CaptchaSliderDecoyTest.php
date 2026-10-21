<?php
namespace Tests\Unit;

use Foundation\Captcha\ImageCaptcha;
use PHPUnit\Framework\TestCase;

final class CaptchaSliderDecoyTest extends TestCase
{
    public function testOppositeSidePlacementNeverOverlapsOrLeavesCanvas(): void
    {
        $position = new \ReflectionMethod(ImageCaptcha::class, 'sliderDecoyX');
        foreach ([1, 2] as $shape) {
            [$width] = getimagesize(dirname(__DIR__).'/resources/templates/slider_'.$shape.'/fixed.png');
            foreach (range($width + 5, 600 - $width - 11) as $target) {
                foreach ([-15, 0, 15] as $change) {
                    $decoyWidth = $width + $change;
                    $x = $position->invoke(null, $target, $width, $decoyWidth);
                    $this->assertGreaterThanOrEqual($width, $x);
                    $this->assertLessThanOrEqual(600, $x + $decoyWidth);
                    $this->assertTrue($x + $decoyWidth <= $target || $x >= $target + $width);
                }
            }
        }
    }

    public function testDefaultSliderOnlyPaintsItsRealTarget(): void
    {
        $render = new \ReflectionMethod(ImageCaptcha::class, 'slider');
        for ($i=0;$i<16;$i++) {
            $background = imagecreatetruecolor(600, 360);
            imagefill($background, 0, 0, 0xffffff);
            [$piece, $answer] = $render->invoke(new ImageCaptcha(), $background);
            $x = (int)round($answer['percentage']*600); $width=imagesx($piece); $outside=0;
            for ($y=0;$y<360;$y++) for ($column=0;$column<600;$column++) {
                if (($column<$x || $column>=$x+$width) && (imagecolorat($background,$column,$y)&0xffffff)!==0xffffff) $outside++;
            }
            $this->assertSame(0,$outside,'默认不能出现第二个歧义缺口');
        }
    }

    public function testExtraHoleIsRenderedButNeverAcceptedAsAnswer(): void
    {
        $engine = new ImageCaptcha(sliderDecoy: true);
        $slider = new \ReflectionMethod(ImageCaptcha::class, 'slider');
        for ($sample = 0; $sample < 24; $sample++) {
            $background = imagecreatetruecolor(600, 360);
            imagefill($background, 0, 0, 0xffffff);
            [$piece, $answer] = $slider->invoke($engine, $background);
            $targetX = (int) round($answer['percentage'] * 600);
            $width = imagesx($piece);
            $decoyColumns = [];
            for ($x = 0; $x < 600; $x++) {
                if ($x >= $targetX && $x < $targetX + $width) continue;
                for ($y = 0; $y < 360; $y++) {
                    if ((imagecolorat($background, $x, $y) & 0xffffff) !== 0xffffff) {
                        $decoyColumns[] = $x;
                        break;
                    }
                }
            }
            $this->assertNotEmpty($decoyColumns, '必须渲染额外干扰缺口');
            $track = static fn(float $ratio) => ['bgImageWidth'=>300, 'bgImageHeight'=>180,
                'startTime'=>1000, 'stopTime'=>2000, 'trackList'=>[
                    ['type'=>'down','x'=>10,'y'=>20,'t'=>0],
                    ['type'=>'up','x'=>10+$ratio*300,'y'=>20,'t'=>1000],
                ]];
            $this->assertTrue($engine->verify(['type'=>'SLIDER'] + $answer, $track($answer['percentage'])));
            $this->assertFalse($engine->verify(['type'=>'SLIDER'] + $answer, $track($decoyColumns[0]/600)));
        }
    }
}
