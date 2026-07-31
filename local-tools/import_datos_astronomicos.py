#!/usr/bin/env python3

import argparse
import csv
import sys
import time
import unicodedata
from datetime import date, datetime, time as datetime_time
from decimal import Decimal, InvalidOperation
from pathlib import Path
from typing import Any, Iterator

from dotenv import load_dotenv


SCRIPT_DIR = Path(__file__).resolve().parent
PROJECT_DIR = SCRIPT_DIR.parent
DEFAULT_CSV_PATH = SCRIPT_DIR / "datos_astronomicos.csv"
DEFAULT_ENV_PATH = PROJECT_DIR / ".env"
DEFAULT_BATCH_SIZE = 500

CSV_TO_DATABASE = [
    ("Fecha", "fecha", "date"),
    ("HoraSalidaLuna", "hora_salida_luna", "time"),
    ("HoraSalidaSol", "hora_salida_sol", "time"),
    ("HoraPuestaLuna", "hora_puesta_luna", "time"),
    ("HoraPuestaSol", "hora_puesta_sol", "time"),
    ("AzimutSalidaLuna", "azimut_salida_luna", "number"),
    ("AzimutSalidaSol", "azimut_salida_sol", "number"),
    ("AzimutPuestaLuna", "azimut_puesta_luna", "number"),
    ("AzimutPuestaSol", "azimut_puesta_sol", "number"),
    ("DistanciaLuna_km", "distancia_luna_km", "number"),
    ("DistanciaSol_km", "distancia_sol_km", "number"),
    ("DiaCicloLunar", "dia_ciclo_lunar", "number"),
    ("IluminacionPorc", "iluminacion_porc", "number"),
    ("LatitudEclipticaLuna", "latitud_ecliptica_luna", "number"),
    ("FraccionAnioTrópico", "fraccion_anio_tropico", "number"),
    ("DiferenciaSalidaLuna_min", "diferencia_salida_luna_min", "number"),
    ("DiferenciaPuestaLuna_min", "diferencia_puesta_luna_min", "number"),
    ("DiferenciaSalidaSol_min", "diferencia_salida_sol_min", "number"),
    ("DiferenciaPuestaSol_min", "diferencia_puesta_sol_min", "number"),
    ("FaseLunar", "fase_lunar", "text"),
    ("HoraFaseLunar", "hora_fase_lunar", "time"),
    ("AnguloNodoSol", "angulo_nodo_sol", "number"),
]


class RowConversionError(ValueError):
    def __init__(self, line_number: int, row_date: str, field: str, value: str, reason: str):
        self.line_number = line_number
        self.row_date = row_date or "no disponible"
        self.field = field
        self.value = value
        super().__init__(
            f"Línea {line_number}, fecha {self.row_date}, campo {field}, "
            f"valor {value!r}: {reason}"
        )


def normalize_header(value: str) -> str:
    return unicodedata.normalize("NFC", value.strip())


def parse_arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Valida e importa datos astronómicos desde CSV a MySQL."
    )
    parser.add_argument(
        "csv_path",
        nargs="?",
        type=Path,
        default=DEFAULT_CSV_PATH,
        help=f"Ruta del CSV (predeterminado: {DEFAULT_CSV_PATH})",
    )
    parser.add_argument(
        "--batch-size",
        type=int,
        default=DEFAULT_BATCH_SIZE,
        help=f"Filas por lote (predeterminado: {DEFAULT_BATCH_SIZE})",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Valida y convierte todas las filas sin conectarse a MySQL.",
    )
    return parser.parse_args()


def load_database_config(env_path: Path) -> tuple[dict[str, Any], str]:
    if not env_path.is_file():
        raise RuntimeError(f"No se encontró el archivo de configuración: {env_path}")
    load_dotenv(env_path, override=False)

    import os

    suffixes = ("HOST", "PORT", "NAME", "USER", "PASSWORD")
    for prefix in ("WEB_DB_", "STORE_DB_"):
        values = {suffix: os.getenv(prefix + suffix, "") for suffix in suffixes}
        if all(value.strip() for value in values.values()):
            try:
                port = int(values["PORT"].strip())
            except ValueError as exc:
                raise RuntimeError(f"{prefix}PORT debe ser un número entero.") from exc
            if not 1 <= port <= 65535:
                raise RuntimeError(f"{prefix}PORT debe estar entre 1 y 65535.")
            return {
                "host": values["HOST"].strip(),
                "port": port,
                "database": values["NAME"].strip(),
                "user": values["USER"].strip(),
                "password": values["PASSWORD"],
                "charset": "utf8mb4",
                "use_unicode": True,
                "connection_timeout": 20,
            }, prefix

    expected = " o ".join(
        prefix + "{" + ",".join(suffixes) + "}" for prefix in ("WEB_DB_", "STORE_DB_")
    )
    raise RuntimeError(f"El .env no contiene una familia completa de variables: {expected}.")


