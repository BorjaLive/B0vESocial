import HELPEX from "./helpex.js";
import CompLoader from "./compLoader.js";
import API from "./api.js";
import { NOTIFICATION, TAREA, TAREA_ID } from "./constants.js";

var postModal;
var pModal = {};
var pModalElements = ["Header", "ProfilePic", "Name", "User", "Content", "Text", "Image", "Video", "Spinner", "Fecha", "Footer", "ComentBtn", "Responses", "ResponsesText", "Father", "FatherPost", "Medallas", "VociferarBtn", "VociferarIcon", "VociferarSpinner", "VociferarCounter", "FavoritoBtn", "FavoritoIcon", "FavoritoSpinner", "FavoritoCounter", "MedallaBtn", "MedallaIcon", "MedallaSpinner", "EliminarBtn", "EliminarIcon", "EliminarSpinner"];
var userListModal, userListModalTitle, userListModalBody, medallaListModal, medallaListModalBody;
let modalsDiv = document.createElement("div");
document.body.appendChild(modalsDiv);
CompLoader.loadComp("socialDrawer", modalsDiv)
.then(() => {
    postModal = document.getElementById("postModal");
    pModalElements.forEach(e => pModal[e] = document.getElementById("postModal"+e));
    userListModal = document.getElementById("userListModal");
    userListModalTitle = document.getElementById("userListModalTitle");
    userListModalBody = document.getElementById("userListModalBody");
    medallaListModal = document.getElementById("medallaListModal");
    medallaListModalBody = document.getElementById("medallaListModalBody");
});

function stylizeTest(text){
    let parts = text.split("@");
    parts.shift();
    let n = {}
    parts.forEach(p => {
        let palabra = p.split(" ")[0];
        if(n[palabra] === undefined) n[palabra] = 1;
        let pos = text.search(p, n[palabra]);
        let left = text.substring(0, pos-1);
        let right = "";
        let rightPos = text.substring(pos).search(" ");
        if(rightPos != -1)
            right = text.substring(pos+rightPos);
        text = left + `<a href="user.html?sid=${HELPEX.trim(palabra, ".")}">@${palabra}</a>` + right;
        n[palabra]++;
    });
    return text;
}

function drawUserSection(usuario){
    let userSection = document.createElement("a");
    userSection.style.textDecoration = "none";
    userSection.href = "user.html?sid="+usuario.usuario;
    HELPEX.addClases(userSection, "d-flex flex-row justify-content-start align-items-center text-light");
    let userPic = document.createElement("img");
    userPic.src = usuario.profilePic;
    HELPEX.addClases(userPic, "profile-pic mx-3");
    let userName = document.createElement("h4");
    userName.innerText = usuario.nombre;
    let userRealName = document.createElement("spam");
    HELPEX.addClases(userRealName, "ms-2 text-secondary");
    userRealName.innerText = `(@${usuario.usuario})`;
    userSection.appendChild(userPic);
    userSection.appendChild(userName);
    userSection.appendChild(userRealName);
    return userSection;
}
function drawPostSection(post, refreshAction = null){
    let postSection = document.createElement("div");
    HELPEX.addClases(postSection, "d-flex flex-column px-2 mb-1");
    let postText = document.createElement("spam");
    HELPEX.addClases(postText, "fs-6 my-1");
    postText.innerHTML = stylizeTest(post.texto);
    postSection.appendChild(postText);
    if(post.foto !== null){
        let postImg = document.createElement("img");
        HELPEX.addClases(postImg, "rounded align-self-center");
        postImg.src = post.foto;
        postImg.style.width = "80%";
        postSection.appendChild(postImg);
    }
    if(post.video !== null){
        let postVideo = document.createElement("video");
        postVideo.controls = true;
        HELPEX.addClases(postVideo, "rounded align-self-center");
        postVideo.src = post.video;
        postVideo.style.width = "90%";
        postSection.appendChild(postVideo);
    }
    postSection.style.cursor = "pointer";
    postSection.addEventListener("click", () => {
        pModal.Header.classList.add("visually-hidden");
        pModal.Father.classList.add("visually-hidden");
        pModal.Content.classList.add("visually-hidden");
        pModal.Footer.classList.add("visually-hidden");
        pModal.Spinner.classList.remove("visually-hidden");
        
        HELPEX.hideAllModals(postModal, postModal);

        API.getPost(post.id, true)
        .then(post => openPost(post, pModal, refreshAction))
        .catch(error => HELPEX.showMSG("Error al abrir publicación", "No se ha podido obtener la publicación", error));
    });
    
    if(post.medallas !== undefined && post.medallas.length > 0){
        let medallasDiv = HELPEX.createElement("div", "d-flex flex-row flex-wrap justify-content-center align-items-start mt-1 pt-2 border-top border-secondary");
        post.medallas.forEach(medalla => {
            if(medalla instanceof Object) medalla = medalla.icon;
            let img = HELPEX.createElement("img", "medalla mx-2");
            img.src = medalla;
            medallasDiv.appendChild(img);
        })
        postSection.appendChild(medallasDiv);
    }

    return postSection;
}
function drawPost(data, refreshAction = null){
    let container = document.createElement("div");
    HELPEX.addClases(container, "d-flex flex-column p-1 my-1 rounded border border-secondary");

    let userSection = drawUserSection(data.usuario);
    let postSection = drawPostSection(data, refreshAction);

    container.appendChild(userSection);
    container.appendChild(smallHR());
    container.appendChild(postSection);
    return container;
}
function drawPosts(posts, div, refreshAction = null){
    if(posts.length == 0 && div.childElementCount == 0) div.appendChild(randomEmptyFace());
    posts.forEach(post => div.appendChild(drawPost(post, refreshAction)));
}

