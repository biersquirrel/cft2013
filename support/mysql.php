<?php
	// CubicleSoft MySQL database interface.
	// (C) 2012 CubicleSoft.  All Rights Reserved.

	class MySQL
	{
		private $numqueries, $totaltime, $dbobj, $origdbobj, $currdbname;
		private $mserver, $musername, $mpassword;

		static function ConvertToDBDate($ts, $gmt = true)
		{
			return ($gmt ? gmdate("Y-m-d", $ts) : date("Y-m-d", $ts)) . " 00:00:00";
		}

		static function ConvertToDBTime($ts, $gmt = true)
		{
			return ($gmt ? gmdate("Y-m-d H:i:s", $ts) : date("Y-m-d H:i:s", $ts));
		}

		static function ConvertFromDBTime($field, $gmt = true)
		{
			$year = (int)substr($field, 0, 4);
			$month = (int)substr($field, 5, 2);
			$day = (int)substr($field, 8, 2);
			$hour = (int)substr($field, 11, 2);
			$min = (int)substr($field, 14, 2);
			$sec = (int)substr($field, 17, 2);

			return ($gmt ? gmmktime($hour, $min, $sec, $month, $day, $year) : mktime($hour, $min, $sec, $month, $day, $year));
		}

		function __construct($server = "", $username = "", $password = "", $dbname = "")
		{
			$this->numqueries = 0;
			$this->totaltime = 0;
			$this->dbobj = false;
			$this->origdbobj = false;
			$this->currdbname = "";

			if ($server != "")  $this->Connect($server, $username, $password, $dbname);
		}

		function __destruct()
		{
			$this->Disconnect();
		}

		function Connect($server, $username, $password, $dbname = "")
		{
			$this->origdbobj = $this->dbobj;
			$this->dbobj = false;
			$this->mserver = false;

			$startts = microtime(true);

			if (!function_exists("mysql_connect"))
			{
				throw new Exception(MySQL::MySQL_Translate("The function mysql_connect() does not exist.  The MySQL library appears to be disabled."));
				exit();
			}
			$this->dbobj = @mysql_connect($server, $username, $password, true);
			if ($this->dbobj === false)
			{
				throw new Exception(MySQL::MySQL_Translate("Unable to connect to database.  %s (%d)", mysql_error(), mysql_errno()));
				exit();
			}

			// Set Unicode support.
			$sql = "SET NAMES 'utf8'";
			@mysql_free_result(@mysql_query($sql, $this->dbobj));
			$this->numqueries++;

			$this->totaltime += (microtime(true) - $startts);

			// Select the database if provided.
			if ($dbname != "")  return $this->UseDB($dbname);

			return true;
		}

		function SetMaster($server, $username, $password)
		{
			$this->mserver = $server;
			$this->musername = $username;
			$this->mpassword = $password;
		}

		function Disconnect()
		{
			$startts = microtime(true);

			if ($this->dbobj !== false)
			{
				@mysql_close($this->dbobj);
				$this->dbobj = false;
			}
			if ($this->origdbobj !== false)
			{
				@mysql_close($this->origdbobj);
				$this->origdbobj = false;
			}

			$this->totaltime += (microtime(true) - $startts);

			return true;
		}

		function UseDB($dbname)
		{
			if ($this->dbobj === false)  return false;

			$startts = microtime(true);

			if (!@mysql_select_db($dbname, $this->dbobj))
			{
				throw new Exception(MySQL::MySQL_Translate("Unable to select database.  %s (%d)", mysql_error($this->dbobj), mysql_errno($this->dbobj)));
				exit();
			}
			$this->currdbname = $dbname;

			$this->totaltime += (microtime(true) - $startts);

			return true;
		}

		// Query(SQL, Param, Param2, ...)
		// SQL may contain special sequences to be replaced with safe parameters:
		//   %b = Escape a column or database name.
		//   %s = Escape a string and surround with single quotes.
		//   %d = Integer.
		//   %f = Double.
		//   %L = Escape a string for a 'LIKE' query.
		function Query()
		{
			$params = func_get_args();
			return $this->InternalQuery(false, $params);
		}

		function UnbufferedQuery()
		{
			$params = func_get_args();
			return $this->InternalQuery(true, $params);
		}

		function GetRow()
		{
			$params = func_get_args();
			$dbresult = $this->InternalQuery(false, $params);
			if ($dbresult === false)  return false;
			$row = $dbresult->NextRow();
			unset($dbresult);

			return $row;
		}

		function GetRowArray()
		{
			$params = func_get_args();
			$dbresult = $this->InternalQuery(false, $params);
			if ($dbresult === false)  return false;
			$row = $dbresult->NextRow(false);
			unset($dbresult);

			return $row;
		}

		function GetCol()
		{
			$result = array();

			$params = func_get_args();
			$dbresult = $this->InternalQuery(false, $params);
			if ($dbresult === false)  return false;
			while ($row = $dbresult->NextRow(false, MYSQL_NUM))
			{
				$result[] = $row[0];
			}

			return $result;
		}

		function GetOne()
		{
			$params = func_get_args();
			$dbresult = $this->InternalQuery(false, $params);
			if ($dbresult === false)  return false;
			$row = $dbresult->NextRow(false, MYSQL_NUM);
			unset($dbresult);
			if ($row === false)  return false;

			return $row[0];
		}

		function GetVersion()
		{
			return $this->GetOne("SELECT VERSION()");
		}

		function GetInsertID()
		{
			return $this->GetOne("SELECT LAST_INSERT_ID()");
		}

		function TableExists($name)
		{
			return ($this->GetOne("SHOW TABLES LIKE '%L'", $name) === false ? false : true);
		}

		function NumQueries()
		{
			return $this->numqueries;
		}

		// Execution time in microseconds.
		function ExecutionTime()
		{
			return $this->totaltime;
		}

		private function InternalQuery($unbuffered, $args)
		{
			if ($this->dbobj === false)  return false;

			$startts = microtime(true);

			$y2 = count($args);
			if (!$y2)  return false;
			if ($y2 == 2 && is_array($args[1]))
			{
				$args = array_merge(array($args[0]), $args[1]);
				$y2 = count($args);
			}
			$x2 = 1;
			if (trim($args[0]) == "")  return false;
			$data = explode("%", trim($args[0]));
			$sql = $data[0];
			$y = count($data);
			if ($y != 2 || !is_array($data[1]))  $x = 1;
			else
			{
				$data = $data[1];
				$x = 0;
			}
			for (; $x < $y; $x++)
			{
				$chr = substr($data[$x], 0, 1);
				if ($x2 < $y2 && $chr != "")
				{
					if (!isset($args[$x2]))  $sql .= "NULL";
					else
					{
						if ($chr == "b")
						{
							$sqlvar = mysql_real_escape_string($args[$x2]);
							$sql .= $sqlvar;
							$x2++;
						}
						else if ($chr == "s")
						{
							$sqlvar = mysql_real_escape_string(MakeValidUTF8($args[$x2]));
							$sql .= "'" . $sqlvar . "'";
							$x2++;
						}
						else if ($chr == "d")
						{
							$sql .= (int)$args[$x2];
							$x2++;
						}
						else if ($chr == "f")
						{
							$sql .= (double)$args[$x2];
							$x2++;
						}
						else if ($chr == "L")
						{
							$sqlvar = mysql_real_escape_string(MakeValidUTF8($args[$x2]));
							$sql .= str_replace(array("_", "%"), array("\\_", "\\%"), $sqlvar);
							$x2++;
						}
						else
						{
							$sql .= "%" . $chr;
						}
					}

					$sql .= substr($data[$x], 1);
				}
				else if ($chr == "")
				{
					$sql .= "%%";
					$x++;
				}
				else  $sql .= "%" . $data[$x];
			}

			if ($this->mserver !== false && strtoupper(substr($sql, 0, 7)) != "SELECT ")
			{
				$this->Connect($this->mserver, $this->musername, $this->mpassword, $this->currdbname);
			}

			if ($unbuffered)  $result = @mysql_unbuffered_query($sql, $this->dbobj);
			else  $result = @mysql_query($sql, $this->dbobj);
			if ($result === false)
			{
				throw new Exception(MySQL::MySQL_Translate("Error running SQL query.  %s (%d)<br />\n<pre>%s</pre>\n", mysql_error($this->dbobj), mysql_errno($this->dbobj), htmlspecialchars($sql)));
				exit();
			}
			if (!is_resource($result))  return false;
			$this->numqueries++;

			$this->totaltime += (microtime(true) - $startts);

			return new MySQL_DB_Statement($result);
		}

		private static function MySQL_Translate()
		{
			$args = func_get_args();
			if (!count($args))  return "";

			return call_user_func_array((defined("CS_TRANSLATE_FUNC") && function_exists(CS_TRANSLATE_FUNC) ? CS_TRANSLATE_FUNC : "sprintf"), $args);
		}
	}

	class MySQL_DB_Row
	{
		function __construct($data)
		{
			foreach ($data as $key => $val)
			{
				if (is_string($key))
				{
					$key = str_replace(" ", "_", $key);
					$this->$key = $val;
				}
			}
		}
	}

	class MySQL_DB_Statement
	{
		private $dbobj;

		function __construct($obj)
		{
			$this->dbobj = $obj;
		}

		function __destruct()
		{
			$this->Free();
		}

		function Free()
		{
			if ($this->dbobj === false)  return false;

		    $x = @mysql_free_result($this->dbobj);
			$this->dbobj = false;

			return $x;
		}

		function NextRow($retobj = true, $fetchtype = MYSQL_ASSOC)
		{
			if ($this->dbobj === false)  return false;

			$result = @mysql_fetch_array($this->dbobj, $fetchtype);
			if ($result === false)
			{
				$this->Free();

				return false;
			}

			return ($retobj ? new MySQL_DB_Row($result) : $result);
		}
	}
?>