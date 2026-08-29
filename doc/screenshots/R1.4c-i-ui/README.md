# R1.4c-i Privacy Requests - UI correction captures

Local verification captures, **not** production screenshots. Taken from a local
server running the corrected views against a local SQLite database.

## No personal data

The only subject rendered is `Sample Subject / sample@example.test`, a synthetic
fixture created locally for this capture. Nothing in these images came from
production, and the local database was deleted afterwards.

## What they show

| File | Width | Screen |
| --- | ---: | --- |
| `privacy-requests-desktop.png` | 1440 | Register, empty state and Record a request |
| `privacy-requests-wide.png` | 1920 | Same, wide desktop |
| `privacy-requests-mobile.png` | 390 | Same, narrow |
| `privacy-request-detail-desktop.png` | 1440 | One request, assembled response |
| `privacy-request-detail-wide.png` | 1920 | Same, wide desktop |
| `privacy-request-detail-mobile.png` | 390 | Same, narrow |

## Measured, not eyeballed

Every capture was taken with the page instrumented. At all three widths, on both
screens:

| Measure | Result |
| --- | --- |
| Horizontal page overflow | 0px |
| Clipped headings, labels or record labels | 0 |
| Unresolvable sprite icons | 0 |
| Form width via `.settings-fields` | 560px desktop and wide, 252px mobile |
| JavaScript errors | none |
| 5xx responses | none |

**The table scrolls inside its own `.table-scroll` container on mobile**, which is
the design system's intended behaviour and matches Retention. The page itself
never scrolls sideways.
