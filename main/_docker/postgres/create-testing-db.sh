#!/bin/bash
# Отдельная БД для тестов (RefreshDatabase не трогает дев-данные).
# Выполняется автоматически при первичной инициализации кластера Postgres.
set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    CREATE DATABASE "${POSTGRES_DB}_testing" OWNER "$POSTGRES_USER";
EOSQL
