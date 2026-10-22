<?php
namespace Foundation\Captcha;

/** Stateless policy; the host owns trusted time, private answers and atomic storage. */
final class ChallengePolicy
{
    public const TICKET_LIMIT = 20;
    public const TICKET_WINDOW_SECONDS = 300;

    /** Persist the returned pool only after the challenge has been stored successfully. */
    public static function nextType(mixed $pool, string $previous = ''): array
    {
        if (!is_array($pool) || !$pool || array_filter($pool, static fn($v)=>!is_string($v))
            || count($pool)!==count(array_unique($pool)) || array_diff($pool,ImageCaptcha::TYPES)) $pool=ImageCaptcha::TYPES;
        $types=array_values(array_diff($pool,[$previous]));
        if (!$types) $types=array_values($pool);
        $type=$types[random_int(0,count($types)-1)];
        return ['type'=>$type,'remaining'=>array_values(array_diff($pool,[$type]))];
    }

    /** Call after rendering; keep this entire answer private. Clock is server Unix milliseconds. */
    public static function prepare(array $answer, int $nowMs): array
    {
        if ($nowMs<0) throw new \InvalidArgumentException('Invalid server clock');
        $answer['issued_at_ms']=$nowMs;
        if (($answer['type']??null)==='SLIDER') $answer['tolerant']=0.012;
        if (($answer['type']??null)==='ROTATE') $answer['tolerant']=0.015;
        return $answer;
    }

    public static function verify(ImageCaptcha $engine, array $answer, array $data, int $nowMs): bool
    {
        if (!$engine->verify($answer,$data)) return false;
        $issued=$answer['issued_at_ms']??null;
        if (!is_int($issued)||$issued<0||$nowMs<$issued) return false;
        $elapsed=$nowMs-$issued;
        return $elapsed>=300 && $elapsed<120000 && $data['stopTime']-$data['startTime']<=$elapsed+250;
    }

    /** Redis EVAL: one host-namespaced binding key; args action, unique event ID, clock override.
     * Use an empty clock override in production (Redis TIME). Returns [allowed,retrySeconds,count].
     * Actions: check, failure (actual incorrect pending answer only), success.
     */
    public static function failureCooldownScript(): string
    {
        $script=file_get_contents(__DIR__.'/redis/failure-cooldown.lua');
        if ($script===false) throw new \RuntimeException('Missing CAPTCHA cooldown script');
        return $script;
    }
}
