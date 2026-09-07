#!/usr/bin/env python3
"""Build the local lunar search catalog from the official USGS result table."""

from __future__ import annotations

import argparse
import json
import re
import unicodedata
import urllib.request
from datetime import date
from html.parser import HTMLParser
from pathlib import Path


SOURCE_URL = "https://planetarynames.wr.usgs.gov/SearchResults?Target=16_Moon"
OFFICIAL_BASE = "https://planetarynames.wr.usgs.gov"


def compact(value: str) -> str:
    return re.sub(r"\s+", " ", value).strip()


def normalized(value: str) -> str:
    ascii_value = unicodedata.normalize("NFKD", value).encode("ascii", "ignore").decode("ascii")
    return compact(ascii_value.lower())


class GazetteerParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.in_results = False
        self.in_row = False
        self.cell_class: str | None = None
        self.cell_parts: list[str] = []
        self.cell_titles: list[str] = []
        self.cell_attrs: dict[str, str] = {}
        self.row: dict[str, dict[str, object]] = {}
        self.rows: list[dict[str, dict[str, object]]] = []

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        attributes = {key: value or "" for key, value in attrs}
        if tag == "tbody" and attributes.get("id") == "results_body":
            self.in_results = True
        elif self.in_results and tag == "tr":
            self.in_row = True
            self.row = {}
        elif self.in_row and tag == "td":
            classes = attributes.get("class", "").split()
            self.cell_class = classes[0] if classes else ""
            self.cell_parts = []
            self.cell_titles = []
            self.cell_attrs = attributes
        elif self.cell_class and attributes.get("title"):
            self.cell_titles.append(attributes["title"])

    def handle_data(self, data: str) -> None:
        if self.cell_class:
            self.cell_parts.append(data)

    def handle_endtag(self, tag: str) -> None:
        if tag == "td" and self.cell_class is not None:
            self.row[self.cell_class] = {
                "text": compact("".join(self.cell_parts)),
                "titles": self.cell_titles[:],
                "sort": self.cell_attrs.get("data-sort", ""),
            }
            self.cell_class = None
        elif tag == "tr" and self.in_row:
            if self.row:
                self.rows.append(self.row)
            self.in_row = False
        elif tag == "tbody" and self.in_results:
            self.in_results = False


def text(row: dict[str, dict[str, object]], key: str) -> str:
    return str(row.get(key, {}).get("text", ""))


def optional_float(value: str) -> float | None:
    try:
        return float(value)
    except ValueError:
        return None


def build(html: str) -> dict[str, object]:
    parser = GazetteerParser()
    parser.feed(html)
    features = []
    for row in parser.rows:
        status_cell = row.get("approvalStatusColumn", {})
        status_titles = status_cell.get("titles", [])
        official_status = str(status_titles[0]) if status_titles else text(row, "approvalStatusColumn")
        if official_status != "Adopted by IAU":
            continue
        feature_id = int(text(row, "featureIDColumn"))
        name = text(row, "featureNameColumn")
        reference_cell = row.get("referenceColumn", {})
        reference_titles = reference_cell.get("titles", [])
        reference_code = text(row, "referenceColumn")
        reference = reference_code
        if reference_titles:
            reference = f"{reference_code} - {reference_titles[0]}"
        approval_cell = row.get("approvalDateColumn", {})
        updated_cell = row.get("lastUpdatedColumn", {})
        approval_sort = str(approval_cell.get("sort", ""))
        updated_sort = str(updated_cell.get("sort", ""))
        features.append({
            "id": f"iau:{feature_id}",
            "iau_feature_id": feature_id,
            "name": name,
            "normalized_name": normalized(name),
            "clean_name": text(row, "cleanFeatureNameColumn") or name,
            "type": text(row, "featureTypeColumn"),
            "type_code": text(row, "featureTypeCodeColumn"),
            "latitude": optional_float(text(row, "centerLatLonColumn")),
            "longitude": None,
            "diameter_km": optional_float(text(row, "diameterColumn")),
            "approval_status": official_status,
            "approval_date": approval_sort[:10] if approval_sort else text(row, "approvalDateColumn"),
            "origin": text(row, "originColumn") or None,
            "reference": reference or None,
            "last_updated": updated_sort[:10] if updated_sort else text(row, "lastUpdatedColumn"),
            "official_url": f"{OFFICIAL_BASE}/Feature/{feature_id}",
        })

        # Both center coordinates use the same CSS class in the official table.
        # HTMLParser's dict would retain only the last one, so they are repaired below.
    return {
        "metadata": {
            "source": "Gazetteer of Planetary Nomenclature, IAU/USGS",
            "source_url": SOURCE_URL,
            "generated_at": date.today().isoformat(),
            "filter": "Target Moon; Approval Status Adopted by IAU",
            "coordinate_system": "IAU Moon, planetocentric, positive east, -180..180",
            "count": len(features),
        },
        "features": features,
    }


class CoordinateParser(GazetteerParser):
    """Parser variant retaining repeated centerLatLon cells in source order."""

    def __init__(self) -> None:
        super().__init__()
        self.center_values: list[str] = []
        self.row_centers: list[list[str]] = []

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        if self.in_results and tag == "tr":
            self.center_values = []
        super().handle_starttag(tag, attrs)

    def handle_endtag(self, tag: str) -> None:
        if tag == "td" and self.cell_class == "centerLatLonColumn":
            self.center_values.append(compact("".join(self.cell_parts)))
        was_row = tag == "tr" and self.in_row
        super().handle_endtag(tag)
        if was_row:
            self.row_centers.append(self.center_values[:])


def build_with_coordinates(html: str) -> dict[str, object]:
    parser = CoordinateParser()
    parser.feed(html)
    catalog = build(html)
    approved_centers = []
    for row, centers in zip(parser.rows, parser.row_centers):
        titles = row.get("approvalStatusColumn", {}).get("titles", [])
        status = str(titles[0]) if titles else text(row, "approvalStatusColumn")
        if status == "Adopted by IAU":
            approved_centers.append(centers)
    for feature, centers in zip(catalog["features"], approved_centers):
        feature["latitude"] = optional_float(centers[0]) if centers else None
        feature["longitude"] = optional_float(centers[1]) if len(centers) > 1 else None
    return catalog


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--input", type=Path, help="HTML already downloaded from USGS")
    parser.add_argument("--output", type=Path, default=Path("assets/data/moon-gazetteer.json"))
    args = parser.parse_args()
    if args.input:
        html = args.input.read_text(encoding="utf-8")
    else:
        request = urllib.request.Request(SOURCE_URL, headers={"User-Agent": "AquellasLunas catalog updater"})
        with urllib.request.urlopen(request, timeout=120) as response:
            html = response.read().decode("utf-8")
    catalog = build_with_coordinates(html)
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(catalog, ensure_ascii=False, separators=(",", ":")) + "\n", encoding="utf-8")
    print(f"Wrote {catalog['metadata']['count']} approved lunar features to {args.output}")


if __name__ == "__main__":
    main()
