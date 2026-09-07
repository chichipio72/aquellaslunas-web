#!/usr/bin/env python3
"""Build automatic lunar enrichment from USGS geology and the LPI crater database."""

from __future__ import annotations

import argparse
import csv
import json
import math
import struct
import sys
import tempfile
import unicodedata
import zipfile
from collections import defaultdict
from datetime import date
from pathlib import Path

MOON_RADIUS_M = 1_737_400.0
CELL_M = 250_000.0
USGS_SOURCE_URL = "https://astrogeology.usgs.gov/search/map/Moon/Geology/Unified_Geologic_Map_of_the_Moon_GIS_v2/"
LPI_SOURCE_URL = "https://www.lpi.usra.edu/lunar/surface/Lunar_Impact_Crater_Database_v08Sep2015.xls"


def normalize(value: object) -> str:
    plain = unicodedata.normalize("NFKD", str(value or "")).encode("ascii", "ignore").decode("ascii")
    return " ".join(plain.lower().split())


def projected(lon: float, lat: float) -> tuple[float, float]:
    if lon >= 180.0:
        lon = -180.0 + 1e-7
    return MOON_RADIUS_M * math.radians(lon), MOON_RADIUS_M * math.radians(lat)


def read_dbf(path: Path) -> list[dict[str, object]]:
    with path.open("rb") as handle:
        header = handle.read(32)
        record_count = struct.unpack("<I", header[4:8])[0]
        header_length = struct.unpack("<H", header[8:10])[0]
        record_length = struct.unpack("<H", header[10:12])[0]
        fields = []
        while True:
            descriptor = handle.read(32)
            if descriptor[0] == 0x0D:
                handle.seek(-31, 1)
                break
            name = descriptor[:11].split(b"\0", 1)[0].decode("ascii")
            fields.append((name, chr(descriptor[11]), descriptor[16], descriptor[17]))
        handle.seek(header_length)
        rows = []
        for _ in range(record_count):
            record = handle.read(record_length)
            if not record or record[0:1] == b"*":
                rows.append({})
                continue
            offset = 1
            row: dict[str, object] = {}
            for name, field_type, length, decimals in fields:
                raw = record[offset:offset + length].decode("cp1252", "replace").strip()
                offset += length
                if field_type in "NF" and raw:
                    try:
                        row[name] = float(raw) if decimals else int(raw)
                    except ValueError:
                        row[name] = raw
                else:
                    row[name] = raw or None
            rows.append(row)
        return rows


def inside_polygon(x: float, y: float, parts: list[int], points: list[tuple[float, float]]) -> bool:
    inside = False
    ends = parts[1:] + [len(points)]
    for start, end in zip(parts, ends):
        previous_x, previous_y = points[end - 1]
        for current_x, current_y in points[start:end]:
            if ((current_y > y) != (previous_y > y)):
                crossing_x = (previous_x - current_x) * (y - current_y) / (previous_y - current_y) + current_x
                if x < crossing_x:
                    inside = not inside
            previous_x, previous_y = current_x, current_y
    return inside


def shapefile_records(path: Path):
    with path.open("rb") as handle:
        handle.seek(100)
        while True:
            record_header = handle.read(8)
            if len(record_header) < 8:
                return
            _, length_words = struct.unpack(">2i", record_header)
            content = handle.read(length_words * 2)
            shape_type = struct.unpack("<i", content[:4])[0]
            if shape_type not in (5, 15, 25):
                yield None
                continue
            xmin, ymin, xmax, ymax = struct.unpack("<4d", content[4:36])
            part_count, point_count = struct.unpack("<2i", content[36:44])
            parts = list(struct.unpack(f"<{part_count}i", content[44:44 + part_count * 4]))
            point_offset = 44 + part_count * 4
            coordinates = struct.unpack(f"<{point_count * 2}d", content[point_offset:point_offset + point_count * 16])
            points = list(zip(coordinates[::2], coordinates[1::2]))
            yield (xmin, ymin, xmax, ymax, parts, points)


