@extends('layouts.tv')

@section('content')
    <div id="overviewTemplate" style="display:none">
        @include('tv.partials.overview', ['tournaments' => $tournaments])
    </div>

    <div id="tvStage"></div>
@endsection

@push('scripts')
    @php
        $rotationPayload = $tournaments
            ->map(function ($tournament) {
                return [
                    'id' => $tournament->id,
                    'name' => $tournament->name,
                    'public_id' => $tournament->public_id,
                    'parent_id' => $tournament->parent_id,
                    'follow_url' => url('/follow/' . $tournament->public_id),
                    'tv_url' => url('/tv/' . $tournament->public_id),
                ];
            })
            ->values();
    @endphp
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const stage = document.getElementById("tvStage")
            const overviewTemplate = document.getElementById("overviewTemplate")
            let index = 0
            let rotationTimer = null
            let configPollTimer = null
            let rotationTime = {{ \App\Models\TvTournament::where('user_id', auth()->id())->value('rotation_time') ?? 20 }}
            let pages = buildPages(@json($rotationPayload))

            function showPage() {
                if (pages.length === 0) {
                    stage.innerHTML =
                        `<div class="flex min-h-[calc(100vh-5rem)] items-center justify-center text-xl text-gray-400">Keine TV-Turniere ausgewählt.</div>`
                    stage.style.opacity = 1
                    return
                }

                const page = pages[index]
                stage.style.opacity = 0

                setTimeout(() => {
                    if (page.type === "overview") {
                        stage.innerHTML = overviewTemplate.innerHTML
                    } else {
                        stage.innerHTML =
                            `<iframe src="${page.url}" style="width:100%;height:100vh;border:none"></iframe>`
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

            function buildPages(tournaments) {
                return [
                    {
                        type: "overview"
                    },
                    ...tournaments.map(tournament => ({
                        type: "tournament",
                        key: tournament.id,
                        url: tournament.tv_url
                    }))
                ]
            }

            function scheduleRotation() {
                if (rotationTimer) {
                    clearInterval(rotationTimer)
                }

                rotationTimer = setInterval(next, rotationTime * 1000)
            }

            function sameTournamentSet(tournaments) {
                const current = pages
                    .filter(page => page.type === "tournament")
                    .map(page => page.key)
                const incoming = tournaments.map(tournament => tournament.id)

                return JSON.stringify(current) === JSON.stringify(incoming)
            }

            async function refreshConfig() {
                try {
                    const res = await fetch("{{ route('tv.rotation.data') }}", {
                        headers: {
                            "X-Requested-With": "XMLHttpRequest"
                        }
                    })

                    if (!res.ok) {
                        return
                    }

                    const data = await res.json()
                    const nextRotationTime = Number(data.rotationTime || 20)
                    const tournaments = Array.isArray(data.tournaments) ? data.tournaments : []
                    const overviewHtml = typeof data.overviewHtml === "string" ? data.overviewHtml : null

                    if (!sameTournamentSet(tournaments)) {
                        if (overviewHtml !== null) {
                            overviewTemplate.innerHTML = overviewHtml
                        }

                        pages = buildPages(tournaments)
                        index = Math.min(index, Math.max(pages.length - 1, 0))
                        showPage()
                    }

                    if (rotationTime !== nextRotationTime) {
                        rotationTime = nextRotationTime
                        scheduleRotation()
                    }
                } catch (error) {
                    console.error("TV Rotation Config Fehler", error)
                }
            }

            showPage()
            scheduleRotation()
            configPollTimer = setInterval(refreshConfig, 5000)
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
