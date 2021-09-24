<?php
	require 'vendor/autoload.php';
    include "installation_constants.php";
	use PHPMailer\PHPMailer\PHPMailer;
	use PHPMailer\PHPMailer\SMTP;

    
    define("MAX_FILE_SIZE", 100*1024*1024);
    define("PROFILE_PIC_SIZE", "512");
    define("PRECIO_TOKISKI", 3000);
    define("MAX_POSTS_PER_QUERY", 10);

    define("NOTIFICATION_MENCION", 1);
    define("NOTIFICATION_VOCIFERADO", 2);
    define("NOTIFICATION_FAVORITO", 3);
    define("NOTIFICATION_RESPONDIDO", 4);
    define("NOTIFICATION_MEDALLA", 5);

    define("TAREA_TODAS", 1);
    define("TAREA_VER_NOTIFICACIONES", 2);
    define("TAREA_PUBLICAR", 3);
    define("TAREA_RESPONDER", 4);
    define("TAREA_MENCIONAR", 5);
    define("TAREA_VOCIFERAR", 6);
    define("TAREA_FAVORITO", 7);

    define("MONEDAS_TAREA", [
        TAREA_TODAS => 40,
        TAREA_VER_NOTIFICACIONES => 10,
        TAREA_PUBLICAR => 10,
        TAREA_RESPONDER => 10,
        TAREA_MENCIONAR => 10,
        TAREA_VOCIFERAR => 10,
        TAREA_FAVORITO => 10
    ]);

    define("PRODUCTO_100MONEDAS", 1);

    if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'){
        define("FFMPEG_BIN", "C:\\Program Files\\ffmpeg\\ffmpeg.exe");
        define("FFPROBE_BIN", "C:\\Program Files\\ffmpeg\\ffprobe.exe");
	}else{
        define("FFMPEG_BIN", "/usr/bin/ffmpeg");
        define("FFPROBE_BIN", "/usr/bin/ffprobe");
	}


    function conn(){
        return new mysqli(SQL_HOST, SQL_USER, SQL_PASS, SQL_BASE);
    }

    function func_get_args_names($func) {
        $f = new ReflectionFunction($func);
        $result = array();
        foreach ($f->getParameters() as $param) {
            $result[] = $param->name;
        }
        return $result;
    }

    function safeString($string){
		$no_permitidas =array ("á","é","í","ó","ú","Á","É","Í","Ó","Ú","ñ","Ñ","à","è","ì","ò","ù","À","È","Ì","Ò","Ù","ä","ë","ï","ö","ü","Ä","Ë","Ï","Ö","Ü","ç","Ç");
		$permitidas = 	array ("a","e","i","o","u","A","E","I","O","U","n","N","a","e","i","o","u","A","E","I","O","U","a","e","i","o","u","A","E","I","O","U","c","C");
		$string = str_replace($no_permitidas, $permitidas ,$string);
		$string = str_replace(' ', '-', $string);
	   	return preg_replace('/[^A-Za-z0-9\-]/', '', $string);
	}
    function generateRandomCode(){
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < 8; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
    if (!function_exists('str_starts_with')) {
        function str_starts_with($haystack, $needle) {
            return (string)$needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
        }
    }
    function array_disect($array, $key){
        $res = [];
        foreach($array as $element){
            $res[] = $element[$key];
        }
        return $res;
    }
    function date2path($date){
        if(str_contains($date, " ")) $date = explode(" ", $date)[0];
        $date = explode("-", $date);
        return $date[0]."/".$date[1]."/".$date[2];
    }
    function tmpfilepath(){
        $tmpHandle = tmpfile();
        $metaDatas = stream_get_meta_data($tmpHandle);
        $tmpFilename = $metaDatas['uri'];
        fclose($tmpHandle);
        return $tmpFilename;
    }
    function randomFileFromDir($dir, $filter = "*.*"){
        $files = glob($dir.'/'.$filter);
        $file = array_rand($files);
        return $files[$file];
    }
    function extractAts($text){
        $ats = [];
        $parts = explode("@", $text);
        array_shift($parts);
        foreach($parts as $part){
            $words = explode(" ", $part);
            if($words[0] != "") $ats[] = $words[0];
        }
        return $ats;
    }



    function sendMail($email, $subject, $body){
        $mail = new PHPMailer;
		$mail->isSMTP();
		$mail->SMTPDebug = SMTP::DEBUG_OFF;
		$mail->Host = MAIL_HOST;
		$mail->Port = MAIL_PORT;
		$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
		$mail->SMTPAuth = true;
		$mail->isHTML(true);
		$mail->CharSet = 'UTF-8';

		$mail->Username = MAIL_FROM;
		$mail->Password = MAIL_FROM_PASS;

		$mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
		$mail->addReplyTo(MAIL_ADMIN, "B0ve Social");
		$mail->addAddress($email);

		$mail->Subject = $subject;
		$mail->Body = $body;

		$res = $mail->send();
		//echo 'Mailer Error: '. $mail->ErrorInfo;
		return $res;
    }
    function composeDateIntervalWithDivides($intervalo, $fechaName){
        $group = "GROUP BY ";
        $select = "";
        $divName = "";
        
        if($intervalo === null) $intervalo = "week";
        switch($intervalo){
            case "year":
                $group .= "YEAR(`$fechaName`)";
                $select = "YEAR(`$fechaName`) as ";
                $divName = "ano";
            break;
            case "month":
                $group .= "YEAR($fechaName), MONTH($fechaName)";
                $select = "CONCAT(MONTH($fechaName), '/', YEAR($fechaName)) as ";
                $divName = "mes";
            break;
            case "week":
                $group .= "YEAR($fechaName), WEEK($fechaName)";
                $select = "CONCAT(WEEK($fechaName), '/', YEAR($fechaName)) as ";
                $divName = "semana";
            break;
            case "day":
                $group .= "YEAR($fechaName), MONTH($fechaName), DAY($fechaName)";
                $select = "CONCAT(DAY($fechaName), '/', MONTH($fechaName), '/', YEAR($fechaName)) as ";
                $divName = "dia";
            break;
        }
        $select .= "`$divName`";

        return [$group, $select, $divName];
    }
    function tabularEstadisticas($res, $statName, $divName){
        $estadistica = [];
        $times = [];
        $header = [$divName];
        while($row = $res->fetch_assoc()){
            $times[$row[$divName]][] = $row;
        }

        foreach($times as $time => $rows){
            $estRow = [$time];
            foreach($rows as $row){
                $head = "";
                foreach($row as $key => $elem){
                    if($key != $statName && $key != $divName && substr($key, 0, 2) != "id"){
                        $head .= $elem." ";
                    }
                }
                if($head == "") $head = "Todo";
                $pos = array_search($head, $header);
                if($pos === false){
                    $header[] = $head;
                    $pos = count($header)-1;
                }
                $estRow[$pos] = intval($row[$statName]);
            }
            $estadistica[] = $estRow;
        }
        //var_dump($estadistica);
        $n = count($header);
        foreach($estadistica as $key => $stat){
            for($i = 1; $i < $n; $i++){
                if(!isset($stat[$i])){
                    $estadistica[$key][$i] = 0;
                }
            }
            ksort($estadistica[$key]);
        }

        return array_merge([$header], $estadistica);
    }
    function sendMailActivation($email, $usuario, $codigo){
        sendMail($email, "B0vE Social: Codigo de activación", "
            <html xmlns=\"http://www.w3.org/1999/xhtml\" xmlns:v=\"urn:schemas-microsoft-com:vml\" xmlns:o=\"urn:schemas-microsoft-com:office:office\">
            <head>
                <meta http-equiv=\"Content-type\" content=\"text/html; charset=utf-8\" />
                <meta name=\"viewport\" content=\"width=device-width, initial-scale=1, maximum-scale=1\" />
                <meta http-equiv=\"X-UA-Compatible\" content=\"IE=edge\" />
                <meta name=\"format-detection\" content=\"date=no\" />
                <meta name=\"format-detection\" content=\"address=no\" />
                <meta name=\"format-detection\" content=\"telephone=no\" />
                <meta name=\"x-apple-disable-message-reformatting\" />
                <title>Tu cuenta de Steam: acceso desde un nuevo navegador web o dispositivo móvil</title>
                <style type=\"text/css\" media=\"screen\">
                    @font-face {
                        font-family: 'Motiva Sans';
                        font-style: normal;
                        font-weight: 300;
                        src: local('Motiva Sans'), url('https://store.cloudflare.steamstatic.com/public/shared/fonts/email/MotivaSans-Light.woff') format('woff');
                    }

                    @font-face {
                        font-family: 'Motiva Sans';
                        font-style: normal;
                        font-weight: normal;
                        src: local('Motiva Sans'), url('https://store.cloudflare.steamstatic.com//public/shared/fonts/email/MotivaSans-Regular.woff') format('woff');
                    }

                    @font-face {
                        font-family: 'Motiva Sans';
                        font-style: normal;
                        font-weight: bold;
                        src: local('Motiva Sans'), url('https://store.cloudflare.steamstatic.com//public/shared/fonts/email/MotivaSans-Bold.woff') format('woff');
                    }
                </style>

                <style type=\"text/css\" media=\"screen\">
                    body { padding:0 !important; margin:0 auto !important; display:block !important; min-width:100% !important; width:100% !important; background:#ffffff; -webkit-text-size-adjust:none }
                    a { color:#3999ec; text-decoration:underline }
                    body a { color:#ffffff; text-decoration:underline }
                    img { margin: 0 !important; -ms-interpolation-mode: bicubic; }

                        table { mso-table-lspace:0pt; mso-table-rspace:0pt; }
                        img, a img{ border:0; outline:none; text-decoration:none; }
                        #outlook a { padding:0; }
                        .ReadMsgBody { width:100%; }
                        .ExternalClass { width:100%; }
                        div,p,a,li,td,blockquote { mso-line-height-rule:exactly; }
                        a[href^=tel],a[href^=sms] { color:inherit; text-decoration:none; }
                        .ExternalClass, .ExternalClass p, .ExternalClass td, .ExternalClass div, .ExternalClass span, .ExternalClass font { line-height:100%; }

                    a[x-apple-data-detectors] { color: inherit !important; text-decoration: inherit !important; font-size: inherit !important; font-family: inherit !important; font-weight: inherit !important; line-height: inherit !important; }

                    .btn-18 a { display: block; padding: 13px 35px; text-decoration: none; }

                    .l-white a { color: #ffffff; }
                    .l-black a { color: #000001; }
                    .l-grey1 a { color: #dbdee2; }
                    .l-grey2 a { color: #a1a2a4; }
                    .l-grey3 a { color: #dadcdd; }
                    .l-grey4 a { color: #f1f1f1; }
                    .l-grey5 a { color: #dddedf; }
                    .l-grey6 a { color: #bfbfbf; }
                    .l-grey7 a { color: #dcdddd; }
                    .l-grey8 a { color: #8e96a4; }
                    .l-green a { color: #a4d007; }
                    .l-blue a { color: #6a7c96; }
                    .l-blue1 a { color: #3999ec; }
                    .l-blue2 a { color: #9eb8cc; }


                    @media only screen and (max-device-width: 480px), only screen and (max-width: 480px) {
                        .mpy-35 { padding-top: 35px !important; padding-bottom: 35px !important; }

                        .mpx-15 { padding-left: 15px !important; padding-right: 15px !important; }

                        .mpx-20 { padding-left: 20px !important; padding-right: 20px !important; }

                        .mpb-30 { padding-bottom: 30px !important; }

                        .mpb-10 { padding-bottom: 10px !important; }

                        .mpb-15 { padding-bottom: 15px !important; }

                        .mpb-20 { padding-bottom: 20px !important; }

                        .mpb-35 { padding-bottom: 35px !important; }

                        .mpb-40 { padding-bottom: 40px !important; }

                        .mpb-50 { padding-bottom: 50px !important; }

                        .mpb-60 { padding-bottom: 60px !important; }

                        .mpt-30 { padding-top: 30px !important; }

                        .mpt-40 { padding-top: 40px !important; }

                        .mpy-40 { padding-top: 40px !important; padding-bottom: 40px !important; }

                        .mpt-0 { padding-top: 0px !important; }

                        .mpr-0 { padding-right: 0px !important; }

                        .mfz-14 { font-size: 14px !important; }

                        .mfz-28 { font-size: 28px !important; }

                        .mfz-16 { font-size: 16px !important; }

                        .mfz-24 { font-size: 24px !important; }

                        .mlh-18 { line-height: 18px !important; }

                        u + body .gwfw { width:100% !important; width:100vw !important; }

                        .td,
                        .m-shell { width: 100% !important; min-width: 100% !important; }

                        .mt-left { text-align: left !important; }
                        .mt-center { text-align: center !important; }
                        .mt-right { text-align: right !important; }

                        .m-left { text-align: left !important; }
                        .me-left { margin-right: auto !important; }
                        .me-center { margin: 0 auto !important; }
                        .me-right { margin-left: auto !important; }

                        .mh-auto { height: auto !important; }
                        .mw-auto { width: auto !important; }

                        .fluid-img img { width: 100% !important; max-width: 100% !important; height: auto !important; }

                        .column,
                        .column-top,
                        .column-dir,
                        .column-dir-top { float: left !important; width: 100% !important; display: block !important; }

                        .kmMobileStretch { float: left !important; width: 100% !important; display: block !important; padding-left: 0 !important; padding-right: 0 !important; }

                        .m-hide { display: none !important; width: 0 !important; height: 0 !important; font-size: 0 !important; line-height: 0 !important; min-height: 0 !important; }
                        .m-block { display: block !important; }

                        .mw-15 { width: 15px !important; }

                        .mw-2p { width: 2% !important; }
                        .mw-32p { width: 32% !important; }
                        .mw-49p { width: 49% !important; }
                        .mw-50p { width: 50% !important; }
                        .mw-100p { width: 100% !important; }

                        .mbgs-200p { background-size: 200% auto !important; }
                    }
                </style>
            </head>
            <body class=\"body\" style=\"padding:0 !important; margin:0 auto !important; display:block !important; min-width:100% !important; width:100% !important; background:#ffffff; -webkit-text-size-adjust:none;\">
                <center>
                    <table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"style=\"margin: 0; padding: 0; width: 100%; height: 100%;\" bgcolor=\"#ffffff\" class=\"gwfw\">
                        <tr>
                            <td style=\"margin: 0; padding: 0; width: 100%; height: 100%;\" align=\"center\" valign=\"top\">
                                <table width=\"775\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"class=\"m-shell\">
                                    <tr>
                                        <td class=\"td\" style=\"width:775px; min-width:775px; font-size:0pt; line-height:0pt; padding:0; margin:0; font-weight:normal;\">
                                            <table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">
                                                <tr>
                                                    <td class=\"p-80 mpy-35 mpx-15\" bgcolor=\"#212429\" style=\"padding: 80px;\">
                                                        <table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">
                                                            <tr>
                                                                <td class=\"img pb-45\" style=\"font-size:0pt; line-height:0pt; text-align:left; padding-bottom: 45px;\">
                                                                    <a href=\"https://b0ve.com/\" target=\"_blank\">
                                                                        <img src=\"https://b0ve.com/assets/img/profile.png\" width=\"100\" height=\"100\" border=\"0\" alt=\"B0vE\" />
                                                                    </a>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    <table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">
                                                                        <tr>
                                                                            <td class=\"title-36 pb-30 c-grey6 fw-b\" style=\"font-size:36px; line-height:42px; font-family:'Motiva Sans', Helvetica, Arial, sans-serif; text-align:left; padding-bottom: 30px; color:#bfbfbf; font-weight:bold;\">
                                                                                <span style=\"color: #77b9ee;\">
                                                                                    @$usuario
                                                                                </span>
                                                                            </td>
                                                                        </tr>
                                                                    </table>
                                                                    <table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">
                                                                        <tr>
                                                                            <td class=\"text-18 c-grey4 pb-30\" style=\"font-size:18px; line-height:25px; font-family:'Motiva Sans', Helvetica, Arial, sans-serif; text-align:left; color:#dbdbdb; padding-bottom: 30px;\">
                                                                                Activa tu cuenta de B0vE Social con el siguiente código.
                                                                            </td>
                                                                        </tr>
                                                                    </table>
                                                                    <table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">
                                                                        <tr>
                                                                            <td class=\"pb-70 mpb-50\" style=\"padding-bottom: 70px;\">
                                                                                <table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"bgcolor=\"#17191c\">
                                                                                    <tr>
                                                                                        <td class=\"py-30 px-56\" style=\"padding-top: 30px; padding-bottom: 30px; padding-left: 56px; padding-right: 56px;\">
                                                                                            <table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">
                                                                                                <tr>
                                                                                                    <td style=\"font-size:18px; line-height:25px; font-family:'Motiva Sans', Helvetica, Arial, sans-serif; color:#8f98a0; text-align:center;\">
                                                                                                        Codigo de activación
                                                                                                    </td>
                                                                                                </tr>
                                                                                                <tr>
                                                                                                    <td style=\"padding-bottom: 16px\"></td>
                                                                                                </tr>
                                                                                                <tr>
                                                                                                    <td class=\"title-48 c-blue1 fw-b a-center\" style=\"font-size:48px; line-height:52px; font-family:'Motiva Sans', Helvetica, Arial, sans-serif; color:#3a9aed; font-weight:bold; text-align:center;\">
                                                                                                        $codigo
                                                                                                    </td>
                                                                                                </tr>
                                                                                            </table>
                                                                                        </td>
                                                                                    </tr>
                                                                                </table>
                                                                            </td>
                                                                        </tr>
                                                                    </table>
                                                                    <table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">
                                                                        <tr>
                                                                            <td class=\"pt-30\" style=\"padding-top: 30px;\">
                                                                                <table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">
                                                                                    <tr>
                                                                                        <td class=\"img\" width=\"3\" bgcolor=\"#3a9aed\" style=\"font-size:0pt; line-height:0pt; text-align:left;\"></td>
                                                                                        <td class=\"img\" width=\"37\" style=\"font-size:0pt; line-height:0pt; text-align:left;\"></td>
                                                                                        <td>
                                                                                            <table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">
                                                                                                <tr>
                                                                                                    <td class=\"text-16 py-20 c-grey4 fallback-font\" style=\"font-size:16px; line-height:22px; font-family:'Motiva Sans', Helvetica, Arial, sans-serif; text-align:left; padding-top: 20px; padding-bottom: 20px; color:#f1f1f1;\">
                                                                                                        Saludos,
                                                                                                        <br />
                                                                                                        B0vE
                                                                                                    </td>
                                                                                                </tr>
                                                                                            </table>
                                                                                        </td>
                                                                                    </tr>
                                                                                </table>
                                                                            </td>
                                                                        </tr>
                                                                    </table>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </center>
            </body>
            </html>
        ");
    }
    function sendMailCompra($euros){
        $monedas = $euros*100;
        sendMail(MAIL_ADMIN, "B0vE Social: Compra", "
            Un usuario ha comprado $monedas monedas ($euros €)
        ");
    }
?>