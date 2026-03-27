@extends('layouts.tv')

@section('content')
    <div id="tvStage" class="w-screen h-screen relative"></div>


    <!-- Übersicht -->

    <div id="overviewTemplate" class="hidden">

        <div class="flex flex-col items-center justify-center h-screen w-full">


            <div class="flex justify-evenly items-center w-full max-w-6xl">

                @foreach ($tournaments as $tournament)
                    <div class="flex flex-col items-center">

                        <div class="text-3xl mb-6">
                            {{ $tournament->name }}
                        </div>

                        {{-- Hauptturnier --}}
                        <div class="bg-white p-4 rounded">
                            {!! QrCode::size(444)->generate(url('/follow/' . $tournament->public_id)) !!}
                        </div>

                        {{-- Lucky Loser --}}
                        @if ($tournament->children->where('type', 'lucky_loser')->first())
                            @php
                                $lucky = $tournament->children->where('type', 'lucky_loser')->first();
                            @endphp

                            <div class="text-yellow-400 text-xl mt-6 mb-2">
                                Lucky-Loser
                            </div>

                            <div class="bg-white p-3 rounded">
                                {!! QrCode::size(260)->generate(url('/follow/' . $lucky->id)) !!}
                            </div>
                        @endif

                    </div>
                @endforeach

            </div>

        </div>

    </div>
@endsection

@push('scripts')
    <script>
        const rotationTime = {{ \App\Models\TvTournament::value('rotation_time') ?? 20 }};
        document.addEventListener("DOMContentLoaded", function() {

            const stage = document.getElementById("tvStage")

            const pages = [

                {
                    type: "overview"
                },

                @foreach ($tournaments as $t)
                    {
                        type: "tournament",
                        url: "/tv/{{ $t->public_id }}"
                    },
                @endforeach

            ]

            let index = 0


            function showPage() {

                const page = pages[index]

                stage.style.opacity = 0

                setTimeout(() => {

                    if (page.type === "overview") {

                        stage.innerHTML =
                            document.getElementById("overviewTemplate").innerHTML

                    } else {

                        stage.innerHTML =
                            `<iframe src="${page.url}" 
                    style="width:100%;height:100vh;border:none"></iframe>`

                    }

                    stage.style.opacity = 1

                }, 500)

            }


            function next() {

                index++

                if (index >= pages.length) {
                    index = 0
                }

                showPage()

            }


            showPage()

            setInterval(next, rotationTime * 1000)

        })
    </script>
@endpush

@push('styles')
    <style>
        #tvStage {
            transition: opacity .6s ease;
        }
    </style>
@endpush
