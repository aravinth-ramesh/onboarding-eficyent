<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = AdminActivityLog::with('admin')
            ->when($request->filled('admin_id'), fn ($q) => $q->where('admin_id', $request->input('admin_id')))
            ->when($request->filled('action'), fn ($q) => $q->where(function ($sub) use ($request) {
                $term = $request->input('action');
                $sub->where('action', 'like', "%{$term}%")
                    ->orWhere('path', 'like', "%{$term}%");
            }))
            ->latest('created_at')->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.admin-activity.index', [
            'logs' => $logs,
            'admins' => Admin::orderBy('name')->get(),
        ]);
    }
}
