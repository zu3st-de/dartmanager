@extends('layouts.tv')

@section('content')

<style>
    body {
        background: #000;
        color: white;
        font-family: Arial, Helvetica, sans-serif;
    }

    /* Würfel Animation */

    @keyframes shakeDice {

        0% {
            transform: translateY(0px) rotate(0deg);
        }

        25% {
            transform: translateY(-15px) rotate(-10deg);
        }

        50% {
            transform: translateY(8px) rotate(10deg);
        }

        75% {
            transform: translateY(-8px) rotate(-6deg);
        }

        100% {
            transform: translateY(0px) rotate(0deg);
        }

    }

    .dice-container span {
        display: inline-block;
        margin: 0 25px;
        animation: shakeDice 0.6s infinite;
    }
</style>


<div style="
display:flex;
flex-direction:column;
align-items:center;
justify-content:center;
height:100vh;
text-align:center;
">

    <div style="font-size:70px;font-weight:bold;margin-bottom:40px;">
        {{ $tournament->name }}
    </div>

    <div style="font-size:48px;margin-bottom:30px;">
        Turnier startet in wenigen Augenblicken
    </div>

    <div style="font-size:32px;color:#aaa;margin-bottom:120px;">
        Teilnehmer werden ausgelost
    </div>

    <div class="dice-container" style="font-size:120px;">
        <span>⚀</span>
        <span>⚁</span>
        <span>⚂</span>
    </div>

</div>


<script>
    const dice = ["⚀", "⚁", "⚂", "⚃", "⚄", "⚅"]

    setInterval(() => {

        document.querySelectorAll(".dice-container span").forEach(el => {

            el.innerText = dice[Math.floor(Math.random() * 6)]

        })

    }, 200)
</script>

@endsection