def geologic_period(age: str | None) -> str | None:
    value = normalize(age)
    if "pre-nectarian" in value:
        return "Pre-Nectarian"
    matches = []
    for token, label in (
        ("copernican", "Copernican"), ("eratosthenian", "Eratosthenian"),
        ("imbrian", "Imbrian"), ("nectarian", "Nectarian"),
    ):
        if token in value:
            matches.append(label)
    return " / ".join(matches) or None


def join_geology(features: list[dict[str, object]], source_zip: Path) -> tuple[dict[str, object], dict[str, int]]:
    with tempfile.TemporaryDirectory(prefix="moon-geology-") as directory:
        names = zipfile.ZipFile(source_zip).namelist()
        shp_name = next(name for name in names if name.endswith("/Shapefiles/GeoUnits.shp"))
        dbf_name = next(name for name in names if name.endswith("/Shapefiles/GeoUnits.dbf"))
        domu_name = next(name for name in names if name.endswith("Unified_Geologic_Map_of_the_Moon_DOMU_descriptions.csv"))
        archive = zipfile.ZipFile(source_zip)
        archive.extract(shp_name, directory)
        archive.extract(dbf_name, directory)
        domu = {}
        decoded = archive.read(domu_name)
        # The CSV begins with a UTF-8 BOM.
        for row in csv.DictReader(decoded.decode("utf-8-sig").splitlines()):
            domu[row["Unit"]] = row
        dbf_rows = read_dbf(Path(directory) / dbf_name)
        points = []
        cells: dict[tuple[int, int], list[int]] = defaultdict(list)
        for index, feature in enumerate(features):
            x, y = projected(float(feature["longitude"]), float(feature["latitude"]))
            points.append((x, y))
            cells[(math.floor(x / CELL_M), math.floor(y / CELL_M))].append(index)
        matches: list[list[int]] = [[] for _ in features]
        polygons = list(shapefile_records(Path(directory) / shp_name))
        for polygon_index, polygon in enumerate(polygons):
            if polygon is None:
                continue
            xmin, ymin, xmax, ymax, parts, vertices = polygon
            candidates = []
            for cell_x in range(math.floor(xmin / CELL_M), math.floor(xmax / CELL_M) + 1):
                for cell_y in range(math.floor(ymin / CELL_M), math.floor(ymax / CELL_M) + 1):
                    candidates.extend(cells.get((cell_x, cell_y), ()))
            for feature_index in candidates:
                x, y = points[feature_index]
                if xmin <= x <= xmax and ymin <= y <= ymax and inside_polygon(x, y, parts, vertices):
                    matches[feature_index].append(polygon_index)

        output = {}
        counters = {"matched": 0, "unmatched": 0, "ambiguous": 0}
        for feature, indexes in zip(features, matches):
            if not indexes:
                counters["unmatched"] += 1
                continue
            units = []
            seen = set()
            for index in indexes:
                row = dbf_rows[index]
                symbol = str(row.get("FIRST_Unit") or "")
                key = (symbol, row.get("FIRST_Un_1"), row.get("FIRST_Un_2"))
                if key in seen:
                    continue
                seen.add(key)
                description = domu.get(symbol, {})
                units.append({
                    "symbol": symbol or None,
                    "age": row.get("FIRST_Un_1"),
                    "period": geologic_period(str(row.get("FIRST_Un_1") or "")),
                    "name": description.get("Name") or row.get("FIRST_Un_2") or None,
                    "map_unit_name": row.get("FIRST_Un_2") or None,
                    "description": description.get("Description") or None,
                    "interpretation": None if description.get("Interpretation") in (None, "", "n/a") else description.get("Interpretation"),
                })
            if len(units) != 1:
                counters["ambiguous"] += 1
                continue
            counters["matched"] += 1
            output[str(feature["id"])] = {
                "regional_geology": {
                    "relation": "La coordenada central del accidente nombrado cae dentro de esta unidad cartografiada.",
                    "evidence": "derived",
                    "method": "Point-in-polygon in the USGS Moon 2000 equidistant cylindrical projection",
                    "map_scale": "1:5,000,000",
                    "unit": units[0],
                    "source": "usgs_unified_geology_v2",
                    "limitation": "Es contexto regional en el punto central; no afirma que el accidente completo pertenezca a esta unidad.",
                }
            }
        return output, counters


