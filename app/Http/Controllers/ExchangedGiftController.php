<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use App\Models\ExchangeIdea;
use App\Models\ExchangeAssignment;
use App\Models\ExchangeParticipant;
use App\Models\ExchangeProject;
use App\Models\ExchangeAwardVote;

class ExchangedGiftController extends Controller
{
    /*
     |--------------------------------------------------------------------------
     | LANDING + MENU
     |--------------------------------------------------------------------------
     */

    public function landing()
    {
        return Inertia::render('ExchangedGift/Landing');
    }

    public function menu(Request $request, ExchangeProject $project)
    {
        return Inertia::render('ExchangedGift/GroupServices', [
            'project' => $project,
        ]);
    }

    /*
     |--------------------------------------------------------------------------
     | VERIFY SANTA SECRET CODE
     |--------------------------------------------------------------------------
     */

    public function verifySantaCode(Request $request)
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:exchange_projects,id'],
            'code'       => ['required', 'string'],
        ]);

        $project = ExchangeProject::find($data['project_id']);

        $valid = $project && hash_equals(
            (string) $project->santa_secret_code,
            (string) $data['code']
        );

        return response()->json(['valid' => $valid]);
    }

    /*
     |--------------------------------------------------------------------------
     | PAGES
     |--------------------------------------------------------------------------
     */

    public function index(Request $request, ExchangeProject $project)
    {
        $projects = ExchangeProject::orderBy('name')->get();

        if ($projects->isEmpty()) {
            $defaultName = 'OD-MISPS 2025';

            $project = ExchangeProject::create([
                'name'              => $defaultName,
                'slug'              => $this->generateProjectSlug($defaultName),
                'organizer_name'    => null,
                'organizer_email'   => null,
                'unique_code'       => $this->generateUniqueCode(),
                'santa_secret_code' => $this->generateUniqueSantaCode('santa_secret_code'),
            ]);

            $projects = collect([$project]);
        }

        return Inertia::render('ExchangedGift/GenerateNames', [
            'projects'           => $projects->values(),
            'currentProjectId'   => $project->id,
            'currentProjectSlug' => $project->slug,
            'currentPage'        => 'classic',
        ]);
    }

    public function ideas(Request $request, ExchangeProject $project)
    {
        $projects = ExchangeProject::orderBy('name')->get();

        if ($projects->isEmpty()) {
            $defaultName = 'OD-MISPS 2025';

            $project = ExchangeProject::create([
                'name'              => $defaultName,
                'slug'              => $this->generateProjectSlug($defaultName),
                'organizer_name'    => null,
                'organizer_email'   => null,
                'unique_code'       => $this->generateUniqueCode(),
                'santa_secret_code' => $this->generateUniqueSantaCode('santa_secret_code'),
            ]);

            $projects = collect([$project]);
        }

        return Inertia::render('ExchangedGift/GiftIdeas', [
            'projects'           => $projects->values(),
            'currentProjectId'   => $project->id,
            'currentProjectSlug' => $project->slug,
            'currentPage'        => 'ideas',
        ]);
    }

    public function awards(Request $request, ExchangeProject $project = null)
    {
        $projects = ExchangeProject::orderBy('name')->get();

        if ($projects->isEmpty()) {
            $defaultName = 'OD-MISPS 2025';

            $default = ExchangeProject::create([
                'name'              => $defaultName,
                'slug'              => $this->generateProjectSlug($defaultName),
                'organizer_name'    => null,
                'organizer_email'   => null,
                'unique_code'       => $this->generateUniqueCode(),
                'santa_secret_code' => $this->generateUniqueSantaCode('santa_secret_code'),
            ]);

            $projects = collect([$default]);
            $project  = $default;
        }

        $currentProject = $project ?: $projects->first();

        return Inertia::render('ExchangedGift/Awards', [
            'projects'           => $projects->values(),
            'currentProjectId'   => $currentProject->id,
            'currentProjectSlug' => $currentProject->slug,
            'currentPage'        => 'awards',
        ]);
    }

    public function wishlist(Request $request, ExchangeProject $project = null)
    {
        $projects = ExchangeProject::orderBy('name')->get();

        if ($projects->isEmpty()) {
            $defaultName = 'OD-MISPS 2025';

            $default = ExchangeProject::create([
                'name'              => $defaultName,
                'slug'              => $this->generateProjectSlug($defaultName),
                'organizer_name'    => null,
                'organizer_email'   => null,
                'unique_code'       => $this->generateUniqueCode(),
                'santa_secret_code' => $this->generateUniqueSantaCode('santa_secret_code'),
            ]);

            $projects = collect([$default]);
            $project  = $default;
        }

        $currentProject = $project ?: $projects->first();

        return Inertia::render('ExchangedGift/Wishlist', [
            'projects'           => $projects->values(),
            'currentProjectId'   => $currentProject->id,
            'currentProjectSlug' => $currentProject->slug,
            'currentPage'        => 'wishlist',
        ]);
    }



    // ✅ keep this in sync with your Awards.vue awardCategories keys