def validate_headers(raw_headers: list[str] | None) -> dict[str, str]:
    if raw_headers is None:
        raise RuntimeError("El CSV está vacío y no contiene encabezados.")
    normalized_headers = [normalize_header(header) for header in raw_headers]
    expected_headers = [item[0] for item in CSV_TO_DATABASE]
    if normalized_headers != expected_headers:
        missing = [header for header in expected_headers if header not in normalized_headers]
        extra = [header for header in normalized_headers if header not in expected_headers]
        details = []
        if missing:
            details.append("faltan: " + ", ".join(missing))
        if extra:
            details.append("sobran: " + ", ".join(extra))
        if not details:
            details.append("el orden no coincide con el esperado")
        raise RuntimeError("Encabezados inválidos; " + "; ".join(details) + ".")
    return dict(zip(normalized_headers, raw_headers))


def parse_date(value: str) -> date:
    return datetime.strptime(value, "%Y-%m-%d").date()


def parse_time(value: str) -> datetime_time:
    for pattern in ("%H:%M:%S", "%H:%M"):
        try:
            return datetime.strptime(value, pattern).time()
        except ValueError:
            continue
    raise ValueError("se esperaba una hora HH:MM o HH:MM:SS")


def parse_number(value: str) -> Decimal:
    normalized = value.replace(",", ".")
    try:
        number = Decimal(normalized)
    except InvalidOperation as exc:
        raise ValueError("se esperaba un número con coma o punto decimal") from exc
    if not number.is_finite():
        raise ValueError("el número debe ser finito")
    return number


def convert_value(value: str, value_type: str) -> Any:
    stripped = value.strip()
    if stripped == "":
        return None
    if value_type == "date":
        return parse_date(stripped)
    if value_type == "time":
        return parse_time(stripped)
    if value_type == "number":
        return parse_number(stripped)
    return stripped


def convert_row(
    raw_row: dict[str, str | None],
    header_lookup: dict[str, str],
    line_number: int,
) -> tuple[Any, ...]:
    raw_date = (raw_row.get(header_lookup["Fecha"]) or "").strip()
    converted = []
    for csv_header, _database_column, value_type in CSV_TO_DATABASE:
        source_header = header_lookup[csv_header]
        raw_value = raw_row.get(source_header)
        value = "" if raw_value is None else raw_value
        try:
            converted.append(convert_value(value, value_type))
        except (ValueError, OverflowError) as exc:
            raise RowConversionError(
                line_number, raw_date, source_header, value, str(exc)
            ) from exc
    return tuple(converted)


def iter_converted_rows(csv_path: Path) -> Iterator[tuple[int, tuple[Any, ...]]]:
    with csv_path.open("r", encoding="utf-8-sig", newline="") as handle:
        reader = csv.DictReader(handle, delimiter=";")
        header_lookup = validate_headers(reader.fieldnames)
        for line_number, raw_row in enumerate(reader, start=2):
            if None in raw_row:
                raise RowConversionError(
                    line_number,
                    str(raw_row.get(header_lookup["Fecha"]) or ""),
                    "fila",
                    str(raw_row[None]),
                    "contiene más valores que encabezados",
                )
            yield line_number, convert_row(raw_row, header_lookup, line_number)


def build_upsert_sql() -> str:
    columns = [item[1] for item in CSV_TO_DATABASE]
    column_sql = ", ".join(f"`{column}`" for column in columns)
    placeholders = ", ".join(["%s"] * len(columns))
    updates = ", ".join(
        f"`{column}` = VALUES(`{column}`)" for column in columns if column != "fecha"
    )
    return (
        f"INSERT INTO `datos_astronomicos` ({column_sql}) VALUES ({placeholders}) "
        f"ON DUPLICATE KEY UPDATE {updates}"
    )


