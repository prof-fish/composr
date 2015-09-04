<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2015

 See text/EN/licence.txt for full licencing information.


 NOTE TO PROGRAMMERS:
   Do not edit this file. If you need to make changes, save your changed file to the appropriate *_custom folder
   **** If you ignore this advice, then your website upgrades (e.g. for bug fixes) will likely kill your changes ****

*/

/*EXTRA FUNCTIONS: (mssql|sqlsrv)\_.+*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    core_database_drivers
 */

/*
Use the Enterprise Manager to get things set up.
You need to go into your server properties and turn the security to "SQL Server and Windows"
*/

/**
 * Standard code module initialisation function.
 * @ignore
 */
function init__database__sqlserver()
{
    safe_ini_set('mssql.textlimit', '300000');
    safe_ini_set('mssql.textsize', '300000');
}

/**
 * Database Driver.
 *
 * @package    core_database_drivers
 */
class Database_Static_sqlserver
{
    public $cache_db = array();

    /**
     * Get the default user for making db connections (used by the installer as a default).
     *
     * @return string The default user for db connections
     */
    public function db_default_user()
    {
        return 'sa';
    }

    /**
     * Get the default password for making db connections (used by the installer as a default).
     *
     * @return string The default password for db connections
     */
    public function db_default_password()
    {
        return '';
    }

    /**
     * Create a table index.
     *
     * @param  ID_TEXT $table_name The name of the table to create the index on
     * @param  ID_TEXT $index_name The index name (not really important at all)
     * @param  string $_fields Part of the SQL query: a comma-separated list of fields to use on the index
     * @param  array $db The DB connection to make on
     * @param  ID_TEXT $unique_key_field The name of the unique key field for the table
     */
    public function db_create_index($table_name, $index_name, $_fields, $db, $unique_key_field = 'id')
    {
        if ($index_name[0] == '#') {
            if (db_has_full_text($db)) {
                $index_name = substr($index_name, 1);
                $unique_index_name = 'index' . $index_name . '_' . strval(mt_rand(0, 10000));
                $this->db_query('CREATE UNIQUE INDEX ' . $unique_index_name . ' ON ' . $table_name . '(' . $unique_key_field . ')', $db);
                $this->db_query('CREATE FULLTEXT CATALOG ft AS DEFAULT', $db, null, null, true); // Might already exist
                $this->db_query('CREATE FULLTEXT INDEX ON ' . $table_name . '(' . $_fields . ') KEY INDEX ' . $unique_index_name, $db, null, null, true);
            }
            return;
        }
        $this->db_query('CREATE INDEX index' . $index_name . '_' . strval(mt_rand(0, 10000)) . ' ON ' . $table_name . '(' . $_fields . ')', $db);
    }

    /**
     * Change the primary key of a table.
     *
     * @param  ID_TEXT $table_name The name of the table to create the index on
     * @param  array $new_key A list of fields to put in the new key
     * @param  array $db The DB connection to make on
     */
    public function db_change_primary_key($table_name, $new_key, $db)
    {
        $this->db_query('ALTER TABLE ' . $table_name . ' DROP PRIMARY KEY', $db);
        $this->db_query('ALTER TABLE ' . $table_name . ' ADD PRIMARY KEY (' . implode(',', $new_key) . ')', $db);
    }

    /**
     * Assemble part of a WHERE clause for doing full-text search
     *
     * @param  string $content Our match string (assumes "?" has been stripped already)
     * @param  boolean $boolean Whether to do a boolean full text search
     * @return string Part of a WHERE clause for doing full-text search
     */
    public function db_full_text_assemble($content, $boolean)
    {
        $content = str_replace('"', '', $content);
        return 'CONTAINS ((?),\'' . $this->db_escape_string($content) . '\')';
    }

    /**
     * Get the ID of the first row in an auto-increment table (used whenever we need to reference the first).
     *
     * @return integer First ID used
     */
    public function db_get_first_id()
    {
        return 1;
    }

    /**
     * Get a map of Composr field types, to actual database types.
     *
     * @return array The map
     */
    public function db_get_type_remap()
    {
        $type_remap = array(
            'AUTO' => 'integer identity',
            'AUTO_LINK' => 'integer',
            'INTEGER' => 'integer',
            'UINTEGER' => 'bigint',
            'SHORT_INTEGER' => 'smallint',
            'REAL' => 'real',
            'BINARY' => 'smallint',
            'MEMBER' => 'integer',
            'GROUP' => 'integer',
            'TIME' => 'integer',
            'LONG_TRANS' => 'integer',
            'SHORT_TRANS' => 'integer',
            'LONG_TRANS__COMCODE' => 'integer',
            'SHORT_TRANS__COMCODE' => 'integer',
            'SHORT_TEXT' => 'varchar(255)',
            'LONG_TEXT' => 'text',
            'ID_TEXT' => 'varchar(80)',
            'MINIID_TEXT' => 'varchar(40)',
            'IP' => 'varchar(40)',
            'LANGUAGE_NAME' => 'varchar(5)',
            'URLPATH' => 'varchar(255)',
        );
        return $type_remap;
    }

