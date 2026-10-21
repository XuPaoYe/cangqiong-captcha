<?php
namespace Tests\Unit;

use Foundation\Captcha\ImageCaptcha;
use PHPUnit\Framework\TestCase;

final class InputBoundaryTest extends TestCase
{
    private function track(): array
    {
        return ['bgImageWidth'=>300,'bgImageHeight'=>180,'startTime'=>1000,'stopTime'=>2000,
            'trackList'=>[['type'=>'down','x'=>10,'y'=>20,'t'=>0],['type'=>'up','x'=>160,'y'=>20,'t'=>900]]];
    }

    public function testCorruptAnswersFailClosedWithoutPhpWarnings(): void
    {
        $engine=new ImageCaptcha(); $valid=['type'=>'SLIDER','percentage'=>.5,'tolerant'=>.02];
        set_error_handler(static function($severity,$message){throw new \ErrorException($message,0,$severity);});
        try {
            foreach ([[],['type'=>'POW'],['type'=>[]],['type'=>new \stdClass()],
                array_replace($valid,['percentage'=>INF]),array_replace($valid,['percentage'=>'0.5']),
                array_replace($valid,['percentage'=>-1]),array_replace($valid,['percentage'=>2]),
                array_replace($valid,['tolerant'=>NAN]),array_replace($valid,['tolerant'=>1]),
                array_replace($valid,['tolerant'=>0]),array_replace($valid,['tolerant'=>-.02]),
                ['type'=>'WORD_IMAGE_CLICK','tolerant'=>50/600,'points'=>[]],
                ['type'=>'WORD_IMAGE_CLICK','tolerant'=>50/600,'points'=>array_fill(0,4,[.2,.3])]] as $answer) {
                $this->assertFalse($engine->verify($answer,$this->track()));
            }
        } finally { restore_error_handler(); }
    }

    public function testCoordinatesMustBeFiniteJsonNumbers(): void
    {
        $engine=new ImageCaptcha();$answer=['type'=>'SLIDER','percentage'=>.5,'tolerant'=>.02];
        $this->assertTrue($engine->verify($answer,$this->track()));
        foreach (['x','y','t'] as $key) foreach (['10','1e1',null,true,[],new \stdClass(),INF,NAN,100001,-100001] as $value) {
            $track=$this->track();$track['trackList'][0][$key]=$value;
            $this->assertFalse($engine->verify($answer,$track),$key.' invalid '.get_debug_type($value));
        }
    }

    public function testDragCannotContainExtraPressReleaseOrClickEvents(): void
    {
        $engine=new ImageCaptcha();$answer=['type'=>'SLIDER','percentage'=>.5,'tolerant'=>.02];
        foreach (['down','up','click'] as $event) {
            $track=$this->track();array_splice($track['trackList'],1,0,[['type'=>$event,'x'=>90,'y'=>20,'t'=>400]]);
            $this->assertFalse($engine->verify($answer,$track));
        }
        $track=$this->track();array_splice($track['trackList'],1,0,[['type'=>'move','x'=>90,'y'=>20,'t'=>400]]);
        $this->assertTrue($engine->verify($answer,$track));
    }

    public function testWordClicksPermitPointerMovesButNeverDragEvents(): void
    {
        $answer=['type'=>'WORD_IMAGE_CLICK','points'=>[[.1,.2],[.3,.4],[.5,.6],[.7,.8]],'tolerant'=>50/600];
        $track=$this->track();$track['trackList']=array_map(static fn($p,$i)=>['type'=>'click','x'=>$p[0]*300,'y'=>$p[1]*180,'t'=>$i*200],$answer['points'],[0,1,2,3]);
        array_splice($track['trackList'],1,0,[['type'=>'move','x'=>40,'y'=>40,'t'=>100]]);
        $engine=new ImageCaptcha();$this->assertTrue($engine->verify($answer,$track));
        foreach (['down','up'] as $event) { $track['trackList'][1]['type']=$event;$this->assertFalse($engine->verify($answer,$track)); }
    }
}
