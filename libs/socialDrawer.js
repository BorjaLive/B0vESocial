import HELPEX from "./helpex.js";
import CompLoader from "./compLoader.js";
import API from "./api.js";

var postModal;
var pModal = {};
var pModalElements = ["Header", "ProfilePic", "Name", "User", "Content", "Text", "Image", "Video", "Spinner", "Fecha", "Footer", "ComentBtn", "Responses", "ResponsesText", "Father", "FatherPost", "VociferarBtn", "VociferarIcon", "VociferarSpinner", "VociferarCounter", "FavoritoBtn", "FavoritoIcon", "FavoritoSpinner", "FavoritoCounter", "MedallaBtn", "MedallaIcon", "MedallaSpinner", "EliminarBtn", "EliminarIcon", "EliminarSpinner"];
var userListModal, userListModalTitle, userListModalBody;
let modalsDiv = document.createElement("div");
document.body.appendChild(modalsDiv);
CompLoader.loadComp("socialDrawer", modalsDiv)
.then(() => {
    postModal = document.getElementById("postModal");
    pModalElements.forEach(e => pModal[e] = document.getElementById("postModal"+e));
    userListModal = document.getElementById("userListModal");
    userListModalTitle = document.getElementById("userListModalTitle");
    userListModalBody = document.getElementById("userListModalBody");
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
        text = left + `<a href="user.html?sid=${palabra}">@${palabra}</a>` + right;
        n[palabra]++;
    });
    return text;
}

function drawPost(data, refreshAction = null){
    let container = document.createElement("div");
    HELPEX.addClases(container, "d-flex flex-column p-1 my-1 rounded border border-secondary");

    let userSection = document.createElement("a");
    userSection.style.textDecoration = "none";
    userSection.href = "user.html?sid="+data.usuario.usuario;
    HELPEX.addClases(userSection, "d-flex flex-row justify-content-start align-items-center text-light");
    let userPic = document.createElement("img");
    userPic.src = data.usuario.profilePic;
    HELPEX.addClases(userPic, "profile-pic mx-3");
    let userName = document.createElement("h4");
    userName.innerText = data.usuario.nombre;
    let userRealName = document.createElement("spam");
    HELPEX.addClases(userRealName, "ms-2 text-secondary");
    userRealName.innerText = `(@${data.usuario.usuario})`;
    userSection.appendChild(userPic);
    userSection.appendChild(userName);
    userSection.appendChild(userRealName);

    let postSection = document.createElement("div");
    HELPEX.addClases(postSection, "d-flex flex-column px-2 mb-1");
    let postText = document.createElement("spam");
    HELPEX.addClases(postText, "fs-6 my-1");
    postText.innerHTML = stylizeTest(data.texto);
    postSection.appendChild(postText);
    if(data.foto !== null){
        let postImg = document.createElement("img");
        HELPEX.addClases(postImg, "rounded align-self-center");
        postImg.src = data.foto;
        postImg.style.width = "80%";
        postSection.appendChild(postImg);
    }
    if(data.video !== null){
        let postVideo = document.createElement("video");
        postVideo.controls = true;
        HELPEX.addClases(postVideo, "rounded align-self-center");
        postVideo.src = data.video;
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
        console.log("mostrar");
        HELPEX.hideAllModals(null, postModal);

        API.getPost(data.id, true)
        .then(post => openPost(post, refreshAction))
        .catch(error => HELPEX.showMSG("Error al abrir publicación", "No se ha podido obtener la publicación", error));
    });

    container.appendChild(userSection);
    container.appendChild(postSection);
    return container;
}
function drawPosts(posts, div, refreshAction = null){
    posts.forEach(post => div.appendChild(drawPost(post, refreshAction)));
}

