<?php
    include "constants.php";
    use PayPal\Api\Amount;
    use PayPal\Api\Details;
    use PayPal\Api\Item;
    use PayPal\Api\ItemList;
    use PayPal\Api\Payer;
    use PayPal\Api\Payment;
    use PayPal\Api\RedirectUrls;
    use PayPal\Api\Transaction;
    use \PayPal\Rest\ApiContext;
    use \PayPal\Auth\OAuthTokenCredential;

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


    function createPost($texto, $padre = null, $imagen = false, $video = false){
        global $link, $userID;

        $mencions = [];
        $ats = extractAts($texto);
        foreach($ats as $at){
            if($at != "tokiski"){
                try{
                    $atID = getUserIDbySID($at);
                    $mencions[] = $atID;
                }catch(Exception $e){
                    throw new Exception("La mencicición @".$at." no se corresponde con ningún usuario registrado. \n ".$e);
                }
            }
        }

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

        foreach($mencions as $mencion){
            createNotification($mencion, $id, $userID, NOTIFICATION_MENCION);
        }
        if($padre !== null){
            $padreData = getPost($padre);
            createNotification($padreData["usuario"]["id"], $id, $userID, NOTIFICATION_RESPONDIDO);
            performTareaDiaria(TAREA_RESPONDER);
        }

        performTareaDiaria(TAREA_PUBLICAR);
        if(count($mencions) != 0) performTareaDiaria(TAREA_MENCIONAR);

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

        $postData = getPost($post);
        $favorito = $postData["favorito"];
        if($favorito){
            $stmt = $link->prepare("DELETE FROM `favorito` WHERE `usuario` = ? AND `post` = ?");
        }else{
            $stmt = $link->prepare("INSERT INTO `favorito` (`usuario`, `post`) VALUES (?, ?)");
            createNotification($postData["usuario"]["id"], $postData["id"], $userID, NOTIFICATION_FAVORITO);
        }
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("ii", $userID, $post);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->close();

        performTareaDiaria(TAREA_FAVORITO);

        return !$favorito;
    }
    function doVociferar($post){
        global $link, $userID;
        
        $postData = getPost($post);
        $vociferado = $postData["vociferado"];
        if($vociferado){
            $stmt = $link->prepare("DELETE FROM `vociferar` WHERE `usuario` = ? AND `post` = ?");
        }else{
            $stmt = $link->prepare("INSERT INTO `vociferar` (`usuario`, `post`) VALUES (?, ?)");
            createNotification($postData["usuario"]["id"], $postData["id"], $userID, NOTIFICATION_VOCIFERADO);
        }
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("ii", $userID, $post);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->close();

        performTareaDiaria(TAREA_VOCIFERAR);

        return !$vociferado;
    }
    function darMedalla($id, $post){
        global $link, $userID;

        $stmt = $link->prepare("SELECT `usuario` FROM `medalla` WHERE `id` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->bind_result($usuario);
        if(!$stmt->fetch()) throw new Exception("Medalla no encontrada");
        $stmt->close();

        if($usuario != $userID) throw new Exception("No puedes dar una medalla que no te pertenece.");

        //TODO: No permitir que se den medallas a un post propio
        $stmt = $link->prepare("SELECT `post`.`usuario` FROM `post` WHERE `id` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("i", $post);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->bind_result($autor);
        if(!$stmt->fetch()) throw new Exception("Post no encontrado");
        $stmt->close();

        if($autor == $userID) throw new Exception("No puedes dar una medalla a un post tuyo.");

        $stmt = $link->prepare("UPDATE `medalla` SET `usuario` = NULL, `post` = ? WHERE `id` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("ii", $post, $id);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->close();

        createNotification($autor, $post, $userID, NOTIFICATION_MEDALLA);
    }
    function apropiarMedalla($id){
        global $link, $userID;

        $stmt = $link->prepare("SELECT `post`.`usuario` FROM `medalla` INNER JOIN `post` ON(`medalla`.`post` = `post`.`id`) WHERE `medalla`.`id` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->bind_result($usuario);
        if(!$stmt->fetch()) throw new Exception("Medalla no encontrada");
        $stmt->close();

        if($usuario != $userID) throw new Exception("No puedes apropiarte de una medalla que no está en un post tuyo.");

        $stmt = $link->prepare("UPDATE `medalla` SET `usuario` = ?, `post` = NULL WHERE `id` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("ii", $userID, $id);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->close();
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
        $stmt->bind_param("ii", $precio, $usuario);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->close();
    }
    function agregarDinero($usuario, $monedas){
        global $link;

        $stmt = $link->prepare("UPDATE `usuario` SET `monedas` = `monedas` + ? WHERE `id` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("ii", $monedas, $usuario);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->close();
    }
    function comprarModenas($euros){
        global $userID;

        $monedas = $euros*100;

        $apiContext = new ApiContext(new OAuthTokenCredential(PAYPAL_CLIENT_ID, PAYPAL_CLIENT_SECRET));

        $payer = new Payer();
        $payer->setPaymentMethod("paypal");

        $item1 = new Item();
        $item1->setName('100 Monedas B0vE')
            ->setCurrency('EUR')
            ->setQuantity($euros)
            ->setSku(PRODUCTO_100MONEDAS)
            ->setPrice(1);

        $itemList = new ItemList();
        $itemList->setItems([$item1]);

        $amount = new Amount();
        $amount->setCurrency("EUR")
            ->setTotal($euros);

        $transaction = new Transaction();
        $transaction->setAmount($amount)
            ->setItemList($itemList)
            ->setDescription("Compra de ".$monedas." monedas B0vE.")
            ->setInvoiceNumber(uniqid())
            ->setCustom($userID);

        $redirectUrls = new RedirectUrls();
        $redirectUrls->setReturnUrl(EXTERNAL_URL."payment.php?success=true")
            ->setCancelUrl(EXTERNAL_URL."payment.php?success=false");

        $payment = new Payment();
        $payment->setIntent("sale")
            ->setPayer($payer)
            ->setRedirectUrls($redirectUrls)
            ->setTransactions([$transaction]);

        $request = clone $payment;

        try {
            $payment->create($apiContext);
        } catch (Exception $ex) {
            throw $ex;
        }

        $approvalUrl = $payment->getApprovalLink();

        return $approvalUrl;
    }
    function ejecutarComprarModenas($usuario, $euros, $paymentID){
        global $link;

        //Por el unique, fallará si esta repetido
        $stmt = $link->prepare("INSERT INTO `compramoneda` (`usuario`, `euros`, `transaccion`) VALUES (?, ?, ?)");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("iis", $usuario, $euros, $paymentID);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->close();

        agregarDinero($usuario, $euros * 100);
    }

    function createNotification($usuario, $post, $autor, $tipo){
        global $link, $userID;

        $stmt = $link->prepare("INSERT INTO `notificacion` (`usuario`, `post`, `autor`, `tipo`) VALUES (?, ?, ?, ?)");
        //if($link->error) throw new Exception($link->error);
        $stmt->bind_param("iiii", $usuario, $post, $autor, $tipo);
        $stmt->execute();
        //if($stmt->error) throw new Exception($stmt->error);
        $stmt->close();
        //No importa si está duplicado y no se puede insertar
    }
    function performTareaDiaria($tipo){
        global $link, $userID;

        if(!isset(MONEDAS_TAREA[$tipo])) throw new Exception("Tipo de tarea diaria válida.");

        $stmt = $link->prepare("INSERT INTO `tareasdiarias` (`usuario`, `tipo`) VALUES (?, ?)");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("ii", $userID, $tipo);
        $stmt->execute();
        if($stmt->error){
            //Si no se puede insertar es porque ya esta, luego no se desbloquea y no se paga
            $paga = null;
        }else $paga = MONEDAS_TAREA[$tipo];
        $stmt->close();
        
        

        //Comprobar si las tiene todas
        $stmt = $link->prepare("SELECT COUNT(`tipo`) FROM `tareasdiarias` WHERE `usuario` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("i", $userID);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->bind_result($n);
        $stmt->fetch();
        $stmt->close();
        if($n == count(MONEDAS_TAREA)-1){
            performTareaDiaria(TAREA_TODAS);
        }
    }
    function cobrarTareaDiaria($tipo){
        global $link, $userID;
        if(!isset(MONEDAS_TAREA[$tipo])) throw new Exception("Tipo de tarea diaria válida.");

        $stmt = $link->prepare("SELECT `cobrado` FROM `tareasdiarias` WHERE `usuario` = ? AND `tipo` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("ii", $userID, $tipo);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->bind_result($cobrado);
        
        if($stmt->fetch()){
            if($cobrado == 1) throw new Exception("Ya has cobrado esta tarea.");
        }else throw new Exception("No has completado la tarae.");
        $stmt->close();

        agregarDinero($userID, MONEDAS_TAREA[$tipo]);

        $stmt = $link->prepare("UPDATE `tareasdiarias` SET `cobrado` = '1' WHERE `usuario` = ? AND `tipo` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("ii", $userID, $tipo);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->close();
        
        return MONEDAS_TAREA[$tipo];
    }
    function comprarMedalla($id){
        global $link, $userID;

        $stmt = $link->prepare("SELECT `precio`  FROM `tipomedalla` WHERE `id` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->bind_result($precio);
        if(!$stmt->fetch()) throw new Exception("Tipo de medalla no encontrada");
        $stmt->close();

        pagar($userID, $precio);

        $stmt = $link->prepare("INSERT INTO `medalla` (`tipo`, `usuario`) VALUES (?, ?)");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("ii", $id, $userID);
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

        $user["medallas"] = [];
        $stmt = $link->prepare("SELECT DISTINCT `tipo` FROM `medalla` WHERE `usuario` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("i", $user["id"]);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->bind_result($medalla);
        while($stmt->fetch()) $user["medallas"][] = EXTERNAL_URL."data/medallas/$medalla.gif";
        $stmt->close();

        if($data === null)
            return $user;
        else
            return array_merge($data, $user);
    }
    function getUserIDbySID($sid){
        global $link;

        $stmt = $link->prepare("SELECT `id` FROM `usuario` WHERE `usuario` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("s", $sid);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->bind_result($id);
        if(!$stmt->fetch()) throw new Exception("Usuario no encontrado");
        $stmt->close();

        return $id;
    }

    function getPublicUser($usuario){
        global $userID;

        $user = getUser($usuario);
        if($user["id"] != $userID){
            unset($user["pass"]);
            unset($user["email"]);
            unset($user["activo"]);
            unset($user["monedas"]);
            unset($user["tokiskis"]);
        }

        return $user;
    }
    function getPublicUserData($usuario){
        global $userID;

        $user = getUserData($usuario);
        if($user["id"] != $userID){
            unset($user["pass"]);
            unset($user["email"]);
            unset($user["activo"]);
            unset($user["monedas"]);
            unset($user["tokiskis"]);
        }

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
            if($link->error) throw new Exception($link->error);
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

            //El padre del post
            if($data["padre"] !== null) $data["padre"] = getPost($data["padre"]);

            //Las medallas que tiene
            $stmt = $link->prepare("SELECT `id`, `tipo` FROM `medalla` WHERE `post` = ?");
            if($link->error) throw new Exception($link->error);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            if($stmt->error) throw new Exception($stmt->error);
            $res = $stmt->get_result();
            $data["medallas"] = [];
            while($medalla = $res->fetch_array(MYSQLI_ASSOC)){
                $medalla["icon"] = EXTERNAL_URL."data/medallas/".$medalla["tipo"].".gif";
                $data["medallas"][] = $medalla;
            }
            $stmt->close();
        }

        return $data;
    }
    function getPosts($usuario, $foto, $video, $inicio){
        global $link;

        list($tipos, $parametros, $where) = postPrepareParameter($usuario, $foto, $video, $inicio);
        $stmt = $link->prepare("SELECT `post`.`id`, `texto`, `foto`, `video`, `post`.`usuario`, `usuario`.`usuario`, `fecha`, `nombre`, NULLIF(GROUP_CONCAT(IFNULL(`medalla`.`tipo`, 'null') SEPARATOR '|'), 'null') as `medallas` FROM `post` LEFT OUTER JOIN `medalla` ON(`post`.`id` = `medalla`.`post`) INNER JOIN `usuario` ON(`post`.`usuario` = `usuario`.`id`) WHERE `post`.`usuario` = ? $where GROUP BY `post`.`id` ORDER BY `fecha` DESC  LIMIT ? OFFSET ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param($tipos, ...$parametros);
    
        return postReFormat($stmt);
    }
    function getVociferados($usuario, $foto, $video, $inicio){
        global $link;

        list($tipos, $parametros, $where) = postPrepareParameter($usuario, $foto, $video, $inicio);
        $stmt = $link->prepare("SELECT `post`.`id`, `texto`, `foto`, `video`, `post`.`usuario`, `usuario`.`usuario`, `fecha`, `nombre`, NULLIF(GROUP_CONCAT(IFNULL(`medalla`.`tipo`, 'null') SEPARATOR '|'), 'null') as `medallas` FROM `post` LEFT OUTER JOIN `medalla` ON(`post`.`id` = `medalla`.`post`) INNER JOIN `usuario` ON(`post`.`usuario` = `usuario`.`id`) WHERE `post`.`id` IN (SELECT `post` FROM `vociferar` WHERE `vociferar`.`usuario` = ?) $where GROUP BY `post`.`id` LIMIT ? OFFSET ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param($tipos, ...$parametros);
    
        return postReFormat($stmt);
    }
    function getFavoritos($usuario, $foto, $video, $inicio){
        global $link;

        list($tipos, $parametros, $where) = postPrepareParameter($usuario, $foto, $video, $inicio);
        $stmt = $link->prepare("SELECT `post`.`id`, `texto`, `foto`, `video`, `post`.`usuario`, `usuario`.`usuario`, `fecha`, `nombre`, NULLIF(GROUP_CONCAT(IFNULL(`medalla`.`tipo`, 'null') SEPARATOR '|'), 'null') as `medallas` FROM `post` LEFT OUTER JOIN `medalla` ON(`post`.`id` = `medalla`.`post`) INNER JOIN `usuario` ON(`post`.`usuario` = `usuario`.`id`) WHERE `post`.`id` IN (SELECT `post` FROM `favorito` WHERE `favorito`.`usuario` = ?) $where GROUP BY `post`.`id` LIMIT ? OFFSET ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param($tipos, ...$parametros);
    
        return postReFormat($stmt);
    }
    function getFeed($inicio){
        global $link, $userID;
        $limite = MAX_POSTS_PER_QUERY;

        $user = "%@".getUser($userID)["usuario"]."%";
        $stmt = $link->prepare("SELECT `post`.`id`, `texto`, `foto`, `video`, `post`.`usuario`, `usuario`.`usuario`, `fecha`, `nombre`, NULLIF(GROUP_CONCAT(IFNULL(`medalla`.`tipo`, 'null') SEPARATOR '|'), 'null') as `medallas` FROM `post` LEFT OUTER JOIN `medalla` ON(`post`.`id` = `medalla`.`post`) INNER JOIN `usuario` ON(`post`.`usuario` = `usuario`.`id`) WHERE `post`.`usuario` = ? OR `post`.`usuario` IN (SELECT `acechado` FROM `acechar` WHERE `acechador` = ?) OR `texto` LIKE ? OR `texto` LIKE '%@tokiski%' OR `post`.`id` IN (SELECT `post` FROM `vociferar` WHERE `vociferar`.`usuario` = ? OR `vociferar`.`usuario` IN (SELECT `acechado` FROM `acechar` WHERE `acechador` = ?)) GROUP BY `post`.`id` ORDER BY `fecha` DESC LIMIT ? OFFSET ?");
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
        $stmt->bind_result($id, $texto, $foto, $video, $usuarioID, $usuario, $fecha, $nombre, $medallas);

        $posts = [];
        while($stmt->fetch()){
            if($foto) $foto = EXTERNAL_URL."data/posts/".date2path($fecha)."/".$id.".jpg"; else $foto = null;
            if($video) $video = EXTERNAL_URL."data/posts/".date2path($fecha)."/".$id.".mp4"; else $video = null;

            if($medallas === null){
                $medallas = [];
            }else{
                $medallasIDs = explode("|", $medallas);
                $medallas = [];
                foreach($medallasIDs as $medallaID){
                    $medallas[] = EXTERNAL_URL."data/medallas/$medallaID.gif";
                }
            }

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
                "medallas" => $medallas,
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
    function getNotificaciones(){
        global $link, $userID;

        $stmt = $link->prepare("SELECT * FROM `notificacion` WHERE `usuario` = ? ORDER BY `fecha` DESC LIMIT 100");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("i", $userID);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $res = $stmt->get_result();

        $notificaciones = [];
        while($data = $res->fetch_array(MYSQLI_ASSOC)){
            if($data["post"] !== null) $data["post"] = getPost($data["post"], true);
            if($data["autor"] !== null) $data["autor"] = getUser($data["autor"]);
            $notificaciones[] = $data;
        }
        $stmt->close();

        performTareaDiaria(TAREA_VER_NOTIFICACIONES);

        return $notificaciones;
    }
    function getTareasDiarias(){
        global $link, $userID;

        $tareas = [];
        foreach(array_keys(MONEDAS_TAREA) as $tarea){
            $tareas[$tarea] = [
                "conseguido" => false,
                "cobrado" => false
            ];
        }

        $stmt = $link->prepare("SELECT `tipo`, `cobrado` FROM `tareasdiarias` WHERE `usuario` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("i", $userID);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->bind_result($tipo, $cobrado);

        while($stmt->fetch()){
            $tareas[$tipo]["conseguido"] = true;
            $tareas[$tipo]["cobrado"] = $cobrado == 1;
        }
        $stmt->close();

        return $tareas;
    }
    function getTipoMedallasUser(){
        global $link, $userID;

        $stmt = $link->prepare("SELECT t.`id`, t.`nombre`, t.`precio`, (SELECT COUNT(*) FROM `medalla` m WHERE m.`tipo` = t.`id` AND m.`usuario` = ?) as `cantidad` FROM `tipomedalla` t ORDER BY t.`precio`");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("i", $userID);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $res = $stmt->get_result();

        $medallas = [];
        while($data = $res->fetch_array(MYSQLI_ASSOC)){
            $data["icon"] = EXTERNAL_URL."data/medallas/".$data["id"].".gif";
            $medallas[] = $data;
        }
        $stmt->close();

        return $medallas;
    }
    function getMedallas(){
        global $link, $userID;

        $stmt = $link->prepare("SELECT `medalla`.`id`, `medalla`.`tipo`, `nombre`, COUNT(*) as `cantidad` FROM `medalla` INNER JOIN `tipomedalla` ON(`medalla`.`tipo` = `tipomedalla`.`id`) WHERE `medalla`.`usuario` = ? GROUP BY `tipo`");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("i", $userID);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $res = $stmt->get_result();

        $medallas = [];
        while($data = $res->fetch_array(MYSQLI_ASSOC)){
            $data["icon"] = EXTERNAL_URL."data/medallas/".$data["tipo"].".gif";
            $medallas[] = $data;
        }
        $stmt->close();

        return $medallas;
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
    function createTipoMedalla($nombre, $precio){
        global $link;

        $stmt = $link->prepare("INSERT INTO `tipomedalla` (`nombre`, `precio`) VALUES (?, ?)");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("si", $nombre, $precio);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->close();

        return $link->insert_id;
    }
    function deleteTipoMedalla($id){
        global $link;

        $stmt = $link->prepare("DELETE FROM `tipomedalla` WHERE `id` = ?");
        if($link->error) throw new Exception($link->error);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $stmt->close();

        $icon = "../data/medallas/$id.gif";
        if(file_exists($icon)) unlink($icon);
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
    function getCompras($euros = null){
        global $link;

        $where = "";
        if($euros !== null) $where = "WHERE `euros` = ?";

        $stmt = $link->prepare("SELECT `euros`, `transaccion`, `fecha`, `usuario`.`id`, `usuario`.`nombre`  FROM `compramoneda`  INNER JOIN `usuario` ON (`compramoneda`.`usuario` = `usuario`.`id`) $where");
        if($link->error) throw new Exception($link->error);
        if($euros !== null) $stmt->bind_param("i", $euros);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $res = $stmt->get_result();

        $compras = [];
        while($data = $res->fetch_array(MYSQLI_ASSOC)){
            $data["profilePic"] = EXTERNAL_URL."data/profiles/".$data["id"].".jpg";
            $compras[] = $data;
        }
        
        return $compras;
    }
    function getEstadisticaUsuariosActivos($fechaInicio = null, $fechaFin = null, $intervalo = null){
        global $link;

        list($group, $select, $divName) = composeDateIntervalWithDivides($intervalo, "dia");

        $sql = "SELECT `dia`, `cantidad`, $select FROM `estadisticausuariosactivos` $group ORDER BY `dia`";
        $res = $link->query($sql);
        if($link->error) throw new Exception($sql." --> ".$link->error);

        $tabla = tabularEstadisticas($res, "cantidad", $divName);
        $tabla[0][1] = "Usuarios activos";
        return $tabla;
    }
    function getTipoMedallas(){
        global $link;

        $stmt = $link->prepare("SELECT `id`, `nombre`, `precio`  FROM `tipomedalla`");
        if($link->error) throw new Exception($link->error);
        $stmt->execute();
        if($stmt->error) throw new Exception($stmt->error);
        $res = $stmt->get_result();

        $medallas = [];
        while($data = $res->fetch_array(MYSQLI_ASSOC)){
            $data["icon"] = EXTERNAL_URL."data/medallas/".$data["id"].".gif";
            $medallas[] = $data;
        }
        
        return $medallas;
    }

    function test($text){
        return getNotificaciones();
    }
?>