#!/usr/bin/env python3
"""Build the compact runtime catalog used by nightly crater recommendations."""

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DATA = ROOT / "assets" / "data"


def load(name):
    return json.loads((DATA / name).read_text(encoding="utf-8"))


gazetteer = load("moon-gazetteer.json")
automatic = load("moon-geology-auto.json").get("objects", {})
pilot = load("moon-geology-experiment.json").get("objects", [])
curated = load("moon-features.json").get("features", [])

pilot_ids = {
    int(iau_id)
    for item in pilot
    for iau_id in [item.get("identity", {}).get("iau_feature_id")]
    if iau_id is not None
}
curated_names = {
    str(item.get("name", "")).casefold()
    for item in curated
    if item.get("layer") == "craters"
}

objects = []
for feature in gazetteer.get("features", []):
    diameter = feature.get("diameter_km")
    if feature.get("type") != "Crater, craters" or not isinstance(diameter, (int, float)) or diameter < 20:
        continue
    enrichment = automatic.get(feature["id"], {})
    topography = enrichment.get("topography") or {}
    morphology = enrichment.get("morphology") or {}
    objects.append({
        "id": feature["id"],
        "name": feature["name"],
        "latitude": feature["latitude"],
        "longitude": feature["longitude"],
        "diameter_km": diameter,
        "curated": feature["name"].casefold() in curated_names,
        "scientific_profile": feature.get("iau_feature_id") in pilot_ids,
        "catalog_enrichment": bool(enrichment.get("catalog_match") == "safe"),
        "has_depth": any("depth" in key for key in topography),
        "has_central_peak": any("central_peak" in key for key in topography),
        "has_rays": bool(morphology.get("rays")),
    })

output = {
    "metadata": {
        "source": "Derived from local IAU/USGS, LPI and curated lunar catalogs",
        "minimum_diameter_km": 20,
        "count": len(objects),
    },
    "craters": objects,
}
(DATA / "moon-crater-observation.json").write_text(
    json.dumps(output, ensure_ascii=False, separators=(",", ":")) + "\n",
    encoding="utf-8",
)
print(f"moon-crater-observation: {len(objects)} craters")
