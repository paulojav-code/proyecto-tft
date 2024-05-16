<?php
    function query($con,$sql,$params = []){
        $stmt = $con->prepare($sql);
        if($params != []){
            call_user_func_array([$stmt,'bind_param'],$params);
        }
        try{
            $stmt->execute();
            
            $res = [];
            $result = $stmt->get_result();
            if(isset($result->num_rows) && $result->num_rows > 0){
                while($row = $result->fetch_array(MYSQLI_ASSOC)){
                    $res[] = $row;
                }
            }
            return $res;
        }catch(Throwable $th){
            return ['error'=>$con->error];
        }
    }
    function get_table($t){
        $tables = json_decode(file_get_contents('includes/tables.json'),1);

        if(isset($tables[$t])){
            return $tables[$t];
        }
        return [];
    }
    function query_select($con,$t,$j){
        $res = [];
        if(!isset($j['select_type'])){
            $j['select_type'] = 'all';
        }
        $type_colums = [
            'all'=>'*',
            'foreign'=>$t['id'].' AS `id`,name',
            'files'=>'id_files AS `id`,name,url_file AS `url`'
        ];
        $columns = $type_colums[$j['select_type']];
        $sql = 'SELECT '.$columns.' FROM '.DATABASE_NAME.'.'.$t['name'].' WHERE `active` = 1';
        if(isset($j['id_pages']) || isset($j['id_sites']) || isset($j['id'])){
            if(isset($j['id_pages'])){
                $id = $j['id_pages'];
                $id_name = 'id_pages';
            }else if(isset($j['id_sites'])){
                $id = $j['id_sites'];
                $id_name = 'id_sites';
            }else if(isset($j['id'])){
                $id = $j['id'];
                $id_name = $t['id'];
            }
            if($id == ""){
                return ['error'=>'id vacia.'];
            }
            $res = query($con,$sql.' AND '.$id_name.' = ?;',['i',&$id]);
        }else{
            $res = query($con,$sql.';');
        }
        return $res;
    }
    function query_insert($con,$t,$j){
        $res = [];
        $columns = [];
        $params = [''];
        $values = [];
        $insert = [];
        
        foreach($t['columns'] as $i){
            if(!isset($i['default']) || !$i['default']){
                $columns[] = $i;
                $values[] = '`'.$i['name'].'`';
                $params[0] .= $i['var'];
                $insert[] = '?';
            }
        }

        foreach($columns as $i){
            if(!isset($j['columns'][$i['name']])){
                return ['error'=>'campos faltantes: '.$i['name']];
            }
            $params[] = &$j['columns'][$i['name']];
        }
        
        $sql = 'INSERT INTO '.DATABASE_NAME.'.'.$t['name']. ' ('.implode(', ',$values).') VALUES ('.implode(', ',$insert).');';

        $res = query($con,$sql,$params);

        if(isset($res['error'])){
            return $res;
        }else{
            return ['query'=>'insert','table'=>$t['name']];
        }
    }
    function query_update($con,$t,$j){
        $res = [];
        $values = [];
        $params = [''];

        if(!isset($j[$t['id']])||$j[$t['id']]==''){
            return ['error'=>'id no existe'];   
        }
        foreach($t['columns'] as $i){
            if(isset($j['columns'][$i['name']])){
                if($i['name'] != $t['id']){
                    $values[] = '`'.$i['name'].'`'.' = ?';
                    $params[0] .= $i['var'];
                    $params[] = &$j['columns'][$i['name']];
                }
            }
        }
        if($values==[]){
            return ['error'=>'parametros faltantes'];   
        }

        $params[0] .= 'i';
        $params[] = &$j[$t['id']];

        $sql = 'UPDATE '.DATABASE_NAME.'.'.$t['name'].' SET '.implode(', ',$values).' WHERE '.$t['id'].' = ?;';

        $res = query($con,$sql,$params);

        if(isset($res['error'])){
            return $res;
        }else{
            return ['query'=>'update','table'=>$t['name']];
        }
    }
    function query_delete($con,$t,$j){
        $res = [];
        $active = '0';

        if(!isset($j['id'])||$j['id']==''){
            return ['error'=>'id no existe'];   
        }

        if(isset($j['delete'])){
            if($j['delete']){
                $active = '1';
            }else{
                $active = '0';
            }
        }

        $sql = 'UPDATE '.DATABASE_NAME.'.'.$t['name'].' SET `active`='.$active.' WHERE '.$t['id'].' = ?;';
        $params = ['i',&$j['id']];
        $res = query($con,$sql,$params);
    
        if(isset($res['error'])){
            return $res;
        }else{
            return ['query'=>'delete','table'=>$t['name']];
        }
    }
    function query_login($con,$user,$j){
        if(isset($j['login']) && $user == []){
            return ['login'=>false,'msg'=>'sesion expirada'];
        }else if($user != []){
            return ['login'=>true,'msg'=>'sesion a tiempo'];
        }
        
        $res = query($con,'SELECT * FROM '.DATABASE_NAME.'.users WHERE `username` = ? AND `active` = 1;',['s',&$j['username']]);
        if($res == []){
            return ['login'=>false,'error'=>'usuario no existe'];
        }
        $res = $res[0];
        if($res['password'] != $j['password']){
            return ['login'=>false,'error'=>'password incorrecta'];
        }
        $headers = array('alg'=>'HS256','typ'=>'JWT');
        $payload = array('id_users'=>$res['id_users'],'username'=>$res['username'],'type'=>$res['id_type_users'],'exp'=>(time() + (2 * 60 * 60)));

        $jwt = generate_jwt($headers, $payload);

        query($con,'INSERT INTO '.DATABASE_NAME.'.audits(`id_users`,`request`,`date`) VALUES (?,"login",NOW());',['i',&$res['id_users']]);

        return ['login'=>true,'username'=>$res['username'],'token'=>$jwt];
    }
    function generate_jwt($headers, $payload, $secret = 'secret') {
        $headers_encoded = base64url_encode(json_encode($headers));
        
        $payload_encoded = base64url_encode(json_encode($payload));
        
        $signature = hash_hmac('SHA256', "$headers_encoded.$payload_encoded", $secret, true);
        $signature_encoded = base64url_encode($signature);
        
        $jwt = "$headers_encoded.$payload_encoded.$signature_encoded";
        
        return $jwt;
    }
    function is_jwt_valid($jwt,$secret='secret') {
        try {
            $tokenParts = explode('.', $jwt);
            $header = base64_decode($tokenParts[0]);
            $payload = base64_decode($tokenParts[1]);
            $signature_provided = $tokenParts[2];
            
            $payload_data = json_decode($payload,1);
            $expiration = $payload_data['exp'];
            $is_token_expired = ($expiration - time()) < 0;
        
            $base64_url_header = base64url_encode($header);
            $base64_url_payload = base64url_encode($payload);
            $signature = hash_hmac('SHA256', $base64_url_header . "." . $base64_url_payload, $secret, true);
            $base64_url_signature = base64url_encode($signature);
        
            $is_signature_valid = ($base64_url_signature === $signature_provided);
            
            if ($is_token_expired || !$is_signature_valid) {
                return [];
            } else {
                return $payload_data;
            }
        } catch (\Throwable $th) {
            return [];
        }
    }
    function base64url_encode($str) {
        return rtrim(strtr(base64_encode($str), '+/', '-_'), '=');
    }
    function set_audit($con,$d,$u){
        unset($d['login']);
        $a = json_encode($d);
        query($con,'INSERT INTO '.DATABASE_NAME.'.audits(`id_users`,`request`,`date`) VALUES (?,?,NOW());',['is',&$u['id_users'],&$a]);
    }
?>