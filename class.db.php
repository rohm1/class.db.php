<?php

class db {
	/**
	 * @var array
	 */
	public static $except_quotes = array('NULL', 'NOW()');

	/**
	 * @var int
	 */
	 public static $nb = 0;

	/**
	 * contructor: connects to database if not connected
	 *
	 * @return void
	 */
	public function __construct($host, $name_db, $pwd_db, $tb_db) {
		$db = mysql_connect($host, $name_db, $pwd_db) or die ("erreur de connexion");
		mysql_select_db($tb_db, $db) or die ("erreur de connexion base");
	}

	/**
	 * mysql_query wrapper
	 *
	 * @param $sql - sql query
	 * @return mysql_result
	 */
	public static function query($sql) {
		$result = mysql_query($sql) or die('sql error: ' . mysql_error() . '<br /> query: ' . $sql);

		if(strtolower(substr(trim($sql), 0, 6)) != 'select') {
			if(function_exists('log_sql'))
				log_sql($sql);
		}
		else
			db::$nb++;

		return $result;
	}

	/**
	 * fetches result data array of a given query
	 *
	 * @param $sql
	 * @return array
	 */
	public static function fetchArray($sql) {
		$result = db::query($sql);
		$arr = array();
		while($row = mysql_fetch_assoc($result))
			$arr[] = $row;

		return $arr;
	}

	/**
	 * escapes a string or data array
	 *
	 * @param $data
	 * @return mixed
	 */
	public static function escape($data) {
		if(is_array($data)) {
			foreach ($data AS $key=>$str)
				$data[$key] = mysql_real_escape_string($str);
			return $data;
		}
		else
			return mysql_real_escape_string($data);
	}

	/**
	 * inserts a data array into a table
	 * example: $db->insert("user", array("name"=>"Sascha", "lastlogin"=>"NOW()", "type"=>'NULL'))
	 * returns inserted primary key
	 *
	 * @param $table - mysql table
	 * @param $data
	 * @param $escape - boolean if data should be escaped
	 * @return int
	 */
	public static function insert($table, $data=array(), $escape=true) {
		if($escape)
			$data = db::escape($data);
		foreach($data AS $key=>$value) {
			if(!in_array($value, db::$except_quotes) || is_numeric($value))
				$data[$key] = "'".$value."'";
		}
		db::query("INSERT INTO ".$table."(".implode(",",array_keys($data)).") VALUES (".implode(",",array_values($data)).")");
		return mysql_insert_id();
	}

	/**
	 * updates one or more rows
	 * example: $db->update("user", array("name"=>"Sascha", "lastlogin"=>"NOW()"), "userid='1'")
	 * returns number of affected rows
	 *
	 * @param $table
	 * @param $data
	 * @param $where
	 * @param $limit
	 * @param $escape
	 * @return int
	 */
	public static function update($table, $data=array(), $where, $limit=1,$escape=true) {
		if($escape)
			$data = db::escape($data);

		$newdata = array();
		foreach($data AS $key=>$value) {
			if (!in_array($value, db::$except_quotes))
				$newdata[$key] = $key."='".$value."'";
			else
				$newdata[$key] = $key."=".$value;
		}

		if(is_array($where) && $where) {
			$whereSql = "1";
			foreach($where as $k=>$v)
				$whereSql .= " AND ".$k."='".db::escape($v)."' ";
		}
		else
			$whereSql = $where;

		if($limit>0)
			$limit_sql = " LIMIT ".$limit;
		else
			$limit_sql = "";

		db::query("UPDATE ".$table." SET ".implode(",",$newdata)." WHERE ".$whereSql.$limit_sql);
		return mysql_affected_rows();
	}

	/**
	 * delete one or more rows
	 * example: $db->delete("user", "userid='1'",1)
	 * or: $db->delete("user", array("userid"=>1), 1)
	 * returns number of affected rows
	 *
	 * @param $table
	 * @param $where
	 * @param $limit
	 * @return int
	 */
	public static function delete($table, $where, $limit=-1) {
		if($limit>0)
			$limit_sql = " LIMIT ".$limit;
		else
			$limit_sql = "";

		$whereSql = "1";
		if(is_array($where) && $where) {
			foreach($where AS $k=>$v)
				$whereSql .= ' AND '.$k."='".db::escape($v)."' ";
		}
		else
			$whereSql = $where;

		db::query("DELETE FROM ".$table." WHERE ".$whereSql.$limit_sql);
		return mysql_affected_rows();
	}

	/**
	 * gets a page
	 *
	 * @param $sql
	 * @param $nbr - max number of rows per page
	 * @param $page - page number based on limit
	 * @return array
	 */
	public static function getPage($sql, $nbr=-1, $page=-1) {
		if($page != -1 && $nbr != -1)
			$limit = " LIMIT ".(($page-1)*$nbr).",".$nbr;
		elseif($nbr != -1)
			$limit = " LIMIT ".$nbr;
		else
			$limit = "";

		return db::fetchArray($sql.$limit);
	}

}
?>
