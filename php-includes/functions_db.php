<?php
# Connect to the Database:
function connect(){
    try {

        $name = constant(ENV."_DBNAME");      // Database name
        $host = constant(ENV."_DBHOST");      // Host name
        $user = constant(ENV."_USERNAME");    // Username
        $pass = constant(ENV."_PASSWORD");    // Password

        $dbc = new PDO("mysql:host=$host; dbname=$name", $user, $pass);

        $connect = $dbc;
    }
    catch(PDOException $e) {
        $connect = 0;
        echo $e->getMessage();
    }
    return $connect;
}

function truncateTable($table){
    $dbc = connect();
    $stmt = $dbc->prepare("TRUNCATE TABLE ".$table);
    $stmt->execute();
}

?>
