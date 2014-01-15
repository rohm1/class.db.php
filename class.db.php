<?php

class db
{
    /**
     * @var array
     */
    public static $except_quotes = array('NULL', 'NOW()');

    /**
     * @var int
     */
    public static $nb = 0;

    /**
     * @var mysqli
     */
    public static $co = null;

    /**
     * contructor: connects to database if not connected
     *
     * @return void
     */
    public function __construct($host, $name_db, $pwd_db, $tb_db)
    {
        $co = new mysqli($host, $name_db, $pwd_db);
        if ($co->connect_error) {
            die($co->connect_error);
        }

        if (!$co->select_db($tb_db)) {
            die("Error while selecting table '" . $tb_db . "'");
        }

        self::$co = $co;
    }

    /**
     * mysql_query wrapper
     *
     * @param $sql - sql query
     * @return mysql_result
     */
    public static function query($sql)
    {
        $sql = trim($sql);
        $result = self::$co->query($sql);
        if (!$result) {
            throw new mysqli_sql_exception('<br />' . self::$co->error . '<br />query: ' . $sql);
        }

        if (strtolower(substr($sql, 0, 6)) == 'select') {
            self::$nb++;
        }

        return $result;
    }

    /**
     * fetches result data array of a given query
     *
     * @param $sql
     * @return array
     */
    public static function fetchArray($sql)
    {
        $result = self::query($sql);
        $arr = array();
        while ($row = $result->fetch_assoc()) {
            $arr[] = $row;
        }

        return $arr;
    }

    /**
     * escapes a string or data array
     *
     * @param $data
     * @return mixed
     */
    public static function escape($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $str) {
                $data[$key] = self::$co->real_escape_string($str);
            }

            return $data;
        } else {
            return self::$co->real_escape_string($data);
        }
    }

    /**
     * inserts a data array into a table
     * example: db::insert("user", array("name"=>"Sascha", "lastlogin"=>"NOW()", "type"=>'NULL'))
     * returns inserted primary key
     *
     * @param $table - mysql table
     * @param $data
     * @param $escape - boolean if data should be escaped
     * @return int
     */
    public static function insert($table, $data, $escape = true)
    {
        if ($escape) {
            $data = self::escape($data);
        }

        $keys = array();
        $values = array();
        foreach ($data as $key => $value) {
            $keys[] = '`' . $key . '`';
            if (!in_array($value, self::$except_quotes)) {
                $values[] = "'" . $value . "'";
            } else {
                $values[] = $value;
            }
        }

        self::query("INSERT INTO " . $table . " (" . implode(", ", $keys) . ") VALUES (" . implode(", ", $values) . ")");
        return self::$co->insert_id;
    }

    /**
     * updates one or more rows
     * example: db::update("user", array("name"=>"Sascha", "lastlogin"=>"NOW()"), "userid='1'")
     * returns number of affected rows
     *
     * @param $table
     * @param $data
     * @param $where
     * @param $limit
     * @param $escape
     * @return int
     */
    public static function update($table, $data, $where, $limit = 1, $escape = true)
    {
        if ($escape) {
            $data = self::escape($data);
        }

        $newdata = array();
        foreach ($data as $key => $value) {
            if (!in_array($value, self::$except_quotes)) {
                $newdata[] = '`' . $key . "`='" . $value . "'";
            } else {
                $newdata[] = '`' . $key . "`=" . $value;
            }
        }

        if (is_array($where)) {
            $whereSql = array();
            foreach ($where as $k => $v) {
                $whereSql[] = "`" . $k . "`='" . self::escape($v) . "'";
            }
            $whereSql = implode(' AND ', $whereSql);
        } else {
            $whereSql = $where;
        }

        if ($limit > 0) {
            $limitSql = "LIMIT " . $limit;
        } else {
            $limitSql = "";
        }

        self::query("UPDATE " . $table . " SET " . implode(", ", $newdata) . " WHERE (" . $whereSql . ') ' . $limitSql);
        return mysqli_affected_rows(self::$co);
    }

    /**
     * delete one or more rows
     * example: db::delete("user", "userid='1'",1)
     * or: db::delete("user", array("userid"=>1), 1)
     * returns number of affected rows
     *
     * @param $table
     * @param $where
     * @param $limit
     * @return int
     */
    public static function delete($table, $where, $limit = -1)
    {
        if (is_array($where) && $where) {
            $whereSql = array();
            foreach ($where as $k => $v) {
                $whereSql[] = '`' . $k . "`='" . self::escape($v) . "'";
            }
            $whereSql = implode(' AND ', $whereSql);
        } else {
            $whereSql = $where;
        }

        if ($limit > 0) {
            $limitSql = "LIMIT " . $limit;
        } else {
            $limitSql = "";
        }

        self::query("DELETE FROM " . $table . " WHERE (" . $whereSql . ') ' . $limitSql);
        return mysqli_affected_rows(self::$co);
    }

    /**
     * gets a page
     *
     * @param $sql
     * @param $nbr - max number of rows per page
     * @param $page - page number based on limit
     * @return array
     */
    public static function getPage($sql, $nbr = -1, $page = -1)
    {
        if ($page != -1 && $nbr != -1) {
            $limit = "LIMIT " . (($page-1)*$nbr) . "," . $nbr;
        } elseif ($nbr != -1) {
            $limit = "LIMIT " . $nbr;
        } else {
            $limit = "";
        }

        return self::fetchArray($sql . ' ' . $limit);
    }

}