def format_preview(row: tuple[Any, ...]) -> str:
    values = {
        database_column: value.isoformat() if isinstance(value, (date, datetime_time)) else value
        for (_csv_header, database_column, _value_type), value in zip(CSV_TO_DATABASE, row)
    }
    return repr(values)


def main() -> int:
    args = parse_arguments()
    started_at = time.monotonic()
    rows_read = 0
    rows_processed = 0
    batches_sent = 0
    errors_found = 0
    connection = None
    cursor = None
    database_error_types: tuple[type[BaseException], ...] = ()

    try:
        if args.batch_size < 1:
            raise RuntimeError("--batch-size debe ser mayor que cero.")
        csv_path = args.csv_path.expanduser().resolve()
        if not csv_path.is_file():
            raise RuntimeError(f"No se encontró el CSV: {csv_path}")
        database_config, variable_prefix = load_database_config(DEFAULT_ENV_PATH)
        print(f"Configuración: variables {variable_prefix}* desde {DEFAULT_ENV_PATH}")
        print(f"CSV: {csv_path}")
        print(f"Modo: {'validación sin carga' if args.dry_run else 'carga MySQL'}")

        if not args.dry_run:
            try:
                import mysql.connector
            except ImportError as exc:
                raise RuntimeError(
                    "Falta mysql-connector-python; instalá las dependencias de requirements.txt."
                ) from exc
            database_error_types = (mysql.connector.Error,)
            connection = mysql.connector.connect(**database_config)
            cursor = connection.cursor()

        batch: list[tuple[Any, ...]] = []
        for line_number, converted_row in iter_converted_rows(csv_path):
            rows_read += 1
            if rows_read <= 2:
                print(f"Conversión línea {line_number}: {format_preview(converted_row)}")
            batch.append(converted_row)
            rows_processed += 1
            if len(batch) < args.batch_size:
                continue
            if args.dry_run:
                print(f"Validado lote: {rows_processed} filas procesadas")
            else:
                cursor.executemany(build_upsert_sql(), batch)
                connection.commit()
                batches_sent += 1
                print(f"Lote {batches_sent} confirmado: {rows_processed} filas procesadas")
            batch.clear()

        if batch:
            if args.dry_run:
                print(f"Validado lote final: {rows_processed} filas procesadas")
            else:
                cursor.executemany(build_upsert_sql(), batch)
                connection.commit()
                batches_sent += 1
                print(f"Lote {batches_sent} confirmado: {rows_processed} filas procesadas")

    except RowConversionError as exc:
        errors_found += 1
        rows_read = max(rows_read, exc.line_number - 1)
        print(f"Error de conversión: {exc}", file=sys.stderr)
        if connection is not None:
            connection.rollback()
        return_code = 2
    except database_error_types as exc:
        errors_found += 1
        if connection is not None:
            connection.rollback()
        print(
            f"Error de base de datos; el lote actual fue revertido: "
            f"{type(exc).__name__}: {exc}",
            file=sys.stderr,
        )
        return_code = 3
    except RuntimeError as exc:
        errors_found += 1
        print(f"Error: {exc}", file=sys.stderr)
        if connection is not None:
            connection.rollback()
        return_code = 2
    except Exception as exc:
        errors_found += 1
        if connection is not None:
            connection.rollback()
        print(
            f"Error inesperado: {type(exc).__name__}: {exc}",
            file=sys.stderr,
        )
        return_code = 4
    else:
        return_code = 0
    finally:
        if cursor is not None:
            cursor.close()
        if connection is not None and connection.is_connected():
            connection.close()
        elapsed = time.monotonic() - started_at
        print(
            "\nResumen final\n"
            f"- Filas leídas: {rows_read}\n"
            f"- Filas procesadas: {rows_processed}\n"
            f"- Lotes enviados: {batches_sent}\n"
            f"- Errores encontrados: {errors_found}\n"
            f"- Tiempo total: {elapsed:.2f} s"
        )
    return return_code


if __name__ == "__main__":
    raise SystemExit(main())
