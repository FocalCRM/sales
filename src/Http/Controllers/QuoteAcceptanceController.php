<?php

declare(strict_types=1);

namespace Focal\Sales\Http\Controllers;

use Focal\Core\Enums\ActivityType;
use Focal\Sales\Actions\AcceptQuoteAction;
use Focal\Sales\Enums\QuoteStatus;
use Focal\Sales\Exceptions\QuoteNotAcceptableException;
use Focal\Sales\Models\Quote;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class QuoteAcceptanceController extends Controller
{
    /**
     * Display the public customer quote acceptance portal.
     */
    public function show(string $token): View
    {
        /** @var Quote $quote */
        $quote = Quote::query()
            ->where('public_token', $token)
            ->with(['deal.contacts.companies', 'items'])
            ->firstOrFail();

        // 1. If quote is Draft, transition to Sent upon client opening
        if ($quote->status === QuoteStatus::Draft) {
            $quote->updateQuietly(['status' => QuoteStatus::Sent]);
            $quote->status = QuoteStatus::Sent;
        }

        // 2. Log portal viewing activity on the deal (throttled to once every 2 hours per quote)
        $recentView = $quote->deal->activities()
            ->where('metadata->quote_id', $quote->id)
            ->where('metadata->event', 'portal_view')
            ->where('created_at', '>=', now()->subHours(2))
            ->exists();

        if (! $recentView) {
            $quote->deal->logActivity(
                type: ActivityType::Note,
                title: 'Proposal Viewed by Customer',
                body: "Quote #{$quote->quote_number} (\"{$quote->title}\") was opened and viewed via the customer portal.",
                metadata: [
                    'quote_id' => $quote->id,
                    'event' => 'portal_view',
                    'ip' => request()->ip(),
                ]
            );
        }

        return view('focal-sales::quotes.public-portal', compact('quote'));
    }

    /**
     * Process quote acceptance and e-signature.
     */
    public function accept(Request $request, string $token, AcceptQuoteAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'signed_name' => ['required', 'string', 'min:2', 'max:255'],
            'signed_email' => ['required', 'email', 'max:255'],
            'agree_terms' => ['accepted'],
        ]);

        try {
            $action->execute(
                token: $token,
                signedByName: (string) $validated['signed_name'],
                signedByEmail: (string) $validated['signed_email']
            );

            return redirect()
                ->route('focal.quotes.show', ['token' => $token])
                ->with('status', 'Quote proposal accepted and successfully signed!');
        } catch (QuoteNotAcceptableException $e) {
            return redirect()
                ->route('focal.quotes.show', ['token' => $token])
                ->withErrors(['error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            // Internal failures (e.g. a won-stage requirement) are logged; the public visitor gets a generic message.
            report($e);

            return redirect()
                ->route('focal.quotes.show', ['token' => $token])
                ->withErrors(['error' => 'We could not complete the acceptance of this proposal. Please contact your sales representative.']);
        }
    }
}
