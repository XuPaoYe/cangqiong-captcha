<?php
namespace Tests\Unit;

use Foundation\Captcha\ImageCaptcha;
use PHPUnit\Framework\TestCase;

final class ImageProtocolTest extends TestCase
{
    public function testFiveTypesAcceptCorrectTracksAndRejectInvalidBounds(): void
    {
        $engine = new ImageCaptcha();
        foreach (ImageCaptcha::TYPES as $type) {
            $challenge = $engine->generate($type); $answer = $challenge['answer'];
            $this->assertSame(in_array($type, ImageCaptcha::CLICK_TYPES, true) ? 'WORD_IMAGE_CLICK' : $type, $challenge['public']['type']);
            $this->assertSame($type, $answer['type']);
            $this->assertArrayNotHasKey('answer', $challenge['public']);
            $this->assertArrayNotHasKey('percentage', $challenge['public']);
            $width = $type === 'ROTATE' ? 242 : 300;
            $track = in_array($type, ImageCaptcha::CLICK_TYPES, true)
                ? array_map(static fn($point, $i) => ['x'=>$point[0]*300, 'y'=>$point[1]*180, 't'=>$i*400, 'type'=>'click'], $answer['points'], [0,1,2])
                : [['x'=>10, 'y'=>30, 't'=>0, 'type'=>'down'], ['x'=>10+$answer['percentage']*$width, 'y'=>31, 't'=>800, 'type'=>'up']];
            $data = ['bgImageWidth'=>$width, 'bgImageHeight'=>180, 'startTime'=>10000, 'stopTime'=>12000, 'trackList'=>$track];
            $this->assertTrue($engine->verify($answer, $data));
            $bad=$data; $bad['bgImageWidth']=600; $this->assertFalse($engine->verify($answer, $bad));
            $bad=$data; $bad['stopTime']=10001; $this->assertFalse($engine->verify($answer, $bad));
            $bad=$data; $bad['trackList'][0]['x']=INF; $this->assertFalse($engine->verify($answer, $bad));
            $bad=$data; $bad['trackList'][0]['type']='unknown'; $this->assertFalse($engine->verify($answer, $bad));
            $bad=$data; $bad['trackList']=array_fill(0,1001,$track[0]); $this->assertFalse($engine->verify($answer, $bad));
        }
    }

    public function testMissingBackgroundsFailClosed(): void
    {
        $this->expectException(\RuntimeException::class);
        (new ImageCaptcha(__DIR__.'/missing-resources'))->generate();
    }
}
