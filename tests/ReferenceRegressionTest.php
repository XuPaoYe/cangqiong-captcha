<?php
namespace Tests\Unit;

use Foundation\Captcha\ImageCaptcha;
use Foundation\Captcha\PowCaptcha;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Frozen independent expectations, never regenerated from the implementation under test. */
final class ReferenceRegressionTest extends TestCase
{
    private const FIXTURES = __DIR__.'/fixtures/reference/';

    public function testFixtureIntegrityAndCoverage(): void
    {
        $manifest = json_decode(file_get_contents(self::FIXTURES.'manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(1, $manifest['schema']);
        $this->assertSame(2204, $manifest['decisionCount']);
        $this->assertSame(24200, $manifest['maskPixels']);
        $this->assertSame([175, 80], $manifest['cutOrigin']);
        $this->assertSame(['decisions.json', 'background.png', 'expected-cut-1.png', 'expected-cut-2.png'], array_keys($manifest['hashes']));
        foreach ($manifest['hashes'] as $name => $hash) {
            $this->assertSame($hash, hash_file('sha256', self::FIXTURES.$name), $name);
        }
        $cases = self::decisions();
        $this->assertCount(2204, $cases);
        $this->assertCount(2204, array_unique(array_column($cases, 'id')));
        $counts = array_count_values(array_column($cases, 'type'));
        $this->assertSame(['SLIDER'=>462, 'ROTATE'=>726, 'CONCAT'=>726, 'WORD_IMAGE_CLICK'=>122, 'POW'=>168], $counts);
        foreach (array_keys($counts) as $type) {
            $expected = array_column(array_filter($cases, static fn($case) => $case['type'] === $type), 'expected');
            $this->assertContains(true, $expected, $type.' needs positive samples');
            $this->assertContains(false, $expected, $type.' needs negative samples');
        }
    }

    public function testVerifierMatchesFrozenDecisions(): void
    {
        $image = new ImageCaptcha();
        $pow = new PowCaptcha();
        foreach (self::decisions() as $case) {
            $this->assertIsBool($case['expected'], $case['id']);
            $actual = ($case['type'] === 'POW' ? $pow : $image)->verify($case['answer'], $case['input']);
            $this->assertSame($case['expected'], $actual, $case['id']);
        }
    }

    public static function masks(): iterable
    {
        yield 'first shape' => [1];
        yield 'second shape' => [2];
    }

    #[DataProvider('masks')]
    public function testCroppedPixelsMatchFrozenReference(int $shape): void
    {
        $background = imagecreatefrompng(self::FIXTURES.'background.png');
        $mask = imagecreatefrompng(dirname(__DIR__).'/resources/templates/slider_'.$shape.'/mask.png');
        $expected = imagecreatefrompng(self::FIXTURES.'expected-cut-'.$shape.'.png');
        $actual = ImageCaptcha::cut($background, $mask, 175, 80);
        $this->assertSame(110, imagesx($actual));
        $this->assertSame(110, imagesy($actual));
        $this->assertSame(imagesx($expected), imagesx($actual));
        $this->assertSame(imagesy($expected), imagesy($actual));
        for ($y = 0; $y < imagesy($actual); $y++) {
            for ($x = 0; $x < imagesx($actual); $x++) {
                // Compare decoded RGBA, independent of PNG compression/encoder versions.
                $this->assertSame(
                    imagecolorsforindex($expected, imagecolorat($expected, $x, $y)),
                    imagecolorsforindex($actual, imagecolorat($actual, $x, $y)),
                    "Shape $shape pixel ($x, $y)",
                );
            }
        }
    }

    private static function decisions(): array
    {
        return json_decode(file_get_contents(self::FIXTURES.'decisions.json'), true, flags: JSON_THROW_ON_ERROR);
    }
}
