<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

use App\Models\ExchangeProject;
use App\Models\ExchangeParticipant;
use App\Models\ExchangeFoodItem;
use App\Models\ExchangeFoodPayer;

class FoodPlanningController extends Controller
{
    public function show(ExchangeProject $project)
    {
        return Inertia::render('ExchangedGift/FoodPlanning', [
            'project' => $project,
        ]);
    }

    public function data(Request $request)
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer', Rule::exists('exchange_projects', 'id')],
            'list_type'  => ['nullable', 'string'],
        ]);

        $projectId = (int) $validated['project_id'];
        $listType  = $validated['list_type'] ?? 'classic';

        // ✅ FIX: participants table uses project_id (NOT exchange_project_id)
        $participants = ExchangeParticipant::query()
            ->where('project_id', $projectId)
            ->where('list_type', $listType)
            ->orderBy('id')
            ->get(['id', 'name']);

        // These two tables SHOULD use exchange_project_id (based on how we designed them)
        $items = ExchangeFoodItem::query()
            ->where('exchange_project_id', $projectId)
            ->orderBy('id', 'desc')
            ->get();

        $payers = ExchangeFoodPayer::query()
            ->where('exchange_project_id', $projectId)
            ->get()
            ->keyBy('exchange_participant_id');

        // --- compute totals in cents ---
        $totalCents = 0;
        foreach ($items as $it) {
            $unit = (int) round(((float) $it->unit_price) * 100);
            $qty  = (int) $it->qty;
            $totalCents += ($unit * $qty);
        }

        // Boss pays are TOTAL pays (not extra)
        $bossPayCentsSum = 0;
        $bossIds = [];

        foreach ($participants as $p) {
            $row = $payers->get($p->id);
            if ($row && $row->is_boss) {
                $payCents = (int) round(((float) $row->pay_amount) * 100);
                $bossPayCentsSum += $payCents;
                $bossIds[] = $p->id;
            }
        }

        $n = $participants->count();
        $bossCount = count($bossIds);
        $nonBossCount = $n - $bossCount;

        $errors = [];

        if ($n === 0) $errors[] = 'No participants found.';
        if ($bossPayCentsSum > $totalCents) $errors[] = 'Boss pay total exceeds food total.';
        if ($bossCount > 0 && $nonBossCount === 0 && $bossPayCentsSum !== $totalCents) {
            $errors[] = 'All participants are bosses; boss pay total must equal the food total.';
        }

        // Non-boss share
        $nonBossShareCents = 0;
        $remainder = 0;

        if ($nonBossCount > 0) {
            $pool = $totalCents - $bossPayCentsSum;
            $nonBossShareCents = intdiv($pool, $nonBossCount);
            $remainder = $pool % $nonBossCount; // distribute +1 cent to first remainder non-bosses
        }

        // Build shares list
        $shares = [];
        $nonBossGivenExtraCent = 0;

        foreach ($participants as $p) {
            $payer = $payers->get($p->id);
            $isBoss = (bool) ($payer?->is_boss);

            if ($isBoss) {
                $payCents = (int) round(((float) $payer->pay_amount) * 100);
            } else {
                $payCents = $nonBossShareCents;
                if ($remainder > 0 && $nonBossGivenExtraCent < $remainder) {
                    $payCents += 1;
                    $nonBossGivenExtraCent++;
                }
            }

            $shares[] = [
                'participant_id'    => $p->id,
                'name'              => $p->name,
                'is_boss'           => $isBoss,
                'pay_amount'        => round($payCents / 100, 2),
                'payment_status'    => $payer?->payment_status ?? 'unpaid',
                'payment_reference' => $payer?->payment_reference ?? '',
                'payment_notes'     => $payer?->payment_notes ?? '',
            ];
        }

        $project = ExchangeProject::find($projectId);

        return response()->json([
            'participants' => $participants,
            'items'        => $items,
            'payers'       => $payers->values(),
            'project'      => $project ? [
                'id'            => $project->id,
                'name'          => $project->name,
                'gcash_name'    => $project->gcash_name,
                'gcash_number'  => $project->gcash_number,
                'gcash_qr_note' => $project->gcash_qr_note,
            ] : null,
            'summary'      => [
                'total'          => round($totalCents / 100, 2),
                'boss_pay_total' => round($bossPayCentsSum / 100, 2),
                'non_boss_share' => round($nonBossShareCents / 100, 2),
                'paid_count'     => $payers->where('payment_status', 'paid')->count(),
                'verified_count' => $payers->where('payment_status', 'verified')->count(),
                'unpaid_count'   => $n - $payers->whereIn('payment_status', ['paid', 'verified'])->count(),
                'errors'         => $errors,
            ],
            'shares'       => $shares,
        ]);
    }

    // POST /exchange/food-planning/items
    public function storeItem(Request $request)
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', Rule::exists('exchange_projects', 'id')],
            'name'       => ['required', 'string', 'max:255'],
            'unit_price' => ['required', 'numeric', 'min:0.01'],
            'qty'        => ['required', 'integer', 'min:1'],
            'notes'      => ['nullable', 'string'],
        ]);

        $item = ExchangeFoodItem::create([
            'exchange_project_id' => $data['project_id'],
            'name' => $data['name'],
            'unit_price' => $data['unit_price'],
            'qty' => $data['qty'],
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json(['item' => $item], 201);
    }

    // PUT /exchange/food-planning/items/{item}
    public function updateItem(Request $request, ExchangeFoodItem $item)
    {
        $data = $request->validate([
            'name'       => ['sometimes', 'string', 'max:255'],
            'unit_price' => ['sometimes', 'numeric', 'min:0.01'],
            'qty'        => ['sometimes', 'integer', 'min:1'],
            'notes'      => ['nullable', 'string'],
        ]);

        $item->update($data);

        return response()->json(['item' => $item]);
    }

    // DELETE /exchange/food-planning/items/{item}
    public function destroyItem(ExchangeFoodItem $item)
    {
        $item->delete();
        return response()->json(['ok' => true]);
    }

    // POST /exchange/food-planning/payers
    public function savePayers(Request $request)
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', Rule::exists('exchange_projects', 'id')],
            'bosses' => ['array'],
            'bosses.*.participant_id' => ['required', 'integer'],
            'bosses.*.pay_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $projectId = (int) $data['project_id'];

        ExchangeFoodPayer::where('exchange_project_id', $projectId)->update([
            'is_boss' => false,
            'pay_amount' => 0,
        ]);

        foreach (($data['bosses'] ?? []) as $b) {
            ExchangeFoodPayer::updateOrCreate(
                [
                    'exchange_project_id' => $projectId,
                    'exchange_participant_id' => (int) $b['participant_id'],
                ],
                [
                    'is_boss' => true,
                    'pay_amount' => $b['pay_amount'],
                ]
            );
        }

        return response()->json(['ok' => true]);
    }

    // POST /exchange/food-planning/payment-status
    public function updatePaymentStatus(Request $request)
    {
        $data = $request->validate([
            'project_id'        => ['required', 'integer', Rule::exists('exchange_projects', 'id')],
            'participant_id'    => ['required', 'integer', Rule::exists('exchange_participants', 'id')],
            'payment_status'    => ['required', 'string', Rule::in(['unpaid', 'paid', 'verified'])],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'payment_notes'     => ['nullable', 'string', 'max:500'],
        ]);

        $payer = ExchangeFoodPayer::updateOrCreate(
            [
                'exchange_project_id'     => (int) $data['project_id'],
                'exchange_participant_id' => (int) $data['participant_id'],
            ],
            [
                'payment_status'    => $data['payment_status'],
                'payment_reference' => $data['payment_reference'] ?? null,
                'payment_notes'     => $data['payment_notes'] ?? null,
            ]
        );

        return response()->json([
            'ok'      => true,
            'payer'   => $payer,
            'message' => 'Payment status updated to ' . ucfirst($data['payment_status']),
        ]);
    }

    // POST /exchange/food-planning/gcash
    public function saveGcash(Request $request)
    {
        $data = $request->validate([
            'project_id'    => ['required', 'integer', Rule::exists('exchange_projects', 'id')],
            'gcash_name'    => ['nullable', 'string', 'max:255'],
            'gcash_number'  => ['nullable', 'string', 'max:50'],
            'gcash_qr_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $project = ExchangeProject::findOrFail($data['project_id']);
        $project->update([
            'gcash_name'    => $data['gcash_name'] ?? null,
            'gcash_number'  => $data['gcash_number'] ?? null,
            'gcash_qr_note' => $data['gcash_qr_note'] ?? null,
        ]);

        return response()->json([
            'ok'      => true,
            'project' => $project,
            'message' => 'GCash payment details saved successfully.',
        ]);
    }
}
