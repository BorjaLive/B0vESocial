export const MAX_CHARS_IN_POST = 150;
export const PROFILE_PIC_PLACEHOLDER = "img/placeHolderProfilePic.jpg";
export const NOTIFICATION = {MENCION: 1, VOCIFERADO: 2, FAVORITO: 3, RESPONDIDO: 4, MEDALLA: 5}
export const TAREA_ID = {1: "TODAS", 2: "VER_NOTIFICACIONES", 3: "PUBLICAR", 4: "RESPONDER", 5: "MENCIONAR", 6: "VOCIFERAR", 7: "FAVORITO"};
export const TAREA = {
    TODAS: {
        id: 1,
        nombre: "Todas las tareas",
        descripcion: "Completa todas las tareas diárias para desbloquear un gran bonus.",
        monedas: 40
    },
    VER_NOTIFICACIONES: {
        id: 2,
        nombre: "Revisar la actividad",
        descripcion: "Entra en la página de actividad para revisar que se cuece entre los usuarios que acechas.",
        monedas: 10
    },
    PUBLICAR: {
        id: 3,
        nombre: "Publica algo",
        descripcion: "Haz una publicación de cualquier tipo.",
        monedas: 10
    },
    RESPONDER: {
        id: 4,
        nombre: "Responde a alguien",
        descripcion: "Contesta a una publicación ya existente.",
        monedas: 10
    },
    MENCIONAR: {
        id: 5,
        nombre: "Menciona a alguien",
        descripcion: "Crea una publicación en la que menciones a alguien.",
        monedas: 10
    },
    VOCIFERAR: {
        id: 6,
        nombre: "Vocifera algo",
        descripcion: "Vocifera una publicación para que puedan verlo las personas que te acechan.",
        monedas: 10
    },
    FAVORITO: {
        id: 7,
        nombre: "Crea un favorito",
        descripcion: "Marca una publicación como favorito para tenerla guardada. (Puedes deshacerlo)",
        monedas: 10
    }
};