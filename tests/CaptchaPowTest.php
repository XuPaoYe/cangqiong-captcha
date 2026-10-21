<?php
namespace Tests\Unit;

use Foundation\Captcha\PowCaptcha;
use PHPUnit\Framework\TestCase;

final class CaptchaPowTest extends TestCase
{
    public function testOfficialDefaultsAndFreshIdentifiers(): void
    {
        $engine = new PowCaptcha();
        $one = $engine->generate(); $two = $engine->generate();
        $this->assertSame([4, 16, 4], $one['public']['data']['POW']);
        $this->assertSame('POW', $one['public']['type']);
        $this->assertNotSame($one['public']['id'], $two['public']['id']);
        $this->assertSame($one['public']['id'], $one['answer']['id']);
        $this->assertSame(['id', 'type', 'data'], array_keys($one['public']));
    }

    public function testProofBindsTokenIndexAndAllSolutions(): void
    {
        $engine = new PowCaptcha();
        $challenge = $engine->generate('POW_test');
        $solutions = [];
        // Independent test solver: compare individual hash nibbles, not package internals.
        for ($index = 1; $index <= 4; $index++) {
            $salt = substr(hash('sha256', 'POW_test' . $index), 0, 16);
            $target = substr(hash('sha256', 'POW_test' . $index . 'd'), 0, 4);
            for ($nonce = 0; ; $nonce++) {
                if (str_starts_with(hash('sha256', $salt . $nonce), $target)) break;
            }
            $solutions[] = $nonce;
        }
        $data = ['data' => implode('-', $solutions)];
        $this->assertTrue($engine->verify($challenge['answer'], $data));
        $this->assertFalse($engine->verify($engine->generate('another_token')['answer'], $data));
        $this->assertFalse($engine->verify($challenge['answer'], ['data' => implode('-', array_reverse($solutions))]));
        $this->assertFalse($engine->verify($challenge['answer'], ['data' => implode('-', array_slice($solutions, 1))]));
        $this->assertFalse($engine->verify($challenge['answer'], ['data' => $data['data'] . '-0']));
    }

    public function testMalformedInputFailsClosedWithoutNumericCoercion(): void
    {
        $engine = new PowCaptcha(); $answer = $engine->generate('token', [1, 1, 1])['answer'];
        foreach ([null, [], 0, '', '-1', '00', '1e3', '1.0', "'0'", '"0"', '9007199254740992', str_repeat('1', 272)] as $bad) {
            $this->assertFalse($engine->verify($answer, ['data' => $bad]));
        }
        foreach ([[0, 16, 4], [7, 16, 4], [4, 0, 4], [4, 65, 4], [4, 16, 0], [4, 16, 17], ['4', 16, 4], [4, 16], [1 => 4, 2 => 16, 3 => 4]] as $parameters) {
            $this->assertFalse($engine->verify(['id' => 'token', 'parameters' => $parameters], ['data' => '0']));
            try { $engine->generate('token', $parameters); $this->fail('Invalid configuration accepted'); }
            catch (\InvalidArgumentException) { $this->addToAssertionCount(1); }
        }
    }

}