    /**
     * Close the database connections. We don't really need to close them (will close at exit), just disassociate so we can refresh them.
     */
    public function db_close_connections()
    {
        $this->cache_db = array();
    }

    /**
     * Create a new table.
     *
     * @param  ID_TEXT $table_name The table name
     * @param  array $fields A map of field names to Composr field types (with *#? encodings)
     * @param  array $db The DB connection to make on
     */
    public function db_create_table($table_name, $fields, $db)
    {
        $type_remap = $this->db_get_type_remap();

        $_fields = '';
        $keys = '';
        foreach ($fields as $name => $type) {
            if ($type[0] == '*') { // Is a key
                $type = substr($type, 1);
                if ($keys != '') {
                    $keys .= ', ';
                }
                $keys .= $name;
            }

            if ($type[0] == '?') { // Is perhaps null
                $type = substr($type, 1);
                $perhaps_null = 'NULL';
            } else {
                $perhaps_null = 'NOT NULL';
            }

            $type = isset($type_remap[$type]) ? $type_remap[$type] : $type;

            $_fields .= '    ' . $name . ' ' . $type;
            if (substr($name, -13) == '__text_parsed') {
                $_fields .= ' DEFAULT \'\'';
            } elseif (substr($name, -13) == '__source_user') {
                $_fields .= ' DEFAULT ' . strval(db_get_first_id());
            }
            $_fields .= ' ' . $perhaps_null . ',' . "\n";
        }

        $query = 'CREATE TABLE ' . $table_name . ' (
          ' . $_fields . '
          PRIMARY KEY (' . $keys . ')
        )';
        $this->db_query($query, $db, null, null);
    }

    /**
     * Encode an SQL statement fragment for a conditional to see if two strings are equal.
     *
     * @param  ID_TEXT $attribute The attribute
     * @param  string $compare The comparison
     * @return string The SQL
     */
    public function db_string_equal_to($attribute, $compare)
    {
        return $attribute . " LIKE '" . $this->db_escape_string($compare) . "'";
    }

    /**
     * Encode an SQL statement fragment for a conditional to see if two strings are not equal.
     *
     * @param  ID_TEXT $attribute The attribute
     * @param  string $compare The comparison
     * @return string The SQL
     */
    public function db_string_not_equal_to($attribute, $compare)
    {
        return $attribute . "<>'" . $this->db_escape_string($compare) . "'";
    }

    /**
     * This function is internal to the database system, allowing SQL statements to be build up appropriately. Some databases require IS NULL to be used to check for blank strings.
     *
     * @return boolean Whether a blank string IS NULL
     */
    public function db_empty_is_null()
    {
        return false;
    }

    /**
     * Delete a table.
     *
     * @param  ID_TEXT $table The table name
     * @param  array $db The DB connection to delete on
     */
    public function db_drop_table_if_exists($table, $db)
    {
        $this->db_query('DROP TABLE ' . $table, $db, null, null, true);
    }

    /**
     * Determine whether the database is a flat file database, and thus not have a meaningful connect username and password.
     *
     * @return boolean Whether the database is a flat file database
     */
    public function db_is_flat_file_simple()
    {
        return false;
    }

    /**
     * Encode a LIKE string comparision fragement for the database system. The pattern is a mixture of characters and ? and % wilcard symbols.
     *
     * @param  string $pattern The pattern
     * @return string The encoded pattern
     */
    public function db_encode_like($pattern)
    {
        return $this->db_escape_string(str_replace('%', '*', $pattern));
    }

    /**
     * Get a database connection. This function shouldn't be used by you, as a connection to the database is established automatically.
     *
     * @param  boolean $persistent Whether to create a persistent connection
     * @param  string $db_name The database name
     * @param  string $db_host The database host (the server)
     * @param  string $db_user The database connection username
     * @param  string $db_password The database connection password
     * @param  boolean $fail_ok Whether to on error echo an error and return with a NULL, rather than giving a critical error
     * @return ?array A database connection (null: failed)
     */
    public function db_get_connection($persistent, $db_name, $db_host, $db_user, $db_password, $fail_ok = false)
    {
        // Potential caching
        if (isset($this->cache_db[$db_name][$db_host])) {
            return $this->cache_db[$db_name][$db_host];
        }

        if ((!function_exists('sqlsrv_connect')) && (!function_exists('mssql_pconnect'))) {
            $error = 'The sqlserver PHP extension not installed (anymore?). You need to contact the system administrator of this server.';
            if ($fail_ok) {
                echo $error;
                return null;
            }
            critical_error('PASSON', $error);
        }

        if (function_exists('sqlsrv_connect')) {
            if ($db_host == '127.0.0.1' || $db_host == 'localhost') {
                $db_host = '(local)';
            }
            $db = @sqlsrv_connect($db_host, ($db_user == '') ? array('Database' => $db_name) : array('UID' => $db_user, 'PWD' => $db_password, 'Database' => $db_name));
        } else {
            $db = $persistent ? @mssql_pconnect($db_host, $db_user, $db_password) : @mssql_connect($db_host, $db_user, $db_password);
        }
        if ($db === false) {
            $error = 'Could not connect to database-server (' . @strval($php_errormsg) . ')';
            if ($fail_ok) {
                echo $error;
                return null;
            }
            critical_error('PASSON', $error); //warn_exit(do_lang_tempcode('CONNECT_DB_ERROR'));
        }
        if (!function_exists('sqlsrv_connect')) {
            if (!mssql_select_db($db_name, $db)) {
                $error = 'Could not connect to database (' . mssql_get_last_message() . ')';
                if ($fail_ok) {
                    echo $error;
                    return null;
                }
                critical_error('PASSON', $error); //warn_exit(do_lang_tempcode('CONNECT_ERROR'));
            }
        }

        $this->cache_db[$db_name][$db_host] = $db;
        return $db;
    }

    /**
     * Find whether full-text-search is present
     *
     * @param  array $db A DB connection
     * @return boolean Whether it is
     */
    public function db_has_full_text($db)
    {
        global $SITE_INFO;
        if (array_key_exists('skip_fulltext_sqlserver', $SITE_INFO)) {
            return false;
        }
        return true;
    }

    /**
     * Find whether full-text-boolean-search is present
     *
     * @return boolean Whether it is
     */
    public function db_has_full_text_boolean()
    {
        return false;
    }

    /**
     * Escape a string so it may be inserted into a query. If SQL statements are being built up and passed using db_query then it is essential that this is used for security reasons. Otherwise, the abstraction layer deals with the situation.
     *
     * @param  string $string The string
     * @return string The escaped string
     */
    public function db_escape_string($string)
    {
        return str_replace("'", "''", $string);
    }

    /**
     * This function is a very basic query executor. It shouldn't usually be used by you, as there are abstracted versions available.
     *
     * @param  string $query The complete SQL query
     * @param  array $db A DB connection
     * @param  ?integer $max The maximum number of rows to affect (null: no limit)
     * @param  ?integer $start The start row to affect (null: no specification)
     * @param  boolean $fail_ok Whether to output an error on failure
     * @param  boolean $get_insert_id Whether to get the autoincrement ID created for an insert query
     * @return ?mixed The results (null: no results), or the insert ID
     */
    public function db_query($query, $db, $max = null, $start = null, $fail_ok = false, $get_insert_id = false)
    {
        if (!is_null($max)) {
            if (is_null($start)) {
                $max += $start;
            }

            if ((strtoupper(substr($query, 0, 7)) == 'SELECT ') || (strtoupper(substr($query, 0, 8)) == '(SELECT ')) { // Unfortunately we can't apply to DELETE FROM and update :(. But its not too important, LIMIT'ing them was unnecessarily anyway
                $query = 'SELECT TOP ' . strval(intval($max)) . substr($query, 6);
            }
        }

        $GLOBALS['SUPPRESS_ERROR_DEATH'] = true;
        if (function_exists('sqlsrv_query')) {
            $results = sqlsrv_query($db, $query, array(), array('Scrollable' => 'static'));
        } else {
            $results = mssql_query($query, $db);
        }
        $GLOBALS['SUPPRESS_ERROR_DEATH'] = false;
        if (($results === false) && (strtoupper(substr($query, 0, 12)) == 'INSERT INTO ') && (strpos($query, '(id, ') !== false)) {
            $pos = strpos($query, '(');
            $table_name = substr($query, 12, $pos - 13);
            if (function_exists('sqlsrv_query')) {
                @sqlsrv_query($db, 'SET IDENTITY_INSERT ' . $table_name . ' ON');
            } else {
                @mssql_query('SET IDENTITY_INSERT ' . $table_name . ' ON', $db);
            }
        }
        if (!is_null($start)) {
            if (function_exists('sqlsrv_fetch_array')) {
                sqlsrv_fetch($results, SQLSRV_SCROLL_ABSOLUTE, $start - 1);
            } else {
                @mssql_data_seek($results, $start);
            }
        }
        if ((($results === false) || ((strtoupper(substr($query, 0, 7)) == 'SELECT ') || (strtoupper(substr($query, 0, 8)) == '(SELECT ')) && ($results === true)) && (!$fail_ok)) {
            if (function_exists('sqlsrv_errors')) {
                $err = serialize(sqlsrv_errors());
            } else {
                $_error_msg = array_pop($GLOBALS['ATTACHED_MESSAGES_RAW']);
                if (is_null($_error_msg)) {
                    $error_msg = make_string_tempcode('?');
                } else {
                    $error_msg = $_error_msg[0];
                }
                $err = mssql_get_last_message() . '/' . $error_msg->evaluate();
                if (function_exists('ocp_mark_as_escaped')) {
                    ocp_mark_as_escaped($err);
                }
            }
            if ((!running_script('upgrader')) && (!get_mass_import_mode())) {
                if (!function_exists('do_lang') || is_null(do_lang('QUERY_FAILED', null, null, null, null, false))) {
                    fatal_exit(htmlentities('Query failed: ' . $query . ' : ' . $err));
                }

                fatal_exit(do_lang_tempcode('QUERY_FAILED', escape_html($query), ($err)));
            } else {
                echo htmlentities('Database query failed: ' . $query . ' [') . ($err) . htmlentities(']' . '<br />' . "\n");
                return null;
            }
        }

        if ((strtoupper(substr($query, 0, 7)) == 'SELECT ') || (strtoupper(substr($query, 0, 8)) == '(SELECT ') && ($results !== false) && ($results !== true)) {
            return $this->db_get_query_rows($results);
        }

        if ($get_insert_id) {
            if (strtoupper(substr($query, 0, 7)) == 'UPDATE ') {
                return null;
            }

            $pos = strpos($query, '(');
            $table_name = substr($query, 12, $pos - 13);
            if (function_exists('sqlsrv_query')) {
                $res2 = sqlsrv_query($db, 'SELECT MAX(IDENTITYCOL) AS v FROM ' . $table_name);
                $ar2 = sqlsrv_fetch_array($res2, SQLSRV_FETCH_ASSOC);
            } else {
                $res2 = mssql_query('SELECT MAX(IDENTITYCOL) AS v FROM ' . $table_name, $db);
                $ar2 = mssql_fetch_array($res2);
            }
            return $ar2['v'];
        }

        return null;
    }

    /**
     * Get the rows returned from a SELECT query.
     *
     * @param  resource $results The query result pointer
     * @param  ?integer $start Whether to start reading from (null: irrelevant for this forum driver)
     * @return array A list of row maps
     */
    public function db_get_query_rows($results, $start = null)
    {
        $out = array();

        if (!function_exists('sqlsrv_num_fields')) {
            $num_fields = mssql_num_fields($results);
            $types = array();
            $names = array();
            for ($x = 1; $x <= $num_fields; $x++) {
                $types[$x - 1] = mssql_field_type($results, $x - 1);
                $names[$x - 1] = strtolower(mssql_field_name($results, $x - 1));
            }

            $i = 0;
            while (($row = mssql_fetch_row($results)) !== false) {
                $j = 0;
                $newrow = array();
                foreach ($row as $v) {
                    $type = strtoupper($types[$j]);
                    $name = $names[$j];

                    if (($type == 'SMALLINT') || ($type == 'INT') || ($type == 'INTEGER') || ($type == 'UINTEGER') || ($type == 'BYTE') || ($type == 'COUNTER')) {
                        if (!is_null($v)) {
                            $newrow[$name] = intval($v);
                        } else {
                            $newrow[$name] = null;
                        }
                    } else {
                        if ($v == ' ') {
                            $v = '';
                        }
                        $newrow[$name] = $v;
                    }

                    $j++;
                }

                $out[] = $newrow;

                $i++;
            }
        } else {
            if (function_exists('sqlsrv_fetch_array')) {
                while (($row = sqlsrv_fetch_array($results, SQLSRV_FETCH_ASSOC)) !== null) {
                    $out[] = $row;
                }
            } else {
                while (($row = mssql_fetch_row($results)) !== false) {
                    $out[] = $row;
                }
            }
        }

        if (function_exists('sqlsrv_free_stmt')) {
            sqlsrv_free_stmt($results);
        } else {
            mssql_free_result($results);
        }
        return $out;
    }
}
