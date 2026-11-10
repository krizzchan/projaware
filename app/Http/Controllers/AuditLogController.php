<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:100'],
            'subject' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = AuditLog::query()->leftJoin('users', 'audit_logs.user_id', '=', 'users.id')
            ->select(['audit_logs.id', 'audit_logs.action', 'audit_logs.subject_type', 'audit_logs.subject_id', 'audit_logs.created_at', 'users.name as actor_name']);
        if (! empty($filters['action'])) {
            $query->where('audit_logs.action', $filters['action']);
        }
        if (! empty($filters['subject'])) {
            $query->where('audit_logs.subject_type', $filters['subject']);
        }

        return Inertia::render('menu/audit-logs', [
            'logs' => $query->orderByDesc('audit_logs.id')->paginate(25)->withQueryString(),
            'filters' => ['action' => $filters['action'] ?? '', 'subject' => $filters['subject'] ?? ''],
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'subjects' => AuditLog::query()->distinct()->orderBy('subject_type')->pluck('subject_type'),
        ]);
    }
}
