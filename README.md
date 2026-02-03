# Дока

Создать базу для тестов через контейнер testprj-db.

`psql -U postgres -c "CREATE DATABASE testprj_testing;"`
На нее настоен `phpunit.xml`.
