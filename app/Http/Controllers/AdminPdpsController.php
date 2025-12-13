<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminPdpsController extends Controller
{
    /**
     * GET /admin/pdps.json — list PDPs with aggregates
     * Filter: status (active|done)
     * Fields: pdp_id, title, owner_name, curator_name, active_entries_count, pending_entries_count
     */
    public function index(Request $request)
    {
        $status = strtolower((string)$request->query('status', ''));
        $page = max(1, (int)$request->query('page', 1));
        $perPage = min(100, max(10, (int)$request->query('per_page', 20)));
        $sortBy = $request->query('sort_by', 'pending_entries_count');
        $sortDir = strtolower($request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['title','owner_name','curator_name','active_entries_count','pending_entries_count'];
        if (!in_array($sortBy, $allowedSort, true)) $sortBy = 'pending_entries_count';

        $base = DB::table('pdps as d')
            ->select([
                'd.id as pdp_id',
                'd.title',
                'uo.name as owner_name',
                DB::raw('COALESCE(uc.name, "—") as curator_name'),
                DB::raw('COALESCE(SUM(CASE WHEN p.status IS NOT NULL THEN 1 ELSE 0 END),0) as active_entries_count'),
                DB::raw('COALESCE(SUM(CASE WHEN p.status = "pending" THEN 1 ELSE 0 END),0) as pending_entries_count'),
            ])
            ->join('users as uo', 'uo.id', '=', 'd.user_id')
            // first curator per PDP
            ->leftJoin(DB::raw('(
                SELECT pc.pdp_id, MIN(pc.user_id) as curator_id
                FROM pdp_curators pc
                GROUP BY pc.pdp_id
            ) as firstc'), 'firstc.pdp_id', '=', 'd.id')
            ->leftJoin('users as uc', 'uc.id', '=', 'firstc.curator_id')
            ->leftJoin('pdp_skills as s', 's.pdp_id', '=', 'd.id')
            ->leftJoin('pdp_skill_criterion_progress as p', 'p.pdp_skill_id', '=', 's.id')
            ->groupBy('d.id', 'd.title', 'uo.name', 'uc.name');

        if ($status === 'active') {
            $base->where('d.status', '!=', 'Done');
        } elseif ($status === 'done') {
            $base->where('d.status', '=', 'Done');
        }

        $orderExpr = match ($sortBy) {
            'title' => 'd.title',
            'owner_name' => 'uo.name',
            'curator_name' => DB::raw('curator_name'),
            'active_entries_count' => DB::raw('active_entries_count'),
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
                'pdp_id' => (int)$r->pdp_id,
                'title' => (string)$r->title,
                'owner_name' => (string)$r->owner_name,
                'curator_name' => (string)$r->curator_name,
                'active_entries_count' => (int)$r->active_entries_count,
                'pending_entries_count' => (int)$r->pending_entries_count,
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
}
