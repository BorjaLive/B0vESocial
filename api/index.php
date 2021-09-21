<?php
    include "../func/conn.php";
//sleep(2);
    try{
        //throw new Exception("Mis huevos");
        $INPUT = json_decode(file_get_contents("php://input"), true);
        $ACTION_LEVELS = json_decode(file_get_contents("actions.json"), true);

        $action = sGet("action");
        
        //Verificar que la acción existe
        if(!isset($ACTION_LEVELS[$action])){
            echo json_encode(array("status" => "error", "msg" => ["error" => "Action not recognized"]));
            die();
        }

        //Obtener el nivel de permisos del ejecutor y su usuario, si tiene
        list($level, $userID) = validateExecuter(sGet("user"), sGet("pass"), sGet("adminCode"), $ACTION_LEVELS[$action]);

        //Comprobar si el ejecutor tiene permiso para la acción
        if($ACTION_LEVELS[$action] > $level){
            echo json_encode(array("status" => "error", "msg" => ["error" => "Insufficient permission level"]));
            die();
        }

        //Obtener parametros y llamar a la función
        $parameters = [];
        foreach(func_get_args_names($action) as $parameter){
            $parameters[] = sGet($parameter);
        }
        $resoult = call_user_func_array($action, $parameters);
        $data = ["status" => "success", "msg" => $resoult];
    
    }catch(Exception $e){
        $data = array("status" => "error", "msg" => ["error" => $e->getMessage()]);
    }

    echo json_encode($data);


    function sGet($key){
        global $INPUT;
        if(isset($INPUT[$key]))
            return $INPUT[$key];
        else
            return null;
    }
?>