function openPost(data, elems, refreshAction = null){
    elems.Header.style.cursor = "pointer";
    elems.Header.onclick = () => location.href = "user.html?sid="+data.usuario.usuario;

    elems.ProfilePic.src = data.usuario.profilePic;
    elems.Name.innerText = data.usuario.nombre;
    elems.User.innerText = `(@${data.usuario.usuario})`;

    elems.Text.innerHTML = stylizeTest(data.texto);
    if(data.foto !== null){
        elems.Image.src = data.foto;
        elems.Image.classList.remove("visually-hidden");
    }else elems.Image.classList.add("visually-hidden");
    if(data.video !== null){
        elems.Video.src = data.video;
        elems.Video.classList.remove("visually-hidden");
    }else elems.Video.classList.add("visually-hidden");
    elems.Fecha.innerText = HELPEX.isoDate2esp(data.fecha);

    if(data.medallas !== undefined && data.medallas.length > 0){
        HELPEX.removeAllChilds(elems.Medallas);
        data.medallas.forEach(medalla => {
            let medallaBTN = HELPEX.createElement("img", "medalla mx-2");
            medallaBTN.src = medalla.icon;
            elems.Medallas.appendChild(medallaBTN);

            if(data.usuario.id == window.user){
                medallaBTN.style.cursor = "pointer";
                medallaBTN.addEventListener("click", () => {
                    HELPEX.showConf("Apropiarse de medalla", "Confirme que desea apropiarse de esta medalla. Eso la eliminará del post pero la tendrás en posesión para darsela a otro post.")
                    .then(() => {
                        API.apropiarMedalla(medalla.id)
                        .then(() => {
                            medallaBTN.remove();
                            if(elems === pModal) HELPEX.hideAllModals(postModal, postModal);
                            if(refreshAction !== null) refreshAction();
                        })
                        .catch(HELPEX.generalUnespectedError);
                    })
                    .catch(() => {});
                });
            }

        })
        elems.Medallas.classList.remove("visually-hidden");
    }else elems.Medallas.classList.add("visually-hidden");

    elems.ResponsesText.innerText = `Respuestas (${data.respuestas.length}):`;
    if(elems === pModal)//HELPEX.getLocationFileName() == "post"
        elems.ComentBtn.onclick = () => location.href=`post.html?id=${data.id}`;
    else
        elems.ComentBtn.classList.add("visually-hidden");
    HELPEX.removeAllChilds(elems.Responses);
    data.respuestas.forEach(respuesta => elems.Responses.appendChild(drawPost(respuesta, refreshAction)));

    if(data.padre !== null){
        HELPEX.removeAllChilds(elems.FatherPost);
        elems.FatherPost.appendChild(drawPost(data.padre));
        elems.Father.classList.remove("visually-hidden");
    }else elems.Father.classList.add("visually-hidden");

    if(data.vociferado){
        elems.VociferarIcon.classList.add("text-success");
        elems.VociferarIcon.classList.remove("text-light");
    }else{
        elems.VociferarIcon.classList.add("text-light");
        elems.VociferarIcon.classList.remove("text-success");
    }
    elems.VociferarBtn.onclick = () => {
        elems.VociferarBtn.disabled = true;
        elems.VociferarSpinner.classList.remove("visually-hidden");
        elems.VociferarIcon.classList.add("visually-hidden");
        API.doVociferar(data.id)
        .then(vociferado => {
            elems.VociferarBtn.disabled = false;
            elems.VociferarSpinner.classList.add("visually-hidden");
            elems.VociferarIcon.classList.remove("visually-hidden");
            if(vociferado){
                elems.VociferarIcon.classList.add("text-success");
                elems.VociferarIcon.classList.remove("text-light");
                elems.VociferarCounter.innerText -= -1;
            }else{
                elems.VociferarIcon.classList.remove("text-success");
                elems.VociferarIcon.classList.add("text-light");
                elems.VociferarCounter.innerText -= 1;
            }
            reloadLoadTareasDiarias();
        })
        .catch(HELPEX.generalUnespectedError);
    };
    elems.VociferarCounter.innerText = data.vociferados;

    elems.FavoritoIcon.style.color = data.favorito?"gold":"#f8f9fa";
    elems.FavoritoBtn.onclick = () => {
        elems.FavoritoBtn.disabled = true;
        elems.FavoritoSpinner.classList.remove("visually-hidden");
        elems.FavoritoIcon.classList.add("visually-hidden");
        API.doFavorito(data.id)
        .then(favorito => {
            elems.FavoritoBtn.disabled = false;
            elems.FavoritoSpinner.classList.add("visually-hidden");
            elems.FavoritoIcon.classList.remove("visually-hidden");
            if(favorito){
                elems.FavoritoIcon.style.color = "gold";
                elems.FavoritoCounter.innerText -= -1;
            }else{
                elems.FavoritoIcon.style.color = "#f8f9fa";
                elems.FavoritoCounter.innerText -= 1;
            }
            reloadLoadTareasDiarias();
        })
        .catch(HELPEX.generalUnespectedError);
    };
    elems.FavoritoCounter.innerText = data.favoritos;

    //Medallas
    if(data.usuario.id == window.user)
        elems.MedallaBtn.classList.add("visually-hidden");
    else
        elems.MedallaBtn.classList.remove("visually-hidden");
    elems.MedallaBtn.onclick = () => {
        HELPEX.hideAllModals(medallaListModal, medallaListModal);
        medallaListModalBody.appendChild(HELPEX.createElement("div", "spinner-border"));
        API.getMedallas()
        .then(medallas => {
            HELPEX.removeAllChilds(medallaListModalBody);
            console.log(medallas);
            medallas.forEach(medalla => {
                let container = HELPEX.createElement("div", "d-flex flex-column justify-content-center align-items-center p-1 m-2 border border-secondary");
                let img = HELPEX.createElement("img", "medalla mx-2");
                img.src = medalla.icon;
                img.title = medalla.nombre;
                container.appendChild(img);
                container.appendChild(HELPEX.createElement("span", "fs-5", medalla.nombre));
                container.style.cursor = "pointer";
                container.addEventListener("click", () => {
                    API.darMedalla(medalla.id, data.id)
                    .then(() => {
                        let medallaNew = HELPEX.createElement("img", "medalla mx-2");
                        medallaNew.src = medalla.icon;
                        elems.Medallas.appendChild(medallaNew);
                        elems.Medallas.classList.remove("visually-hidden");
                        if(refreshAction !== null) refreshAction();
                        if(elems === pModal) HELPEX.hideAllModals(postModal, postModal);
                    })
                    .catch(HELPEX.generalUnespectedError);
                });
                medallaListModalBody.appendChild(container);
            })
        })
        .catch(HELPEX.generalUnespectedError);
    }

    if(data.usuario.id !== window.user){
        elems.EliminarBtn.classList.add("visually-hidden");
    }
    elems.EliminarBtn.onclick = () => {
        HELPEX.showConf("Eliminar publicación", "¿Seguro que deseas eliminar esta publicación?", true)
        .then(() => {
            API.deletePost(data.id)
            .then(() => {
                HELPEX.showConfStopLoading();
                if(refreshAction !== null) refreshAction();
            })
            .catch(HELPEX.generalUnespectedError);
        })
        .catch(() => {
            HELPEX.hideAllModals(null, postModal);
        });
    };
    
    elems.Header.classList.remove("visually-hidden");
    elems.Content.classList.remove("visually-hidden");
    elems.Footer.classList.remove("visually-hidden");
    elems.Spinner.classList.add("visually-hidden");
}