def angular_distance(first: dict[str, object], second: dict[str, object]) -> float:
    lat1, lon1 = map(math.radians, (float(first["latitude"]), float(first["longitude"])))
    lat2, lon2 = map(math.radians, (float(second["latitude"]), float(second["longitude"])))
    cosine = math.sin(lat1) * math.sin(lat2) + math.cos(lat1) * math.cos(lat2) * math.cos(lon1 - lon2)
    return math.degrees(math.acos(max(-1.0, min(1.0, cosine))))


def numeric(value: object) -> float | None:
    return float(value) if isinstance(value, (int, float)) and not isinstance(value, bool) else None


def crater_payload(row: dict[str, object]) -> dict[str, object]:
    topography = {}
    for key, column, evidence, label in (
        ("measured_rim_to_floor_depth_km", "measured_depth", "measured", "Measured rim-to-floor depth"),
        ("modeled_rim_to_floor_depth_km", "modeled_depth", "modeled", "Theoretical rim-to-floor depth"),
        ("modeled_rim_height_km", "modeled_rim_height", "modeled", "Theoretical rim height"),
        ("measured_central_peak_height_km", "measured_peak", "measured", "Measured central peak height"),
    ):
        value = numeric(row.get(column))
        if value is not None and value != 0:
            topography[key] = {"value": value, "evidence": evidence, "label": label, "source": "lpi_crater_database_2015"}
    chronology = None
    if row.get("age"):
        chronology = {
            "relative_age": row["age"], "age_class": row.get("age_class"),
            "evidence": "published_estimate", "source_detail": row.get("age_source"),
            "source": "lpi_crater_database_2015",
        }
    morphology = {}
    if row.get("rays"):
        morphology["rays"] = {"value": row["rays"], "evidence": "measured", "source": "lpi_crater_database_2015"}
    if numeric(row.get("peak_degradation")) is not None:
        morphology["peak_degradation"] = {
            "value": numeric(row["peak_degradation"]), "evidence": "measured",
            "source": "lpi_crater_database_2015", "limitation": "Database classification value; consult its column description before editorial interpretation.",
        }
    payload = {
        "catalog_match": row["match"],
        "crater_catalog": {
            "catalog_diameter_km": row["diameter"],
            "coordinate_separation_degrees": row["separation"],
            "diameter_difference_percent": row["diameter_difference_percent"],
            "source": "lpi_crater_database_2015",
        },
    }
    if chronology:
        payload["chronology"] = chronology
    if topography:
        payload["topography"] = topography
    if morphology:
        payload["morphology"] = morphology
    return payload


