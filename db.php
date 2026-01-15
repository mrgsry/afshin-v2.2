<?php
$DB_HOST = 'localhost';
$DB_USER = 'hnetdiih_admin';
$DB_PASS = 'P@ssw0rd-123#$';
$DB_NAME = 'hnetdiih_afshin_app';

$mysqli = mysqli_connect($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME);
if(!$mysqli){
    die("Database connection error: " . mysqli_connect_error());
}
mysqli_set_charset($mysqli, 'utf8mb4');
?>