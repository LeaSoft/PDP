<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PdpSkillService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class CuratorController extends Controller
{
    public function __construct(private PdpSkillService $service) {}

    /**
     * Get list of users (mentees) whose PDPs are curated by the current user
     */
    public function mentees(Request $request)
    {
        $curatorId = $request->user()->id;

        // Get distinct users who have PDPs curated by current user
        $mentees = DB::table('users')
            ->select([
                'users.id as user_id',
                'users.name',
                'users.email',
            ])
            ->join('pdps', 'pdps.user_id', '=', 'users.id')
            ->join('pdp_curators', 'pdp_curators.pdp_id', '=', 'pdps.id')
            ->where('pdp_curators.user_id', '=', $curatorId)
            ->distinct()
            ->orderBy('users.name')
            ->get();

        // For each mentee, count pending approvals
        $result = [];
        foreach ($mentees as $mentee) {
            $pendingCount = DB::table('pdp_skill_criterion_progress')
                ->join('pdp_skills', 'pdp_skills.id', '=', 'pdp_skill_criterion_progress.pdp_skill_id')
                ->join('pdps', 'pdps.id', '=', 'pdp_skills.pdp_id')
                ->join('pdp_curators', function ($j) use ($curatorId) {
                    $j->on('pdp_curators.pdp_id', '=', 'pdps.id')
                      ->where('pdp_curators.user_id', '=', $curatorId);
                })
                ->where('pdps.user_id', '=', $mentee->user_id)
                ->where('pdp_skill_criterion_progress.status', '=', 'pending')
                ->count();

            $result[] = [
                'user_id' => (int)$mentee->user_id,
                'name' => $mentee->name,
                'email' => $mentee->email,
                'pending_count' => (int)$pendingCount,
            ];
        }

        return response()->json($result);
    }

    /**
     * Get pending approvals for a specific mentee
     */
    public function menteePendingApprovals(Request $request, User $user)
    {
        $curatorId = $request->user()->id;
        $menteeId = $user->id;

        // Verify curator has access to this mentee's PDPs
        $hasAccess = DB::table('pdps')
            ->join('pdp_curators', 'pdp_curators.pdp_id', '=', 'pdps.id')
            ->where('pdps.user_id', '=', $menteeId)
            ->where('pdp_curators.user_id', '=', $curatorId)
            ->exists();

        abort_unless($hasAccess, Response::HTTP_FORBIDDEN);

        // Get pending approvals filtered by both curator and mentee
        $out = $this->service->pendingApprovalsForMentee($curatorId, $menteeId);

        return response()->json($out);
    }

    /**
     * Get total pending approvals count for all mentees
     */
    public function pendingApprovalsCount(Request $request)
    {
        $curatorId = $request->user()->id;

        $total = DB::table('pdp_skill_criterion_progress')
            ->join('pdp_skills', 'pdp_skills.id', '=', 'pdp_skill_criterion_progress.pdp_skill_id')
            ->join('pdps', 'pdps.id', '=', 'pdp_skills.pdp_id')
            ->join('pdp_curators', function ($j) use ($curatorId) {
                $j->on('pdp_curators.pdp_id', '=', 'pdps.id')
                  ->where('pdp_curators.user_id', '=', $curatorId);
            })
            ->where('pdp_skill_criterion_progress.status', '=', 'pending')
            ->count();

        return response()->json(['total' => (int)$total]);
    }
}
