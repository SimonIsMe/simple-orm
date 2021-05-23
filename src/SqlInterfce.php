<?php

namespace SimpleOrm;

interface SqlInterfce
{
    public function insert(string $sql, array $parameters = []): int;

    public function exec(string $sql, array $parameters = []): int;

    public function select(string $sql, array $parameters = []): array;
}