function displayList(res, title = "Lista de usuarios"){
    HELPEX.removeAllChilds(userListModalBody);
    let spinner = document.createElement("div");
    HELPEX.addClases(spinner, "spinner-border align-self-center");
    userListModalBody.appendChild(spinner);
    userListModalTitle.innerText = title;
    HELPEX.hideAllModals(null, userListModal);

    res.then(users => {
        let ul = document.createElement("ul");
        HELPEX.addClases(ul, "list-group");
        users.forEach(user => {
            let li = document.createElement("li");
            HELPEX.addClases(li, "list-group-item border-light d-flex flex-row justify-content-start align-items-center text-light bg-dark");
            li.style.cursor = "pointer";
            li.addEventListener("click", () => location.href = "user.html?sid="+user.usuario);

            let userPic = document.createElement("img");
            userPic.src = user.profilePic;
            HELPEX.addClases(userPic, "profile-pic mx-3");
            let nameDiv = document.createElement("div");
            HELPEX.addClases(nameDiv, "d-flex flex-column justify-content-start align-items-start");
            let userName = document.createElement("h4");
            userName.innerText = user.nombre;
            let userRealName = document.createElement("spam");
            HELPEX.addClases(userRealName, "ms-2 text-secondary");
            userRealName.innerText = `(@${user.usuario})`;
            li.appendChild(userPic);
            nameDiv.appendChild(userName);
            nameDiv.appendChild(userRealName);
            li.appendChild(nameDiv);

            ul.appendChild(li);
        });
        HELPEX.removeAllChilds(userListModalBody);
        userListModalBody.appendChild(ul);
    }).catch(HELPEX.generalUnespectedError);
}

