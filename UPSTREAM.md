# Upstream provenance and porting differences

- Upstream: https://github.com/dromara/tianai-captcha
- Release: **1.5.6**, commit **89c064a29abd76bf0b253adbe7f31e8baf965e84**.
- Original authors: 天爱有情 and Tianai contributors. The adapted source is Apache-2.0.
- This is an independent, unofficial PHP/GD port, maintained by XuPaoYe.

Paths below are relative to `tianai-captcha/src/main/java/cloud/tianai/captcha/`:

| Upstream source | PHP implementation | Differences |
| --- | --- | --- |
| generator/impl/StandardSliderImageCaptchaGenerator.java | ImageCaptcha::slider | GD instead of AWT; cryptographic randomness; two original Cangqiong shapes; one real target by default; optional decoy with scaled-width overlap/bounds protection |
| generator/common/util/CaptchaImageUtils.java | ImageCaptcha::cut | Antialiased masks from original procedural geometry; source RGB and fractional alpha preservation tested independently |
| generator/impl/AbstractClickImageCaptchaGenerator.java | ImageCaptcha::words | Bounded random packing, three ordered targets, two distractors, distinct symbols, equal-pixel hit radii |
| generator/impl/StandardWordClickImageCaptchaGenerator.java | ImageCaptcha::words / wordHint | Renamed OFL font, GD positioning and randomized raster hint interference; not pixel-identical to AWT |
| generator/impl/StandardRotateImageCaptchaGenerator.java | ImageCaptcha::rotate | GD circular crop, reverse rotation, percentage 250–549/600, SDK track denominator 242 |
| validator/impl/SimpleImageCaptchaValidator.java | ImageCaptcha::verify | Float32 normalization and inclusive bounds; additional finite JSON-number, stored-answer, event-sequence, dimension, event count and timing checks |
| generator/impl/PowCaptchaGenerator.java; validator/common/util/CapPowVerifier.java | PowCaptcha | SHA256 salt/target derivation, ordered nonces, odd hex-prefix lengths; stricter parameter bounds and canonical JS-safe integers |

No Java cache framework, Spring/Solon integration, BasicCaptchaTrackValidator behavioral heuristics or HTTP/session/database implementation is included. TACBuilder multi-resource fixes in 1.5.6 do not apply to this PHP architecture. POW is an independent core API; host applications must decide whether and how to enable it. Its presence is not proof of human interaction.

`resources/templates/slider_{1,2}/{mask,fixed,active}.png` are original Cangqiong artwork generated from analytic geometry by `scripts/generate-templates.php`. No upstream template images are shipped. The generator draws the masks, recessed targets and movable-piece bevels without reading external artwork. Reproduction and rendering checks are documented in `resources/templates/README.md`.

Font provenance, renaming and reproduction instructions are under `resources/fonts/`. The PHP renderer uses a Noto Sans SC subset, not the font shipped by Tianai. Bundled scenery, illustrations and fictional portraits are independently AI-generated; no upstream photographs are redistributed. The public package discovers JPEGs in its trusted background directory. No customer/framework source or configuration is published.

The official browser SDK is not distributed by this repository. Use the same pinned upstream commit and retain its separate MulanPSL-2.0 license and embedded third-party notices. Host-specific browser theme/touch/lifecycle adapters are outside this package; upgrading a browser SDK still requires integration testing.

Local release validation is recorded in CHANGELOG.md. No OCR attack benchmark, production throughput result, physical-device acceptance or full Java compatibility is claimed.

## Frozen reference baseline

The historical reference run passed 2204 decisions and 24200 mask pixels before the test-tool migration. The 2204 independently produced expected decisions remain in `tests/fixtures/reference/`, with provenance and SHA256 hashes. `composer test` includes this regression suite; `composer parity` selects it alone. Of these, 1356 supported historical decisions remain unchanged; 726 retired CONCAT and 122 old four-target click cases now explicitly assert rejection, without changing the fixture or pretending compatibility. This preserves sampled historical decisions for supported types, not full or ongoing upstream equivalence.

The old cut-image fixtures were deliberately retired when the upstream template artwork was replaced. `TemplateRenderingTest` separately checks the original shapes, transparent bounds, exact source-pixel preservation (24200 pixels), and recess/piece visibility on light and dark backgrounds. It does not claim pixel equivalence to the retired artwork.

## Cangqiong extensions

Alphanumeric and shape ordered clicks share the existing click protocol and private answer validation. Their server-side kinds are ALPHANUMERIC_CLICK and SHAPE_CLICK; public interaction type remains WORD_IMAGE_CLICK. Shape artwork is procedurally generated in PHP, independent of fonts. CONCAT generation and verification are removed. Slider masks and artwork are rendered at 4x then downsampled with fractional coverage; cut preserves source colour and combined alpha instead of thresholding edges.
