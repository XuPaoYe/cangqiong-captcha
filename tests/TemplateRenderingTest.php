<?php
namespace Tests\Unit;

use Foundation\Captcha\ImageCaptcha;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TemplateRenderingTest extends TestCase
{
    private const ROOT = __DIR__.'/../resources/templates/';

    public static function shapes(): iterable
    {
        yield 'top tab / right notch' => [1, [[54,12],[50,55],[16,60]], [[94,60],[54,104],[0,0]]];
        yield 'left tab / top notch' => [2, [[12,54],[55,55],[88,20]], [[60,18],[105,54],[0,0]]];
    }

    public static function shapeIds(): iterable
    {
        yield 'first shape' => [1];
        yield 'second shape' => [2];
    }

    #[DataProvider('shapes')]
    public function testOriginalGeometryHasTabsNotchesAndBoundedLayers(int $shape, array $inside, array $outside): void
    {
        $mask = imagecreatefrompng(self::ROOT.'slider_'.$shape.'/mask.png');
        foreach ($inside as [$x,$y]) $this->assertSame(0, imagecolorsforindex($mask,imagecolorat($mask,$x,$y))['alpha']);
        foreach ($outside as [$x,$y]) $this->assertSame(127, imagecolorsforindex($mask,imagecolorat($mask,$x,$y))['alpha']);
        foreach (['mask','fixed','active'] as $layer) {
            $image = imagecreatefrompng(self::ROOT.'slider_'.$shape.'/'.$layer.'.png');
            $this->assertSame([110,110],[imagesx($image),imagesy($image)]);
            $visible = 0;
            for ($y=0;$y<110;$y++) for ($x=0;$x<110;$x++) {
                $alpha = imagecolorsforindex($image,imagecolorat($image,$x,$y))['alpha'];
                $solid = imagecolorsforindex($mask,imagecolorat($mask,$x,$y))['alpha'] < 127;
                if (!$solid) $this->assertSame(127,$alpha,"$shape/$layer: outside geometry ($x,$y)");
                if ($x===0 || $y===0 || $x===109 || $y===109) $this->assertSame(127,$alpha,'Must retain transparent canvas margins');
                $visible += $alpha<127 ? 1 : 0;
            }
            $this->assertGreaterThan(300,$visible);
        }
    }

    #[DataProvider('shapeIds')]
    public function testCurvedContoursRetainPartialAlpha(int $shape): void
    {
        $mask=imagecreatefrompng(self::ROOT.'slider_'.$shape.'/mask.png');
        $partial=0;
        for($y=0;$y<110;$y++)for($x=0;$x<110;$x++) {
            $a=imagecolorsforindex($mask,imagecolorat($mask,$x,$y))['alpha'];
            if($a>0&&$a<127)$partial++;
        }
        $this->assertGreaterThan(100,$partial,'Curves need subpixel coverage instead of binary staircase edges');
        $this->assertLessThan(600,$partial,'Antialiasing must not blur the whole piece');
    }

    public function testCutCombinesSourceAlphaWithoutTintingEdgeColours(): void
    {
        $source=imagecreatetruecolor(3,1);imagealphablending($source,false);imagesavealpha($source,true);
        $mask=imagecreatetruecolor(3,1);imagealphablending($mask,false);
        foreach([[0,64],[64,64],[0,127]] as $x=>[$sourceAlpha,$maskAlpha]) {
            imagesetpixel($source,$x,0,imagecolorallocatealpha($source,200,100,50,$sourceAlpha));
            imagesetpixel($mask,$x,0,imagecolorallocatealpha($mask,255,255,255,$maskAlpha));
        }
        $cut=ImageCaptcha::cut($source,$mask,0,0);
        $this->assertSame(['red'=>200,'green'=>100,'blue'=>50,'alpha'=>64],imagecolorsforindex($cut,imagecolorat($cut,0,0)));
        $this->assertSame(['red'=>200,'green'=>100,'blue'=>50,'alpha'=>96],imagecolorsforindex($cut,imagecolorat($cut,1,0)));
        $this->assertSame(127,imagecolorsforindex($cut,imagecolorat($cut,2,0))['alpha']);
    }

    #[DataProvider('shapeIds')]
    public function testNewMasksCropSourcePixelsExactly(int $shape): void
    {
        $background = imagecreatetruecolor(600,360);
        for ($y=0;$y<360;$y++) for ($x=0;$x<600;$x++) imagesetpixel($background,$x,$y,(($x%256)<<16)|(($y%256)<<8)|(($x+$y)%256));
        $mask = imagecreatefrompng(self::ROOT.'slider_'.$shape.'/mask.png');
        $actual = ImageCaptcha::cut($background,$mask,175,80);
        $this->assertSame([110,110],[imagesx($actual),imagesy($actual)]);
        for ($y=0;$y<110;$y++) for ($x=0;$x<110;$x++) {
            $pixel = imagecolorsforindex($actual,imagecolorat($actual,$x,$y));
            $maskAlpha = imagecolorsforindex($mask,imagecolorat($mask,$x,$y))['alpha'];
            $solid = $maskAlpha < 127;
            $expected = $solid ? ['red'=>(175+$x)%256,'green'=>(80+$y)%256,'blue'=>(255+$x+$y)%256,'alpha'=>$maskAlpha] : ['red'=>0,'green'=>0,'blue'=>0,'alpha'=>127];
            $this->assertSame($expected,$pixel,"$shape cut pixel ($x,$y)");
        }
    }

    #[DataProvider('shapeIds')]
    public function testRecessAndPieceStayVisibleOnLightAndDarkImages(int $shape): void
    {
        $fixed = imagecreatefrompng(self::ROOT.'slider_'.$shape.'/fixed.png');
        $active = imagecreatefrompng(self::ROOT.'slider_'.$shape.'/active.png');
        $mask = imagecreatefrompng(self::ROOT.'slider_'.$shape.'/mask.png');
        foreach ([0x080b10,0xf5f7fa] as $color) {
            foreach ([$fixed,$active] as $layer) {
                $canvas=imagecreatetruecolor(110,110);imagefill($canvas,0,0,$color);imagecopy($canvas,$layer,0,0,0,0,110,110);
                $contrast=0;
                for($y=0;$y<110;$y++)for($x=0;$x<110;$x++) {
                    $pixel=imagecolorat($canvas,$x,$y);
                    $difference=max(abs((($pixel>>16)&255)-(($color>>16)&255)),abs((($pixel>>8)&255)-(($color>>8)&255)),abs(($pixel&255)-($color&255)));
                    $contrast += $difference>=35 ? 1 : 0;
                }
                $this->assertGreaterThan(100,$contrast,'Edges must remain visible on near-black and near-white images');
            }
        }
        // Bevels must not tint or hide the actual image texture in the middle of the piece.
        $this->assertSame(127,imagecolorsforindex($active,imagecolorat($active,55,55))['alpha']);
        $this->assertSame(0,imagecolorsforindex($mask,imagecolorat($mask,55,55))['alpha']);
    }
}
