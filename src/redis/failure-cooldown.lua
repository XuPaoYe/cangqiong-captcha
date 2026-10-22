-- Reuse the installed limiter's Redis commands and isolated security namespace.
local now
if ARGV[3] == '' then
    local clock = redis.call('TIME')
    now = tonumber(clock[1]) * 1000 + math.floor(tonumber(clock[2]) / 1000)
else
    now = tonumber(ARGV[3])
end
if ARGV[1] == 'success' then
    redis.call('ZREMRANGEBYSCORE', KEYS[1], '-inf', '+inf')
    return {1, 0, 0}
end
redis.call('ZREMRANGEBYSCORE', KEYS[1], '-inf', now - 300000)
local count = redis.call('ZCARD', KEYS[1])
local function remaining()
    if count < 8 then return 0 end
    local last = redis.call('ZRANGE', KEYS[1], -1, -1, 'WITHSCORES')
    local delay = count >= 12 and 120000 or 30000
    return math.max(0, math.ceil((tonumber(last[2]) + delay - now) / 1000))
end
local retry = remaining()
-- Polling or concurrent rejected requests never extend the cooldown.
if retry > 0 then return {0, retry, count} end
if ARGV[1] == 'failure' then
    redis.call('ZADD', KEYS[1], now, ARGV[2])
    redis.call('PEXPIRE', KEYS[1], 300000)
    count = count + 1
    retry = remaining()
end
return {retry == 0 and 1 or 0, retry, count}