private array $allowedAwardKeys = [
    // ---------------- ATTENDANCE ----------------
    "early_bird_of_the_year",
    "laging_late",
    "best_in_half_day",
    "best_in_lunchbreak",

    // ---------------- PERSONALITY ----------------
    "best_extra_rice",
    "most_lutang",
    "most_madaldal",
    "best_in_pagpapanggap",

    // old nonchalant + new split nonchalant
    "nonchalant_of_the_year",
    "nonchalant_king",
    "nonchalant_queen",

    "laging_naka_headphone_award",
    "toyo_award",
    "adik_sa_energy_drink",
    "coffeeholic_award",
    "galit_na_galit_gustong_manaket_award",
    "pinaka_oa",

    // new additions
    "reklamador_of_the_year",
    "joker_of_the_year",
    "soft_spoken",
    "boss_survivor",
    "madaming_kamukha",
    "paimportante_award",
    "saks_lang",
    "overthinker_award",
    "queen_of_selfies",

    "marites_of_the_year",
    "buraot_of_the_year",
    "jollibee_of_the_year",

    // old banyo + new split banyo
    "banyo_king_queen",
    "banyo_king",
    "banyo_queen",

    // ---------------- FOOD ----------------
    "merienda_queen_king",
    "budol_queen_king",
    "best_in_pares",
    "alak_ay_tubig_award",
    "pinaka_malaking_tiyan_award",

    // new additions
    "last_piece_grabber",

    // ---------------- MONEY ----------------
    "gastador_of_the_year",
    "pinaka_kuripot_award",
    "best_in_utang",

    // new additions
    "may_gc_ba",
    "wala_nang_budget",
];


    /* --------------------------------------------------------------------------
     | AWARDS: STATUS
     * -------------------------------------------------------------------------- */
    public function awardsStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:exchange_projects,id'],
        ]);

        $progress = $this->getAwardsProgress((int) $data['project_id']);

        return response()->json($progress);
    }

    /* --------------------------------------------------------------------------
     | AWARDS: VERIFY CODE (SERVER-SIDE)
     * -------------------------------------------------------------------------- */
    public function verifyAwardsCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:exchange_projects,id'],
            'code'       => ['required', 'string'],
        ]);

        $projectId = (int) $data['project_id'];
        $code = strtoupper(trim($data['code']));

        $progress = $this->getAwardsProgress($projectId);

        // if closed, still allow verify so user can view results
        $voter = ExchangeParticipant::where('project_id', $projectId)
            ->where('list_type', 'classic')
            ->where('unique_code', $code)
            ->first();

        if (! $voter) {
            return response()->json([
                'valid' => false,
                'message' => 'Code not found for this group.',
                ...$progress,
            ], 422);
        }

        $hasVoted = ExchangeAwardVote::where('project_id', $projectId)
            ->where('voter_participant_id', $voter->id)
            ->exists();

        return response()->json([
            'valid' => true,
            'participant' => [
                'id' => $voter->id,
                'name' => $voter->name,
            ],
            'has_voted' => $hasVoted,
            ...$progress,
        ]);
    }

    /* --------------------------------------------------------------------------
     | AWARDS: SUBMIT VOTES (ONE-TIME + CLOSE WHEN ALL VOTED)
     * -------------------------------------------------------------------------- */
    public function submitAwardsVotes(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:exchange_projects,id'],
            'voter_code' => ['required', 'string'],
            'votes'      => ['required', 'array', 'min:1'],
            'votes.*.award_key'  => ['required', 'string', Rule::in($this->allowedAwardKeys)],
            'votes.*.nominee_id' => ['required', 'integer', 'exists:exchange_participants,id'],
        ]);

        $projectId = (int) $data['project_id'];
        $code = strtoupper(trim($data['voter_code']));

        $progress = $this->getAwardsProgress($projectId);

        if (!empty($progress['session_closed'])) {
            return response()->json([
                'message' => 'Voting session is already closed.',
                ...$progress,
            ], 422);
        }

        $voter = ExchangeParticipant::where('project_id', $projectId)
            ->where('list_type', 'classic')
            ->where('unique_code', $code)
            ->first();

        if (! $voter) {
            return response()->json([
                'message' => 'Invalid unique code. Please check the email you received.',
                ...$progress,
            ], 422);
        }

        // ✅ one-time voting per code
        $alreadyVoted = ExchangeAwardVote::where('project_id', $projectId)
            ->where('voter_participant_id', $voter->id)
            ->exists();

        if ($alreadyVoted) {
            return response()->json([
                'message' => 'This unique code has already voted. Voting is one-time only.',
                ...$this->getAwardsProgress($projectId),
            ], 422);
        }

        // ✅ ensure nominees belong to same project + list_type classic
        $nomineeIds = collect($data['votes'])->pluck('nominee_id')->unique()->values();
        $validNomineeCount = ExchangeParticipant::where('project_id', $projectId)
            ->where('list_type', 'classic')
            ->whereIn('id', $nomineeIds)
            ->count();

        if ($validNomineeCount !== $nomineeIds->count()) {
            return response()->json([
                'message' => 'Some nominees are invalid for this group.',
                ...$this->getAwardsProgress($projectId),
            ], 422);
        }

        DB::transaction(function () use ($data, $projectId, $voter) {
            $now = now();
            $rows = [];

            foreach ($data['votes'] as $vote) {
                $rows[] = [
                    'project_id'             => $projectId,
                    'award_key'              => $vote['award_key'],
                    'voter_participant_id'   => $voter->id,
                    'nominee_participant_id' => $vote['nominee_id'],
                    'created_at'             => $now,
                    'updated_at'             => $now,
                ];
            }

            ExchangeAwardVote::insert($rows);
        });

        $newProgress = $this->getAwardsProgress($projectId);

        return response()->json([
            'message' => $newProgress['session_closed']
                ? 'Your votes were saved. Voting is now CLOSED (all participants voted).'
                : 'Your award votes have been saved. Thank you!',
            ...$newProgress,
        ]);
    }

    /* --------------------------------------------------------------------------
     | AWARDS: RESULTS
     * -------------------------------------------------------------------------- */
    public function awardsResults(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:exchange_projects,id'],
        ]);

        $projectId = (int) $data['project_id'];
        $progress = $this->getAwardsProgress($projectId);

        $rows = ExchangeAwardVote::select(
                'award_key',
                'nominee_participant_id',
                DB::raw('COUNT(*) as votes')
            )
            ->where('project_id', $projectId)
            ->groupBy('award_key', 'nominee_participant_id')
            ->orderBy('award_key')
            ->orderByDesc('votes')
            ->get();

        $nomineeIds = $rows->pluck('nominee_participant_id')->unique()->values();
        $nameMap = ExchangeParticipant::whereIn('id', $nomineeIds)
            ->pluck('name', 'id');

        $byAward = [];
        foreach ($rows as $r) {
            $byAward[$r->award_key][] = [
                'id' => (int) $r->nominee_participant_id,
                'name' => $nameMap[$r->nominee_participant_id] ?? ('#' . $r->nominee_participant_id),
                'votes' => (int) $r->votes,
            ];
        }

        $results = [];
        foreach ($byAward as $awardKey => $nominees) {
            $max = collect($nominees)->max('votes') ?? 0;
            $winners = array_values(array_filter($nominees, fn ($n) => $n['votes'] === $max));

            $results[$awardKey] = [
                'total_votes' => array_sum(array_column($nominees, 'votes')),
                'max_votes' => $max,
                'is_tie' => count($winners) > 1,
                'winners' => $winners,
                'nominees' => $nominees, // breakdown
            ];
        }

        return response()->json([
            ...$progress,
            'results' => $results,
        ]);
    }

    /* --------------------------------------------------------------------------
     | HELPERS
     * -------------------------------------------------------------------------- */
    private function getAwardsProgress(int $projectId): array
    {
        $participantsTotal = ExchangeParticipant::where('project_id', $projectId)
            ->where('list_type', 'classic')
            ->count();

        $votersTotal = ExchangeAwardVote::where('project_id', $projectId)
            ->distinct('voter_participant_id')
            ->count('voter_participant_id');

        $sessionClosed = ($participantsTotal > 0) && ($votersTotal >= $participantsTotal);

        return [
            'participants_total' => $participantsTotal,
            'voters_total' => $votersTotal,
            'session_closed' => $sessionClosed,
        ];
    }

    /*
     |--------------------------------------------------------------------------
     | HELPERS
     |--------------------------------------------------------------------------
     */

    protected function generateUniqueCode(int $length = 8): string
    {
        do {
            $code = strtoupper(Str::random($length));
        } while (ExchangeProject::where('unique_code', $code)->exists());

        return $code;
    }

    protected function generateUniqueSantaCode(string $column, int $length = 10): string
    {
        do {
            $raw  = strtoupper(Str::random($length));
            $code = substr($raw, 0, 4) . '-' . substr($raw, 4, 4);
        } while (ExchangeProject::where($column, $code)->exists());

        return $code;
    }

    protected function generateParticipantCode(int $length = 8): string
    {
        do {
            $code = strtoupper(Str::random($length));
        } while (ExchangeParticipant::where('unique_code', $code)->exists());

        return $code;
    }


    protected function generateProjectSlug(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') $base = 'group';

        $slug = $base;
        $counter = 2;

        while (ExchangeProject::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /*
     |--------------------------------------------------------------------------
     | JSON: COMMON DATA (participants + classic assignments + budget stats)
     |--------------------------------------------------------------------------
     */

    public function data(Request $request): JsonResponse
    {
        $projectId = $request->query('project_id') ?? ExchangeProject::query()->min('id');
        $listType  = $request->query('list_type', 'classic');

        if (! $projectId) {
            return response()->json([
                'participants' => [],
                'assignments'  => [],
                'budget_stats' => (object) [],
            ]);
        }

        $participants = ExchangeParticipant::where('project_id', $projectId)
            ->when($listType, fn ($q) => $q->where('list_type', $listType))
            ->orderBy('id')
            ->get();

        $assignmentsGrouped = [];
        if ($listType === 'classic') {
            $assignmentsGrouped = ExchangeAssignment::with(['giver', 'receiver'])
                ->where('project_id', $projectId)
                ->where('mode', 'multi')
                ->get()
                ->groupBy('giver_id')
                ->map(function ($items) {
                    return [
                        'giverId'    => $items->first()->giver_id,
                        'giverName'  => $items->first()->giver->name,
                        'recipients' => $items->sortBy('gift_number')->pluck('receiver.name')->values(),
                    ];
                })
                ->values()
                ->toArray();
        }

        $budgetStats = $participants
            ->whereNotNull('budget_vote')
            ->groupBy('budget_vote')
            ->map->count();

        return response()->json([
            'participants' => $participants,
            'assignments'  => $assignmentsGrouped,
            'budget_stats' => $budgetStats,
        ]);
    }

    /*
     |--------------------------------------------------------------------------
     | PROJECTS
     |--------------------------------------------------------------------------
     */

    public function storeProject(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'organizer_name'  => ['nullable', 'string', 'max:255'],
            'organizer_email' => ['nullable', 'email', 'max:255'],
        ]);

        $data['unique_code']       = $this->generateUniqueCode();
        $data['santa_secret_code'] = $this->generateUniqueSantaCode('santa_secret_code');
        $data['slug']              = $this->generateProjectSlug($data['name']);

        $project = ExchangeProject::create($data);

        if (!empty($project->organizer_email)) {
            try {
                Mail::send(
                    'emails.santa_secret_code',
                    ['project' => $project],
                    function ($message) use ($project) {
                        $message->to($project->organizer_email, $project->organizer_name ?: null)
                            ->subject($project->name . ' | Christmas Party Planner | JKLeonardo Tech');
                    }
                );
            } catch (\Throwable $e) {
                Log::error('Failed to send Santa Secret Code email: ' . $e->getMessage());
            }
        }

        return response()->json($project, 201);
    }

    public function joinGroup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $code = strtoupper(trim($validated['code']));

        $project = ExchangeProject::where('unique_code', $code)->first();

        if (! $project) {
            return response()->json([
                'message' => 'Group not found. Please check the code and try again.',
            ], 404);
        }

        return response()->json([
            'project_id'   => $project->id,
            'project_name' => $project->name,
            'project_slug' => $project->slug,
        ]);
    }

    /*
     |--------------------------------------------------------------------------
     | PARTICIPANTS
     |--------------------------------------------------------------------------
     */

