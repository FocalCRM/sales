<?php

declare(strict_types=1);

namespace Focal\Sales\Http\Controllers;

use Focal\Sales\Actions\BookMeetingAction;
use Focal\Sales\Models\SalesMeetingLink;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;

class SalesMeetingBookingController extends Controller
{
    /**
     * Display rep's public meeting booking page.
     */
    public function show(string $slug): View
    {
        /** @var SalesMeetingLink $link */
        $link = SalesMeetingLink::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with('user')
            ->firstOrFail();

        return view('focal-sales::meetings.book', compact('link'));
    }

    /**
     * Book meeting slot with rep.
     */
    public function book(Request $request, string $slug, BookMeetingAction $action): RedirectResponse
    {
        /** @var SalesMeetingLink $link */
        $link = SalesMeetingLink::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'time' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $scheduledAt = Carbon::parse("{$validated['date']} {$validated['time']}");

        $action->execute(
            link: $link,
            fullName: (string) $validated['name'],
            email: (string) $validated['email'],
            scheduledAt: $scheduledAt,
            phone: isset($validated['phone']) ? (string) $validated['phone'] : null,
            notes: isset($validated['notes']) ? (string) $validated['notes'] : null
        );

        return redirect()
            ->route('focal.meetings.show', ['slug' => $slug])
            ->with('status', "Meeting booked for {$scheduledAt->format('l, F j \a\t g:i A')}! An invitation has been dispatched.");
    }
}
