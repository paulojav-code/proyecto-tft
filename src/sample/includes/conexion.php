<?php
    const DATABASE_HOST = '52.9.201.245:3306';
    const DATABASE_NAME = 'db_templates_test';
    const DATABASE_USER = 'webmasterbelair';
    const DATABASE_PASS = 'Wbmstr11_';

    $con = new mysqli(DATABASE_HOST,DATABASE_USER,DATABASE_PASS,DATABASE_NAME);

    if($con->connect_errno){
        echo 'Failed to connect to MySQL: '.$con->connect_error;
        exit();
    }
    $con->query("SET NAMES 'utf8'");
?>