import CompLoader from "./compLoader.js";
import API from "./api.js";

String.prototype.search = function(pat, n = 1) {
    let str = this.toString();
    let L = str.length, i= -1;
    while(n-- && i++<L){
        i= str.indexOf(pat, i);
        if (i < 0) break;
    }
    return i;
}

var preloadAction = null;

var msgModal, msgTitle, msgBody, msgTextarea;
var confModal, confTitle, confBody, confCancelBTN, confBTN, confSpinner;
var selModal;
var multiSelModal;
let modalsDiv = document.createElement("div");
var globalWaiting;
document.body.appendChild(modalsDiv);
CompLoader.loadComp("helpexModals", modalsDiv)
.then(() => {
    msgModal = document.getElementById("msgModal");
    msgTitle = document.getElementById("msgTitle");
    msgBody = document.getElementById("msgBody");
    msgTextarea = document.getElementById("msgTextarea");

    confModal = document.getElementById("confModal");
    confTitle = document.getElementById("confTitle");
    confBody = document.getElementById("confBody");
    confCancelBTN = document.getElementById("confCancelBTN");
    confBTN = document.getElementById("confBTN");
    confSpinner = document.getElementById("confSpinner");

    selModal = document.getElementById("selModal");

    multiSelModal = document.getElementById("multiSelModal");

    globalWaiting = document.getElementById("globalWaiting");

    if(preloadAction !== null){
        preloadAction();
        preloadAction = null;
    }
});

function showMSG(titulo, cuerpo, log = null){
    try{
        msgTitle.innerText = titulo;
        msgBody.innerText = cuerpo;
        if(log === null){
            msgTextarea.classList.add("visually-hidden");
        }else{
            console.log(log);
            msgTextarea.value = log;
            msgTextarea.classList.remove("visually-hidden");
        }
        hideAllModals(null, msgModal);
    }catch(e){
        console.log(e);
        console.log(log);
    }
}
function globalLoaderShow(text = null){
    if(text === null){
        globalWaiting.firstElementChild.classList.add("visually-hidden");
    }else{
        globalWaiting.firstElementChild.classList.remove("visually-hidden");
        globalWaiting.firstElementChild.innerText = text;
    }
    globalWaiting.classList.remove("visually-hidden");
}
function globalLoaderHide(){
    globalWaiting.classList.add("visually-hidden");
}
function hideAllModals(except = null, open = null){
    if(except !== null && except instanceof Element)
        except = except.id;
    if(open !== null && !(open instanceof Element))
        open = document.getElementById("open");
    
    let timeout;
    if(document.querySelector(".modal.show") === null){
        timeout = 1;
    }else{
        timeout = 500;
        globalLoaderShow();
    }
    setTimeout(() => {
        globalLoaderHide();
        let modals = document.getElementsByClassName("modal");
        for(let i = 0; i < modals.length; i++){
            if(modals[i].id != except) bootstrap.Modal.getOrCreateInstance(modals[i]).hide();
        }
        if(open != null){
            let modal = bootstrap.Modal.getOrCreateInstance(open);
            modal.show();
            let videos = document.getElementsByTagName("video");
            for(let i = 0; i < videos.length; i++){
                videos[i].pause()
            }
            open.addEventListener('hidden.bs.modal', () => {
                let videos = open.getElementsByTagName("video");
                for(let i = 0; i < videos.length; i++){
                    videos[i].pause()
                }
            });
        }
    }, timeout);
}

let meses = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];

function addClases (element, clases) {
    clases.split(" ").forEach(clas => {
        element.classList.add(clas);
    });
}
function createElement (type, clases = null, text = null) {
    let e = document.createElement(type);
    if(clases !== null) addClases(e, clases);
    if(text !== null) e.innerText = text;
    return e;
}

