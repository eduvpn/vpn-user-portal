"use strict";

document.addEventListener("DOMContentLoaded", function() {
<<<<<<< HEAD
<<<<<<< HEAD
    const sessionPtr = document.getElementById("irmaAuth").dataset.sessionPtr;
=======
    const sessionPtr = document.getElementById('irmaAuth').dataset.sessionPtr;

>>>>>>> b3277c8be6e94450ee1517cbe4a332e3fbe1221b
    const irmaFrontend = irma.newPopup({
        debugging: false,

        session: {
            start: false,
            mapping: {
<<<<<<< HEAD
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
=======
              sessionPtr: () => function() { return JSON.parse(sessionPtr);}
>>>>>>> b3277c8be6e94450ee1517cbe4a332e3fbe1221b
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
