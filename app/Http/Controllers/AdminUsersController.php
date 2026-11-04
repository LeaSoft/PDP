<?php

namespace App\Http\Controllers;

use App\Models\AdminActionLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class AdminUsersController extends Controller
{
    /**
     * GET /admin/users.json — list users with activity metrics
     * Filter: active_last_days (int)
     * Fields: user_id, name, email, pro_level_key, current_level_key, super_admin, is_moderator, last_activity_at, pdp_count
     */
    public function index(Request $request)
    {
        $activeLastDays = (int) $request->query('active_last_days', 0);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(10, (int) $request->query('per_page', 20)));
        $sortBy = $request->query('sort_by', 'last_activity_at');
        $sortDir = strtolower($request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['name', 'email', 'last_activity_at', 'pdp_count'];
        if (! in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'last_activity_at';
        }

        // Build base query with aggregates
        $base = DB::table('users as u')
            ->select([
                'u.id as user_id',
                'u.name',
                'u.email',
                'u.pro_level_key',
                'u.current_level_key',
                'u.super_admin',
                'u.is_moderator',
                DB::raw('(CASE WHEN u.google_id IS NULL THEN 0 ELSE 1 END) as google_linked'),
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
                    'user_id' => (int) $r->user_id,
                    'name' => (string) $r->name,
                    'email' => (string) $r->email,
                    'pro_level_key' => $r->pro_level_key !== null ? (string) $r->pro_level_key : null,
                    'current_level_key' => $r->current_level_key !== null ? (string) $r->current_level_key : null,
                    'super_admin' => (bool) $r->super_admin,
                    'is_moderator' => (bool) $r->is_moderator,
                    'google_linked' => (bool) $r->google_linked,
                    'last_activity_at' => $r->last_activity_at,
                    'pdp_count' => (int) $r->pdp_count,
                ];
            });

        return response()->json([
            'data' => $rows,
            'meta' => [
                'total' => (int) $total,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * PUT /admin/users/{user}/roles.json — grant or revoke super_admin / is_moderator, logged as admin action
     */
    public function updateRoles(Request $request, User $user)
    {
        $roles = $request->validate([
            'super_admin' => ['sometimes', 'boolean'],
            'is_moderator' => ['sometimes', 'boolean'],
        ]);

        // An admin can't drop their own admin role, so there is always at least one admin left
        if ($user->is($request->user()) && array_key_exists('super_admin', $roles) && ! $roles['super_admin']) {
            return response()->json(['message' => 'You cannot remove your own admin role'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->forceFill($roles)->save();

        AdminActionLog::create([
            'admin_user_id' => (int) $request->user()->id,
            'action' => 'update_user_roles',
            'target_type' => 'user',
            'target_id' => (int) $user->id,
            'meta' => $roles,
        ]);

        return response()->json([
            'user_id' => (int) $user->id,
            'super_admin' => (bool) $user->super_admin,
            'is_moderator' => (bool) $user->is_moderator,
        ]);
    }
}