function drawNotificacion(data){
    let container = document.createElement("div");
    HELPEX.addClases(container, "d-flex flex-column p-1 my-1 rounded border border-secondary");

    let userSection = drawUserSection(data.autor);
    let post = drawPost(data.post, null);

    let info = document.createElement("span");
    info.classList.add("mx-2");
    let infoIcon;
    switch(data.tipo){
        case NOTIFICATION.RESPONDIDO:
            info.innerText = "ha respondido a una publicación tuya.";
            infoIcon = HELPEX.makeFontAwsome("fas", "fa-feather-alt");
            post.removeChild(post.firstChild);
            post.removeChild(post.firstChild);
        break;
        case NOTIFICATION.MENCION:
            info.innerText = "te ha mencionado en una publicación.";
            infoIcon = HELPEX.makeFontAwsome("fas", "fa-feather");
            post.removeChild(post.firstChild);
            post.removeChild(post.firstChild);
        break;
        case NOTIFICATION.VOCIFERADO:
            info.innerText = "ha vociferado una publicación tuya.";
            infoIcon = HELPEX.makeFontAwsome("fas", "fa-bullhorn");
        break;
        case NOTIFICATION.FAVORITO:
            info.innerText = "le ha gustado una publicación tuya.";
            infoIcon = HELPEX.makeFontAwsome("fas", "fa-star");
        break;
        case NOTIFICATION.MEDALLA:
            info.innerText = "le ha dado una medalla a tu publicación.";
            infoIcon = HELPEX.makeFontAwsome("fas", "fa-award");
        break;
    }
    userSection.appendChild(info);
    userSection.appendChild(infoIcon);


    container.appendChild(userSection);
    container.appendChild(smallHR());
    container.appendChild(post);
    return container;
}
function drawNotificaciones(notificaciones, div){
    if(notificaciones.length == 0 && div.childElementCount == 0) div.appendChild(randomEmptyFace());
    notificaciones.forEach(notificacion => div.appendChild(drawNotificacion(notificacion)));
}

function smallHR(){
    let hr = document.createElement("hr");
    hr.style.marginTop = ".5rem";
    hr.style.marginBottom = ".2rem";
    return hr;
}
let emptyFaces = ["far fa-smile-wink", "far fa-smile-beam", "far fa-grin-squint-tears", "far fa-laugh-wink", "far fa-smile", "far fa-laugh-squint", "far fa-laugh-beam", "far fa-grin-squint-tears fa-spin"]
function randomEmptyFace(mensaje = "Nada que mostrar, ¡Ponte las pilas!"){
    let container = document.createElement("div");
    HELPEX.addClases(container, "d-flex flex-column justify-content-center align-items-center p-2");
    let face = document.createElement("i");
    HELPEX.addClases(face, HELPEX.selectRandom(emptyFaces));
    face.style.fontSize = "4rem";
    let text = document.createElement("span");
    HELPEX.addClases(text, "fs-5");
    text.innerText = mensaje;
    container.appendChild(face);
    container.appendChild(text);
    return container;
}

