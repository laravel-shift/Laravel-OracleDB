<?php

namespace Jfelder\OracleDB;

use Illuminate\Database\Connection;
use Jfelder\OracleDB\Schema\OracleBuilder as OracleSchemaBuilder;
use Jfelder\OracleDB\Query\Processors\OracleProcessor;
use Doctrine\DBAL\Driver\OCI8\Driver as DoctrineDriver;
use Jfelder\OracleDB\Query\Grammars\OracleGrammar as QueryGrammer;
use Jfelder\OracleDB\Query\OracleBuilder as OracleQueryBuilder;
use Jfelder\OracleDB\Schema\Grammars\OracleGrammar as SchemaGrammer;
use PDO;

class OracleConnection extends Connection
{
    /**
     * Get a schema builder instance for the connection.
     *
     * @return \Jfelder\OracleDB\Schema\OracleBuilder
     */
    public function getSchemaBuilder()
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new OracleSchemaBuilder($this);
    }

    /**
     * Get a new query builder instance.
     *
     * @return \Jfelder\OracleDB\Query\OracleBuilder
     */
    public function query()
    {
        return new OracleQueryBuilder(
            $this, $this->getQueryGrammar(), $this->getPostProcessor()
        );
    }

    /**
     * Get the default query grammar instance.
     *
     * @return \Jfelder\OracleDB\Query\Grammars\OracleGrammar
     */
    protected function getDefaultQueryGrammar()
    {
        return $this->withTablePrefix(new QueryGrammer);
    }

    /**
     * Get the default schema grammar instance.
     *
     * @return \Jfelder\OracleDB\Schema\Grammars\OracleGrammar
     */
    protected function getDefaultSchemaGrammar()
    {
        return $this->withTablePrefix(new SchemaGrammer);
    }

    /**
     * Get the default post processor instance.
     *
     * @return \Jfelder\OracleDB\Query\Processors\OracleProcessor
     */
    protected function getDefaultPostProcessor()
    {
        return new OracleProcessor;
    }

    /**
     * Get the Doctrine DBAL driver.
     *
     * @return \Doctrine\DBAL\Driver\OCI8\Driver
     */
    protected function getDoctrineDriver()
    {
        return new DoctrineDriver;
    }

    /**
     * Bind values to their parameters in the given statement.
     *
     * @param  \PDOStatement $statement
     * @param  array  $bindings
     * @return void
     */
    public function bindValues($statement, $bindings)
    {
        foreach ($bindings as $key => $value) {
            $statement->bindValue(
                $key,
                $value,
                match (true) {
                    is_int($value) => PDO::PARAM_INT,
                    is_bool($value) => PDO::PARAM_BOOL,
                    is_null($value) => PDO::PARAM_NULL,
                    is_resource($value) => PDO::PARAM_LOB,
                    default => PDO::PARAM_STR
                },
            );
        }
    }

    /**
     * Run an "insert get ID" statement against an oracle database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return int
     */
    public function oracleInsertGetId($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            $last_insert_id = 0;

            $statement = $this->getPdo()->prepare($query);

            $this->bindValues($statement, $this->prepareBindings($bindings));

            // bind final param to a var to capture the id obtained by the query's "returning id into" clause
            $statement->bindParam(count($bindings), $last_insert_id, PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, 8);

            $this->recordsHaveBeenModified();

            $statement->execute();

            return (int) $last_insert_id;
        });
    }
}
