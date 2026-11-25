<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardAdminController extends Controller
{
    public function index(): View
    {
        return view('portal.admin', [
            'counts' => [
                'Users' => DB::table('users')->count(),
                'Verification requests' => DB::table('verification_requests')->count(),
                'Pending applications' => DB::table('live_access_applications')->where('status', 'pending')->count(),
                'Available services' => DB::table('service_prices')->where('available', true)->where('service', '<>', 'instant-ipe-bulk')->distinct()->count('service'),
            ],
        ]);
    }

    public function records(string $section): View
    {
        $records = match ($section) {
            'requests' => DB::table('verification_requests')->join('users', 'users.id', '=', 'verification_requests.user_id')
                ->select('verification_requests.*', 'users.email')->latest('verification_requests.created_at'),
            'applications' => DB::table('live_access_applications')->join('users', 'users.id', '=', 'live_access_applications.user_id')
                ->select('live_access_applications.*', 'users.email')->latest('live_access_applications.created_at'),
        };

        return view('portal.admin', ['section' => $section, 'records' => $records->paginate(15)]);
    }

    public function result(int $id): View
    {
        $record = DB::table('verification_requests')->find($id);
        abort_if($record === null, 404);

        return view('portal.result', ['record' => $record, 'back' => route('admin.records', 'requests')]);
    }

    public function review(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected'])]]);
        $updated = DB::table('live_access_applications')->where('id', $id)->where('status', 'pending')
            ->update(['status' => $data['status'], 'updated_at' => now()]);

        return to_route('admin.records', 'applications')
            ->with('notice', $updated ? 'Application status updated.' : 'This application is no longer pending.');
    }

    public function document(int $id): StreamedResponse
    {
        $application = DB::table('live_access_applications')->find($id);
        $disk = Storage::disk('local');
        abort_unless($application && $disk->exists($application->document_path), 404);

        return $disk->download($application->document_path, 'verification-document-'.basename($application->document_path), [
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function errorCharge(): View
    {
        return view('portal.admin', [
            'section' => 'error-charge',
            'chargeErrors' => (bool) DB::table('verification_settings')->where('version', 'v1')->value('charge_errors'),
        ]);
    }

    public function saveErrorCharge(Request $request): RedirectResponse
    {
        $data = $request->validate(['charge_errors' => ['required', 'boolean']]);

        DB::table('verification_settings')->upsert(
            [['version' => 'v1', 'charge_errors' => $data['charge_errors']]],
            ['version'],
            ['charge_errors'],
        );

        return to_route('admin.error-charge')->with('notice', 'V1 error charge setting saved.');
    }

    public function catalog(): View
    {
        $catalog = config('verification_catalog');
        $catalog['v1'] += ['instant-ipe-single' => 'Instant-ipe'];
        $prices = DB::table('service_prices')->where('service', '<>', 'instant-ipe-bulk')->get();

        foreach ($catalog as $version => $services) {
            foreach (array_keys($services) as $service) {
                if (! $prices->contains(fn ($price) => $price->version === $version && strtolower($price->service) === $service)) {
                    $prices->push((object) [
                        'service' => $service,
                        'version' => $version,
                        'price_minor' => null,
                        'error_charge_minor' => null,
                        'available' => false,
                    ]);
                }
            }
        }

        foreach ($prices as $price) {
            $price->label = $catalog[$price->version][strtolower($price->service)] ?? $price->service;
        }

        $prices = $prices->sortBy([['version', 'asc'], ['label', 'asc']])->values();
        $page = LengthAwarePaginator::resolveCurrentPage();

        return view('portal.admin', [
            'section' => 'pricing',
            'prices' => new LengthAwarePaginator($prices->forPage($page, 15), $prices->count(), 15, $page, ['path' => route('admin.catalog')]),
        ]);
    }

    public function savePrice(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'service' => ['required', 'string', 'max:100', 'not_regex:/\Ainstant-ipe-bulk\z/i'],
            'version' => ['required', Rule::in(['v1', 'v2', 'v3'])],
            'price_minor' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'error_charge_minor' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'available' => ['required', 'boolean'],
        ]);

        DB::table('service_prices')->upsert([$data], ['service', 'version'], ['price_minor', 'error_charge_minor', 'available']);

        return to_route('admin.catalog')->with('notice', 'Service pricing saved.');
    }

    public function content(string $category): View
    {
        return view('portal.admin', [
            'section' => $category,
            'pages' => DB::table('portal_pages')->whereLike('slug', $category.'-%')->orderBy('slug')->paginate(15),
        ]);
    }

    public function saveContent(Request $request, string $category): RedirectResponse
    {
        $data = $request->validate([
            'slug' => $category === 'docs'
                ? ['required', Rule::in(['v1', 'v2', 'v3'])]
                : ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:30000'],
        ]);

        $data['slug'] = $category.'-'.$data['slug'];
        DB::table('portal_pages')->upsert([$data], ['slug'], ['title', 'body']);

        return to_route('admin.content', $category)->with('notice', 'Page published.');
    }
}