public function storeParticipant(Request $request): JsonResponse
{
    $data = $request->validate([
        'project_id' => ['required', 'integer', 'exists:exchange_projects,id'],
        'name'       => ['required', 'string', 'max:255'],
        'email'      => [
            'required',
            'email',
            'max:255',
            Rule::unique('exchange_participants', 'email')
                ->where(function ($q) use ($request) {
                    $q->where('project_id', $request->input('project_id'))
                      ->where('list_type', $request->input('list_type'));
                }),
        ],

        // ⬅️ Codename is now REQUIRED
        'codename'   => ['required', 'string', 'min:2', 'max:40'],

        'list_type'   => ['required', 'string', Rule::in(['classic', 'something'])],
        'budget_vote' => ['nullable', 'integer', Rule::in([100, 200, 300, 400, 500, 600, 700, 800, 900, 1000])],

            'wishlist_1'  => ['nullable', 'string', 'max:2000'],
            'wishlist_2'  => ['nullable', 'string', 'max:2000'],
            'wishlist_3'  => ['nullable', 'string', 'max:2000'],
            'wishlist_1_description' => ['nullable', 'string'],
            'wishlist_2_description' => ['nullable', 'string'],
            'wishlist_3_description' => ['nullable', 'string'],
    ]);

    // 🔹 Normalize codename (trim + collapse spaces)
    $data['codename'] = trim(preg_replace('/\s+/', ' ', $data['codename']));

    // 🔹 Safe characters only
    if (!preg_match('/^[\pL\pN _\-]+$/u', $data['codename'])) {
        return response()->json([
            'message' => 'Codename can only contain letters, numbers, spaces, hyphen, and underscore.',
            'errors'  => ['codename' => ['Invalid codename characters.']],
        ], 422);
    }

    // 🔹 Enforce codename uniqueness inside same project + list_type (case-insensitive)
    $exists = ExchangeParticipant::where('project_id', $data['project_id'])
        ->where('list_type', $data['list_type'])
        ->whereNotNull('codename')
        ->whereRaw('LOWER(codename) = ?', [Str::lower($data['codename'])])
        ->exists();

    if ($exists) {
        return response()->json([
            'message' => 'That codename is already taken in this group. Please choose another one.',
            'errors'  => ['codename' => ['Codename already taken.']],
        ], 422);
    }

    // 🔹 Generate unique participant code
    $data['unique_code'] = $this->generateParticipantCode();

    // Fetch project for the email
    $project = ExchangeProject::find($data['project_id']);

    $participant = ExchangeParticipant::create($data);

    // If classic list changes, clear classic assignments
    if ($data['list_type'] === 'classic') {
        ExchangeAssignment::where('project_id', $data['project_id'])->delete();
    }

    // 🔹 Send welcome email with unique code (for awards / games)
    if (!empty($participant->email)) {
        try {
            Mail::send(
                'emails.participant_welcome_code',
                [
                    'participant' => $participant,
                    'project'     => $project,
                ],
                function ($message) use ($participant, $project) {
                    $message->to($participant->email, $participant->name)
                            ->subject($project->name . ' | Christmas Party Planner | JKLeonardo Tech');
                }
            );
        } catch (\Throwable $e) {
            Log::error('Failed to send participant welcome email: ' . $e->getMessage());
        }
    }

    return response()->json($participant, 201);
}


    public function deleteParticipant(ExchangeParticipant $participant): JsonResponse
    {
        if ($participant->list_type === 'classic') {
            ExchangeAssignment::where('project_id', $participant->project_id)
                ->where(function ($q) use ($participant) {
                    $q->where('giver_id', $participant->id)
                      ->orWhere('receiver_id', $participant->id);
                })
                ->delete();
        }

        ExchangeIdea::where('project_id', $participant->project_id)
            ->where('participant_id', $participant->id)
            ->delete();

        $participant->delete();

        return response()->json(['message' => 'Deleted']);
    }

    /*
     |--------------------------------------------------------------------------
     | CLASSIC ASSIGNMENTS (Small Gifts)
     |--------------------------------------------------------------------------
     */

    public function generateAssignments(Request $request): JsonResponse
    {
        $projectId = $request->input('project_id');
        $listType  = 'classic';
        $mode = 'multi';

        ExchangeAssignment::where('project_id', $projectId)
            ->where('mode', $mode)
            ->delete();

        $participants = ExchangeParticipant::where('project_id', $projectId)
            ->where('list_type', $listType)
            ->orderBy('id')
            ->get()
            ->values();

        $n = $participants->count();

        if ($n < 2) {
            return response()->json([
                'message' => 'At least 2 participants are required to generate assignments.',
            ], 422);
        }

        $giftsEach = min(5, $n - 1);

        ExchangeAssignment::where('project_id', $projectId)->delete();

        $rows = [];
        $now  = now();

        foreach ($participants as $i => $giver) {
            for ($giftNumber = 1; $giftNumber <= $giftsEach; $giftNumber++) {
                $receiverIndex = ($i + $giftNumber) % $n;
                $receiver      = $participants[$receiverIndex];

                $rows[] = [
                    'project_id'  => $projectId,
                    'giver_id'    => $giver->id,
                    'mode'        => $mode,
                    'receiver_id' => $receiver->id,
                    'gift_number' => $giftNumber,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }
        }

        if (!empty($rows)) {
            ExchangeAssignment::insert($rows);
        }

        $grouped = ExchangeAssignment::with(['giver', 'receiver'])
            ->where('project_id', $projectId)
            ->get()
            ->groupBy('giver_id')
            ->map(function ($items) {
                return [
                    'giverId'    => $items->first()->giver_id,
                    'giverName'  => $items->first()->giver->name,
                    'recipients' => $items->sortBy('gift_number')->pluck('receiver.name')->values(),
                ];
            })
            ->values()
            ->toArray();

        return response()->json([
            'assignments' => $grouped,
            'gifts_each'  => $giftsEach,
            'count'       => $n,
        ]);
    }

    /*
     |--------------------------------------------------------------------------
     | "SOMETHING" IDEAS (GiftIdeas.vue)
     |--------------------------------------------------------------------------
     */

    public function ideasData(Request $request): JsonResponse
    {
        $projectId = $request->query('project_id');
        $listType  = $request->query('list_type', 'classic');

        if (! $projectId) {
            return response()->json([
                'participants' => [],
                'ideas'        => [],
            ]);
        }

        $participants = ExchangeParticipant::where('project_id', $projectId)
            ->where('list_type', $listType)
            ->orderBy('id')
            ->get();

        $ideas = ExchangeIdea::where('project_id', $projectId)
            ->get()
            ->keyBy('participant_id');

        $ideasList = $participants->map(function ($p) use ($ideas) {
            $row = $ideas->get($p->id);
            if (! $row) return null;

            return [
                'id'        => $p->id,
                'name'      => $p->name,
                'something' => $row->something,
                'phrase'    => $row->phrase,
            ];
        })->filter()->values()->all();

        return response()->json([
            'participants' => $participants,
            'ideas'        => $ideasList,
        ]);
    }

    public function saveIdeas(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id'             => ['required', 'exists:exchange_projects,id'],
            'ideas'                  => ['required', 'array'],
            'ideas.*.participant_id' => ['required', 'exists:exchange_participants,id'],
            'ideas.*.something'      => ['required', 'string', 'max:255'],
            'ideas.*.phrase'         => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($data) {
            ExchangeIdea::where('project_id', $data['project_id'])->delete();

            $now  = now();
            $rows = [];

            foreach ($data['ideas'] as $item) {
                $rows[] = [
                    'project_id'     => $data['project_id'],
                    'participant_id' => $item['participant_id'],
                    'something'      => $item['something'],
                    'phrase'         => $item['phrase'] ?? null,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
            }

            if (! empty($rows)) {
                ExchangeIdea::insert($rows);
            }
        });

        return response()->json(['status' => 'ok']);
    }

    public function sendIdeaEmail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:exchange_projects,id'],
            'email'      => ['required', 'email'],
            'list_type'  => ['nullable', 'string', Rule::in(['classic', 'something'])],
        ]);

        $listType = $data['list_type'] ?? 'classic';
        $project  = ExchangeProject::findOrFail($data['project_id']);

        $participant = ExchangeParticipant::where('project_id', $project->id)
            ->where('list_type', $listType)
            ->where('email', $data['email'])
            ->first();

        if (! $participant) {
            return response()->json([
                'message' => 'No participant found with that email for this project.',
            ], 404);
        }

        $idea = ExchangeIdea::where('project_id', $project->id)
            ->where('participant_id', $participant->id)
            ->first();

        if (! $idea) {
            return response()->json([
                'message' => 'No theme has been generated yet for this participant.',
            ], 404);
        }

        $budgetWinner = $this->getBudgetWinnerForProject($project->id);

        Mail::send('emails.idea_theme', [
            'participant' => $participant,
            'idea'        => $idea,
            'project'     => $project,
            'budgetWinner'  => $budgetWinner,
        ], function ($message) use ($participant, $project) {
            $message->to($participant->email, $participant->name)
                        ->subject($project->name . ' | Christmas Party Planner | JKLeonardo Tech');
        });

        return response()->json([
            'message' => 'Your theme has been emailed. Please check your inbox.',
        ]);
    }

    public function sendAllIdeaEmails(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:exchange_projects,id'],
            'list_type'  => ['nullable', 'string', Rule::in(['classic', 'something'])],
        ]);

        $listType = $data['list_type'] ?? 'classic';
        $project  = ExchangeProject::findOrFail($data['project_id']);

        $participants = ExchangeParticipant::where('project_id', $project->id)
            ->where('list_type', $listType)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get();

        if ($participants->isEmpty()) {
            return response()->json([
                'message' => 'No participants with email found for this project.',
                'sent'    => 0,
            ]);
        }

        $ideas = ExchangeIdea::where('project_id', $project->id)
            ->get()
            ->keyBy('participant_id');

        $budgetWinner = $this->getBudgetWinnerForProject($project->id);

        $sent = 0;

        foreach ($participants as $participant) {
            $idea = $ideas->get($participant->id);
            if (! $idea) continue;

            Mail::send('emails.idea_theme', [
                'participant' => $participant,
                'idea'        => $idea,
                'project'     => $project,
                'budgetWinner'  => $budgetWinner,
            ], function ($message) use ($participant, $project) {
                $message->to($participant->email, $participant->name)
                        ->subject($project->name . ' | Christmas Party Planner | JKLeonardo Tech');
            });

            $sent++;
        }

        return response()->json([
            'message' => "Themes emailed to {$sent} participant(s).",
            'sent'    => $sent,
        ]);
    }

    /* --------------------------------------------------------------------------
     | SECRET SANTA (1-to-1)
     |-------------------------------------------------------------------------- */

    public function secretSanta(Request $request, ExchangeProject $project = null)
    {
        $projects = ExchangeProject::orderBy('name')->get();

        if ($projects->isEmpty()) {
            $defaultName = 'OD-MISPS 2025';

            $default = ExchangeProject::create([
                'name'              => $defaultName,
                'slug'              => $this->generateProjectSlug($defaultName),
                'organizer_name'    => null,
                'organizer_email'   => null,
                'unique_code'       => $this->generateUniqueCode(),
                'santa_secret_code' => $this->generateUniqueSantaCode('santa_secret_code'),
            ]);

            $projects = collect([$default]);
            $project = $default;
        }

        $currentProject = $project ?: $projects->first();

        return Inertia::render('ExchangedGift/SecretSanta', [
            'projects'           => $projects->values(),
            'currentProjectId'   => $currentProject->id,
            'currentProjectSlug' => $currentProject->slug,
            'currentPage'        => 'secret-santa',
        ]);
    }

    public function secretSantaData(Request $request): JsonResponse
    {
        $projectId = $request->query('project_id');

        if (!$projectId) {
            return response()->json([
                'assigned_givers' => [],
                'secret_pairs'    => [],
            ]);
        }

        $assignments = ExchangeAssignment::with('receiver')
            ->where('project_id', $projectId)
            ->where('mode', 'secret')
            ->get();

        $giverIds = $assignments->pluck('giver_id')->values();
        $totalBought = $assignments->where('is_bought', true)->count();

        $pairs = $assignments->map(function ($row) {
            $r = $row->receiver;

            return [
                'giver_id'  => $row->giver_id,
                'is_bought' => (bool) $row->is_bought,
                'receiver'  => $r ? [
                    'id'                          => $r->id,
                    'name'                        => $r->name,
                    'codename'                    => $r->codename,
                    'wishlist_1'                  => $r->wishlist_1,
                    'wishlist_1_description'      => $r->wishlist_1_description,
                    'wishlist_2'                  => $r->wishlist_2,
                    'wishlist_2_description'      => $r->wishlist_2_description,
                    'wishlist_3'                  => $r->wishlist_3,
                    'wishlist_3_description'      => $r->wishlist_3_description,
                ] : null,
            ];
        })->values();

        return response()->json([
            'assigned_givers' => $giverIds,
            'secret_pairs'    => $pairs,
            'total_bought'    => $totalBought,
            'total_assigned'  => $assignments->count(),
        ]);
    }

    public function toggleBoughtStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id'  => ['required', 'integer', 'exists:exchange_projects,id'],
            'giver_id'    => ['required', 'integer', 'exists:exchange_participants,id'],
            'unique_code' => ['nullable', 'string'],
        ]);

        $assignment = ExchangeAssignment::where('project_id', $data['project_id'])
            ->where('giver_id', $data['giver_id'])
            ->where('mode', 'secret')
            ->first();

        if (!$assignment) {
            return response()->json(['message' => 'Assignment not found.'], 404);
        }

        // Verify participant unique_code if provided
        if (!empty($data['unique_code'])) {
            $participant = ExchangeParticipant::where('id', $data['giver_id'])
                ->where('unique_code', $data['unique_code'])
                ->first();
            if (!$participant) {
                return response()->json(['message' => 'Invalid participant code.'], 403);
            }
        }

        $assignment->is_bought = !$assignment->is_bought;
        $assignment->save();

        return response()->json([
            'is_bought' => (bool) $assignment->is_bought,
            'message'   => $assignment->is_bought ? 'Marked gift as bought! 🎁' : 'Marked gift as not yet bought.',
        ]);
    }


    public function generateSecretSanta(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:exchange_projects,id'],
        ]);

        $projectId = (int) $data['project_id'];
        $mode = 'secret';

        $participants = ExchangeParticipant::where('project_id', $projectId)
            ->where('list_type', 'classic')
            ->orderBy('id')
            ->get()
            ->values();

        $n = $participants->count();

        if ($n < 2) {
            return response()->json([
                'message' => 'At least 2 participants are required to generate Secret Santa.',
            ], 422);
        }

        ExchangeAssignment::where('project_id', $projectId)
            ->where('mode', $mode)
            ->delete();

        $giverIds = $participants->pluck('id')->all();
        $receiverIds = $giverIds;

        if ($n === 2) {
            $receiverIds = array_reverse($receiverIds);
        } else {
            $attempts = 0;
            do {
                shuffle($receiverIds);
                $attempts++;
                $ok = true;
                for ($i = 0; $i < $n; $i++) {
                    if ($giverIds[$i] === $receiverIds[$i]) {
                        $ok = false;
                        break;
                    }
                }
            } while (!$ok && $attempts < 500);

            if (!$ok) {
                return response()->json([
                    'message' => 'Unable to generate Secret Santa (please try again).',
                ], 500);
            }
        }

        $now = now();
        $rows = [];

        for ($i = 0; $i < $n; $i++) {
            $rows[] = [
                'project_id'  => $projectId,
                'mode'        => $mode,
                'giver_id'    => $giverIds[$i],
                'receiver_id' => $receiverIds[$i],
                'gift_number' => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        ExchangeAssignment::insert($rows);

        return response()->json([
            'message' => 'Secret Santa assignments generated.',
            'count'   => $n,
        ]);
    }

    public function sendSecretSantaEmail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:exchange_projects,id'],
            'email'      => ['required', 'email'],
        ]);

        $project = ExchangeProject::findOrFail($data['project_id']);

        $participant = ExchangeParticipant::where('project_id', $project->id)
            ->where('list_type', 'classic')
            ->where('email', $data['email'])
            ->first();

        if (!$participant) {
            return response()->json([
                'message' => 'No participant found with that email for this project.',
            ], 404);
        }

        $assignment = ExchangeAssignment::with('receiver')
            ->where('project_id', $project->id)
            ->where('mode', 'secret')
            ->where('giver_id', $participant->id)
            ->first();

        if (!$assignment || !$assignment->receiver) {
            return response()->json([
                'message' => 'Secret Santa has not been generated yet for this group.',
            ], 404);
        }

        $budgetWinner = $this->getBudgetWinnerForProject($project->id);

        Mail::send(
            'emails.secret_santa_assignment',
            [
                'project'       => $project,
                'participant'   => $participant,
                'receiver'      => $assignment->receiver,
                'budgetWinner'  => $budgetWinner,
            ],
            function ($message) use ($participant, $project) {
                $message->to($participant->email, $participant->name)
                        ->subject($project->name . ' | Christmas Party Planner | JKLeonardo Tech');
            }
        );

        return response()->json([
            'message' => 'Your Secret Santa recipient has been emailed. Please check your inbox.',
        ]);
    }

    public function sendAllSecretSantaEmails(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:exchange_projects,id'],
        ]);

        $project = ExchangeProject::findOrFail($data['project_id']);

        $participants = ExchangeParticipant::where('project_id', $project->id)
            ->where('list_type', 'classic')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get();

        if ($participants->isEmpty()) {
            return response()->json([
                'message' => 'No participants with email found for this project.',
                'sent'    => 0,
            ]);
        }

        $assignments = ExchangeAssignment::with('receiver')
            ->where('project_id', $project->id)
            ->where('mode', 'secret')
            ->get()
            ->keyBy('giver_id');

        // ✅ NEW: include budget in the email just like classic_assignments.blade.php
        $budgetWinner = $this->getBudgetWinnerForProject($project->id);

        $sent = 0;

        foreach ($participants as $participant) {
            $assignment = $assignments->get($participant->id);

            if (!$assignment || !$assignment->receiver) {
                continue;
            }

            Mail::send(
                'emails.secret_santa_assignment',
                [
                    'project'      => $project,
                    'participant'  => $participant,
                    'receiver'     => $assignment->receiver,
                    'budgetWinner' => $budgetWinner, // ✅ NEW
                ],
                function ($message) use ($participant, $project) {
                    $message->to($participant->email, $participant->name)
                        ->subject($project->name . ' | Christmas Party Planner | JKLeonardo Tech');
                }
            );

            $sent++;
        }

        return response()->json([
            'message' => "Secret Santa emailed to {$sent} participant(s).",
            'sent'    => $sent,
        ]);
    }

        /*
     |--------------------------------------------------------------------------
     | WISHLIST: LOOKUP + UPDATE BY UNIQUE CODE
     |--------------------------------------------------------------------------
     */