function loadHotKeys(div){
    CompLoader.loadComp("hotkeys", div)
    .catch(HELPEX.generalUnespectedError);
}
var drawTareasOptions = null;
function loadTareasDiarias(tareas, div, stacked = true){
    drawTareasOptions = {div, stacked};
    HELPEX.removeAllChilds(div);

    let size = stacked?6:5;
    let titulo = HELPEX.createElement("div", "d-flex flex-row flex-wrap-reverse justify-content-between align-items-center");
    titulo.appendChild(HELPEX.createElement("h4", "ms-1 mt-1", "Tareas diarias:"));
    let walletDiv = HELPEX.createElement("div", "d-flex flex-row justify-content-center align-items-center m-1 p-2 border border-secondary rounded-3");
    walletDiv.appendChild(HELPEX.createElement("span", `fs-${size} me-1`));
    walletDiv.appendChild(HELPEX.createElement("i", `icon-moneda fs-${size-1} ms-1`));
    API.getUser().then(user => walletDiv.firstElementChild.innerText = user.monedas).catch(HELPEX.generalUnespectedError);
    titulo.appendChild(walletDiv);
    div.appendChild(titulo);

    if(!stacked) div.appendChild(HELPEX.createElement("span", "p-1 m-2 border border-secondary rounded fs-7", "Completa tareas diárias para ganar monedas. Usa las monedas para comprar medallas, estampas y @tokiski."))

    Object.entries(tareas).forEach(entry => {
        let tarea = entry[0];
        let estado = entry[1];

        let tareaDiv = HELPEX.createElement("div", "d-flex flex-column justify-content-center align-items-stretch border border-secondary p-1 m-1 rounded-2");

        let mainDiv = HELPEX.createElement("div", "d-flex flex-row justify-content-between align-items-center ps-2");
        let name = HELPEX.createElement("span", "me-2");
        name.innerText = TAREA[TAREA_ID[tarea]].nombre;
        let btn = HELPEX.createElement("button", "btn");
        if(!estado.conseguido){
            btn.classList.add("btn-secondary");
            btn.disabled = true;
        }else if(estado.conseguido && !estado.cobrado){
            btn.classList.add("btn-success");
        }else if(estado.conseguido && estado.cobrado){
            btn.classList.add("btn-success");
            btn.disabled = true;
        }
        let btnSpinner = HELPEX.createElement("div", "spinner-border spinner-border-sm text-light visually-hidden");
        btn.appendChild(btnSpinner);
        btn.appendChild(HELPEX.createElement("span", "me-1", `${stacked?"":"Cobrar "}${TAREA[TAREA_ID[tarea]].monedas}`));
        btn.appendChild(HELPEX.createElement("i", "icon-moneda ms-1"));
        mainDiv.appendChild(name);
        mainDiv.appendChild(btn);
        tareaDiv.appendChild(mainDiv);

        if(!stacked){
            tareaDiv.appendChild(HELPEX.createElement("span", "border-top border-secondary", TAREA[TAREA_ID[tarea]].descripcion));
            name.classList.add("fs-5");
        }

        btn.addEventListener("click", () => {
            btnSpinner.classList.remove("visually-hidden");
            btnSpinner.nextElementSibling.classList.add("visually-hidden");
            btnSpinner.nextElementSibling.nextElementSibling.classList.add("visually-hidden");
            btn.disabled = true;
            API.cobrarTareaDiaria(tarea)
            .then(() => {
                btnSpinner.classList.add("visually-hidden");
                btnSpinner.nextElementSibling.classList.remove("visually-hidden");
                btnSpinner.nextElementSibling.nextElementSibling.classList.remove("visually-hidden");

                API.getUser().then(user => walletDiv.firstElementChild.innerText = user.monedas).catch(HELPEX.generalUnespectedError);
            })
            .catch(HELPEX.generalUnespectedError);
        });

        div.appendChild(tareaDiv);
    });
}
function reloadLoadTareasDiarias(){
    if(drawTareasOptions !== null)
        API.getTareasDiarias()
        .then(tareas => loadTareasDiarias(tareas, drawTareasOptions.div, drawTareasOptions.stacked))
        .catch(HELPEX.generalUnespectedError);
}

