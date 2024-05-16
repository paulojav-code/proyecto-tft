<?php
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");
    
    include_once('includes/conexion.php');
    include_once('includes/functions.php');

    $json = json_decode(file_get_contents('php://input'),1);

    if(!isset($json['action'])){
        echo json_encode(['error'=>'01','msg'=>'action no definida.']);
        exit();
    }
    if(!isset($json['table'])){
        echo json_encode(['error'=>'04','msg'=>'tabla no definida']);
        exit();
    }
    $table = [];
    $table = get_table($json['table']);
    if($table == []){
        echo json_encode(['error'=>'05','msg'=>'tabla no existe']);
        exit();
    }

    $type_action = [
        'select'=>function($p){return query_select($p['con'],$p['table'],$p['json']);},
        'insert'=>function($p){return query_insert($p['con'],$p['table'],$p['json']);},
        'update'=>function($p){return query_update($p['con'],$p['table'],$p['json']);},
        'delete'=>function($p){return query_delete($p['con'],$p['table'],$p['json']);}
    ];
    
    $res = isset($type_action[$json['action']]) ? $type_action[$json['action']](['con'=>$con,'table'=>$table,'json'=>$json]) : ['error'=>'action desconocida'];
    echo json_encode($res);
?>