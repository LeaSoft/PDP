<?php

namespace App\Http\Controllers;

use App\Services\ProfessionalLevelService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\User;

class UserProfessionalLevelController extends Controller
{
    public function __construct(private ProfessionalLevelService $service)
    {
    }

    public function show(Request $request)
    {
        $user = $request->user();
        $data = $this->service->current($user);
        // Also return the levels list so the client can render ladder/labels
        $data['levels'] = $this->service->levels();
        return response()->json($data);
    }

    /**
     * Set initial professional level for the current user.
     * - Allowed for any authenticated user if not set yet.
     * - If already set, only moderators may override via this endpoint as well.
     */
    public function setSelf(Request $request)
    {
        $payload = $request->validate([
            'level_key' => ['required','string'],
        ]);

        $levels = $this->service->levels();
        $key = (string) $payload['level_key'];
        $exists = false;
        foreach ($levels as $lvl) {
            if (($lvl['key'] ?? null) === $key) { $exists = true; break; }
        }
        if (!$exists) {
            return response()->json(['message' => 'Unknown level key'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $request->user();
        if ($user->pro_level_key && !($user->is_moderator)) {
            return response()->json(['message' => 'Initial level already set'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->pro_level_key = $key;
        $user->save();

        $data = $this->service->current($user);
        $data['levels'] = $levels;
        return response()->json($data, Response::HTTP_OK);
    }

    /**
     * Moderator: set/override professional level for a specific user.
     */
    public function setForUser(Request $request, User $user)
    {
        // Only moderators can set level for any user
        abort_unless($request->user() && $request->user()->is_moderator, Response::HTTP_FORBIDDEN);

        $payload = $request->validate([
            'level_key' => ['required','string'],
        ]);

        $levels = $this->service->levels();
        $key = (string) $payload['level_key'];
        $exists = false;
        foreach ($levels as $lvl) {
            if (($lvl['key'] ?? null) === $key) { $exists = true; break; }
        }
        if (!$exists) {
            return response()->json(['message' => 'Unknown level key'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->pro_level_key = $key;
        $user->save();

        $data = $this->service->current($user);
        $data['levels'] = $levels;
        $data['user_id'] = $user->id;
        return response()->json($data, Response::HTTP_OK);
    }
}
