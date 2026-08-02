#!/usr/bin/env python3
"""Envía una notificación Web Push de prueba a las suscripciones activas."""

from __future__ import annotations

import argparse
import json
import os
import sys
from pathlib import Path
from typing import Any

import mysql.connector
from dotenv import load_dotenv
from pywebpush import WebPushException, webpush


PROJECT_ROOT = Path(__file__).resolve().parent.parent


def required_environment(name: str) -> str:
    value = os.getenv(name, "").strip()
    if not value:
        raise RuntimeError(f"Falta la variable {name}.")
    return value


def database_config() -> dict[str, Any]:
    try:
        port = int(required_environment("WEB_DB_PORT"))
    except ValueError as exception:
        raise RuntimeError("WEB_DB_PORT debe ser un número entero.") from exception
    if not 1 <= port <= 65535:
        raise RuntimeError("WEB_DB_PORT debe estar entre 1 y 65535.")
    return {
        "host": required_environment("WEB_DB_HOST"),
        "port": port,
        "database": required_environment("WEB_DB_NAME"),
        "user": required_environment("WEB_DB_USER"),
        "password": required_environment("WEB_DB_PASSWORD"),
        "charset": "utf8mb4",
        "use_unicode": True,
        "connection_timeout": 20,
    }


def arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--id", type=int, help="Envía únicamente a la suscripción activa indicada.")
    return parser.parse_args()


def safe_push_error(exception: WebPushException) -> tuple[str, int | None]:
    response = getattr(exception, "response", None)
    status = getattr(response, "status_code", None)
    reason = getattr(response, "reason", "")
    if isinstance(status, int):
        return f"HTTP {status}" + (f" {reason}" if isinstance(reason, str) and reason else ""), status
    return "Error de envío Web Push", None


def main() -> int:
    load_dotenv(PROJECT_ROOT / ".env", override=False)
    args = arguments()
    private_key = required_environment("WEB_PUSH_VAPID_PRIVATE_KEY")
    subject = required_environment("WEB_PUSH_VAPID_SUBJECT")
    payload = json.dumps(
        {"title": "Aquellas Lunas", "body": "Esta es una notificación de prueba.", "url": "./"},
        ensure_ascii=False,
        separators=(",", ":"),
    )

    connection = mysql.connector.connect(**database_config())
    try:
        cursor = connection.cursor(dictionary=True)
        query = "SELECT id, endpoint, p256dh, auth FROM web_push_subscriptions WHERE active = 1"
        parameters: tuple[Any, ...] = ()
        if args.id is not None:
            query += " AND id = %s"
            parameters = (args.id,)
        query += " ORDER BY id"
        cursor.execute(query, parameters)
        subscriptions = cursor.fetchall()
        if not subscriptions:
            print("No hay suscripciones activas para enviar.")
            return 0

        successes = 0
        failures = 0
        for subscription in subscriptions:
            subscription_id = int(subscription["id"])
            try:
                webpush(
                    subscription_info={
                        "endpoint": subscription["endpoint"],
                        "keys": {"p256dh": subscription["p256dh"], "auth": subscription["auth"]},
                    },
                    data=payload,
                    vapid_private_key=private_key,
                    vapid_claims={"sub": subject},
                    ttl=300,
                    timeout=20,
                )
                cursor.execute(
                    "UPDATE web_push_subscriptions SET last_success_at = CURRENT_TIMESTAMP, "
                    "last_error_at = NULL, last_error_message = NULL WHERE id = %s",
                    (subscription_id,),
                )
                successes += 1
                print(f"Suscripción {subscription_id}: enviada.")
            except WebPushException as exception:
                message, status = safe_push_error(exception)
                cursor.execute(
                    "UPDATE web_push_subscriptions SET active = %s, last_error_at = CURRENT_TIMESTAMP, "
                    "last_error_message = %s WHERE id = %s",
                    (0 if status in (404, 410) else 1, message[:1000], subscription_id),
                )
                failures += 1
                print(f"Suscripción {subscription_id}: {message}.", file=sys.stderr)
            except Exception as exception:  # La salida evita endpoint, claves y cuerpo remoto.
                message = f"Error local: {type(exception).__name__}"
                cursor.execute(
                    "UPDATE web_push_subscriptions SET last_error_at = CURRENT_TIMESTAMP, "
                    "last_error_message = %s WHERE id = %s",
                    (message, subscription_id),
                )
                failures += 1
                print(f"Suscripción {subscription_id}: {message}.", file=sys.stderr)
            connection.commit()

        print(f"Resultado: {successes} enviadas, {failures} con error.")
        return 1 if failures else 0
    finally:
        connection.close()


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except RuntimeError as exception:
        print(f"Error: {exception}", file=sys.stderr)
        raise SystemExit(2)
    except mysql.connector.Error as exception:
        code = exception.errno if isinstance(exception.errno, int) else "desconocido"
        print(f"Error: no se pudo consultar WEB_DB (código {code}).", file=sys.stderr)
        raise SystemExit(2)
