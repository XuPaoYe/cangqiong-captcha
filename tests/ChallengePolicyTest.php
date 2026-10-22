<?php
namespace Tests\Unit;
use Foundation\Captcha\{ImageCaptcha,ChallengePolicy};
use PHPUnit\Framework\TestCase;
final class ChallengePolicyTest extends TestCase
{
    public function testRoundsIncludeAllTypesWithoutAdjacentRepeats(): void
    {
        $pool=null;$last='';$round=[];
        for($i=0;$i<100;$i++){
            $next=ChallengePolicy::nextType($pool,$last);
            $this->assertNotSame($last,$next['type']);$last=$next['type'];$pool=$next['remaining'];$round[]=$last;
            if($i%5===4){$this->assertEqualsCanonicalizing(ImageCaptcha::TYPES,$round);$round=[];}
        }
        foreach([null,[],['bad'],[[]],['SLIDER','SLIDER']] as $invalid)$this->assertContains(ChallengePolicy::nextType($invalid)['type'],ImageCaptcha::TYPES);
    }
    public function testAllTypesTimingPrecisionAndPrivateMetadata(): void
    {
        $engine=new ImageCaptcha();
        foreach(ImageCaptcha::TYPES as $type){
            $g=$engine->generate($type);$a=ChallengePolicy::prepare($g['answer'],100000);
            $this->assertArrayNotHasKey('issued_at_ms',$g['public']);
            $w=$type==='ROTATE'?242:300;
            $track=in_array($type,ImageCaptcha::CLICK_TYPES,true)
                ? array_map(static fn($p,$i)=>['x'=>$p[0]*300,'y'=>$p[1]*180,'type'=>'click','t'=>$i*400],$a['points'],[0,1,2])
                : [['x'=>0,'y'=>0,'type'=>'down','t'=>0],['x'=>$a['percentage']*$w,'y'=>0,'type'=>'up','t'=>800]];
            $d=['bgImageWidth'=>$w,'bgImageHeight'=>180,'startTime'=>1000,'stopTime'=>3000,'trackList'=>$track];
            $this->assertTrue(ChallengePolicy::verify($engine,$a,$d,102000));
            $this->assertFalse(ChallengePolicy::verify($engine,$a,$d,100299));
            $this->assertFalse(ChallengePolicy::verify($engine,$a,$d,101749));
            $this->assertTrue(ChallengePolicy::verify($engine,$a,$d,101750));
            $this->assertFalse(ChallengePolicy::verify($engine,$a,$d,220000));
            $this->assertFalse(ChallengePolicy::verify($engine,$a,$d,99999));
            $old=$a;unset($old['issued_at_ms']);$this->assertFalse(ChallengePolicy::verify($engine,$old,$d,102000));
            if(in_array($type,['SLIDER','ROTATE'],true)){
                $this->assertSame($type==='SLIDER'?0.012:0.015,$a['tolerant']);
                $small=$d;$small['trackList'][1]['x']+=0.01*$w;
                $large=$d;$large['trackList'][1]['x']+=0.018*$w;
                $this->assertTrue(ChallengePolicy::verify($engine,$a,$small,102000));
                $this->assertFalse(ChallengePolicy::verify($engine,$a,$large,102000));
            }
        }
    }
    public function testCooldownScriptShipsWithPackage():void
    {
        $this->assertStringContainsString("redis.call('TIME')",ChallengePolicy::failureCooldownScript());
    }
}
