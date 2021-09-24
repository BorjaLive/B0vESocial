import API from "./api.js";
import HELPEX from "./helpex.js";

const FILTER_TYPES = {
    TEXT: 1, NUMBER: 2, DATE: 3, SELECT: 4, INTERVAL: 5
}
const MAX_PER_PAGE = 10;

var tableInitialized = false;

var columnsData = [];
var filterData = {};
var tableData = [];

var tBody = null;
var tPages = null;
var loadingDiv = null;

function reloadTable(){
    if(!tableInitialized) return;
    loadingDiv.classList.remove("visually-hidden");
    tBody.classList.add("visually-hidden");
    API.genericFetch(filterData)
    .then(data => {
        tableData = data;
        drawTableData();
        loadingDiv.classList.add("visually-hidden");
        tBody.classList.remove("visually-hidden");
    })
    .catch(error => HELPEX.showMSG("Error al obtener datos", "No se han podido obtener los datos", error));
}

function fillSelect(select, data){
    if(data instanceof Array){
        data.forEach(e => {
            let opt = document.createElement("option");
            if(e instanceof Object){
                opt.value = e.value;
                opt.innerText = e.name;
            }else{
                opt.value = e;
                opt.innerText = e;
            }
            select.appendChild(opt);
        })
    }else if(data instanceof Promise){
        data.then(res => fillSelect(select, res))
        .catch(error => HELPEX.showMSG("Error al obtener datos", "No se han podido cargar datos del servidor", error));
    }
}

function drawTableData(page = 0){
    let nPages = Math.ceil(tableData.length/MAX_PER_PAGE);
    if(page >= nPages) page = nPages-1;
    if(page < 0) page = 0;

    HELPEX.removeAllChilds(tBody);
    for(let i = page*MAX_PER_PAGE; i < Math.min(tableData.length, (page+1)*MAX_PER_PAGE); i++){
        let e = tableData[i];
        let trElement = document.createElement("tr");
        columnsData.forEach(column => {
            let tdElement = document.createElement("td");
            if(column.id instanceof Object){
                tdElement.classList.add("text-end");
                tdElement.classList.add("mr-3");
                let buttonGroupDiv = document.createElement("div");
                buttonGroupDiv.classList.add("dropdown");
                let buttonElement = document.createElement("button");
                buttonElement.classList.add("btn");
                buttonElement.classList.add("btn-outline-primary");
                buttonElement.classList.add("dropdown-toggle");
                buttonElement.type = "button";
                buttonElement.setAttribute("data-bs-toggle", "dropdown");
                buttonElement.innerText = column.id.name;
                let dropDownMenuDiv = document.createElement("ul");
                dropDownMenuDiv.classList.add("dropdown-menu");
                dropDownMenuDiv.classList.add("dropdown-menu-right");
                column.id.actions.forEach(action => {
                    let dropDownItem;
                    if(action === null){
                        dropDownItem = document.createElement("hr");
                        dropDownItem.classList.add("dropdown-divider");
                    }else{
                        dropDownItem = document.createElement("a");
                        dropDownItem.classList.add("dropdown-item");
                        dropDownItem.href = "#";
                        dropDownItem.innerText = action.name;
                        dropDownItem.addEventListener("click", () => action.code(e))
                    }
                    let dropDownItemWrapper = document.createElement("li");
                    dropDownItemWrapper.appendChild(dropDownItem);
                    dropDownMenuDiv.appendChild(dropDownItemWrapper);
                });
                buttonGroupDiv.appendChild(buttonElement);
                buttonGroupDiv.appendChild(dropDownMenuDiv);
                tdElement.appendChild(buttonGroupDiv);
            }else{
                let cellDat = null;
                if(column.id === "-"){
                    cellDat = e;
                }else if(e[column.id] !== undefined){
                    cellDat = e[column.id];
                }
                if(cellDat !== null){
                    if(column.formatter !== undefined){
                        cellDat = column.formatter(cellDat);
                    }

                    if(column.isImage === true){
                        let img = document.createElement("img");
                        if(column.classes !== undefined) HELPEX.addClases(img, column.classes);
                        img.src = cellDat;
                        tdElement.appendChild(img);
                    }else{
                        tdElement.innerText = cellDat;
                    }
                }
            }
            trElement.appendChild(tdElement);
        });
        tBody.appendChild(trElement);
    }

    HELPEX.removeAllChilds(tPages);
    if(nPages > 1){
        if(page != 0){
            tPages.appendChild(makeNavegButton(HELPEX.makeFontAwsome("fas", "fa-angle-double-left"), 0));
            tPages.appendChild(makeNavegButton(HELPEX.makeFontAwsome("fas", "fa-angle-left"), page-1));
        }
        if(page > 3){
            let span = document.createElement("span");
            span.classList.add("mr-1");
            span.classList.add("mb-1");
            span.innerText = "...";
            tPages.appendChild(span);
        }
        for(let i = Math.max(0, page-3); i < page; i++){
            tPages.appendChild(makeNavegButton(i+1, i));
        }
        
        tPages.appendChild(makeNavegButton(page+1, page, false));
        
        for(let i = page+1; i < Math.min(page+4, nPages); i++){
            tPages.appendChild(makeNavegButton(i+1, i));
        }
        if(page < nPages-3){
            let span = document.createElement("span");
            span.classList.add("mr-1");
            span.classList.add("mb-1");
            span.innerText = "...";
            tPages.appendChild(span);
        }
        if(page < nPages-1){
            tPages.appendChild(makeNavegButton(HELPEX.makeFontAwsome("fas", "fa-angle-right"), page+1));
            tPages.appendChild(makeNavegButton(HELPEX.makeFontAwsome("fas", "fa-angle-double-right"), nPages-1));
        }
    }
}
function makeNavegButton(content, page, outline = true){
    let button = document.createElement("button");
    button.type = "button";
    button.classList.add("btn");
    button.classList.add(`btn-${outline?"outline-":""}secondary`);
    button.classList.add("mx-1");
    button.addEventListener("click", () => drawTableData(page));
    if(content instanceof Element)
        button.appendChild(content);
    else
        button.innerText = content;
    return button;
}

