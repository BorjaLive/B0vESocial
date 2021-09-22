import HELPEX from "./helpex.js";
import {SERVER} from "./constants.js";

const API_ENDPOINT = SERVER+"api/";
const API_FILE_ENDPOINT = API_ENDPOINT + "file.php";

function fetchAPI(data) {
    data.user = HELPEX.getCookie("socialUser");
    data.pass = HELPEX.getCookie("socialPass");
    data.adminCode = HELPEX.getCookie("socialAdmin");

    return new Promise((resolve, reject) => {
        fetch(API_ENDPOINT, {
            method: 'POST',
            cache: 'no-cache',
            headers: {
                'Content-Type': 'application/json'
            },
            redirect: 'follow',
            body: JSON.stringify(data)
        })
            .then(response => response.text())
            .then(data => {
                try {
                    data = JSON.parse(data);
                } catch (error) {
                    reject(data);
                }
                if (data.status == "success")
                    resolve(data.msg);
                else
                    reject(data.msg.error);
            })
            .catch(error => {
                reject(error);
            });
    });
}

function fetchFileAPI(properties, progressCallback) {
    var data = new FormData();

    Object.entries(properties).forEach(([key, value]) => {
        data.append(key, value);
    });
    data.append("user", HELPEX.getCookie("socialUser"));
    data.append("pass", HELPEX.getCookie("socialPass"));
    data.append("adminCode", HELPEX.getCookie("socialAdmin"));

    return new Promise((resolve, reject) => {
        let request = new XMLHttpRequest();
        request.open('POST', API_FILE_ENDPOINT);

        if(progressCallback != null){
            request.upload.addEventListener('progress', function (e) {
                progressCallback((e.loaded / e.total) * 100);
            });
        }

        request.addEventListener('load', function (e) {
            if (request.status == 200) {
                try {
                    data = JSON.parse(request.response);
                } catch (error) {
                    reject(request.response);
                }
                if (data.status == "success")
                    resolve(data.msg);
                else
                    reject(data.msg.error);
            } else {
                reject(request.status);
            }
        });

        request.send(data);
    });
}

export default {
    setUserCredentials: (user, pass) => {
        HELPEX.setCookie("socialUser", user);
        HELPEX.setCookie("socialPass", pass);
    },
    clearUserCredentials: () => {
        HELPEX.setCookie("socialUser", "null");
        HELPEX.setCookie("socialPass", "null");
    },
    setAdminCredentials: (code) => {
        HELPEX.setCookie("socialAdmin", code);
    },
    clearAdminCredentials: () => {
        HELPEX.setCookie("socialAdmin", "null");
    },

    genericFetch: (data) => fetchAPI(data),

    test: (usuario) => fetchAPI({
        action: "test",
        usuario
    }),

    registerUser: (nombre, password, email) => fetchAPI({
        action: "registerUser",
        nombre, password, email
    }),
    resendUserActivationMail: () => fetchAPI({
        action: "resendUserActivationMail"
    }),
    activateUser: (codigo) => fetchAPI({
        action: "activateUser",
        codigo
    }),

    getUser: (usuario = null) => fetchAPI({
        action: "getPublicUser",
        usuario
    }),
    getUserData: (usuario = null) => fetchAPI({
        action: "getPublicUserData",
        usuario
    }),
    getPost: (id, completo = false) => fetchAPI({
        action: "getPost",
        id, completo
    }),
    getPosts: (usuario, foto = null, video = null, inicio = 0) => fetchAPI({
        action: "getPosts",
        usuario, foto, video, inicio
    }),
    getVociferados: (usuario, foto = null, video = null, inicio = 0) => fetchAPI({
        action: "getVociferados",
        usuario, foto, video, inicio
    }),
    getFavoritos: (usuario, foto = null, video = null, inicio = 0) => fetchAPI({
        action: "getFavoritos",
        usuario, foto, video, inicio
    }),
    getFeed: (inicio) => fetchAPI({
        action: "getFeed",
        inicio
    }),
    getAcechados: (id = null) => fetchAPI({
        action: "getAcechados",
        id
    }),
    getAcechadores: (id = null) => fetchAPI({
        action: "getAcechadores",
        id
    }),

    updateUserData: (nombre, nacimiento, sexo, estado, descripcion) => fetchAPI({
        action: "updateUserData",
        nombre, nacimiento, sexo, estado, descripcion
    }),
    deleteProfilePic: () => fetchAPI({
        action: "deleteProfilePic"
    }),

    deletePost: (post) => fetchAPI({
        action: "deletePost",
        post
    }),
    doFavorito: (post) => fetchAPI({
        action: "doFavorito",
        post
    }),
    doVociferar: (post) => fetchAPI({
        action: "doVociferar",
        post
    }),
    doAcechar: (usuario) => fetchAPI({
        action: "doAcechar",
        usuario
    }),

    getEstados: () => fetchAPI({
        action: "getEstados"
    }),
    getSexos: () => fetchAPI({
        action: "getSexos"
    }),

    useCodigoMoneda: (codigo) => fetchAPI({
        action: "useCodigoMoneda",
        codigo
    }),
    comprarTokiski: () => fetchAPI({
        action: "comprarTokiski"
    }),

    getAdminIndexInfo: () => fetchAPI({
        action: "getAdminIndexInfo"
    }),
    createCodigoMoneda: (valor, cantidad) => fetchAPI({
        action: "createCodigoMoneda",
        valor, cantidad
    }),
    deleteCodigoMoneda: (codigo) => fetchAPI({
        action: "deleteCodigoMoneda",
        codigo
    }),
    createEstado: (estado) => fetchAPI({
        action: "createEstado",
        estado
    }),
    deleteEstado: (estado) => fetchAPI({
        action: "deleteEstado",
        estado
    }),
    editEstado: (estado, nuevo) => fetchAPI({
        action: "editEstado",
        estado, nuevo
    }),
    createSexo: (sexo) => fetchAPI({
        action: "createSexo",
        sexo
    }),
    deleteSexo: (sexo) => fetchAPI({
        action: "deleteSexo",
        sexo
    }),
    editSexo: (sexo, nuevo) => fetchAPI({
        action: "editSexo",
        sexo, nuevo
    }),


    changeProfilePic: (processCallback, file) => fetchFileAPI({
        action: "changeProfilePic",
        file
    }, processCallback),
    createPost: (texto, processCallback, padre = null) => fetchFileAPI({
        action: "createPost",
        texto, padre
    }, processCallback),
    createPostImage: (texto, file, processCallback, padre = null) => fetchFileAPI({
        action: "createPostImage",
        file, texto, padre
    }, processCallback),
    createPostVideo: (texto, file, processCallback, padre = null) => fetchFileAPI({
        action: "createPostVideo",
        file, texto, padre
    }, processCallback)
}
