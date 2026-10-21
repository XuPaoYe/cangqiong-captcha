# Security policy

Report vulnerabilities through this repository's GitHub **Security → Report a vulnerability** private reporting feature. Do not disclose exploitable details, credentials or production challenge answers in a public issue.

Only the latest independently maintained PHP revision is supported. No response-time SLA or commercial anti-bot guarantee is provided. Disclose the PHP/package version and a minimal reproduction with synthetic data.

This package supplies stateless primitives. Challenge expiry, session/purpose binding, rate limits, storage confidentiality and atomic attempt/ticket consumption belong to the host application. POW proves computational work, not human presence.

## Threat model and required integration controls

The engine rejects malformed/non-finite coordinates, invalid event sequences, corrupt answers and expanded tolerances. It verifies spatial answers, not the authenticity of browser telemetry. Timings, mouse paths and POW are not independent proof of a human. An image solver can synthesize a valid track; adding visual noise does not establish bot resistance.

Applications must:

- Keep answers encrypted/private; never accept an answer, tolerance or verification result supplied by a client.
- Bind unpredictable IDs to a session, instance and purpose, expire them using server time, and atomically consume failed attempts and successful tickets.
- Choose allowed challenge types on the server. Do not let public clients select easier/disabled types or switch to POW.
- Apply layered IP and session/account attempt limits before expensive rendering/verification; bound HTTP bodies and JSON nesting. Session limits alone can be evaded with new sessions; IP limits alone cannot cover distributed clients.
- Retain password authentication and its own abuse controls. A solved challenge is not authorization or an administrator credential.
- Provide usable input alternatives. Evaluate keyboard/touch, visual accessibility, failure recovery, false rejects and legitimate shared-IP traffic before enforcing stronger policies.

Before claiming anti-bot strength, measure blind-guess and image/OCR solver success, false rejects, latency and concurrent resource use against a documented test corpus. Current CI covers protocol/boundary/resource behavior and frozen historical decision/pixel regression; it does not certify those adversarial or production metrics.

Custom resource paths are trusted server configuration. Keep them out of user-controlled uploads. Use the newest reviewed immutable release and the application's lock file; do not run production on a moving branch.

## Local adversarial baseline (2026-10-21)

A small local test using only public challenge images confirmed automated slider solving: 40/64 challenges with the five bundled landscapes and 41/64 with a separate 17-image integration corpus. A simple image matcher took roughly 6 ms median per challenge on the test machine. This is not a production attack rate or a general benchmark; no account authentication was bypassed. No claim of OCR resistance or complete multi-type solver coverage is made.

Separate fixed-guess samples passed 8/120 slider, 8/120 rotation and 18/120 concatenation challenges. Ordered-word attacks were not evaluated. Protocol-valid synthetic input can pass spatial verification; track syntax alone does not prove human presence. Integration-level single-use consumption and layered attempt limits are essential, and the component should not be advertised as solver-proof.
