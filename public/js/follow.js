document.addEventListener("DOMContentLoaded", function () {

    const playerFilter = document.getElementById("playerFilter");

    if (playerFilter) {

        playerFilter.addEventListener("change", function () {

            let player = this.value;

            document.querySelectorAll(".match-card").forEach(match => {

                let p1 = match.dataset.player1;
                let p2 = match.dataset.player2;

                if (player === "" || p1 === player || p2 === player) {

                    match.style.display = "block";

                } else {

                    match.style.display = "none";

                }

            });

        });

    }

});


function toggleGroupGames(groupId) {

    let el = document.getElementById("groupGames" + groupId)

    el.classList.toggle("d-none")

}

// Auto Refresh

setInterval(() => {

    location.reload();

}, 15000);