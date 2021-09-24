import {ROOT} from "./installation_constants.js"

export default {
    loadComp: (nombre, div) => {
        if(!(div instanceof Element))
            div = document.getElementById(div);

        return new Promise((resolve, reject) => {
            fetch(`${ROOT}libs/components/${nombre}.html`)
                .then(response => response.text())
                .then(data => {
                    div.innerHTML = data;
                    setTimeout(() => {
                        resolve();
                    }, 1);
                })
                .catch(error => {
                    reject(error);
                });
        });
    }
}