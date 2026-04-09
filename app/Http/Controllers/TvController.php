<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TvTournament;
use App\Services\Group\GroupTableCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TvController extends Controller
{
    public function manage()
    {
        $tournaments = auth()->user()
            ->tournaments()
            ->where('status', '!=', 'archived')
            ->with('parent')
            ->orderBy('name')
            ->get();

        $selected = $this->currentSelectedTournamentIds();
        $selectedOrder = $this->sanitizeTournamentOrder($selected, $selected);
        $orderedTournaments = $this->orderTournamentsForManageView($tournaments, $selectedOrder);

        $rotationTime = TvTournament::where('user_id', auth()->id())
            ->orderBy('position')
            ->value('rotation_time') ?? 20;

        return view('admin.tv', compact(
            'orderedTournaments',
            'selected',
            'selectedOrder',
            'rotationTime',
        ));
    }

    public function save(Request $request)
    {
        $validated = $request->validate([
            'tournaments' => ['nullable', 'array'],
            'tournaments.*' => ['integer'],
            'ordered_tournaments' => ['nullable', 'array'],
            'ordered_tournaments.*' => ['integer'],
            'rotation_time' => ['nullable', 'integer', 'min:3', 'max:45'],
        ]);

        $tournaments = auth()->user()
            ->tournaments()
            ->where('status', '!=', 'archived')
            ->with('parent')
            ->get();

        $userTournamentIds = $tournaments
            ->pluck('id')
            ->toArray();

        $rotationTime = (int) ($validated['rotation_time'] ?? 20);
        $selected = collect($validated['tournaments'] ?? [])
            ->filter(fn($id) => in_array($id, $userTournamentIds))
            ->values()
            ->all();

        $preferredOrder = collect($validated['ordered_tournaments'] ?? [])
            ->filter(fn($id) => in_array($id, $userTournamentIds))
            ->values()
            ->all();

        $orderedIds = $this->sanitizeTournamentOrder($selected, $preferredOrder);

        $this->persistRotation($orderedIds, $rotationTime);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'TV Reihenfolge aktualisiert.',
                'ordered_tournaments' => $orderedIds,
            ]);
        }

        return back()->with('success', 'TV Programm gespeichert');
    }

    public function toggle(Tournament $tournament)
    {
        if ($tournament->user_id !== auth()->id()) {
            abort(403);
        }

        if ($tournament->status === 'archived') {
            return back()->with('error', 'Archivierte Turniere koennen nicht im TV angezeigt werden.');
        }

        $existingEntry = TvTournament::where('user_id', auth()->id())
            ->where('tournament_id', $tournament->id)
            ->first();

        if ($existingEntry) {
            $selectedIds = $this->currentSelectedTournamentIds();
            $orderedIds = array_values(array_filter($selectedIds, fn($id) => (int) $id !== (int) $tournament->id));
            $rotationTime = $this->currentRotationTime();
            $this->persistRotation($orderedIds, $rotationTime);

            return back()->with('success', 'Turnier aus dem TV entfernt.');
        }

        $tournaments = auth()->user()
            ->tournaments()
            ->where('status', '!=', 'archived')
            ->with('parent')
            ->get();

        $rotationTime = $this->currentRotationTime();
        $selectedIds = $this->currentSelectedTournamentIds();
        $orderedIds = $this->insertTournamentWithDefaultOrder($tournaments, $selectedIds, $tournament->id);
        $this->persistRotation($orderedIds, $rotationTime);

        return back()->with('success', 'Turnier zum TV hinzugefuegt.');
    }

    public function updateRotationTime(Request $request)
    {
        $validated = $request->validate([
            'rotation_time' => ['required', 'integer', 'min:3', 'max:45'],
        ]);

        $updated = TvTournament::where('user_id', auth()->id())
            ->update([
                'rotation_time' => (int) $validated['rotation_time'],
            ]);

        if ($updated === 0) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Lege zuerst ein Turnier fuer das TV fest.',
                ], 422);
            }

            return back()->with('error', 'Lege zuerst ein Turnier fuer das TV fest.');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Rotationszeit aktualisiert.',
                'rotation_time' => (int) $validated['rotation_time'],
            ]);
        }

        return back()->with('success', 'Rotationszeit aktualisiert.');
    }

    public function rotation()
    {
        $tournaments = $this->rotationTournaments();

        return view('tv.rotation', compact('tournaments'));
    }

    public function rotationData(): JsonResponse
    {
        $tournaments = $this->rotationTournaments();
        $rotationTime = TvTournament::where('user_id', auth()->id())
            ->orderBy('position')
            ->value('rotation_time') ?? 20;

        return response()->json([
            'rotationTime' => (int) $rotationTime,
            'tournaments' => $tournaments->map(function (Tournament $tournament) {
                return [
                    'id' => $tournament->id,
                    'name' => $tournament->name,
                    'public_id' => $tournament->public_id,
                    'parent_id' => $tournament->parent_id,
                    'follow_url' => url('/follow/' . $tournament->public_id),
                    'tv_url' => url('/tv/' . $tournament->public_id),
                ];
            })->values(),
            'overviewHtml' => view('tv.partials.overview', [
                'tournaments' => $tournaments,
            ])->render(),
        ]);
    }

    public function rotationConfig(): JsonResponse
    {
        $tournaments = $this->rotationTournaments();
        $rotationTime = TvTournament::where('user_id', auth()->id())
            ->orderBy('position')
            ->value('rotation_time') ?? 20;

        $overviewHtml = view('tv.partials.overview', [
            'tournaments' => $tournaments,
        ])->render();

        $pages = collect([
            ['type' => 'overview'],
        ])->concat($tournaments->map(function (Tournament $tournament) {
            return [
                'type' => 'tournament',
                'public_id' => $tournament->public_id,
                'name' => $tournament->name,
            ];
        }));

        $signature = md5($overviewHtml . json_encode($pages) . $rotationTime);

        return response()->json([
            'rotation_time' => (int) $rotationTime,
            'pages' => $pages->values(),
            'overview_html' => $overviewHtml,
            'signature' => $signature,
        ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, proxy-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function show(Tournament $tournament)
    {
        if ($tournament->user_id !== auth()->id()) {
            abort(403);
        }

        $tournament->load([
            'groups.players',
            'groups.games.player1',
            'groups.games.player2',
            'games.player1',
            'games.player2',
            'games.winner',
        ]);

        if ($tournament->status === 'draft') {
            return view('tv.draft', compact('tournament'));
        }

        if ($tournament->status === 'group_running') {
            $groupData = [];

            foreach ($tournament->groups as $group) {
                $table = app(GroupTableCalculator::class)
                    ->calculate($group);

                $lastGame = $group->games
                    ->whereNotNull('winner_id')
                    ->sortByDesc('updated_at')
                    ->first();

                $currentGame = $group->games
                    ->whereNull('winner_id')
                    ->sortBy('id')
                    ->first();

                $nextGame = $group->games
                    ->whereNull('winner_id')
                    ->sortBy('id')
                    ->skip(1)
                    ->first();

                $groupData[] = [
                    'group' => $group,
                    'table' => $table,
                    'lastGame' => $lastGame,
                    'currentGame' => $currentGame,
                    'nextGame' => $nextGame,
                ];
            }

            return view('tv.show', compact(
                'tournament',
                'groupData',
            ));
        }

        if (in_array($tournament->status, ['ko_running', 'finished'])) {
            $rounds = $this->visibleTvRounds($tournament);

            return view('tv.bracket', compact(
                'tournament',
                'rounds',
            ));
        }

        abort(404);
    }

    private function rotationTournaments()
    {
        return TvTournament::with('tournament')
            ->where('user_id', auth()->id())
            ->orderBy('position')
            ->get()
            ->pluck('tournament')
            ->filter(function ($tournament) {
                return $tournament
                    && $tournament->user_id === auth()->id()
                    && $tournament->status !== 'archived';
            })
            ->values();
    }

    private function currentSelectedTournamentIds(): array
    {
        return TvTournament::where('user_id', auth()->id())
            ->orderBy('position')
            ->pluck('tournament_id')
            ->filter()
            ->values()
            ->all();
    }

    private function currentRotationTime(): int
    {
        return (int) (TvTournament::where('user_id', auth()->id())
            ->orderBy('position')
            ->value('rotation_time') ?? 20);
    }

    private function persistRotation(array $orderedIds, int $rotationTime): void
    {
        TvTournament::where('user_id', auth()->id())->delete();

        foreach (array_values($orderedIds) as $index => $id) {
            TvTournament::create([
                'user_id' => auth()->id(),
                'tournament_id' => $id,
                'position' => $index + 1,
                'rotation_time' => $rotationTime,
            ]);
        }
    }

    private function sanitizeTournamentOrder(array $selectedIds, array $preferredOrder = []): array
    {
        $selectedLookup = collect($selectedIds)
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        $preferred = collect($preferredOrder)
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $selectedLookup->contains($id))
            ->values();

        return $preferred
            ->concat($selectedLookup->diff($preferred))
            ->values()
            ->all();
    }

    private function insertTournamentWithDefaultOrder($tournaments, array $selectedIds, int $newTournamentId): array
    {
        $tournamentMap = $tournaments->keyBy('id');
        $current = collect($selectedIds)
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $tournamentMap->has($id))
            ->unique()
            ->values();

        if ($current->contains($newTournamentId)) {
            return $current->all();
        }

        $tournament = $tournamentMap->get($newTournamentId);

        if (! $tournament) {
            return $current->all();
        }

        if ($tournament->parent_id && $current->contains((int) $tournament->parent_id)) {
            $parentIndex = $current->search((int) $tournament->parent_id);

            if ($parentIndex !== false) {
                $insertIndex = $parentIndex + 1;

                while (isset($current[$insertIndex])) {
                    $candidate = $tournamentMap->get($current[$insertIndex]);

                    if (! $candidate || (int) $candidate->parent_id !== (int) $tournament->parent_id) {
                        break;
                    }

                    $insertIndex++;
                }

                $current->splice($insertIndex, 0, [$newTournamentId]);

                return $current->values()->all();
            }
        }

        if (! $tournament->parent_id) {
            $childIds = $tournaments
                ->filter(fn($candidate) => (int) $candidate->parent_id === (int) $tournament->id)
                ->pluck('id')
                ->map(fn($id) => (int) $id);

            $childPositions = $current
                ->map(fn($id, $index) => $childIds->contains((int) $id) ? $index : null)
                ->filter(fn($index) => $index !== null)
                ->values();

            if ($childPositions->isNotEmpty()) {
                $insertIndex = (int) $childPositions->first();
                $current->splice($insertIndex, 0, [$newTournamentId]);

                return $current->values()->all();
            }
        }

        $current->push($newTournamentId);

        return $current->values()->all();
    }

    private function orderTournamentsForManageView($tournaments, array $selectedOrder)
    {
        $selectedLookup = collect($selectedOrder)
            ->flip();

        $selectedTournaments = collect($selectedOrder)
            ->map(fn($id) => $tournaments->firstWhere('id', $id))
            ->filter();

        $unselectedTournaments = $tournaments
            ->filter(fn($tournament) => ! $selectedLookup->has($tournament->id))
            ->sortBy('name')
            ->values();

        return $selectedTournaments
            ->concat($unselectedTournaments)
            ->values();
    }

    private function visibleTvRounds(Tournament $tournament)
    {
        $koGames = $tournament->games
            ->whereNull('group_id')
            ->sortBy([
                ['round', 'asc'],
                ['position', 'asc'],
            ]);

        $mainRounds = $koGames
            ->where('is_third_place', false)
            ->groupBy('round')
            ->sortKeys();

        if ($mainRounds->isEmpty()) {
            return collect();
        }

        $thirdPlaceRounds = $koGames
            ->where('is_third_place', true)
            ->groupBy('round')
            ->sortKeys();

        $firstUnfinishedRound = null;

        foreach ($mainRounds as $roundNumber => $games) {
            if ($games->contains(fn($game) => $game->winner_id === null)) {
                $firstUnfinishedRound = (int) $roundNumber;
                break;
            }
        }

        $alwaysVisibleRoundNumbers = $mainRounds
            ->filter(fn($games) => $games->count() <= 4)
            ->keys()
            ->map(fn($roundNumber) => (int) $roundNumber);

        $activeRoundNumbers = $mainRounds
            ->filter(fn($_, $roundNumber) => $firstUnfinishedRound !== null && (int) $roundNumber >= $firstUnfinishedRound)
            ->keys()
            ->map(fn($roundNumber) => (int) $roundNumber);

        $visibleRoundNumbers = $alwaysVisibleRoundNumbers
            ->concat($activeRoundNumbers)
            ->unique()
            ->sort()
            ->values();

        $roundsByNumber = collect();

        foreach ($visibleRoundNumbers as $roundNumber) {
            $games = $mainRounds->get($roundNumber);

            if ($games) {
                $roundsByNumber->put($roundNumber, $games->values());
            }
        }

        $thirdPlaceGames = $thirdPlaceRounds->flatten(1)->values();

        if ($thirdPlaceGames->isNotEmpty() && $roundsByNumber->isNotEmpty()) {
            $lastVisibleRound = $roundsByNumber->keys()->last();
            $roundsByNumber->put(
                $lastVisibleRound,
                $roundsByNumber->get($lastVisibleRound)->concat($thirdPlaceGames)->values(),
            );
        }

        return $roundsByNumber;
    }
}
