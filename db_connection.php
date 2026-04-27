<?php
$host = "localhost";
$user = "root";      
$password = "root";   
$dbname = "saii";

$conn = mysqli_connect($host, $user, $password, $dbname,3306);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

?>