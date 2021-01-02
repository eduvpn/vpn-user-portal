"use strict";

document.addEventListener("DOMContentLoaded", function() {
    const sessionPtr = document.getElementById('irmaAuth').dataset.sessionPtr;
    // IRMA front-end configuration
    const irmaFrontend = irma.newPopup({
        debugging: false,

        session: {
            start: false,
            mapping: {
              sessionPtr: () => JSON.parse(sessionPtr)
            },
            result: false
        }
    });
    // Start the popup and show the QR-code
    irmaFrontend.start()
      .then(response => document.querySelector("div#irmaAuth form").submit())
      .catch(error => console.error("Couldn't do what you asked", error));
});