function openPost(data, refreshAction = null){
    console.log(data);

    pModal.ProfilePic.src = data.usuario.profilePic;
    pModal.Name.innerText = data.usuario.nombre;
    pModal.User.innerText = `(@${data.usuario.usuario})`;

    pModal.Text.innerHTML = stylizeTest(data.texto);
    if(data.foto !== null){
        pModal.Image.src = data.foto;
        pModal.Image.classList.remove("visually-hidden");
    }else pModal.Image.classList.add("visually-hidden");
    if(data.video !== null){
        pModal.Video.src = data.video;
        pModal.Video.classList.remove("visually-hidden");
    }else pModal.Video.classList.add("visually-hidden");
    pModal.Fecha.innerText = HELPEX.isoDate2esp(data.fecha);

    pModal.ResponsesText.innerText = `Respuestas (${data.respuestas.length}):`;
    pModal.ComentBtn.onclick = () => location.href=`post.html?id=${data.id}`;
    HELPEX.removeAllChilds(pModal.Responses);
    data.respuestas.forEach(respuesta => pModal.Responses.appendChild(drawPost(respuesta)));

    if(data.padre !== null){
        HELPEX.removeAllChilds(pModal.FatherPost);
        pModal.Responses.appendChild(drawPost(data.padre));
        pModal.Father.classList.remove("visually-hidden");
    }else pModal.Father.classList.add("visually-hidden");

    pModal.VociferarIcon.classList.add(data.vociferado?"text-success":"text-light");
    pModal.VociferarBtn.onclick = () => {
        pModal.VociferarBtn.disabled = true;
        pModal.VociferarSpinner.classList.remove("visually-hidden");
        pModal.VociferarIcon.classList.add("visually-hidden");
        API.doVociferar(data.id)
        .then(vociferado => {
            pModal.VociferarBtn.disabled = false;
            pModal.VociferarSpinner.classList.add("visually-hidden");
            pModal.VociferarIcon.classList.remove("visually-hidden");
            if(vociferado){
                pModal.VociferarIcon.classList.add("text-success");
                pModal.VociferarIcon.classList.remove("text-light");
                pModal.VociferarCounter.innerText -= -1;
            }else{
                pModal.VociferarIcon.classList.remove("text-success");
                pModal.VociferarIcon.classList.add("text-light");
                pModal.VociferarCounter.innerText -= 1;
            }
        })
        .catch(HELPEX.generalUnespectedError);
    };
    pModal.VociferarCounter.innerText = data.vociferados;

    pModal.FavoritoIcon.style.color = data.favorito?"gold":"#f8f9fa";
    pModal.FavoritoBtn.onclick = () => {
        pModal.FavoritoBtn.disabled = true;
        pModal.FavoritoSpinner.classList.remove("visually-hidden");
        pModal.FavoritoIcon.classList.add("visually-hidden");
        API.doFavorito(data.id)
        .then(favorito => {
            pModal.FavoritoBtn.disabled = false;
            pModal.FavoritoSpinner.classList.add("visually-hidden");
            pModal.FavoritoIcon.classList.remove("visually-hidden");
            if(favorito){
                pModal.FavoritoIcon.style.color = "gold";
                pModal.FavoritoCounter.innerText -= -1;
            }else{
                pModal.FavoritoIcon.style.color = "#f8f9fa";
                pModal.FavoritoCounter.innerText -= 1;
            }
        })
        .catch(HELPEX.generalUnespectedError);
    };
    pModal.FavoritoCounter.innerText = data.favoritos;

    //Medallas

    if(data.usuario.id !== window.user){
        pModal.EliminarBtn.classList.add("visually-hidden");
    }
    pModal.EliminarBtn.onclick = () => {
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
    
    pModal.Header.classList.remove("visually-hidden");
    pModal.Content.classList.remove("visually-hidden");
    pModal.Footer.classList.remove("visually-hidden");
    pModal.Spinner.classList.add("visually-hidden");
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

//TODO: Crear el modal que muestra

export default {
    drawPost,
    drawPosts,
    displayList
}