<?php

namespace SimpleOrm;

use SimpleOrm\exceptions\OrmException;
use SimpleOrm\exceptions\ValueIsNotUniqueException;

class MysqlOrm implements SqlInterfce
{
    private string $host;
    private string $username;
    private string $password;
    private string $dbName;

    private static ?\mysqli $connection = null;

    private static function getConnection(
        string $host,
        string $username,
        string $password,
        string $dbName
    ): \mysqli
    {
        if (self::$connection === null) {
            self::$connection = new \mysqli(
                $host,
                $username,
                $password,
                $dbName
            );
        }

        return self::$connection;
    }

    public function __construct(
        string $host,
        string $username,
        string $password,
        string $dbName
    )
    {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->dbName = $dbName;
    }

    private function connection(): \mysqli
    {
        return self::getConnection($this->host, $this->username, $this->password, $this->dbName);
    }

    /**
     * /**
     * @throws OrmException
     * @throws ValueIsNotUniqueException
     */
    public function insert(string $sql, array $parameters = []): int
    {
        $out = $this->callQuery($sql, $parameters);
        return $out['insertId'];
    }

    /**
     * @throws OrmException
     * @throws ValueIsNotUniqueException
     */
    public function exec(string $sql, array $parameters = []): int
    {
        $out = $this->callQuery($sql, $parameters);
        return $out['countAffectedRows'];
    }

    /**
     * @throws OrmException
     */
    public function select(string $sql, array $parameters = []): array
    {
        $stmt = $this->connection()->stmt_init();
        if ($stmt->prepare($sql) ===false) {
            throw new OrmException($stmt->error);
        }

        $this->bindParameters($stmt, $parameters);

        if ($stmt->execute() === false) {
            $stmt->close();
            throw new OrmException();
        }

        $result = $stmt->get_result();
        if ($result === false) {
            $stmt->close();
            throw new OrmException();
        }

        $rows = [];
        while ($row = $result->fetch_array(MYSQLI_BOTH)) {
            $rows[] = $row;
        }

        $stmt->close();
        return $rows;
    }

    /**
     * @throws OrmException
     * @throws ValueIsNotUniqueException
     */
    private function callQuery(string $sql, array $parameters = [])
    {
        $stmt = $this->connection()->stmt_init();
        if ($stmt->prepare($sql) ===false) {
            throw new OrmException($stmt->error);
        }

        $this->bindParameters($stmt, $parameters);

        try {
            $executeResult = $stmt->execute();
        } catch (\mysqli_sql_exception $e) {
            if ($stmt->errno === 1062) {
                throw new ValueIsNotUniqueException();
            }

            throw $e;
        }

        if ($executeResult === false) {
            $errorNo = $stmt->errno;
            $error = $stmt->error;
            $stmt->close();

            if ($errorNo === 1062) {
                throw new ValueIsNotUniqueException();
            }

            throw new OrmException($error);
        }

        $countAffectedRows = $this->connection()->affected_rows;
        $insertId = $stmt->insert_id;

        $stmt->close();

        return [
            'countAffectedRows' => $countAffectedRows,
            'insertId' => $insertId,
        ];
    }


    /**
     * @throws OrmException
     */
    private function bindParameters(&$stmt, array $parameters = [])
    {
        $params = [];
        foreach ($parameters as $parameter) {
            if (is_int($parameter)) {
                $params[] = [ 'type' => 'i', 'value' => $parameter];
            } else if (is_double($parameter)) {
                $params[] = [ 'type' => 'd', 'value' => $parameter];
            } else {
                $params[] = [ 'type' => 's', 'value' => $parameter];
            }
        }

        $keys = '';
        $values = [];
        foreach ($params as $param) {
            $keys .= $param['type'];
            $values[] = $param['value'];
        }

        if (!empty($parameters)) {
            $stmt->bind_param(
                $keys,
                ...$values
            );
        }
    }
}