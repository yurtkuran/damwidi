<?php
# Connect to the Database:
function connect() {
    try {
        $name = constant(ENV . "_DBNAME");
        $host = constant(ENV . "_DBHOST");
        $user = constant(ENV . "_USERNAME");
        $pass = constant(ENV . "_PASSWORD");

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ];

        // Require TLS for DigitalOcean MySQL
        if (ENV === 'DO') {
            $options[PDO::MYSQL_ATTR_SSL_CA] = '/etc/mysql/certs/ca.pem';
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

        return new PDO(
            "mysql:host=$host;dbname=$name",
            $user,
            $pass,
            $options
        );

    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return 0;
    }
}

function truncateTable($table){
    $dbc = connect();
    $stmt = $dbc->prepare("TRUNCATE TABLE ".$table);
    $stmt->execute();
}

?>
