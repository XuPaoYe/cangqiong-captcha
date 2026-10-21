# Upstream provenance and porting differences

- Upstream: https://github.com/dromara/tianai-captcha
- Release: **1.5.6**, commit **89c064a29abd76bf0b253adbe7f31e8baf965e84**.
- Original authors: 天爱有情 and Tianai contributors. Java source and templates are Apache-2.0.
- This is an independent, unofficial PHP/GD port, maintained by XuPaoYe.

Paths below are relative to `tianai-captcha/src/main/java/cloud/tianai/captcha/`:

| Upstream source | PHP implementation | Differences |
| --- | --- | --- |
| generator/impl/StandardSliderImageCaptchaGenerator.java | ImageCaptcha::slider | GD instead of AWT; cryptographic randomness; two upstream shapes; one real target by default; optional decoy with scaled-width overlap/bounds protection |
| generator/common/util/CaptchaImageUtils.java | ImageCaptcha::cut | Precomputed binary masks preserve the original alpha > 100 rule despite GD's 7-bit alpha |
| generator/impl/AbstractClickImageCaptchaGenerator.java | ImageCaptcha::words | Non-overlapping placement, four ordered targets, two distractors, distinct characters |
| generator/impl/StandardWordClickImageCaptchaGenerator.java | ImageCaptcha::words / wordHint | Renamed OFL font, GD positioning and randomized raster hint interference; not pixel-identical to AWT |
| generator/impl/StandardRotateImageCaptchaGenerator.java | ImageCaptcha::rotate | GD circular crop, reverse rotation, percentage 250–549/600, SDK track denominator 242 |
| generator/impl/StandardConcatImageCaptchaGenerator.java | ImageCaptcha::concat | Cyclic top-image shift and randomY split, 0.05 tolerance |
| validator/impl/SimpleImageCaptchaValidator.java | ImageCaptcha::verify | Float32 normalization and inclusive bounds; additional finite JSON-number, stored-answer, event-sequence, dimension, event count and timing checks |
| generator/impl/PowCaptchaGenerator.java; validator/common/util/CapPowVerifier.java | PowCaptcha | SHA256 salt/target derivation, ordered nonces, odd hex-prefix lengths; stricter parameter bounds and canonical JS-safe integers |

No Java cache framework, Spring/Solon integration, BasicCaptchaTrackValidator behavioral heuristics or HTTP/session/database implementation is included. TACBuilder multi-resource fixes in 1.5.6 do not apply to this PHP architecture. POW is an independent core API; host applications must decide whether and how to enable it. Its presence is not proof of human interaction.

`resources/templates/slider_{1,2}/{fixed,active}.png` are derived from the pinned upstream `META-INF/cut-image/template/` resources. Binary `mask.png` files preserve Java's alpha threshold; the expected cut images were captured from that reference implementation and are now compared as decoded RGBA pixels by PHP-only regression tests. The Java harness is no longer included.

Font provenance, renaming and reproduction instructions are under `resources/fonts/`. The PHP renderer uses a Noto Sans SC subset, not the font shipped by Tianai. Landscape backgrounds are independently AI-generated; no upstream photographs are redistributed. The public package discovers JPEGs in its trusted background directory, instead of carrying a private project's fixed portrait catalog or portrait-specific crop preferences. No customer/framework source or configuration is published.

The official browser SDK is not distributed by this repository. Use the same pinned upstream commit and retain its separate MulanPSL-2.0 license and embedded third-party notices. Host-specific browser theme/touch/lifecycle adapters are outside this package; upgrading a browser SDK still requires integration testing.

Local release validation is recorded in CHANGELOG.md. No OCR attack benchmark, production throughput result, physical-device acceptance or full Java compatibility is claimed.

## Frozen reference baseline

The 2026-10-21 reference run passed 2204 decisions and 24200 mask pixels before removal of the Java/Node tooling. Inputs, independently produced expected decisions and expected cut PNGs are preserved in `tests/fixtures/reference/` with provenance and SHA256 hashes. `composer test` includes the PHP regression suite; `composer parity` selects only that suite. Neither command downloads sources or runs Java/Node. This preserves those sampled decisions, not full or ongoing upstream equivalence.