function drawBalance(user, div, size = 5, drawTokiskis = true){
    let walletDiv = HELPEX.createElement("div", "d-flex flex-row justify-content-center align-items-center m-1 p-2 border border-secondary rounded-3");
    walletDiv.appendChild(HELPEX.createElement("i", `icon-moneda fs-${size-1} me-1`));
    walletDiv.appendChild(HELPEX.createElement("span", `fs-${size} ms-1`));
    walletDiv.lastElementChild.innerText = `${user.monedas} ${user.moneda==1?"moneda":"monedas"}`;
    div.appendChild(walletDiv);

    if(drawTokiskis){
        let tokiskiDiv = HELPEX.createElement("div", "d-flex flex-row justify-content-center align-items-center m-1 p-2 border border-secondary rounded-3");
        tokiskiDiv.appendChild(HELPEX.createElement("i", `fas fa-broadcast-tower fs-${size-1} me-1`));
        tokiskiDiv.appendChild(HELPEX.createElement("span", `fs-${size} ms-1`));
        tokiskiDiv.lastElementChild.innerText = `${user.tokiskis} @tokiski`;
        div.appendChild(tokiskiDiv);
    }
}

function drawBuyCoins(euros){
   let buyBtn = HELPEX.createElement("button", "btn btn-secondary d-flex flex-column justify-content-center align-items-center m-1");
   buyBtn.type = "button";
   let coinDiv = HELPEX.createElement("div", "d-flex flex-row justify-content-center align-items-center");
   coinDiv.appendChild(HELPEX.createElement("i", "icon-moneda fs-4 me-1"));
   coinDiv.appendChild(HELPEX.createElement("span", "fs-5 ms-1"));
   coinDiv.lastElementChild.innerText = (euros*100) + " monedas";
   buyBtn.appendChild(coinDiv);
   buyBtn.appendChild(HELPEX.createElement("span", "fs-5"));
   buyBtn.lastElementChild.innerText = euros + " €";
   buyBtn.addEventListener("click", () => buyCoins(euros));
   return buyBtn;
}
function buyCoins(euros){
    //sb-pye43y7768815@personal.example.com
    //hn2yMW&C
    HELPEX.globalLoaderShow("Creando pasarela de pago");
    API.comprarModenas(euros)
    .then(url => {
        location.href = url;
    })
    .catch(error => HELPEX.showMSG("Error al realizar compra", "No se ha podido realizar la compra.", error));
}

function drawBuyMedalla(medalla, refresh){
    let container = HELPEX.createElement("div", "d-flex flex-column justify-content-center align-items-center border border-secondary m-2 p-2");
    container.style.minWidth = "8rem";
    let img = HELPEX.createElement("img", "medalla");
    img.src = medalla.icon;
    container.appendChild(img);
    container.appendChild(HELPEX.createElement("span", "fs-5", medalla.nombre));
    let btn = HELPEX.createElement("button", "btn btn-outline-light");
    btn.type = "button";
    btn.appendChild(HELPEX.createElement("span", null, medalla.precio));
    btn.appendChild(HELPEX.createElement("i", "icon-moneda ms-2"));
    container.appendChild(btn);
    let tienesContador = HELPEX.createElement("div", "d-flex flex-row justify-content-center align-items-center flex-wrap mt-1");
    tienesContador.appendChild(HELPEX.createElement("span", "me-1", "Tienes:"));
    tienesContador.appendChild(HELPEX.createElement("span", null, medalla.cantidad));
    container.appendChild(tienesContador);

    btn.addEventListener("click", () => {
        HELPEX.showConf("Comprar medalla", `Confirma que deseas comprar la medalla "${medalla.nombre}" por ${medalla.precio} monedas`)
        .then(() => {
            API.comprarMedalla(medalla.id)
            .then(() => {
                refresh();
                tienesContador.lastElementChild.innerText = tienesContador.lastElementChild.innerText - -1;
            })
            .catch(HELPEX.generalUnespectedError);
        })
        .catch(() => {});
    });

    return container;
}

export default {
    drawPost,
    drawPosts,
    displayList,
    openPost,
    loadHotKeys,
    loadTareasDiarias,
    drawNotificacion,
    drawNotificaciones,
    drawBalance,
    drawBuyCoins,
    drawBuyMedalla,
    randomEmptyFace,
    pModalElements
}