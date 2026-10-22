<?php
namespace Tests\Unit;
use Foundation\Captcha\ChallengePolicy;
use PHPUnit\Framework\TestCase;
final class FailureCooldownTest extends TestCase
{
    public function testAtomicCooldownIsolationAndExpiry():void
    {
        if(!extension_loaded('redis')||!getenv('TEST_REDIS_PORT'))$this->markTestSkipped('Optional Redis integration requires TEST_REDIS_PORT and ext-redis');
        $r=new \Redis();$r->connect(getenv('TEST_REDIS_HOST')?:'127.0.0.1',(int)getenv('TEST_REDIS_PORT'));
        $key='captcha-package-test:'.bin2hex(random_bytes(16));$other=$key.':other';
        $run=static fn($action,$now,$target=null)=>$r->eval(ChallengePolicy::failureCooldownScript(),[$target??$key,$action,bin2hex(random_bytes(16)),$now*1000],1);
        try{
            for($i=0;$i<80;$i++)$this->assertSame([1,0,0],$run('check',100));
            for($i=0;$i<8;$i++)$run('failure',100);
            $this->assertSame([0,30,8],$run('check',100));
            $this->assertSame([0,20,8],$run('failure',110));
            $this->assertSame([1,0,0],$run('check',110,$other));
            foreach([130,160,190,220] as $now)$run('failure',$now);
            $this->assertSame([0,120,12],$run('check',220));
            $this->assertSame([0,1,12],$run('check',339));
            $this->assertSame(1,$run('check',340)[0]);
            $this->assertGreaterThan(0,$r->pttl($key));$this->assertLessThanOrEqual(300000,$r->pttl($key));
            $this->assertSame([1,0,0],$run('success',340));
            $this->assertSame([1,0,0],$run('check',340));
            $run('failure',400);$this->assertSame([1,0,0],$run('check',701));
        }finally{$r->del($key,$other);$r->close();}
    }
}
