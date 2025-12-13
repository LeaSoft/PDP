<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class AdminEntriesController extends Controller
{
    /**
     * GET /admin/entries.json — list progress entries with filters & pagination
     * Filters: status (pending/approved), older_than (days), curator_id (optional)
     * Returns: entry_id, user_name, pdp_title, skill_name, status, days_pending, assigned_curator_name
     */
    public function index(Request $request)
    {
        $status = $request->string('status')->lower()->value();
        $olderThan = (int) $request->query('older_than', 0);
        $curatorId = $request->query('curator_id');

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(10, (int) $request->query('per_page', 20)));
        $sortBy = $request->query('sort_by', 'created_at');
        $sortDir = strtolower($request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSort = ['created_at', 'status', 'days_pending', 'user_name', 'pdp_title'];
        if (! in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'created_at';
        }

        $query = DB::table('pdp_skill_criterion_progress as p')
            ->select([
                'p.id as entry_id',
                'u.name as user_name',
                'd.title as pdp_title',
                's.skill as skill_name',
                'p.status',
                'p.created_at',
                DB::raw('TIMESTAMPDIFF(DAY, p.created_at, NOW()) as days_pending'),
                DB::raw('COALESCE(cu.name, "—") as assigned_curator_name'),
            ])
            ->join('pdp_skills as s', 's.id', '=', 'p.pdp_skill_id')
            ->join('pdps as d', 'd.id', '=', 's.pdp_id')
            ->join('users as u', 'u.id', '=', 'p.user_id')
            // pick any assigned curator (first by id)
            ->leftJoin(DB::raw('(
                SELECT pc.pdp_id, MIN(pc.user_id) as curator_id
                FROM pdp_curators pc
                GROUP BY pc.pdp_id
            ) as firstc'), 'firstc.pdp_id', '=', 'd.id')
            ->leftJoin('users as cu', 'cu.id', '=', 'firstc.curator_id');

        if (in_array($status, ['pending', 'approved'], true)) {
            $query->where('p.status', '=', $status);
        }
        if ($olderThan > 0) {
            $query->where('p.created_at', '<', now()->subDays($olderThan));
        }
        if ($curatorId) {
            $query->join('pdp_curators as c', 'c.pdp_id', '=', 'd.id')
                  ->where('c.user_id', '=', (int)$curatorId);
        }

        // Sorting
        if ($sortBy === 'days_pending') {
            $query->orderBy(DB::raw('days_pending'), $sortDir);
        } elseif ($sortBy === 'user_name') {
            $query->orderBy('u.name', $sortDir);
        } elseif ($sortBy === 'pdp_title') {
            $query->orderBy('d.title', $sortDir);
        } else {
            $query->orderBy($sortBy === 'status' ? 'p.status' : 'p.created_at', $sortDir);
        }

        $total = (clone $query)->count('p.id');
        $rows = $query
            ->forPage($page, $perPage)
            ->get()
            ->map(function ($r) {
                return [
                    'entry_id' => (int)$r->entry_id,
                    'user_name' => $r->user_name,
                    'pdp_title' => $r->pdp_title,
                    'skill_name' => $r->skill_name,
                    'status' => $r->status,
                    'days_pending' => (int)$r->days_pending,
                    'assigned_curator_name' => $r->assigned_curator_name,
                ];
            });

        return response()->json([
            'data' => $rows,
            'meta' => [
                'total' => (int)$total,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);
    }
}
