"use strict";

document.addEventListener("DOMContentLoaded", function() {
<<<<<<< HEAD
    const sessionPtr = document.getElementById("irmaAuth").dataset.sessionPtr;
    const irmaFrontend = irma.newPopup({
        debugging: false,
        session: {
            start: false,
            mapping: {
                sessionPtr: function() {
                    return JSON.parse(sessionPtr);
                }
=======
    const sessionPtr = document.getElementById('irmaAuth').dataset.sessionPtr;

    const irmaFrontend = irma.newPopup({
        debugging: false,

        session: {
            start: false,
            mapping: {
<<<<<<< HEAD
              sessionPtr: () => JSON.parse(sessionPtr)
>>>>>>> 255d4b1 (change client code)
=======
              sessionPtr: () => function() { return JSON.parse(sessionPtr);}
>>>>>>> 6f72016 (squashed commits?)
            },
            result: false
        }
    });
<<<<<<< HEAD
    irmaFrontend.start().then(function(response) {
        document.querySelector("div#irmaAuth form").submit();
    }).catch(function(error) {
        console.error("Couldn't do what you asked 😢", error);
    });
=======
    // Start the popup and show the QR-code
    irmaFrontend.start()
      .then(document.querySelector("div#irmaAuth form").submit())
      .catch(error => console.error("Couldn't do what you asked", error));
>>>>>>> 255d4b1 (change client code)
});