function createFilters(filters, filterDiv, setFunction){
    filters.forEach(filter => {
        let col = document.createElement("div");
        let disablerControl = document.createElement("div");
        let disablerInput = document.createElement("input");
        let disablerLabel = document.createElement("label");
        let filterInput;

        col.classList.add("col-12");
        col.classList.add("mt-1");
        col.classList.add("col-md-"+(filter.size===undefined?4:filter.size));

        disablerControl.classList.add("custom-control");
        disablerControl.classList.add("custom-switch");
        disablerControl.classList.add("mb-1");

        switch(filter.type){
            case FILTER_TYPES.TEXT:
                filterInput = document.createElement("input");
                filterInput.classList.add("form-control");
                filterInput.type = "text";
            break;
            case FILTER_TYPES.NUMBER:
                filterInput = document.createElement("input");
                filterInput.classList.add("form-control");
                filterInput.type = "number";
                if(filter.max !== undefined)
                    filterInput.max = filter.max;
                if(filter.min !== undefined)
                    filterInput.min = filter.min;
            break;
            case FILTER_TYPES.DATE:
                filterInput = document.createElement("input");
                filterInput.classList.add("form-control");
                filterInput.type = "date";
            break;
            case FILTER_TYPES.SELECT:
                filterInput = document.createElement("select");
                filterInput.classList.add("form-select");
                fillSelect(filterInput, filter.options)
            break;
            case FILTER_TYPES.INTERVAL:
                filterInput = document.createElement("select");
                filterInput.classList.add("form-select");
                fillSelect(filterInput, [{value: "year", name: "Anual"}, {value: "month", name: "Mensual"}, {value: "week", name: "Semanal"}, {value: "day", name: "Diario"}])
            break;
        }
        filterInput.addEventListener("change", () => {
            disablerInput.checked = true;
            setFunction(filterInput.value, filter.id);
        });

        disablerInput.type = "checkbox";
        disablerInput.classList.add("form-check-input");
        disablerInput.id = "filter" + filter.id;
        disablerInput.addEventListener("change", () => {
            if(disablerInput.checked){
                filterInput.disabled = false;
                filterInput.classList.remove("disabled");
                setFunction(filterInput.value, filter.id);
            }else{
                filterInput.disabled = true;
                filterInput.classList.add("disabled");
                setFunction(null, filter.id);
            }
        });
        disablerInput.dispatchEvent(new Event('change'));

        disablerLabel.classList.add("form-check-label");
        disablerLabel.classList.add("ms-1");
        disablerLabel.htmlFor  = "filter" + filter.id;
        disablerLabel.innerText = "Filtrar por "+filter.name;

        disablerControl.appendChild(disablerInput);
        disablerControl.appendChild(disablerLabel);
        col.appendChild(disablerControl);
        col.appendChild(filterInput);
        filterDiv.appendChild(col);
    });
}

