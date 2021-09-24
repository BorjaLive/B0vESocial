<?php
    include "conn.php";

    if(empty($_GET["pass"]) || $_GET["pass"] != ADMIN_CODE){
        echo "Ejecución invalidada";
        die();
    }

    //Reiniciar todas las tareas diarias
    $link->query("TRUNCATE TABLE `tareasdiarias`");
    if($link->error) throw new Exception($link->error);

    //Borrar las notificaciones de hace más de una semana
    $link->query("DELETE FROM `notificacion` WHERE `fecha` < NOW() - INTERVAL 1 WEEK");
    if($link->error) throw new Exception($link->error);

    //Calcular si los usuarios estan activos
    $link->query("UPDATE `usuario` as u SET `activo`= (EXISTS (SELECT * FROM `post` as p WHERE p.`usuario` = u.`id` AND `fecha` > NOW() - INTERVAL 1 MONTH))");
    if($link->error) throw new Exception($link->error);

    //Registrar la cantidad de usuarios activos en el registro
    $link->query("INSERT INTO `estadisticausuariosactivos`(`cantidad`) VALUES ((SELECT COUNT(*) FROM `usuario` WHERE `activo` = '1'))");
    if($link->error) throw new Exception($link->error);

?>