"use strict";

document.addEventListener("DOMContentLoaded", function() {
    const sessionPtr = document.getElementById("irmaAuth").dataset.sessionPtr;
    const irmaFrontend = irma.newPopup({
        debugging: false,

        session: {
            start: false,
            mapping: {
                sessionPtr: function() {
                    return JSON.parse(sessionPtr);
                }
            },
            result: false
        }
    });

    irmaFrontend.start().then(function(response) {
        document.querySelector("div#irmaAuth form").submit();
    }).catch(function(error) {
        console.error("Couldn't do what you asked 😢", error);
    });

});
