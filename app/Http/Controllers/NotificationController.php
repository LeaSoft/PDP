<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;


class NotificationController extends Controller
{
    public function unread()
    {
        return Notification::where('user_id', auth()->id())
            ->where('read', false)
            ->orderByDesc('id')
            ->get();
    }

    public function all()
    {
        return Notification::where('user_id', auth()->id())
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }

    public function markAsRead($id)
    {
        Notification::where('id', $id)
            ->where('user_id', auth()->id())
            ->update(['read' => true]);

        return response()->json(['status' => 'ok']);
    }
}
