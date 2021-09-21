import CompLoader from "./compLoader.js";
import API from "./api.js";
import HELPEX from "./helpex.js";
import { MAX_CHARS_IN_POST } from "./constants.js";

var incentivos = [
    "Publica algo bonito",
    "¿Alguna noticia reciente?",
    "Dedica unas palabras"
]

export default {
    iniPostComposer: (container, publishAction = null, parent = null) => {
        CompLoader.loadComp("postComposer", container)
        .then(() => {
            var postComposerText = document.getElementById("postComposerText");
            var postComposerTextLabel = document.getElementById("postComposerTextLabel");
            var postComposerCharCounter = document.getElementById("postComposerCharCounter");
            var postComposerAddPhoto = document.getElementById("postComposerAddPhoto");
            var postComposerAddVideo = document.getElementById("postComposerAddVideo");
            var postComposerPostBtn = document.getElementById("postComposerPostBtn");
            var postComposerPostBtnIcon = document.getElementById("postComposerPostBtnIcon");
            var postComposerPostBtnSpinner = document.getElementById("postComposerPostBtnSpinner");
            var postComposerPhoto = document.getElementById("postComposerPhoto");
            var postComposerRemovePhoto = document.getElementById("postComposerRemovePhoto");
            var postComposerVideo = document.getElementById("postComposerVideo");
            var postComposerRemoveVideo = document.getElementById("postComposerRemoveVideo");
            var postComposerPostProgress = document.getElementById("postComposerPostProgress");
            var postComposerPostProgressBar = document.getElementById("postComposerPostProgressBar");
            var progressProcesing = document.getElementById("progressProcesing");
            var fileInput = document.getElementById("fileInput");

            postComposerTextLabel.innerText = incentivos[Math.floor(Math.random()*incentivos.length)];

            var image = null, video = null, nextAddAction = null;
            postComposerPostBtn.addEventListener("click", () => {
                let push;
                if(image !== null){
                    push = API.createPostImage(postComposerText.value, image, changeProgress, parent);
                }else if(video !== null){
                    push = API.createPostVideo(postComposerText.value, video, changeProgress, parent);
                }else{
                    push = API.createPost(postComposerText.value, changeProgress, parent);
                }
                push.then((msg) => {
                    postComposerText.value = "";
                    HELPEX.sendClick(postComposerText, "change");
                    HELPEX.sendClick(postComposerRemovePhoto);
                    HELPEX.sendClick(postComposerRemoveVideo);
                    stopLoading();
                    if(publishAction !== null) publishAction();
                })
                .catch(error => {
                    stopLoading();
                    HELPEX.showMSG("Error al publicar", "Se ha producido un error al publicar", error)
                });

                progressProcesing.classList.add("visually-hidden");
                postComposerPostProgress.classList.remove("visually-hidden");
                postComposerPostProgressBar.style.width = "0%";
                postComposerPostBtnSpinner.classList.remove("visually-hidden");
                postComposerPostBtnIcon.classList.add("visually-hidden");
                postComposerPostBtn.disabled = true;
                postComposerPostBtn.classList.add("disabled");
            });

            postComposerAddPhoto.addEventListener("click", () => {
                fileInput.click();
                nextAddAction = "photo";
            });
            postComposerAddVideo.addEventListener("click", () => {
                fileInput.click();
                nextAddAction = "video";
            });
            postComposerRemovePhoto.addEventListener("click", () => {
                image = null;
                postComposerPhoto.classList.add("visually-hidden");
            });
            postComposerRemoveVideo.addEventListener("click", () => {
                video = null;
                postComposerVideo.classList.add("visually-hidden");
            });
            fileInput.addEventListener("change", () => {
                let file = fileInput.files[0];
                if(nextAddAction == "photo"){
                    image = file;
                    postComposerPhoto.classList.remove("visually-hidden");
                    if(video !== null) HELPEX.sendClick(postComposerRemoveVideo);
                }else if(nextAddAction == "video"){
                    video = file;
                    postComposerVideo.classList.remove("visually-hidden");
                    if(image !== null) HELPEX.sendClick(postComposerRemovePhoto);
                }
            });

            function changeProgress(progress){
                if(progress == 100){
                    progressProcesing.classList.remove("visually-hidden");
                    postComposerPostProgress.classList.add("visually-hidden");
                }
                postComposerPostProgressBar.style.width = Math.floor(progress)+"%";
            }
            function stopLoading(){
                postComposerPostBtn.classList.remove("disabled");
                progressProcesing.classList.add("visually-hidden");
                postComposerPostProgress.classList.add("visually-hidden");
                postComposerPostBtnSpinner.classList.add("visually-hidden");
                postComposerPostBtnIcon.classList.remove("visually-hidden");
                postComposerPostBtn.disabled = false;
            }

            let checkChange = (event) => {
                let cChars = postComposerText.value.length + ((event instanceof KeyboardEvent)?1:0);
                postComposerCharCounter.innerText = `${cChars}/${MAX_CHARS_IN_POST}`;
                if(cChars > MAX_CHARS_IN_POST){
                    postComposerCharCounter.classList.add("text-danger");
                    postComposerCharCounter.classList.remove("text-warning");
                }else if((cChars/MAX_CHARS_IN_POST) > .8){
                    postComposerCharCounter.classList.remove("text-danger");
                    postComposerCharCounter.classList.add("text-warning");
                }else{
                    postComposerCharCounter.classList.remove("text-danger");
                    postComposerCharCounter.classList.remove("text-warning");
                }
                
            };
            postComposerText.addEventListener("keypress", checkChange);
            postComposerText.addEventListener("change", checkChange);
            HELPEX.sendClick(postComposerText, "change");

        });
    }
}