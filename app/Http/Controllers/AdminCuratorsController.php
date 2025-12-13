<?php

namespace App\Http\Controllers;

use App\Models\AdminActionLog;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class AdminCuratorsController extends Controller
{
    /**
     * GET /admin/curators.json — list curators with aggregates
     * Filters: overloaded=1 (pending_entries_count > threshold)
     * Fields: curator_id, curator_name, total_assigned_users, pending_entries_count, avg_approval_time_days
     */
    public function index(Request $request)
    {
        $overloaded = (int)$request->query('overloaded', 0) === 1;
        $threshold = (int) (env('ADMIN_OVERLOADED_THRESHOLD', 20));

        $page = max(1, (int)$request->query('page', 1));
        $perPage = min(100, max(10, (int)$request->query('per_page', 20)));
        $sortBy = $request->query('sort_by', 'pending_entries_count');
        $sortDir = strtolower($request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['curator_name','total_assigned_users','pending_entries_count','avg_approval_time_days'];
        if (!in_array($sortBy, $allowedSort, true)) $sortBy = 'pending_entries_count';

        // Build base aggregates per curator
        $base = DB::table('users as u')
            ->select([
                'u.id as curator_id',
                'u.name as curator_name',
                DB::raw('COUNT(DISTINCT d.user_id) as total_assigned_users'),
                DB::raw('COALESCE(SUM(CASE WHEN p.status = "pending" THEN 1 ELSE 0 END),0) as pending_entries_count'),
                DB::raw('COALESCE(AVG(CASE WHEN p.approved = 1 THEN TIMESTAMPDIFF(DAY, p.created_at, p.updated_at) END), 0) as avg_approval_time_days'),
            ])
            ->join('pdp_curators as c', 'c.user_id', '=', 'u.id')
            ->join('pdps as d', 'd.id', '=', 'c.pdp_id')
            ->leftJoin('pdp_skills as s', 's.pdp_id', '=', 'd.id')
            ->leftJoin('pdp_skill_criterion_progress as p', 'p.pdp_skill_id', '=', 's.id')
            ->groupBy('u.id', 'u.name');

        if ($overloaded) {
            $base->havingRaw('pending_entries_count > ?', [$threshold]);
        }

        // Sorting
        $orderExpr = match ($sortBy) {
            'curator_name' => 'u.name',
            'total_assigned_users' => DB::raw('total_assigned_users'),
            'avg_approval_time_days' => DB::raw('avg_approval_time_days'),
            default => DB::raw('pending_entries_count'),
        };

        $total = DB::table(DB::raw("({$base->toSql()}) as t"))
            ->mergeBindings($base)
            ->count();

        $rows = $base
            ->orderBy($orderExpr, $sortDir)
            ->forPage($page, $perPage)
            ->get()
            ->map(fn($r) => [
                'curator_id' => (int)$r->curator_id,
                'curator_name' => (string)$r->curator_name,
                'total_assigned_users' => (int)$r->total_assigned_users,
                'pending_entries_count' => (int)$r->pending_entries_count,
                'avg_approval_time_days' => (float)$r->avg_approval_time_days,
            ]);

        return response()->json([
            'data' => $rows,
            'meta' => [
                'total' => (int)$total,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * POST /admin/curators/{user}/nudge.json — send an internal notification and log action
     */
    public function nudge(Request $request, User $user)
    {
        // Send in-app notification
        app(NotificationService::class)->send(
            (int)$user->id,
            'Nudge from Admin',
            'Please review your pending entries and approvals.',
            'warning',
            url('/curator/mentees')
        );

        // Log admin action
        AdminActionLog::create([
            'admin_user_id' => (int)$request->user()->id,
            'action' => 'nudge_curator',
            'target_type' => 'user',
            'target_id' => (int)$user->id,
            'meta' => [
                'message' => 'Admin nudged curator to review pending entries',
            ],
        ]);

        return response()->json(['status' => 'ok'], Response::HTTP_ACCEPTED);
    }
}
