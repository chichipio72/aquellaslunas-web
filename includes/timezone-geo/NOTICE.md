# Third-party timezone data and reader

- Reader source: `mamluk/geo-tz` 0.2.1, commit
  `c4b00c340870e20b505f347a271100ffe38f48f8`, MIT. The local copy removes the
  Symfony/PSR cache adapters and keeps a per-request PHP array cache.
- Encoded data source: `evansiroky/node-geo-tz`, commit
  `ed663141f27ffa7057c3cfd1a1c7438150631e9f`; see `data/SOURCE.json`.
- Geographic boundaries: `timezone-boundary-builder` / OpenStreetMap, ODbL;
  see `DATA_LICENSE`.

Only the `timezones-1970` dataset is bundled. Runtime lookup is entirely local.

