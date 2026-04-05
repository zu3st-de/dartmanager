@extends('layouts.tv')

@section('content')
    {{-- Template für Overview --}}
    <div id="overviewTemplate" style="display:none">

        <div class="h-full w-full flex items-center justify-center">

            <div class="flex justify-center items-start gap-24">

                @foreach ($tournaments as $tournament)
                    @php
                        $isLucky = $tournament->parent_id !== null;
                        $lucky = $tournaments->firstWhere('parent_id', $tournament->id);
                        $size = $lucky ? 320 : 444;
                    @endphp

                    @if ($isLucky)
                        @continue
                    @endif

                    <div class="flex flex-col items-center gap-8">

                        <div class="text-center">
                            <div class="text-3xl font-semibold mb-6">
                                {{ $tournament->name }}
                            </div>

                            <div class="flex justify-center">
                                <div class="bg-white p-4 rounded-lg">
                                    {!! QrCode::size($size)->generate(url('/follow/' . $tournament->public_id)) !!}
                                </div>
                            </div>
                        </div>

                        @if ($lucky)
                            <div class="text-center">
                                <div class="text-yellow-400 text-xl mb-4">
                                    Lucky-Loser
                                </div>

                                <div class="flex justify-center">
                                    <div class="bg-white p-4 rounded-lg">
                                        {!! QrCode::size($size)->generate(url('/follow/' . $lucky->public_id)) !!}
                                    </div>
                                </div>
                            </div>
                        @endif

                    </div>
                @endforeach

            </div>

        </div>

    </div>

    {{-- Stage Container --}}
    <div id="tvStage"></div>
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