export const INTERNAL = {
    fillSelect,
    createFilters,
    FILTER_TYPES
};

export default {
    FILTER: FILTER_TYPES,
    reloadTable,
    initializeTable: (columns, filters, container, action) => {
        filterData.action = action;
        columnsData = columns;

        let containerDiv = document.getElementById(container);

        let filterDiv = document.createElement("div");
        filterDiv.classList.add("row");
        filterDiv.classList.add("mt-1");
        filterDiv.classList.add("align-items-end");

        createFilters(filters, filterDiv, (val, id) => {
            filterData[id] = val;
            reloadTable();
        });
        containerDiv.appendChild(filterDiv);

        let tableDiv = document.createElement("div");
        tableDiv.classList.add("row");
        tableDiv.classList.add("mt-2");
        let tableElement = document.createElement("table");
        tableElement.classList.add("table");
        tableElement.classList.add("table-striped");
        let theadElement = document.createElement("thead");
        theadElement.classList.add("thead-light");
        let trElement = document.createElement("tr");

        columns.forEach(column => {
            let thElement = document.createElement("th");
            thElement.scope = "col";
            thElement.innerText = column.name;
            trElement.appendChild(thElement);
        });

        tBody = document.createElement("tbody");
        theadElement.appendChild(trElement);
        tableElement.appendChild(theadElement);
        tableElement.appendChild(tBody);
        tableDiv.appendChild(tableElement);

        if(HELPEX.isMobile()){
            let tableResponsiveWrapper = document.createElement("div");
            tableResponsiveWrapper.classList.add("table-responsive");
            tableResponsiveWrapper.appendChild(tableDiv);
            containerDiv.appendChild(tableResponsiveWrapper);
        }else{
            containerDiv.appendChild(tableDiv);
        }

        tPages = document.createElement("div");
        tPages.classList.add("d-flex");
        tPages.classList.add("mt-1");
        tPages.classList.add("d-2");
        tPages.classList.add("justify-content-center");
        tPages.classList.add("mb-3");

        containerDiv.appendChild(tPages);

        loadingDiv = document.createElement("div");
        loadingDiv.classList.add("mt-3");
        loadingDiv.classList.add("text-center");
        loadingDiv.classList.add("visually-hidden");
        let spinnerElement = document.createElement("div");
        spinnerElement.classList.add("spinner-grow");
        spinnerElement.classList.add("text-secondary");
        spinnerElement.style.width = "3rem";
        spinnerElement.style.height = "3rem";
        loadingDiv.appendChild(spinnerElement);
        containerDiv.parentElement.appendChild(loadingDiv);

        tableInitialized = true;
        reloadTable();
    }
}