def match_craters(features: list[dict[str, object]], xls_path: Path) -> tuple[dict[str, object], dict[str, object]]:
    try:
        import xlrd
    except ImportError as error:
        raise SystemExit("xlrd is required only to rebuild from the original LPI XLS") from error
    sheet = xlrd.open_workbook(str(xls_path), on_demand=True).sheet_by_name("Database")
    by_name: dict[str, list[dict[str, object]]] = defaultdict(list)
    for row_index in range(1, sheet.nrows):
        values = sheet.row_values(row_index)
        name = str(values[0]).strip()
        if not name or not numeric(values[1]):
            continue
        by_name[normalize(name)].append({
            "name": name, "diameter": float(values[1]), "latitude": float(values[2]), "longitude": float(values[3]),
            "measured_depth": values[11], "modeled_depth": values[12], "modeled_rim_height": values[16],
            "measured_peak": values[18], "age": values[50], "age_class": values[51], "age_source": values[53],
            "peak_degradation": values[78], "rays": values[80], "row": row_index + 1,
        })
    enriched = {}
    report = {"safe": 0, "probable": 0, "ambiguous": 0, "unmatched": 0, "probable_examples": [], "ambiguous_examples": []}
    for feature in features:
        if not str(feature.get("type", "")).lower().startswith("crater"):
            continue
        candidates = by_name.get(normalize(feature["name"]), [])
        evaluated = []
        for candidate in candidates:
            separation = angular_distance(feature, candidate)
            diameter = float(feature["diameter_km"] or 0)
            difference = abs(candidate["diameter"] - diameter) / diameter * 100 if diameter else math.inf
            evaluated.append((candidate, separation, difference))
        safe = [item for item in evaluated if item[1] <= 0.05 and item[2] <= 1.0]
        probable = [item for item in evaluated if item[1] <= 0.20 and item[2] <= 5.0]
        if len(safe) == 1:
            candidate, separation, difference = safe[0]
            candidate.update({"match":"safe", "separation":round(separation, 6), "diameter_difference_percent":round(difference, 4)})
            enriched[str(feature["id"])] = crater_payload(candidate)
            report["safe"] += 1
        elif len(probable) == 1:
            candidate, separation, difference = probable[0]
            report["probable"] += 1
            if len(report["probable_examples"]) < 100:
                report["probable_examples"].append({"id":feature["id"],"name":feature["name"],"lpi_row":candidate["row"],"separation_degrees":round(separation,6),"diameter_difference_percent":round(difference,4)})
        elif candidates:
            report["ambiguous"] += 1
            if len(report["ambiguous_examples"]) < 100:
                report["ambiguous_examples"].append({"id":feature["id"],"name":feature["name"],"candidate_rows":[item[0]["row"] for item in evaluated]})
        else:
            report["unmatched"] += 1
    return enriched, report


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--gazetteer", type=Path, default=Path("assets/data/moon-gazetteer.json"))
    parser.add_argument("--usgs-map", type=Path, required=True)
    parser.add_argument("--lpi-craters", type=Path, required=True)
    parser.add_argument("--output", type=Path, default=Path("assets/data/moon-geology-auto.json"))
    parser.add_argument("--report", type=Path, default=Path("assets/data/moon-geology-auto-report.json"))
    args = parser.parse_args()
    gazetteer = json.loads(args.gazetteer.read_text(encoding="utf-8"))
    features = gazetteer["features"]
    objects, geology_report = join_geology(features, args.usgs_map)
    craters, crater_report = match_craters(features, args.lpi_craters)
    for object_id, value in craters.items():
        objects.setdefault(object_id, {}).update(value)
    output = {
        "metadata": {
            "status":"automatic_scientific_enrichment", "generated_at":date.today().isoformat(),
            "gazetteer_snapshot":gazetteer["metadata"].get("generated_at"), "object_count":len(objects),
            "policy":"Only unambiguous spatial joins and safe crater matches are imported; missing data remains absent/null.",
            "lola":{"status":"not_processed","reason":"A versioned global raster sampling stage is deferred; no generic circular relief metrics were inferred."},
        },
        "sources": {
            "usgs_unified_geology_v2":{"title":"Unified Geologic Map of the Moon, 1:5M, version 2","version":"2.0 (2020-03-03)","url":USGS_SOURCE_URL},
            "lpi_crater_database_2015":{"title":"Lunar Impact Crater Database","version":"2015-09-08","url":LPI_SOURCE_URL,"evidence_note":"The workbook identifies measured depth, measured central peak height, stratigraphic age and rays as image/map-derived; most other physical columns are theoretical calculations."},
        },
        "objects": objects,
    }
    report = {"generated_at":date.today().isoformat(),"geologic_map":geology_report,"crater_matches":crater_report,"enriched_objects":len(objects)}
    args.output.write_text(json.dumps(output, ensure_ascii=False, separators=(",", ":")) + "\n", encoding="utf-8")
    args.report.write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(report, ensure_ascii=False))


if __name__ == "__main__":
    main()
