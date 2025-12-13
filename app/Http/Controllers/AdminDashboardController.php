<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon; // fallback if needed

class AdminDashboardController extends Controller
{
    /**
     * Global Overview Dashboard metrics (Super Admin only)
     */
    public function index(Request $request)
    {
        $now = now();
        $days7Ago = $now->copy()->subDays(7);

        // Users
        $totalUsers = DB::table('users')->count();
        $activeUsersLast7 = DB::table('pdp_skill_criterion_progress')
            ->where('created_at', '>=', $days7Ago)
            ->distinct('user_id')
            ->count('user_id');

        // PDPs
        $totalPdps = DB::table('pdps')->count();
        // Consider PDP "active" when not finalized (status != 'Done')
        $activePdps = DB::table('pdps')->where('status', '!=', 'Done')->count();

        // Entries (progress entries)
        $totalPendingEntries = DB::table('pdp_skill_criterion_progress')
            ->where('status', '=', 'pending')
            ->count();

        $pendingEntriesOver7Days = DB::table('pdp_skill_criterion_progress')
            ->where('status', '=', 'pending')
            ->where('created_at', '<', $days7Ago)
            ->count();

        // Curators
        $totalCurators = DB::table('pdp_curators')->distinct('user_id')->count('user_id');

        $threshold = (int) (env('ADMIN_OVERLOADED_THRESHOLD', 20));
        // Important: when using groupBy() + count() Laravel wraps the query as a subselect "select * from (...) as temp"
        // which can trigger MySQL error "Duplicate column name 'id'" due to multiple joined id columns.
        // To avoid that, select only the grouping key and count rows in PHP.
        $overloadedCurators = DB::table('pdp_skill_criterion_progress as p')
            ->join('pdp_skills as s', 's.id', '=', 'p.pdp_skill_id')
            ->join('pdps as d', 'd.id', '=', 's.pdp_id')
            ->join('pdp_curators as c', 'c.pdp_id', '=', 'd.id')
            ->where('p.status', '=', 'pending')
            ->select('c.user_id')
            ->groupBy('c.user_id')
            ->havingRaw('COUNT(*) > ?', [$threshold])
            ->get()
            ->count();

        return response()->json([
            'users' => [
                'total' => (int) $totalUsers,
                'active_last_7_days' => (int) $activeUsersLast7,
            ],
            'pdps' => [
                'total' => (int) $totalPdps,
                'active' => (int) $activePdps,
            ],
            'entries' => [
                'pending' => (int) $totalPendingEntries,
                'pending_over_7_days' => (int) $pendingEntriesOver7Days,
            ],
            'curators' => [
                'total' => (int) $totalCurators,
                'overloaded' => (int) $overloadedCurators,
            ],
        ]);
    }
}
