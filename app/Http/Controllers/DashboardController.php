<?php

namespace App\Http\Controllers;

use App\Http\Requests\RequestHistoryFilter;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $entries = DB::table('billing_entries')->where('user_id', $request->user()->id);
        $requests = DB::table('verification_requests')->where('user_id', $request->user()->id);

        return view('portal.overview', [
            'balance' => (clone $entries)->sum('amount_minor'),
            'counts' => (clone $requests)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'requests' => $requests->latest()->orderByDesc('id')->limit(5)->get(),
            'credits' => $entries->where('amount_minor', '>', 0)->latest()->orderByDesc('id')->limit(5)->get(),
        ]);
    }

    public function requests(RequestHistoryFilter $request): View
    {
        $filters = $request->validated();

        return view('portal.requests', [
            'requests' => $this->filteredRequests($request, $filters)->paginate(15)->appends($filters),
            'services' => DB::table('verification_requests')->where('user_id', $request->user()->id)->distinct()->orderBy('service')->pluck('service'),
            'filters' => $filters,
        ]);
    }

    public function export(RequestHistoryFilter $request): StreamedResponse
    {
        $filters = $request->validated();

        return response()->streamDownload(function () use ($request, $filters): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Reference', 'Service', 'Version', 'Status', 'Date (UTC)'], escape: '');

            foreach ($this->filteredRequests($request, $filters)->cursor() as $record) {
                $cells = [$record->reference, $record->service, $record->version, $record->status, $record->created_at];
                fputcsv($stream, array_map(fn (?string $value): string => preg_match('/^[\s=+@-]/u', $value ?? '') ? "'".$value : ($value ?? ''), $cells), escape: '');
            }

            fclose($stream);
        }, 'verification-requests.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    private function filteredRequests(Request $request, array $filters): Builder
    {
        $query = DB::table('verification_requests')->where('user_id', $request->user()->id);

        foreach (['service', 'version', 'status'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $query->where($field, $filters[$field]);
            }
        }

        if (filled($filters['q'] ?? null)) {
            $term = '%'.$filters['q'].'%';
            $query->where(fn (Builder $search) => $search->whereLike('reference', $term)->orWhereLike('service', $term));
        }

        if (filled($filters['from'] ?? null)) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (filled($filters['to'] ?? null)) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return $query->latest()->orderByDesc('id');
    }

    public function result(Request $request, int $id): View
    {
        $record = DB::table('verification_requests')->where('user_id', $request->user()->id)->find($id);
        abort_if($record === null, 404);

        return view('portal.result', ['record' => $record]);
    }

    public function billing(Request $request): View
    {
        $entries = DB::table('billing_entries')->where('user_id', $request->user()->id);

        return view('portal.billing', [
            'balance' => (clone $entries)->sum('amount_minor'),
            'entries' => $entries->latest()->orderByDesc('id')->paginate(15),
        ]);
    }

    public function pricing(Request $request): View
    {
        return view('portal.pricing', [
            'prices' => DB::table('service_prices as prices')
                ->leftJoin('user_service_prices as overrides', fn ($join) => $join
                    ->on('overrides.service_price_id', '=', 'prices.id')->where('overrides.user_id', $request->user()->id))
                ->where('prices.service', '<>', 'instant-ipe-bulk')->select('prices.service', 'prices.version', 'prices.available')
                ->selectRaw('COALESCE(overrides.price_minor, prices.price_minor) AS price_minor')
                ->orderBy('prices.service')->get()->groupBy('version'),
            'pages' => DB::table('portal_pages')->whereLike('slug', 'services-%')->orWhereLike('slug', 'policies-%')->orderBy('title')->get(['slug', 'title']),
        ]);
    }

    public function documentation(Request $request): Response
    {
        return response()->view('portal.api', ['apiKey' => $request->user()->api_key])->header('Cache-Control', 'private, no-store');
    }

    public function rotateKey(Request $request): RedirectResponse
    {
        $user = $request->user();
        $key = 'billp_'.Str::random(48);
        $user->api_key = $key;
        $user->api_key_selector = $user::apiKeySelector($key);
        $user->save();

        return to_route('portal.api')->with('notice', 'Your API key was generated.');
    }

    public function page(string $slug): View
    {
        $page = DB::table('portal_pages')->where('slug', $slug)->first();
        abort_unless($page || in_array($slug, ['docs-v1', 'docs-v2', 'docs-v3']), 404);

        $version = in_array($slug, ['docs-v1', 'docs-v2', 'docs-v3'], true) ? substr($slug, 5) : null;

        return view('portal.page', ['page' => $page, 'slug' => $slug, 'version' => $version, 'services' => $this->documentedServices($version)]);
    }

    /** @return array<string, string> */
    private function documentedServices(?string $version): array
    {
        return config('verification_catalog.'.$version, config('verification_catalog.v1'));
    }

    public function serviceDocumentation(string $version, string $service): View
    {
        $services = $this->documentedServices($version);
        abort_unless(isset($services[$service]), 404);

        return view('portal.docs.service', [
            'version' => $version,
            'service' => $service,
            'name' => $services[$service],
            'hasStatusEndpoint' => in_array($service, ['personalization', 'ipeclearing'], true),
        ]);
    }

    public function liveAccess(Request $request): View
    {
        return view('portal.access', [
            'application' => DB::table('live_access_applications')->where('user_id', $request->user()->id)->first(),
        ]);
    }

    public function apply(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'registration_number' => ['required', 'string', 'max:100'],
            'use_case' => ['required', 'string', 'max:3000'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $disk = Storage::disk('local');
        $path = $request->file('document')->store('live-access', 'local');

        if ($path === false) {
            throw new RuntimeException('Unable to store the application document.');
        }

        try {
            DB::transaction(function () use ($request, $data, $path): void {
                DB::table('users')->where('id', $request->user()->id)->lockForUpdate()->first();
                $exists = DB::table('live_access_applications')->where('user_id', $request->user()->id)->exists();

                if ($exists) {
                    throw ValidationException::withMessages(['document' => 'You already submitted an application.']);
                }

                DB::table('live_access_applications')->insert([
                    'user_id' => $request->user()->id,
                    'business_name' => $data['business_name'],
                    'registration_number' => $data['registration_number'],
                    'use_case' => $data['use_case'],
                    'document_path' => $path,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }, 3);
        } catch (Throwable $exception) {
            $disk->delete($path);
            throw $exception;
        }

        return to_route('portal.access')->with('notice', 'Application submitted for review.');
    }
}
