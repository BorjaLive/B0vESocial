<?php
    include "../func/conn.php";
 
    try{
        list($level, $userID) = validateExecuter($_POST["user"], $_POST["pass"], $_POST["adminCode"], 1);

        if($level < 1){
            echo json_encode(array("status" => "error", "msg" => ["error" => "Insufficient permission level"]));
            die();
        }
    
        if(empty($_POST["action"])){
            echo json_encode(array("status" => "error", "msg" => ["error" => "Action not recognized"]));
            die();
        }
        
        if(isset($_FILES["file"]) && $_FILES["file"]["size"] > MAX_FILE_SIZE){
            echo json_encode(array("status" => "error", "msg" => ["error" => "Max file size exceded"]));
            die();
        }
    
        if(isset($_POST["padre"]) && $_POST["padre"] === "null") $_POST["padre"] = null;
        
        $id = null;
        switch($_POST["action"]){
            case "changeProfilePic":
                $output = "../data/profiles";
                if(!file_exists($output)) mkdir($output, 0777, true);
                $output .= "/$userID.jpg";
                createProfilePic($_FILES['file']['tmp_name'], $_FILES['file']['type'], $output);
            break;
            case "createPost":
                createPost($_POST["texto"], $_POST["padre"]);
            break;
            case "createPostImage":
                //move_uploaded_file($_FILES['file']['tmp_name'], "../data/".$_POST["name"]);
                list($id, $path) = createPost($_POST["texto"], $_POST["padre"], true, false);
                $output = "../data/posts/$path";
                if(!file_exists($output)) mkdir($output, 0777, true);
                $output .= "/$id.jpg";
                if(!createPic($_FILES['file']['tmp_name'], $_FILES['file']['type'], $output)){
                    deletePost($id);
                    throw new Exception("No se ha podido procesar la imagen.");
                }
            break;
            case "createPostVideo":
                list($id, $path) = createPost($_POST["texto"], $_POST["padre"], false, true);
                $output = "../data/posts/$path";
                if(!file_exists($output)) mkdir($output, 0777, true);
                $output .= "/$id.mp4";
                if(!createVid($_FILES['file']['tmp_name'], $_FILES['file']['type'], $output)){
                    deletePost($id);
                    throw new Exception("No se ha podido procesar el video.");
                }
            break;
            case "createPostAudio":
                //TODO: Investigar como procesar audio con la libreria de ffmpeg para php
            break;
            case "createTipoMedalla":
                if($level < 2) throw new Exception("Nivel de permiso insuficiente.");
                $id = createTipoMedalla($_POST["nombre"], $_POST["precio"]);
                $output = "../data/medallas/";
                if(!file_exists($output)) mkdir($output, 0777, true);
                $output .= "/$id.gif";
                move_uploaded_file($_FILES['file']['tmp_name'], $output);
            break;
            default:
                echo json_encode(array("status" => "error", "msg" => ["error" => "Action not recognized"]));
                die();
        }
        echo json_encode(array("status" => "success", "msg" => $id));
    }catch(Exception $e){
        echo json_encode(array("status" => "error", "msg" => ["error" => $e->getMessage()]));
    }



    function createProfilePic($file, $type, $output){
        switch($type){
            case "image/png":
            case "image/jpeg":
            case "image/gif":
            case "image/webp":
                $thumb = imagecreatetruecolor(PROFILE_PIC_SIZE, PROFILE_PIC_SIZE);
                list($ow, $oh) = getimagesize($file);
                switch($type){
                    case "image/png":
                        $o = imagecreatefrompng($file);
                    break;
                    case "image/jpeg":
                        $o = imagecreatefromjpeg($file);
                    break;
                    case "image/gif":
                        $o = imagecreatefromgif($file);
                    break;
                    case "image/webp":
                        $o = imagecreatefromwebp($file);
                    break;
                }
                if($ow/$oh > 1){
                    $ox = ($ow-$oh)/2;
                    $oy = 0;
                    $oz = $oh;
                }else{
                    $ox = 0;
                    $oy = ($oh-$ow)/2;
                    $oz = $ow;
                }
                imagecopyresized($thumb, $o, 0, 0, $ox, $oy, PROFILE_PIC_SIZE, PROFILE_PIC_SIZE, $oz, $oz);
                imagejpeg($thumb, $output);
                return true;
            break;
        }
        return false;
    }

    function createPic($file, $type, $output){
        switch($type){
            case "image/png":
            case "image/jpeg":
            case "image/gif":
            case "image/webp":
                list($ow, $oh) = getimagesize($file);
                $thumb = imagecreatetruecolor($ow, $oh);
                switch($type){
                    case "image/png":
                        $o = imagecreatefrompng($file);
                    break;
                    case "image/jpeg":
                        $o = imagecreatefromjpeg($file);
                    break;
                    case "image/gif":
                        $o = imagecreatefromgif($file);
                    break;
                    case "image/webp":
                        $o = imagecreatefromwebp($file);
                    break;
                }
                imagecopy($thumb, $o, 0, 0, 0, 0, $ow, $oh);
                imagejpeg($thumb, $output);
                return true;
            break;
        }
        return false;
    }
    function createVid($file, $type, $output){
        switch($type){
            case "video/mp4":
            case "video/avi":
                $ffmpeg = FFMpeg\FFMpeg::create([
                    "ffmpeg.binaries" => FFMPEG_BIN,
                    "ffprobe.binaries" => FFPROBE_BIN
                ]);
                $video = $ffmpeg->open($file);

                $frame = $video->frame(FFMpeg\Coordinate\TimeCode::fromSeconds(0));
                $frameFile = tmpfilepath();
                $frame->save($frameFile);
                list($w, $h) = getimagesize($frameFile);
                unlink($frameFile);

                if($w > 1280){
                    $h *= 1280/$w;
                    $w = 1280;
                }else if($h > 720){
                    $w *= 720/$h;
                    $h = 720;
                }

                $video->filters()->resize(new FFMpeg\Coordinate\Dimension($w, $h))->synchronize();
                $codec = new FFMpeg\Format\Video\X264();
                $codec 	-> setKiloBitrate(750)
                        -> setAudioCodec("libmp3lame");
                $video->save($codec, $output);
                return true;
            break;
        }
        
        return false;
    }

?>