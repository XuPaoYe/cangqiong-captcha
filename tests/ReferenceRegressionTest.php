<?php
namespace Tests\Unit;

use Foundation\Captcha\ImageCaptcha;
use Foundation\Captcha\PowCaptcha;
use PHPUnit\Framework\TestCase;

/** Frozen independent expectations, never regenerated from the implementation under test. */
final class ReferenceRegressionTest extends TestCase
{
    private const FIXTURES = __DIR__.'/fixtures/reference/';

    public function testFixtureIntegrityAndCoverage(): void
    {
        $manifest = json_decode(file_get_contents(self::FIXTURES.'manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(2, $manifest['schema']);
        $this->assertSame(2204, $manifest['decisionCount']);
        $this->assertSame(['decisions.json'], array_keys($manifest['hashes']));
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

    public function testActiveVerifiersMatchFrozenDecisionsAndRetiredCasesFailClosed(): void
    {
        $image = new ImageCaptcha();
        $pow = new PowCaptcha();
        foreach (self::decisions() as $case) {
            $this->assertIsBool($case['expected'], $case['id']);
            $actual = ($case['type'] === 'POW' ? $pow : $image)->verify($case['answer'], $case['input']);
            // Retired CONCAT and four-click answers must now fail closed; the historical fixture is unchanged.
            $this->assertSame(in_array($case['type'], ['CONCAT','WORD_IMAGE_CLICK'], true) ? false : $case['expected'], $actual, $case['id']);
        }
    }

    private static function decisions(): array
    {
        return json_decode(file_get_contents(self::FIXTURES.'decisions.json'), true, flags: JSON_THROW_ON_ERROR);
    }
}
