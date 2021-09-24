import {INTERNAL} from "./table.js";
import HELPEX from "./helpex.js";
import API from "./api.js";

var estadisticaInitialized = false;
var filterData = {};
var tableTitle = null;
var graphDiv = null;
var tablaDiv = null;
var loadingDiv = null;
var graficaBtn = null, tablaBtn = null;

function reloadEstadistica(incluirTabla = false){
    if(!estadisticaInitialized) return;
    loadingDiv.classList.remove("visually-hidden");
    //graphDiv.style.display = "none";
    tablaDiv.style.display = "none";
    graficaBtn.disabled = true;
    tablaBtn.disabled = true;
    API.genericFetch(filterData)
    .then(data => {
        drawEstacisticaData(data, incluirTabla);
        loadingDiv.classList.add("visually-hidden");
        graficaBtn.disabled = false;
        tablaBtn.disabled = false;
    })
    .catch(error => HELPEX.showMSG("Error al obtener datos", "No se han podido obtener los datos", error));
}

function drawEstacisticaData(data, incluirTabla = false){
    console.log(data);

    let steped = document.querySelector("input[name=steppedGraph]:checked").value;
    let stacked = document.querySelector("input[name=stackableGraph]:checked").value;
    var dataTable = google.visualization.arrayToDataTable(data);

    var options = {
        title: tableTitle,
        hAxis: {title: data[0][0],  titleTextStyle: {color: '#333'}},
        vAxis: {minValue: 0},
        isStacked: stacked == "true"
    };

    var chart;
    if(steped == "true")
        chart = new google.visualization.SteppedAreaChart(graphDiv);
    else
        chart = new google.visualization.AreaChart(graphDiv);
    chart.draw(dataTable, options);

    graphDiv.style.display = "block";
    if(incluirTabla){
        let head = "", body = "";

        data[0].forEach(v => {
            head += `<th scope="col">${v}</th>`;
        });

        data.forEach((row, i) => {
            if(i > 0){
                body += "<tr>";
                row.forEach(v => {
                    body += `<td>${v}</td>`;
                });
                body += "</tr>";
            }
        });


        tablaDiv.innerHTML = `
            <table class="table table-striped">
                <thead>
                    <tr>
                        ${head}
                    </tr>
                </thead>
                <tbody>
                    ${body}
                </tbody>
            </table>
        `;

        tablaDiv.style.display = "block";
    }else{

    }
}

export default {
    FILTER: INTERNAL.FILTER_TYPES,
    reloadEstadistica,
    initializeEstadistica: (filters, container, title, action) => {
        filterData.action = action;
        tableTitle = title;

        let containerDiv = document.getElementById(container);

        let filterDiv = document.createElement("div");
        filterDiv.classList.add("row");
        filterDiv.classList.add("mt-1");
        filterDiv.classList.add("align-items-end");

        let actButtons = HELPEX.createElement("div", "d-flex justify-content-start flex-row");
        graficaBtn = HELPEX.createElement("button", "btn btn-outline-primary", "Generar grafica");
        tablaBtn = HELPEX.createElement("button", "btn btn-outline-primary ms-2", "Generar tabla");
        graficaBtn.addEventListener("click", () => reloadEstadistica(false));
        tablaBtn.addEventListener("click", () => reloadEstadistica(true));
        graficaBtn.disabled = true;
        tablaBtn.disabled = true;
        actButtons.appendChild(graficaBtn);
        actButtons.appendChild(tablaBtn);
        containerDiv.appendChild(actButtons);

        INTERNAL.createFilters(filters, filterDiv, (val, id) => {
            filterData[id] = val;
            //reloadEstadistica();
        });

        containerDiv.appendChild(filterDiv);
        let drawFilters = HELPEX.createElement("div");
        drawFilters.innerHTML = `
            <div class="row mt-3">
                <h5>Opciones de visualización</h5>
            </div>
            <div class="d-flex flex-row justify-content-around flex-wrap">
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="steppedGraph" id="steppedGraphTrue" value="true" checked>
                    <label class="form-check-label radio-inline" for="steppedGraphTrue">
                        Gráfica discontinua
                    </label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="steppedGraph" id="steppedGraphFalse" value="false">
                    <label class="form-check-label" for="steppedGraphFalse">
                        Gráfica continua
                    </label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="stackableGraph" id="stackableGraphFalse" value="false" checked>
                    <label class="form-check-label" for="stackableGraphFalse">
                        Sin addición
                    </label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="stackableGraph" id="stackableGraphAbsolute" value="absolute">
                    <label class="form-check-label" for="stackableGraphAbsolute">
                        Con adición
                    </label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="stackableGraph" id="stackableGraphRelative" value="percent">
                    <label class="form-check-label" for="stackableGraphRelative">
                        Adición relativa
                    </label>
                </div>
            </div>
        `;
        containerDiv.appendChild(drawFilters);

        graphDiv = HELPEX.createElement("div", null);
        graphDiv.style.width = "100%";
        graphDiv.style.height = "500px";
        containerDiv.appendChild(graphDiv);
        tablaDiv = HELPEX.createElement("div", null);
        containerDiv.appendChild(tablaDiv);
        console.log(graphDiv);

        loadingDiv = HELPEX.createElement("div", "mt-3, text-center visually-hidden");
        let spinnerElement = HELPEX.createElement("div", "spinner-grow, text-secondary");
        spinnerElement.style.width = "3rem";
        spinnerElement.style.height = "3rem";
        loadingDiv.appendChild(spinnerElement);
        containerDiv.parentElement.appendChild(loadingDiv);

        google.charts.load('current', {'packages':['corechart']});
        google.charts.setOnLoadCallback(() => {
            graficaBtn.disabled = false;
            tablaBtn.disabled = false;
            estadisticaInitialized = true;
        });
    }
}