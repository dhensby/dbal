<?php

namespace Doctrine\DBAL\Driver\IBMDB2;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\ParameterType;
use const DB2_AUTOCOMMIT_OFF;
use const DB2_AUTOCOMMIT_ON;
use function db2_autocommit;
use function db2_commit;
use function db2_conn_error;
use function db2_conn_errormsg;
use function db2_connect;
use function db2_escape_string;
use function db2_exec;
use function db2_last_insert_id;
use function db2_num_rows;
use function db2_pconnect;
use function db2_prepare;
use function db2_rollback;
use function db2_server_info;
use function db2_stmt_errormsg;
use function func_get_args;

class DB2Connection implements Connection, ServerInfoAwareConnection
{
    /**
     * @var resource
     */
    private $_conn = null;

    /**
     * @param array  $params
     * @param string $username
     * @param string $password
     * @param array  $driverOptions
     *
     * @throws \Doctrine\DBAL\Driver\IBMDB2\DB2Exception
     */
    public function __construct(array $params, $username, $password, $driverOptions = [])
    {
        $isPersistent = (isset($params['persistent']) && $params['persistent'] == true);

        if ($isPersistent) {
            $this->_conn = db2_pconnect($params['dbname'], $username, $password, $driverOptions);
        } else {
            $this->_conn = db2_connect($params['dbname'], $username, $password, $driverOptions);
        }
        if ( ! $this->_conn) {
            throw new DB2Exception(db2_conn_errormsg());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getServerVersion()
    {
        $serverInfo = db2_server_info($this->_conn);

        return $serverInfo->DBMS_VER;
    }

    /**
     * {@inheritdoc}
     */
    public function requiresQueryForServerVersion()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function prepare($sql)
    {
        $stmt = @db2_prepare($this->_conn, $sql);
        if ( ! $stmt) {
            throw new DB2Exception(db2_stmt_errormsg());
        }

        return new DB2Statement($stmt);
    }

    /**
     * {@inheritdoc}
     */
    public function query(string $sql)
    {
        $stmt = $this->prepare($sql);
        $stmt->execute();

        return $stmt;
    }

    /**
     * {@inheritdoc}
     */
    public function quote($input, $type = ParameterType::STRING)
    {
        $input = db2_escape_string($input);

        if ($type === ParameterType::INTEGER) {
            return $input;
        }

        return "'".$input."'";
    }

    /**
     * {@inheritdoc}
     */
    public function exec($statement)
    {
        $stmt = @db2_exec($this->_conn, $statement);

        if (false === $stmt) {
            throw new DB2Exception(db2_stmt_errormsg());
        }

        return db2_num_rows($stmt);
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId($name = null)
    {
        return db2_last_insert_id($this->_conn);
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction()
    {
        db2_autocommit($this->_conn, DB2_AUTOCOMMIT_OFF);
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        if (!db2_commit($this->_conn)) {
            throw new DB2Exception(db2_conn_errormsg($this->_conn));
        }
        db2_autocommit($this->_conn, DB2_AUTOCOMMIT_ON);
    }

    /**
     * {@inheritdoc}
     */
    public function rollBack()
    {
        if (!db2_rollback($this->_conn)) {
            throw new DB2Exception(db2_conn_errormsg($this->_conn));
        }
        db2_autocommit($this->_conn, DB2_AUTOCOMMIT_ON);
    }

    /**
     * {@inheritdoc}
     */
    public function errorCode()
    {
        return db2_conn_error($this->_conn);
    }

    /**
     * {@inheritdoc}
     */
    public function errorInfo()
    {
        return [
            0 => db2_conn_errormsg($this->_conn),
            1 => $this->errorCode(),
        ];
    }
}
