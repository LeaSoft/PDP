<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class ProfessionalLevelService
{
    /**
     * Count all closed skills (status = 'Done') for the user's PDPs.
     */
    public function countClosedSkills(User $user): int
    {
        // join pdp_skills -> pdps and filter by owner
        $count = DB::table('pdp_skills')
            ->join('pdps', 'pdps.id', '=', 'pdp_skills.pdp_id')
            ->where('pdps.user_id', $user->id)
            ->where('pdp_skills.status', 'Done')
            ->count();

        return (int) $count;
    }

    /**
     * Determine current professional level and progress to next one.
     * Returns array with keys: key, title, index, closed_skills, current_threshold, next_threshold,
     * percent, remaining_to_next, at_max.
     */
    public function current(User $user): array
    {
        $levels = $this->levels();
        if (empty($levels)) {
            // Fallback single level
            return [
                'key' => 'unranked',
                'title' => 'Unranked',
                'index' => 0,
                'closed_skills' => 0,
                'current_threshold' => 0,
                'next_threshold' => null,
                'percent' => 0,
                'remaining_to_next' => null,
                'at_max' => true,
            ];
        }

        $closed = $this->countClosedSkills($user);
        // Base threshold coming from the user's selected starting level (manual or at registration)
        $baseThreshold = $this->baseThresholdForUser($user, $levels);
        // Effective closed = base threshold + actually closed skills after start
        $effectiveClosed = $baseThreshold + $closed;

        // Find highest level whose threshold <= effective closed value
        $currentIdx = 0;
        foreach ($levels as $i => $lvl) {
            if ($effectiveClosed >= (int) $lvl['threshold']) {
                $currentIdx = $i;
            } else {
                break;
            }
        }

        $current = $levels[$currentIdx];
        $next = $levels[$currentIdx + 1] ?? null;

        // Start progress from the higher of current level threshold and the user's base threshold
        $from = max((int) $current['threshold'], (int) $baseThreshold);
        $to = $next ? (int) $next['threshold'] : null;

        $percent = 100;
        $remaining = null;
        if ($to !== null) {
            $span = max(1, $to - $from);
            $progress = max(0, min($span, $effectiveClosed - $from));
            $percent = (int) floor(($progress / $span) * 100);
            $remaining = max(0, $to - $effectiveClosed);
        }

        $result = [
            'key' => (string) $current['key'],
            'title' => (string) $current['title'],
            'index' => (int) $currentIdx,
            'closed_skills' => (int) $closed,
            'effective_closed' => (int) $effectiveClosed,
            'base_threshold' => (int) $baseThreshold,
            'selected_level_key' => $user->pro_level_key,
            'current_threshold' => $from,
            'next_threshold' => $to,
            'percent' => $percent,
            'remaining_to_next' => $remaining,
            'at_max' => $next === null,
        ];

        // Persist the computed current level into DB so that admins can see actual level.
        // Do NOT overwrite the starting level (pro_level_key).
        $newCurrentKey = (string) $current['key'];
        if ($user->current_level_key !== $newCurrentKey) {
            // Save quietly to avoid firing observers/listeners for a derived value.
            $user->forceFill(['current_level_key' => $newCurrentKey])->saveQuietly();
        }

        return $result;
    }

    /**
     * All levels from config, normalized and sorted by threshold ascending.
     * Each item: [key, title, threshold]
     */
    public function levels(): array
    {
        $levels = array_values((array) Config::get('pro_levels.levels', []));
        // Normalize and sort
        $normalized = [];
        foreach ($levels as $lvl) {
            $normalized[] = [
                'key' => (string) ($lvl['key'] ?? $lvl['title'] ?? 'level'),
                'title' => (string) ($lvl['title'] ?? ucfirst((string) ($lvl['key'] ?? 'Level'))),
                'threshold' => (int) ($lvl['threshold'] ?? 0),
            ];
        }
        usort($normalized, fn($a, $b) => $a['threshold'] <=> $b['threshold']);
        return $normalized;
    }

    /**
     * Get base threshold for the user based on selected starting level key.
     */
    public function baseThresholdForUser(User $user, ?array $levels = null): int
    {
        $levels = $levels ?? $this->levels();
        if (!$user->pro_level_key) {
            return 0;
        }
        foreach ($levels as $lvl) {
            if (($lvl['key'] ?? null) === $user->pro_level_key) {
                return (int) ($lvl['threshold'] ?? 0);
            }
        }
        return 0;
    }
}
