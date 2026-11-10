<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveAdvisoryRequest;
use App\Models\Advisory;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class AdvisoryController extends Controller
{
    public function store(SaveAdvisoryRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $advisory = Advisory::create([...$request->validated(), 'user_id' => $request->user()->id]);
            AuditLog::record('advisory.created', 'advisory', $advisory->id);
        });

        return back()->with('success', 'Advisory draft saved.');
    }

    public function update(SaveAdvisoryRequest $request, Advisory $advisory): RedirectResponse
    {
        DB::transaction(function () use ($request, $advisory) {
            $advisory->update($request->validated());
            AuditLog::record('advisory.updated', 'advisory', $advisory->id);
        });

        return back()->with('success', 'Advisory draft updated.');
    }

    public function destroy(Advisory $advisory): RedirectResponse
    {
        DB::transaction(function () use ($advisory) {
            AuditLog::record('advisory.deleted', 'advisory', $advisory->id);
            $advisory->delete();
        });

        return back()->with('success', 'Advisory draft deleted. Sent-message history is preserved.');
    }
}
