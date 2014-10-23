<?php

$opt = getopt('h:U:P:p:d:');
$USAGE = $_SERVER['SCRIPT_FILENAME'] . " [-h db_host] [-U db_user] [-P port] [-p password] -d database\n";

$host = isset($opt['h']) ? $opt['h'] : 'localhost';
$user = isset($opt['U']) ? $opt['U'] : 'postgres';
$port = isset($opt['P']) ? $opt['P'] : 5432;
$password = isset($opt['p']) ? $opt['p'] : '';
if (!isset($opt['d']) || strlen($opt['d']) == 0) {
    var_dump($opt);
    die($USAGE);
}
$database = $opt['d'];

$REPLICATION_SCHEMA = '_replication';


$db = new PDO("pgsql:dbname=$database host=$host port=$port", $user, $password);

$table_list =<<<SQL
select relname from pg_stat_user_tables where schemaname <> '$REPLICATION_SCHEMA'
SQL;

$result = $db->query($table_list);
$table_list = array();
foreach ($result as $row) {
    $table_list[] = $row[0];
}


$pkey_sql =<<<SQL
select
ccu.constraint_name,
ccu.column_name
from
information_schema.table_constraints tc,
information_schema.constraint_column_usage ccu
where tc.table_catalog = ? and tc.table_name = ? and
	tc.table_catalog=ccu.table_catalog
	and
	tc.table_schema=ccu.table_schema
	and
	tc.table_name=ccu.table_name
	and
	tc.constraint_name=ccu.constraint_name
	and
	tc.table_schema <> '$REPLICATION_SCHEMA'
SQL;

$stmt = $db->prepare($pkey_sql);
$pkey_tables = array();
$serial_tables = array();
foreach ($table_list as $table) {
    $ret = $stmt->execute(array($database, $table));
    $data = $stmt->fetchObject();
    if ($data) {
        $pkey_tables[] = $table;
    } else {
        $serial_tables[] = $table;
    }
}

$seq_sql =<<<SQL
select * from information_schema.sequences where sequence_schema <> '$REPLICATION_SCHEMA';
SQL;

$sequences = array();
foreach ($db->query($seq_sql) as $row) {
    $sequences[] = $row['sequence_name'];
}

$pkey_table_string = "    '" . join("',\n    '", $pkey_tables) . "'";
$serial_table_string = "    '" . join("',\n    '", $serial_tables) . "'";
$sequence_string = "    '" . join("',\n    '", $sequences). "'";

echo <<<TXT
"pkeyedtables" => [
$pkey_table_string
],
"serialables" => [
$serial_table_string
],
"sequences" => [
$sequence_string
],

TXT;