export default {
    requireAdmin: () => {
        API.getAdminIndexInfo()
        .catch(error => {
            if(error == "Insufficient permission level") location.href = "login.html";
        });
    },
    logOutAdmin: () => {
        API.clearAdminCredentials();
        location.href = "login.html";
    },
    showMSG,
    showConf: (titulo, cuerpo, requiresLoading = false) => {
        confTitle.innerText = titulo;
        confBody.innerText = cuerpo;
        confBTN.disabled = false;
        confSpinner.classList.add("visually-hidden");
        hideAllModals(null, confModal);
        return new Promise((resolve, reject) => {
            confBTN.onclick = () => {
                if(requiresLoading){
                    confBTN.disabled = true;
                    confSpinner.classList.remove("visually-hidden");
                }else{
                    hideAllModals();
                }
                resolve();
            };
            confCancelBTN.onclick = () => {
                hideAllModals();
                reject();
            };
        });
    },
    showConfStopLoading: () => {
        confBTN.disabled = false;
        confSpinner.classList.add("visually-hidden");
        hideAllModals();
    },
    generalUnespectedError: error => {
        showMSG("Error en el proceso", "La acción no terminó satisfactoriamente debido al siguiente error:", error);
    },
    hideAllModals,
    isMobile: () => {
        let check = false;
        (function(a){if(/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino|android|ipad|playbook|silk/i.test(a)||/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(a.substr(0,4))) check = true;})(navigator.userAgent||navigator.vendor||window.opera);
        return check;
    },
    removeAllChilds: element => {
        while (element.firstChild) element.removeChild(element.lastChild)
    },
    addClases,
    createElement,
    globalLoaderShow,
    globalLoaderHide,
    sGet: (parameterName) =>{
        var result = null, tmp = [];
        location.search
            .substr(1)
            .split("&")
            .forEach(function (item) {
                tmp = item.split("=");
                if (tmp[0] === parameterName) result = decodeURIComponent(tmp[1]);
            });
        return result;
    },
    getLocationFileName: () =>{
        return location.pathname.split("/").pop().split(".").shift();
    },
    createSelectOption: (value, text) => {
        let opt = document.createElement("option");
        opt.value = value;
        opt.text = text;
        return opt;
    },
    cacheBreak: url => url+"?"+(new Date().getTime()),
    arrayDisect: (array, key) => {
        let res = [];
        array.forEach(e => {
            res.push(e[key]);
        });
        return res;
    },
    selectRandom: (array) => array[Math.floor(Math.random() * array.length)],
    sendClick: (element, event = "click") => {
        element.dispatchEvent(new Event(event));
    },
    isoDate2esp: iso => `${iso.substring(11, 16)} ${iso.substring(8, 10)}/${iso.substring(5, 7)}/${iso.substring(0, 4)}`,
    isoDate2textEsp: iso => `${iso.substring(8, 10)} de ${meses[Math.floor(iso.substring(5, 7))-1].toLowerCase()} ${iso.substring(0, 4)}`,
    locale: {
        meses
    },
    makeFontAwsome: (type, icon) => {
        let iElement = document.createElement("i");
        iElement.classList.add(type);
        iElement.classList.add(icon);
        return iElement;
    },
    trim: (s, c) => {
        if (c === "]") c = "\\]";
        if (c === "^") c = "\\^";
        if (c === "\\") c = "\\\\";
        return s.replace(new RegExp(
            "^[" + c + "]+|[" + c + "]+$", "g"
        ), "");
    },
    setCookie: (cname, cvalue, exdays = 365) => {
        var d = new Date();
        d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
        var expires = "expires=" + d.toUTCString();
        document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
    },
    getCookie: (cname) => {
        var name = cname + "=";
        var decodedCookie = decodeURIComponent(document.cookie);
        var ca = decodedCookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) == ' ') {
                c = c.substring(1);
            }
            if (c.indexOf(name) == 0) {
                return c.substring(name.length, c.length);
            }
        }
        return null;
    },
    setPreloadAction: preload => preloadAction = preload 
}