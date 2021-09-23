<?php
# Connect to the Database:
function connect(){
    try {
        
        if (ENV == 'development') {
            // local server
            $name = 'damwidi_v2';   // Database name
            $host = MySQLHOST;      // Host name
            $user = MySQLUSERNAME;  // Username
            $pass = MySQLPASSWORD;  // Password
        } elseif (ENV == 'production') {
            // hostgator
            $name = 'yurtkura_damwidi'; // Database name
            $host = HOSTGATORUSERHOST;  // Host name
            $user = HOSTGATORUSERNAME;  // Username
            $pass = HOSTGATORPASSWORD;  // Password
        }

        $dbc = new PDO("mysql:host=$host;dbname=$name", $user, $pass);
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
