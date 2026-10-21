<?php
namespace Tests\Unit;

use Foundation\Captcha\ImageCaptcha;
use PHPUnit\Framework\TestCase;

final class CustomBackgroundTest extends TestCase
{
    public function testBackgroundDirectoryOverrideKeepsBundledFontAndTemplates(): void
    {
        $directory = sys_get_temp_dir().'/captcha-background-'.bin2hex(random_bytes(6));
        mkdir($directory, 0700);
        try {
            // A uniform background makes unexpected use of bundled scenery detectable.
            $image = imagecreatetruecolor(720, 432);
            imagefill($image, 0, 0, imagecolorallocate($image, 150, 60, 30));
            imagejpeg($image, $directory.'/custom.jpg', 95);
            $engine = new ImageCaptcha(backgroundDirectory: $directory, minimumCropUnits: ['custom'=>144]);
            foreach (ImageCaptcha::TYPES as $type) {
                $challenge = $engine->generate($type);
                $this->assertSame($type, $challenge['public']['type']);
                $this->assertSame(600, $challenge['public']['backgroundImageWidth']);
            }
            $render = new \ReflectionMethod(ImageCaptcha::class, 'photoBackground');
            $result = $render->invoke($engine, 'custom');
            $channels = ['red'=>[], 'green'=>[], 'blue'=>[]];
            for ($y=179;$y<=181;$y++) for ($x=299;$x<=301;$x++) {
                $sample=imagecolorsforindex($result,imagecolorat($result,$x,$y));
                foreach ($channels as $channel=>&$values) $values[]=$sample[$channel];
                unset($values);
            }
            $color=[]; foreach ($channels as $channel=>$values) { sort($values); $color[$channel]=$values[4]; }
            $this->assertEqualsWithDelta(150, $color['red'], 8);
            $this->assertEqualsWithDelta(60, $color['green'], 8);
            $this->assertEqualsWithDelta(30, $color['blue'], 8);
        } finally {
            unlink($directory.'/custom.jpg'); rmdir($directory);
        }
    }

    public function testMissingOverrideNeverFallsBackToBundledImages(): void
    {
        $this->expectException(\RuntimeException::class);
        (new ImageCaptcha(backgroundDirectory: __DIR__.'/missing-backgrounds'))->generate();
    }

    public function testWrongSizeJpegIsRejectedBeforeRendering(): void
    {
        $directory=sys_get_temp_dir().'/captcha-invalid-'.bin2hex(random_bytes(6));mkdir($directory,0700);
        try {
            $image=imagecreatetruecolor(10,10);imagejpeg($image,$directory.'/wrong.jpg');
            $this->expectException(\RuntimeException::class);
            (new ImageCaptcha(backgroundDirectory:$directory))->generate();
        } finally { unlink($directory.'/wrong.jpg');rmdir($directory); }
    }

    public function testInvalidCropConfigurationIsRejected(): void
    {
        foreach ([119,145,'138',false] as $minimum) {
            try { new ImageCaptcha(minimumCropUnits: ['portrait'=>$minimum]); $this->fail('Invalid crop configuration accepted'); }
            catch (\InvalidArgumentException) { $this->addToAssertionCount(1); }
        }
    }
}
