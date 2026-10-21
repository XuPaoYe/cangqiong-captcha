<?php
namespace Tests\Unit;

use Foundation\Captcha\ImageCaptcha;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClickVariantsTest extends TestCase
{
    public static function variants(): iterable
    {
        foreach (ImageCaptcha::CLICK_TYPES as $kind) yield $kind => [$kind];
    }

    #[DataProvider('variants')]
    public function testOrderedClicksShareInteractionWithoutExposingAnswers(string $kind): void
    {
        $engine = new ImageCaptcha();
        for ($sample = 0; $sample < 8; $sample++) {
            $challenge = $engine->generate($kind);
            $this->assertSame($kind, $challenge['answer']['type']);
            $this->assertSame('WORD_IMAGE_CLICK', $challenge['public']['type']);
            $this->assertSame(['type','backgroundImage','templateImage','backgroundImageWidth','backgroundImageHeight','templateImageWidth','templateImageHeight'], array_keys($challenge['public']));
            $hint = imagecreatefromstring(base64_decode(explode(',', $challenge['public']['templateImage'], 2)[1], true));
            $this->assertSame([286,76], [imagesx($hint),imagesy($hint)]);
            $points = $challenge['answer']['points'];
            foreach ($points as $i => $point) foreach (array_slice($points, $i+1) as $other) {
                $this->assertTrue(abs($point[0]-$other[0]) > 2*50/600 || abs($point[1]-$other[1]) > 2*50/360, 'Hit regions must not overlap');
            }
            $track = array_map(static fn($p,$i) => ['type'=>'click','x'=>$p[0]*300,'y'=>$p[1]*180,'t'=>$i*300], $points, [0,1,2]);
            $input = ['bgImageWidth'=>300,'bgImageHeight'=>180,'startTime'=>1000,'stopTime'=>2400,'trackList'=>$track];
            $this->assertTrue($engine->verify($challenge['answer'], $input));
            $wrong = $input; $wrong['trackList'][0]['x'] = $track[1]['x']; $wrong['trackList'][0]['y'] = $track[1]['y'];
            $this->assertFalse($engine->verify($challenge['answer'], $wrong), 'Duplicate or wrong symbol rejected');
            $wrong = $input;
            foreach ([2,1,0] as $i=>$source) { $wrong['trackList'][$i] = $track[$source]; $wrong['trackList'][$i]['t']=$i*300; }
            $this->assertFalse($engine->verify($challenge['answer'], $wrong), 'Wrong order rejected');
            $wrong = $input; array_pop($wrong['trackList']);
            $this->assertFalse($engine->verify($challenge['answer'], $wrong), 'Incomplete answer rejected');
            $wrong = $input; $wrong['trackList'][0]['x'] = -1;
            $this->assertFalse($engine->verify($challenge['answer'], $wrong));
            $wrong = $input; $wrong['trackList'][0]['type'] = 'down';
            $this->assertFalse($engine->verify($challenge['answer'], $wrong));
            $corrupt = $challenge['answer']; $corrupt['tolerant'] = .5;
            $this->assertFalse($engine->verify($corrupt, $input));
            $corrupt = $challenge['answer']; $corrupt['points'][1] = $corrupt['points'][0];
            $this->assertFalse($engine->verify($corrupt, $input));
            // All three visual hint cells need actual foreground content.
            for ($cell=0;$cell<3;$cell++) {
                $strokes=0;
                for($y=6;$y<70;$y++)for($x=22+$cell*86;$x<92+$cell*86;$x++) {
                    $c=imagecolorsforindex($hint,imagecolorat($hint,$x,$y));
                    if(max($c['red'],$c['green'],$c['blue'])<160)$strokes++;
                }
                $this->assertGreaterThan(140,$strokes);
            }
        }
    }

    public function testRandomLayoutsAndFallbackStayClearAndWithinBounds(): void
    {
        $engine=new ImageCaptcha(); $layouts=[];
        foreach (['clickLayout','fallbackClickLayout'] as $method) {
            $layout=new \ReflectionMethod(ImageCaptcha::class,$method);
            for($run=0;$run<500;$run++) {
                $points=$layout->invoke($engine);
                $this->assertCount(5,$points);
                foreach($points as $i=>[$x,$y]) {
                    $this->assertGreaterThanOrEqual(18,$x);$this->assertLessThanOrEqual(482,$x);
                    $this->assertGreaterThanOrEqual(18,$y);$this->assertLessThanOrEqual(242,$y);
                    foreach(array_slice($points,$i+1) as [$otherX,$otherY]) {
                        $this->assertTrue(abs($otherX-$x)>=120||abs($otherY-$y)>=120,'Artwork and hit areas must have clearance');
                    }
                }
                if($method==='clickLayout')$layouts[]=json_encode($points);
            }
        }
        $this->assertGreaterThan(490,count(array_unique($layouts)),'Placement must vary across challenges');
    }

    #[DataProvider('variants')]
    public function testHitAreasUseEqualPixelRadiusAndRejectExtraOrMalformedClicks(string $kind): void
    {
        $engine=new ImageCaptcha();
        $answer=['type'=>$kind,'points'=>[[.2,.25],[.5,.5],[.8,.75]],'tolerant'=>50/600];
        $base=['bgImageWidth'=>300,'bgImageHeight'=>180,'startTime'=>1000,'stopTime'=>2400,
            'trackList'=>[['type'=>'click','x'=>60,'y'=>45,'t'=>0],['type'=>'click','x'=>150,'y'=>90,'t'=>300],['type'=>'click','x'=>240,'y'=>135,'t'=>600]]];
        foreach(['x','y'] as $axis) foreach([-1,1] as $sign) {
            $input=$base;$input['trackList'][0][$axis]+=$sign*24;
            $this->assertTrue($engine->verify($answer,$input),'Inside artwork-sized hit area');
            $input=$base;$input['trackList'][0][$axis]+=$sign*26;
            $this->assertFalse($engine->verify($answer,$input),'Outside fixed radius');
        }
        $input=$base;$input['trackList'][]=['type'=>'click','x'=>240,'y'=>135,'t'=>900];
        $this->assertFalse($engine->verify($answer,$input),'Extra clicks cannot be ignored');
        foreach(['60',null,true,[],INF,NAN] as $invalid) {
            $input=$base;$input['trackList'][0]['x']=$invalid;
            $this->assertFalse($engine->verify($answer,$input));
        }
        $overlap=$answer;$overlap['points'][1]=[.21,.26];
        $this->assertFalse($engine->verify($overlap,$base),'Overlapping stored answer regions must fail closed');
        $answer['points'][]=[.9,.9];
        $this->assertFalse($engine->verify($answer,$base),'Old four-target answers must expire');
    }

    public function testTargetOutlineStaysVisibleWhenFillMatchesBackground(): void
    {
        $engine=new ImageCaptcha();$render=new \ReflectionMethod(ImageCaptcha::class,'symbol');
        foreach ([[49,85,61],[244,224,133]] as $rgb) {
            $background=($rgb[0]<<16)|($rgb[1]<<8)|$rgb[2];
            foreach ([['WORD_IMAGE_CLICK','春',52],['ALPHANUMERIC_CLICK','2A',32],['SHAPE_CLICK','circle',72]] as [$kind,$symbol,$size]) {
                $image=imagecreatetruecolor(100,100);imagefill($image,0,0,$background);
                $render->invoke($engine,$image,$symbol,$size,0,$rgb,$kind);
                $contrast=0;
                for($y=0;$y<100;$y++)for($x=0;$x<100;$x++) {
                    $pixel=imagecolorsforindex($image,imagecolorat($image,$x,$y));
                    if(max(abs($pixel['red']-$rgb[0]),abs($pixel['green']-$rgb[1]),abs($pixel['blue']-$rgb[2]))>30)$contrast++;
                }
                $this->assertGreaterThan(100,$contrast,'Fine outline must separate matching foreground and background');
            }
        }
    }

    public function testEveryAlphanumericGlyphExistsAndIsDistinct(): void
    {
        $engine = new ImageCaptcha();
        $text = new \ReflectionMethod(ImageCaptcha::class, 'text');
        $hashes = [];
        foreach (str_split('23456789ACDEFHJKMNPRTUVWXY') as $character) {
            $image=imagecreatetruecolor(100,100);imagefill($image,0,0,0xffffff);
            $text->invoke($engine,$image,$character,40,0,[30,40,50]);
            ob_start();imagepng($image);$hashes[]=hash('sha256',ob_get_clean());
        }
        $this->assertCount(count($hashes),array_unique($hashes),'Missing glyphs must not become identical tofu boxes');
    }

    public function testShapeSilhouettesAreDistinctAndDoNotDependOnColour(): void
    {
        $render = new \ReflectionMethod(ImageCaptcha::class, 'symbol');
        $engine = new ImageCaptcha(); $hashes=[];
        foreach (['circle','triangle','square','star','heart','cross'] as $shape) {
            $image=imagecreatetruecolor(100,100);imagefill($image,0,0,0xffffff);
            $render->invoke($engine,$image,$shape,72,0,[30,40,50],'SHAPE_CLICK');
            $visible=0;$edge=0;
            for($y=0;$y<100;$y++)for($x=0;$x<100;$x++) {
                $c=imagecolorat($image,$x,$y)&0xffffff;
                if($c!==0xffffff)$visible++;
                if($c!==0xffffff&&$c!==0x1e2832)$edge++;
                if($x<5||$x>94||$y<5||$y>94)$this->assertSame(0xffffff,$c,'Shape must not clip');
            }
            $this->assertGreaterThan(1000,$visible);
            $this->assertGreaterThan(50,$edge,'Shapes need antialiased edges');
            ob_start();imagepng($image);$hashes[]=hash('sha256',ob_get_clean());
        }
        $this->assertCount(6,array_unique($hashes));
    }

    public function testRetiredConcatenationCannotBeGeneratedOrVerified(): void
    {
        $engine=new ImageCaptcha();
        $this->assertNotContains('CONCAT',ImageCaptcha::TYPES);
        $this->assertFalse($engine->verify(['type'=>'CONCAT','percentage'=>.5,'tolerant'=>.05],[]));
        $this->expectException(\InvalidArgumentException::class);
        $engine->generate('CONCAT');
    }
}
