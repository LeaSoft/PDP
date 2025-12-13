<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminUsersController extends Controller
{
    /**
     * GET /admin/users.json — list users with activity metrics
     * Filter: active_last_days (int)
     * Fields: user_id, name, email, last_activity_at, pdp_count
     */
    public function index(Request $request)
    {
        $activeLastDays = (int)$request->query('active_last_days', 0);
        $page = max(1, (int)$request->query('page', 1));
        $perPage = min(100, max(10, (int)$request->query('per_page', 20)));
        $sortBy = $request->query('sort_by', 'last_activity_at');
        $sortDir = strtolower($request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['name','email','last_activity_at','pdp_count'];
        if (!in_array($sortBy, $allowedSort, true)) $sortBy = 'last_activity_at';

        // Build base query with aggregates
        $base = DB::table('users as u')
            ->select([
                'u.id as user_id',
                'u.name',
                'u.email',
                DB::raw('(SELECT MAX(p.created_at) FROM pdp_skill_criterion_progress p WHERE p.user_id = u.id) as last_activity_at'),
                DB::raw('(SELECT COUNT(*) FROM pdps d WHERE d.user_id = u.id) as pdp_count'),
            ]);

        if ($activeLastDays > 0) {
            $base->whereExists(function ($q) use ($activeLastDays) {
                $q->from('pdp_skill_criterion_progress as p')
                  ->whereColumn('p.user_id', 'u.id')
                  ->where('p.created_at', '>=', now()->subDays($activeLastDays));
            });
        }

        // Sorting
        $orderExpr = match ($sortBy) {
            'name' => 'u.name',
            'email' => 'u.email',
            'pdp_count' => DB::raw('pdp_count'),
            default => DB::raw('last_activity_at'),
        };

        // Count total rows using subquery
        $total = DB::table(DB::raw("({$base->toSql()}) as t"))
            ->mergeBindings($base)
            ->count();

        $rows = $base
            ->orderBy($orderExpr, $sortDir)
            ->forPage($page, $perPage)
            ->get()
            ->map(function ($r) {
                return [
                    'user_id' => (int)$r->user_id,
                    'name' => (string)$r->name,
                    'email' => (string)$r->email,
                    'last_activity_at' => $r->last_activity_at,
                    'pdp_count' => (int)$r->pdp_count,
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