public function wishlistLookupByCode(Request $request): JsonResponse
{
    $data = $request->validate([
        'project_id' => ['required', 'integer', 'exists:exchange_projects,id'],
        'code'       => ['required', 'string'],
    ]);

    $code = strtoupper(trim($data['code']));

    $participant = ExchangeParticipant::where('project_id', $data['project_id'])
        ->where('list_type', 'classic')
        ->where('unique_code', $code)
        ->first();

    if (! $participant) {
        return response()->json([
            'message' => 'Unique code not found for this group.',
        ], 404);
    }

    return response()->json([
        'participant' => [
            'id'                       => $participant->id,
            'name'                     => $participant->name,
            'codename'                 => $participant->codename,
            'wishlist_1'               => $participant->wishlist_1,
            'wishlist_1_description'   => $participant->wishlist_1_description,
            'wishlist_2'               => $participant->wishlist_2,
            'wishlist_2_description'   => $participant->wishlist_2_description,
            'wishlist_3'               => $participant->wishlist_3,
            'wishlist_3_description'   => $participant->wishlist_3_description,
        ],
    ]);
}

public function wishlistUpdateByCode(Request $request): JsonResponse
{
    $data = $request->validate([
        'project_id' => ['required', 'integer', 'exists:exchange_projects,id'],
        'code'       => ['required', 'string'],
        'wishlist_1' => ['nullable', 'string', 'max:2000'],
        'wishlist_2' => ['nullable', 'string', 'max:2000'],
        'wishlist_3' => ['nullable', 'string', 'max:2000'],
        'wishlist_1_description' => ['nullable', 'string'],
        'wishlist_2_description' => ['nullable', 'string'],
        'wishlist_3_description' => ['nullable', 'string'],
    ]);

    $code = strtoupper(trim($data['code']));

    $participant = ExchangeParticipant::where('project_id', $data['project_id'])
        ->where('list_type', 'classic')
        ->where('unique_code', $code)
        ->first();

    if (! $participant) {
        return response()->json([
            'message' => 'Unique code not found for this group.',
        ], 404);
    }

    $participant->fill([
        'wishlist_1'             => $data['wishlist_1'] ?? null,
        'wishlist_2'             => $data['wishlist_2'] ?? null,
        'wishlist_3'             => $data['wishlist_3'] ?? null,
        'wishlist_1_description' => $data['wishlist_1_description'] ?? null,
        'wishlist_2_description' => $data['wishlist_2_description'] ?? null,
        'wishlist_3_description' => $data['wishlist_3_description'] ?? null,
    ])->save();

    return response()->json([
        'message'     => 'Wishlist updated successfully.',
        'participant' => $participant,
    ]);
}


    /*
     |--------------------------------------------------------------------------
     | EMAIL CLASSIC ASSIGNMENTS
     |--------------------------------------------------------------------------
     */

    public function sendAssignmentsEmail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:exchange_projects,id'],
            'email'      => ['required', 'email'],
        ]);

        $project = ExchangeProject::findOrFail($data['project_id']);

        $participant = ExchangeParticipant::where('project_id', $project->id)
            ->where('list_type', 'classic')
            ->where('email', $data['email'])
            ->first();

        if (! $participant) {
            return response()->json([
                'message' => 'No classic-list participant found with that email for this project.',
            ], 404);
        }

        $assignments = ExchangeAssignment::with('receiver')
            ->where('project_id', $project->id)
            ->where('mode', 'multi')
            ->where('giver_id', $participant->id)
            ->orderBy('gift_number')
            ->get();

        if ($assignments->isEmpty()) {
            return response()->json([
                'message' => 'No assignments have been generated yet for this participant.',
            ], 404);
        }

        $recipients = $assignments->pluck('receiver.name')->filter()->values();
        $giftsEach  = $recipients->count();

        $budgetWinner = $this->getBudgetWinnerForProject($project->id);

        Mail::send(
            'emails.classic_assignments',
            [
                'participant'  => $participant,
                'project'      => $project,
                'recipients'   => $recipients,
                'giftsEach'    => $giftsEach,
                'budgetWinner' => $budgetWinner,
            ],
            function ($message) use ($participant, $project) {
                $message->to($participant->email, $participant->name)
                        ->subject($project->name . ' | Christmas Party Planner | JKLeonardo Tech');
            }
        );

        return response()->json([
            'message' => 'Your gift list has been emailed. Please check your inbox.',
        ]);
    }

    protected function getBudgetWinnerForProject(int $projectId): ?array
    {
        $votes = ExchangeParticipant::where('project_id', $projectId)
            ->where('list_type', 'classic')
            ->whereNotNull('budget_vote')
            ->pluck('budget_vote');

        if ($votes->isEmpty()) return null;

        $counts = $votes->countBy();
        $maxVotes = $counts->max();

        $leaders = $counts->filter(fn ($count) => $count === $maxVotes);

        $labelsMap = [
            100 => '₱100',
            200 => '₱200',
            300 => '₱300',
            400 => '₱400',
            500 => '₱500+',
        ];

        $leaderValues = $leaders->keys()->values();
        $leaderLabels = $leaderValues->map(fn ($value) => $labelsMap[$value] ?? '₱' . number_format($value, 0));

        return [
            'total_votes' => $votes->count(),
            'max_votes'   => $maxVotes,
            'values'      => $leaderValues->all(),
            'labels'      => $leaderLabels->all(),
            'is_tie'      => $leaderValues->count() > 1,
        ];
    }

    public function generateCodenames(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:exchange_projects,id'],
            'list_type'  => ['required', 'string', Rule::in(['classic', 'something'])],
        ]);

        $participants = ExchangeParticipant::where('project_id', $data['project_id'])
            ->where('list_type', $data['list_type'])
            ->orderBy('id')
            ->get();

        if ($participants->count() < 1) {
            return response()->json(['message' => 'No participants found.'], 422);
        }

        $adjectives = ["Frosty","Jolly","Merry","Sparkly","Cozy","Brave","Silent","Glowing","Cheerful","Snowy"];
        $nouns      = ["Penguin","Reindeer","Elf","Snowman","Star","Candle","Fox","Bear","Owl","Falcon"];

        $used = [];
        foreach ($participants as $p) {
            if (!empty($p->codename)) {
                $used[strtolower($p->codename)] = true;
                continue;
            }

            $attempts = 0;
            do {
                $codename = $adjectives[array_rand($adjectives)] . ' ' . $nouns[array_rand($nouns)];
                $attempts++;
            } while (isset($used[strtolower($codename)]) && $attempts < 300);

            $used[strtolower($codename)] = true;
            $p->codename = $codename;
            $p->save();
        }

        return response()->json([
            'message' => 'Codenames generated.',
            'participants' => $participants->fresh(),
        ]);
    }

    public function setMyCodename(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:exchange_projects,id'],
            'list_type'  => ['required', 'string', Rule::in(['classic', 'something'])],
            'email'      => ['required', 'email', 'max:255'],
            'codename'   => ['required', 'string', 'min:2', 'max:40'],
        ]);

        $codename = trim(preg_replace('/\s+/', ' ', $data['codename']));

        if (!preg_match('/^[\pL\pN _\-]+$/u', $codename)) {
            return response()->json([
                'message' => 'Codename can only contain letters, numbers, spaces, hyphen, and underscore.',
            ], 422);
        }

        $participant = ExchangeParticipant::where('project_id', $data['project_id'])
            ->where('list_type', $data['list_type'])
            ->where('email', $data['email'])
            ->first();

        if (! $participant) {
            return response()->json([
                'message' => 'No participant found with that email in this group.',
            ], 404);
        }

        $exists = ExchangeParticipant::where('project_id', $data['project_id'])
            ->where('list_type', $data['list_type'])
            ->whereNotNull('codename')
            ->whereRaw('LOWER(codename) = ?', [Str::lower($codename)])
            ->where('id', '!=', $participant->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'That codename is already taken in this group. Please choose another one.',
            ], 422);
        }

        $participant->codename = $codename;
        $participant->save();

        return response()->json([
            'message' => 'Codename saved!',
            'participant' => $participant,
        ]);
    }
}
