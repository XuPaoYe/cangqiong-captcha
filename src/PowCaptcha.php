<?php
/**
 * PHP adaptation of Tianai 1.5.6 PowCaptchaGenerator / CapPowVerifier (89c064a2).
 * Original authors: 天爱有情 and contributors. Apache-2.0; see LICENSE / UPSTREAM.md.
 */
namespace Foundation\Captcha;

/** Calculation proof only; callers must separately bind, expire and consume challenges. */
final class PowCaptcha
{
    /** Parameters follow upstream order: difficulty (hex digits), salt length, solution count. */
    public function generate(?string $id = null, array $parameters = [4, 16, 4]): array
    {
        if (!self::validParameters($parameters)) {
            throw new \InvalidArgumentException('POW 参数无效');
        }
        $id ??= bin2hex(random_bytes(32));
        if ($id === '' || strlen($id) > 256) {
            throw new \InvalidArgumentException('POW 挑战标识无效');
        }
        return [
            'public' => ['id' => $id, 'type' => 'POW', 'data' => ['POW' => $parameters]],
            'answer' => ['id' => $id, 'parameters' => $parameters],
        ];
    }

    /** Official SDK sends decimal nonces joined by '-' in track.data. Never solve on the server. */
    public function verify(array $answer, array $track): bool
    {
        $id = $answer['id'] ?? null;
        $parameters = $answer['parameters'] ?? null;
        $data = $track['data'] ?? null;
        if (!is_string($id) || $id === '' || strlen($id) > 256
            || !is_array($parameters) || !self::validParameters($parameters)
            || !is_string($data) || strlen($data) > 271) return false;
        [$difficulty, $saltLength, $count] = $parameters;
        $nonces = explode('-', $data);
        if (count($nonces) !== $count) return false;
        foreach ($nonces as $index => $nonce) {
            // JS safe integers, canonical decimal only; no quotes, exponents, signs or coercion.
            if (!preg_match('/\A(?:0|[1-9][0-9]{0,15})\z/', $nonce)
                || (strlen($nonce) === 16 && strcmp($nonce, '9007199254740991') > 0)) return false;
            $seed = $id . ($index + 1);
            $salt = substr(hash('sha256', $seed), 0, $saltLength);
            $target = substr(hash('sha256', $seed . 'd'), 0, $difficulty);
            // A hex prefix comparison also preserves upstream's odd-nibble target semantics.
            if (!hash_equals($target, substr(hash('sha256', $salt . $nonce), 0, $difficulty))) return false;
        }
        return true;
    }

    private static function validParameters(array $parameters): bool
    {
        return array_is_list($parameters) && count($parameters) === 3
            && is_int($parameters[0]) && $parameters[0] >= 1 && $parameters[0] <= 6
            && is_int($parameters[1]) && $parameters[1] >= 1 && $parameters[1] <= 64
            && is_int($parameters[2]) && $parameters[2] >= 1 && $parameters[2] <= 16;
    }
}
