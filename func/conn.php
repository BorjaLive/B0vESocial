<?php
    include "constants.php";

    $link = conn();

    function validateExecuter($user, $pass, $admin, $targetLevel = 0){
        $userID = checkUser($user, $pass);
        if($admin === ADMIN_CODE) return [2, $userID];
        if($userID !== null){
            if(getUserActivationCode($userID) !==null ){
                if($targetLevel > 0) throw new Exception("Usuario no activado.");
                return [0, $userID];
            } 
            return [1, $userID];
        }
        return [0, $userID];
    }
    function checkUser($user, $pass){
        try{
            $data = getUser($user);
        }catch(Exception $e){
            return null;
        }
        if($data["pass"] !== md5($pass)) throw new Exception("Contraseña incorrecta.");
        return $data["id"];
    }
    function getUserActivationCode($userID){
        global $link;

        $stmt = $link->prepare("SELECT `codigo` FROM `pendientecorreo` WHERE `usuario` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("i", $userID);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->bind_result($codigo);
        if($stmt->fetch())
            return $codigo;
        else
            return null;
    }

    //Acciones de cliente
    function registerUser($nombre, $password, $email){
        global $link;
        
        $nombre = strip_tags($nombre);
        
        $hash = md5($password);
        $user = strtolower(safeString($nombre));
        if($user == "tokiski") throw new Exception("No te puedes llamar tokiski, buen intento.");
        $stmt = $link->prepare("INSERT INTO `usuario` (`usuario`, `pass`, `email`, `nombre`) VALUES (?, ?, ?, ?)");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("ssss", $user, $hash, $email, $nombre);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->close();

        $id = $link->insert_id;
        if($id == 0) throw new Exception("No se ha podido registrar el usuario. Posiblemente el nombre o correo ya esté en uso.");
        
        copy(randomFileFromDir("../data/defaultProfiles"), "../data/profiles/$id.jpg");

        $code = generateRandomCode();
        $stmt = $link->prepare("INSERT INTO `pendientecorreo` (`usuario`, `codigo`) VALUES (?, ?)");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("ss", $id, $code);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->close();

        sendMailActivation($email, $user, $code);
    }
    function resendUserActivationMail(){
        global $userID;

        $user = getUser($userID);
        $code = getUserActivationCode($userID);
        if($code === null) throw new Exception("El usuario ya está activado.");
        sendMailActivation($user["email"], $user["usuario"], $code);
    }
    function activateUser($codigo){
        global $userID;

        $user = getUser($userID);
        $goodCode = getUserActivationCode($userID);
        if($goodCode === null) throw new Exception("El usuario ya está activado.");
        if($goodCode != $codigo) throw new Exception("El codigo no es correcto.");
        
        global $link;
        $stmt = $link->prepare("DELETE FROM `pendientecorreo` WHERE `usuario` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("i", $user["id"]);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->close();
    }
    function updateUserData($nombre, $nacimiento, $sexo, $estado, $descripcion){
        global $userID, $link;

        $nombre = strip_tags($nombre);

        $stmt = $link->prepare("UPDATE `usuario` SET `nombre` = ? WHERE `id` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("si", $nombre, $userID);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->close();

        $stmt = $link->prepare("SELECT COUNT(*) FROM `biografia` WHERE `usuario` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("i", $userID);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->bind_result($n);
        $stmt->fetch();
        $stmt->close();
        
        if($n == 0){
            $stmt = $link->prepare("INSERT INTO `biografia` (`nacimiento`, `sexo`, `estado`, `descripcion`, `usuario`) VALUES (?, ?, ?, ?, ?)");
            if($link->error) throw new Exception($link->error);
            $stmt->bind_param("ssssi", $nacimiento, $sexo, $estado, $descripcion, $userID);
            $stmt->execute();
            if($stmt->error) throw new Exception($stmt->error);
            $stmt->close();
        }else{
            $stmt = $link->prepare("UPDATE `biografia` SET `nacimiento` = ?, `sexo` = ?, `estado` = ?, `descripcion` = ? WHERE `usuario` = ?");
            if($link->error) throw new Exception($link->error);
            $stmt->bind_param("ssssi", $nacimiento, $sexo, $estado, $descripcion, $userID);
            $stmt->execute();
            if($stmt->error) throw new Exception($stmt->error);
            $stmt->close();
        }
    }
    function deleteProfilePic(){
        global $userID;
        
        copy(randomFileFromDir("../data/defaultProfiles"), "../data/profiles/$userID.jpg");
    }


    function createPost($texto, $padre, $imagen = false, $video = false){
        global $link, $userID;

        $texto = strip_tags($texto);
        $tokiskis = getUser($userID)["tokiskis"];
        if(str_contains(strtolower($texto), "@tokiski")){
            if($tokiskis < 1) throw new Exception("No tienes @tokiskis, no puedes hacer esta publicacion.");
            $stmt = $link->prepare("UPDATE `usuario` SET `tokiskis` = `tokiskis` - 1 WHERE `id` = ?");
            if($link->error) throw new Exception($link->error);
            $stmt->bind_param("i", $userID);
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $link->prepare("INSERT INTO `post` (`padre`, `usuario`, `texto`, `foto`, `video`) VALUES (?, ?, ?, ?, ?)");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("iisii", $padre, $userID, $texto, $imagen, $video);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->close();

        $id = $link->insert_id;
        $post = getPost($id);
        return [$id, date2path($post["fecha"])];
    }
    function deletePost($post){
        global $link, $userID;

        $postData = getPost($post);
        if($postData["usuario"]["id"] != $userID) throw new Exception("No puedes borrar un post que no te pertenece.");

        $stmt = $link->prepare("DELETE FROM `post` WHERE `id` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("i", $post);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->close();

        if($postData["foto"] !== null)
            unlink("../data/posts/".date2path($postData["fecha"])."/".$postData["id"].".jpg");
        if($postData["video"] !== null)
            unlink("../data/posts/".date2path($postData["fecha"])."/".$postData["id"].".mp4");

    }
    function doFavorito($post){
        global $link, $userID;

        $favorito = getPost($post)["favorito"];
        if($favorito){
            $stmt = $link->prepare("DELETE FROM `favorito` WHERE `usuario` = ? AND `post` = ?");
        }else{
            $stmt = $link->prepare("INSERT INTO `favorito` (`usuario`, `post`) VALUES (?, ?)");
        }
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("ii", $userID, $post);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->close();
        return !$favorito;
    }
    function doVociferar($post){
        global $link, $userID;
        
        $vociferado = getPost($post)["vociferado"];
        if($vociferado){
            $stmt = $link->prepare("DELETE FROM `vociferar` WHERE `usuario` = ? AND `post` = ?");
        }else{
            $stmt = $link->prepare("INSERT INTO `vociferar` (`usuario`, `post`) VALUES (?, ?)");
        }
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("ii", $userID, $post);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->close();
        return !$vociferado;
    }

    function doAcechar($usuario){
        global $link, $userID;
        
        $stmt = $link->prepare("SELECT COUNT(*) FROM `acechar` WHERE `acechador` = ? AND `acechado` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("ii", $userID, $usuario);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->bind_result($acechado);
        $stmt->fetch();
        $stmt->close();
        
        if($acechado == 1){
            $stmt = $link->prepare("DELETE FROM `acechar` WHERE `acechador` = ? AND `acechado` = ?");
        }else{
            $stmt = $link->prepare("INSERT INTO `acechar` (`acechador`, `acechado`) VALUES (?, ?)");
        }
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("ii", $userID, $usuario);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->close();
        return $acechado == 0;
    }

    function useCodigoMoneda($codigo){
        global $link, $userID;

        $stmt = $link->prepare("SELECT `valor` FROM `codigomoneda` WHERE `codigo` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("s", $codigo);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->bind_result($valor);
        if($stmt->fetch()){
            $stmt->close();

            $stmt = $link->prepare("UPDATE `usuario` SET `monedas` = `monedas` + ? WHERE `id` = ?");
            if($link->error) throw new Exception($link->error);
            $stmt->bind_param("ii", $valor, $userID);
            $stmt->execute();
            if($stmt->error) throw new Exception($stmt->error);
            $stmt->close();

            $stmt = $link->prepare("DELETE FROM `codigomoneda` WHERE `codigo` = ?");
            if($link->error) throw new Exception($link->error);
            $stmt->bind_param("s", $codigo);
            $stmt->execute();
            if($stmt->error) throw new Exception($stmt->error);
            $stmt->close();

            return $valor;
        }else throw new Exception("El codigo no es valido o ya ha sido usado.");
    }
    function comprarTokiski(){
        global $link, $userID;

        $precio = PRECIO_TOKISKI;
        pagar($userID, $precio);

        $stmt = $link->prepare("UPDATE `usuario` SET `tokiskis` = `tokiskis` + 1 WHERE `id` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("i", $userID);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->close();
    }
    function pagar($usuario, $precio){
        global $link;

        if(getUser($usuario)["monedas"] < $precio) throw new Exception("No tienes suficientes monedas");
        $stmt = $link->prepare("UPDATE `usuario` SET `monedas` = `monedas` - ? WHERE `id` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("ii", $precio, $userID);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->close();
    }


    //Obtenccion de datos
    function getUser($user){
        global $link, $userID;
        if($user === null)
            $user = $userID;
        
        if(str_starts_with($user, "@")){
            $where = "`usuario` = ?";
            $user = substr($user, 1);
        }else if(is_numeric($user)){
            $where = "`id` = ?";
        }else{
            $where = "`email` = ?";
        }
        
        $stmt = $link->prepare("SELECT `id`, `usuario`, `pass`, `email`, `nombre`, `activo`, `monedas`, `tokiskis`, `creacion` FROM `usuario` WHERE $where");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("s", $user);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $res = $stmt->get_result();

        $data = $res->fetch_array(MYSQLI_ASSOC);
        $stmt->close();

        if($data === null) throw new Exception("No se ha encontrado el usuario.");
        $data["profilePic"] = EXTERNAL_URL."data/profiles/".$data["id"].".jpg";
        return $data;
    }
    function getUserData($user){
        global $link, $userID;

        if($user === null)
            $user = $userID;

        $user = getUser($user);

        $stmt = $link->prepare("SELECT * FROM `biografia` WHERE `usuario` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("i", $user["id"]);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $res = $stmt->get_result();
        $data = $res->fetch_array(MYSQLI_ASSOC);
        $stmt->close();

        $stmt = $link->prepare("SELECT COUNT(*) FROM `acechar` WHERE `acechado` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("i", $user["id"]);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->bind_result($user["acechadores"]);
        $stmt->fetch();
        $stmt->close();

        $stmt = $link->prepare("SELECT COUNT(*) FROM `acechar` WHERE `acechador` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("i", $user["id"]);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->bind_result($user["acechados"]);
        $stmt->fetch();
        $stmt->close();

        if($data === null)
            return $user;
        else
            return array_merge($data, $user);
    }

    function getPublicUser($usuario){
        $user = getUser($usuario);
        unset($user["pass"]);
        unset($user["email"]);
        unset($user["activo"]);
        unset($user["monedas"]);
        unset($user["tokiskis"]);

        return $user;
    }
    function getPublicUserData($usuario){
        $user = getUserData($usuario);
        unset($user["pass"]);
        unset($user["email"]);
        unset($user["activo"]);
        unset($user["monedas"]);
        unset($user["tokiskis"]);

        return $user;
    }

    function getPost($id, $completo = false){
        global $link, $userID;

        $stmt = $link->prepare("SELECT p.`id`, p.`padre`, p.`usuario`, p.`fecha`, p.`texto`, p.`foto`, p.`video`, (EXISTS (SELECT * FROM `favorito` f WHERE f.`usuario` = ? AND f.`post` = p.`id`)) as `favorito`, (EXISTS (SELECT * FROM `vociferar` f WHERE f.`usuario` = ? AND f.`post` = p.`id`)) as `vociferado`, (SELECT COUNT(`usuario`) FROM `favorito` WHERE `post` = p.`id`) as `favoritos`, (SELECT COUNT(`usuario`) FROM `vociferar` WHERE `post` = p.`id`) as `vociferados` FROM `post` p WHERE p.`id` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("iii", $userID, $userID, $id);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $res = $stmt->get_result();

        $data = $res->fetch_array(MYSQLI_ASSOC);
        $stmt->close();

        if($data === null) throw new Exception("No se ha encontrado el post.");

        $data["usuario"] = getPublicUser($data["usuario"]);
        if($data["foto"])
            $data["foto"] = EXTERNAL_URL."data/posts/".date2path($data["fecha"])."/".$data["id"].".jpg";
        else
            $data["foto"] = null;
        if($data["video"])
            $data["video"] = EXTERNAL_URL."data/posts/".date2path($data["fecha"])."/".$data["id"].".mp4";
        else
            $data["video"] = null;
        $data["favorito"] = $data["favorito"] === 1;
        $data["vociferado"] = $data["vociferado"] === 1;
        
        if($completo){
            //Los hijos de este post
            $stmt = $link->prepare("SELECT `id` FROM `post` WHERE `padre` = ? ORDER BY `fecha` DESC");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            if($stmt->error) throw new Exception($stmt->error);
            $res = $stmt->get_result();
            $respuestas = [];
            while($respuesta = $res->fetch_array(MYSQLI_ASSOC)){
                $respuestas[] = $respuesta["id"];
            }
            $stmt->close();

            $data["respuestas"] = [];
            foreach($respuestas as $respuesta){
                $data["respuestas"][] = getPost($respuesta);
            }

            if($data["padre"] !== null) $data["padre"] = getPost($data["padre"]);
        }

        return $data;
    }
    function getPosts($usuario, $foto, $video, $inicio){
        global $link;

        list($tipos, $parametros, $where) = postPrepareParameter($usuario, $foto, $video, $inicio);
        $stmt = $link->prepare("SELECT `post`.`id`, `texto`, `foto`, `video`, `post`.`usuario`, `usuario`.`usuario`, `fecha`, `nombre` FROM `post` INNER JOIN `usuario` ON(`post`.`usuario` = `usuario`.`id`) WHERE `post`.`usuario` = ? $where ORDER BY `fecha` DESC  LIMIT ? OFFSET ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param($tipos, ...$parametros);
    
        return postReFormat($stmt);
    }
    function getVociferados($usuario, $foto, $video, $inicio){
        global $link;

        list($tipos, $parametros, $where) = postPrepareParameter($usuario, $foto, $video, $inicio);
        $stmt = $link->prepare("SELECT `post`.`id`, `texto`, `foto`, `video`, `post`.`usuario`, `usuario`.`usuario`, `fecha`, `nombre` FROM `post` INNER JOIN `usuario` ON(`post`.`usuario` = `usuario`.`id`) WHERE `post`.`id` IN (SELECT `post` FROM `vociferar` WHERE `vociferar`.`usuario` = ?) $where  LIMIT ? OFFSET ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param($tipos, ...$parametros);
    
        return postReFormat($stmt);
    }
    function getFavoritos($usuario, $foto, $video, $inicio){
        global $link;

        list($tipos, $parametros, $where) = postPrepareParameter($usuario, $foto, $video, $inicio);
        $stmt = $link->prepare("SELECT `post`.`id`, `texto`, `foto`, `video`, `post`.`usuario`, `usuario`.`usuario`, `fecha`, `nombre` FROM `post` INNER JOIN `usuario` ON(`post`.`usuario` = `usuario`.`id`) WHERE `post`.`id` IN (SELECT `post` FROM `favorito` WHERE `favorito`.`usuario` = ?) $where  LIMIT ? OFFSET ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param($tipos, ...$parametros);
    
        return postReFormat($stmt);
    }
    function getFeed($inicio){
        global $link, $userID;
        $limite = MAX_POSTS_PER_QUERY;

        $user = "%@".getUser($userID)["usuario"]."%";
        $stmt = $link->prepare("SELECT `post`.`id`, `texto`, `foto`, `video`, `post`.`usuario`, `usuario`.`usuario`, `fecha`, `nombre` FROM `post` INNER JOIN `usuario` ON(`post`.`usuario` = `usuario`.`id`) WHERE `post`.`usuario` = ? OR `post`.`usuario` IN (SELECT `acechado` FROM `acechar` WHERE `acechador` = ?) OR `texto` LIKE ? OR `texto` LIKE '%@tokiski%' OR `post`.`id` IN (SELECT `post` FROM `vociferar` WHERE `vociferar`.`usuario` = ? OR `vociferar`.`usuario` IN (SELECT `acechado` FROM `acechar` WHERE `acechador` = ?)) ORDER BY `fecha` DESC LIMIT ? OFFSET ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("iisiiii", $userID, $userID, $user, $userID, $userID, $limite, $inicio);

        return postReFormat($stmt);
    }
    function postPrepareParameter($usuario, $foto, $video, $inicio){
        $parametros = [$usuario];
        if($foto === null && $video === null){
            $where = "";
            $tipos = "i";
        }else{
            $where = "AND `foto` = ? AND `video` = ?";
            $tipos = "iii";
            $parametros[] = $foto?1:0;
            $parametros[] = $video?1:0;
        }
        $tipos .= "ii";
        $parametros[] = MAX_POSTS_PER_QUERY;
        $parametros[] = $inicio;
        return [$tipos, $parametros, $where];
    }
    function postReFormat($stmt){
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->bind_result($id, $texto, $foto, $video, $usuarioID, $usuario, $fecha, $nombre);

        $posts = [];
        while($stmt->fetch()){
            if($foto) $foto = EXTERNAL_URL."data/posts/".date2path($fecha)."/".$id.".jpg"; else $foto = null;
            if($video) $video = EXTERNAL_URL."data/posts/".date2path($fecha)."/".$id.".mp4"; else $video = null;
            $posts[] = [
                "id" => $id,
                "texto" => $texto,
                "foto" => $foto,
                "video" => $video,
                "usuario" => [
                    "id" => $usuarioID,
                    "usuario" => $usuario,
                    "nombre" => $nombre,
                    "profilePic" => EXTERNAL_URL."data/profiles/$usuarioID.jpg"
                ],
                "fecha" => $fecha
            ];
        }

        $stmt->close();
        return $posts;
    }
    function getAcechados($id = null){
        global $link, $userID;

        if($id === null)
            $id = $userID;

        $stmt = $link->prepare("SELECT `usuario`.`id`, `usuario`.`usuario`, `usuario`.`nombre` FROM `acechar` INNER JOIN `usuario` ON (`acechar`.`acechado` = `usuario`.`id`) WHERE `acechador` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $res = $stmt->get_result();

        $acechados = [];
        while($acechado = $res->fetch_array(MYSQLI_ASSOC)){
            $acechado["profilePic"] = EXTERNAL_URL."data/profiles/".$acechado["id"].".jpg";
            $acechados[] = $acechado;
        }
        $stmt->close();

        return $acechados;
    }
    function getAcechadores($id){
        global $link, $userID;
        
        if($id === null)
            $id = $userID;

        $stmt = $link->prepare("SELECT `usuario`.`id`, `usuario`.`usuario`, `usuario`.`nombre`  FROM `acechar` INNER JOIN `usuario` ON (`acechar`.`acechador` = `usuario`.`id`) WHERE `acechado` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $res = $stmt->get_result();

        $acechadores = [];
        while($acechador = $res->fetch_array(MYSQLI_ASSOC)){
            $acechador["profilePic"] = EXTERNAL_URL."data/profiles/".$acechador["id"].".jpg";
            $acechadores[] = $acechador;
        }
        $stmt->close();

        return $acechadores;
    }

    function getEstados(){
        global $link;

        $stmt = $link->prepare("SELECT `id` FROM `estado`");
        if($link->error) throw new Exception($link->error);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->bind_result($estado);

        $estados = [];
        while($stmt->fetch()){
            $estados[] = $estado;
        }
        $stmt->close();

        return $estados;
    }
    function getSexos(){
        global $link;

        $stmt = $link->prepare("SELECT `id` FROM `sexo`");
        if($link->error) throw new Exception($link->error);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->bind_result($sexo);

        $sexos = [];
        while($stmt->fetch()){
            $sexos[] = $sexo;
        }
        $stmt->close();

        return $sexos;
    }

    //Acciones de administrador
    function getAdminIndexInfo(){
        return "Buenos datos";
    }
    function createCodigoMoneda($valor, $cantidad){
        global $link;

        $stmt = $link->prepare("INSERT INTO `codigomoneda` (`codigo`, `valor`) VALUES (?, ?)");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("si", $codigo, $valor);

        $codigos = [];
        for($i = 0; $i < $cantidad; $i++){
            $codigo = generateRandomCode();
            $codigos[] = $codigo;
            $stmt->execute();
            if($stmt->error) throw new Exception($stmt->error);
        }
        $stmt->close();

        return $codigos;
    }
    function deleteCodigoMoneda($codigo){
        global $link;

        $stmt = $link->prepare("DELETE FROM `codigomoneda` WHERE `codigo` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("s", $codigo);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->close();
    }
    function createEstado($estado){
        global $link;

        $stmt = $link->prepare("INSERT INTO `estado` (`id`) VALUES (?)");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("s", $estado);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->close();
    }
    function deleteEstado($estado){
        global $link;

        $stmt = $link->prepare("DELETE FROM `estado` WHERE `id` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("s", $estado);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->close();
    }
    function editEstado($estado, $nuevo){
        global $link;

        $stmt = $link->prepare("UPDATE `estado` SET `id` = ? WHERE `id` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("ss", $nuevo, $estado);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->close();
    }
    function createSexo($sexo){
        global $link;

        $stmt = $link->prepare("INSERT INTO `sexo` (`id`) VALUES (?)");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("s", $sexo);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->close();
    }
    function deleteSexo($sexo){
        global $link;

        $stmt = $link->prepare("DELETE FROM `sexo` WHERE `id` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("s", $sexo);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->close();
    }
    function editSexo($sexo, $nuevo){
        global $link;

        $stmt = $link->prepare("UPDATE `sexo` SET `id` = ? WHERE `id` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("ss", $nuevo, $sexo);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->close();
    }

    function getCodigoMonedas($valor){
        global $link;

        if($valor === null){
            $stmt = $link->prepare("SELECT `codigo`, `valor` FROM `codigomoneda` ORDER BY `fecha` DESC");
            if($link->error) throw new Exception($link->error);
        }else{
            $stmt = $link->prepare("SELECT `codigo`, `valor` FROM `codigomoneda` WHERE `valor` = ? ORDER BY `fecha` DESC");
            if($link->error) throw new Exception($link->error);
            $stmt->bind_param("i", $valor);
        }
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->bind_result($codigo, $valor);

        $codigos = [];
        while($stmt->fetch()){
            $codigos[] = [
                "codigo" => $codigo,
                "valor" => $valor
            ];
        }
        $stmt->close();

        return $codigos;
    }

    function test($id){
        return array_disect(getAcechadores($id), "id");
    }
?>