<?php

namespace App\Http\Controllers;

use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    private const string MONEY = '/\A[0-9]{1,8}(?:\.[0-9]{1,2})?\z/';

    private const array PRICE_RULE = ['nullable', 'string', 'regex:'.self::MONEY];

    public function index(Request $request): View
    {
        $search = $request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? '';
        $users = User::query()->addSelect(['balance' => DB::table('billing_entries')
            ->selectRaw('COALESCE(SUM(amount_minor), 0)')->whereColumn('user_id', 'users.id')])
            ->when($search !== '', fn ($query) => $query->where(fn ($matches) => $matches
                ->whereLike('name', '%'.$search.'%')->orWhereLike('email', '%'.$search.'%')))
            ->orderBy('name')->orderBy('id')->paginate(20)->appends(['q' => $search]);

        return view('portal.users.index', ['users' => $users, 'search' => $search]);
    }

    public function create(): View
    {
        return view('portal.users.edit', ['user' => new User]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->accountData($request);
        $password = $this->validatedPassword($request);
        $user = new User;
        $this->saveAccount($user, $data);
        $user->password = $password;
        $user->save();

        return to_route('admin.users.edit', $user)->with('notice', 'User created.');
    }

    public function edit(User $user): View
    {
        $prices = DB::table('service_prices as prices')
            ->leftJoin('user_service_prices as overrides', fn ($join) => $join
                ->on('overrides.service_price_id', '=', 'prices.id')->where('overrides.user_id', $user->id))
            ->where('prices.service', '<>', 'instant-ipe-bulk')->orderBy('prices.version')->orderBy('prices.service')
            ->get(['prices.*', 'overrides.price_minor as custom_price']);
        $catalog = $this->serviceCatalog();

        foreach ($catalog as $version => $services) {
            foreach ($services as $service => $label) {
                if (! $prices->contains(fn ($price) => $price->version === $version && strtolower($price->service) === $service)) {
                    $prices->push((object) [
                        'id' => null, 'service' => $service, 'version' => $version, 'price_minor' => null, 'custom_price' => null,
                    ]);
                }
            }
        }

        foreach ($prices as $price) {
            $price->label = $catalog[$price->version][strtolower($price->service)] ?? $price->service;
        }
        $history = DB::table('billing_entries')->where('user_id', $user->id)
            ->select('billing_entries.*')
            ->selectRaw('SUM(amount_minor) OVER (ORDER BY id ROWS UNBOUNDED PRECEDING) AS resulting_balance')
            ->orderByDesc('id')->paginate(20);

        return view('portal.users.edit', [
            'user' => $user,
            'prices' => $prices->sortBy([['version', 'asc'], ['label', 'asc']]),
            'history' => $history,
            'balance' => DB::table('billing_entries')->where('user_id', $user->id)->sum('amount_minor'),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->accountData($request, $user);
        $prices = $this->existingPrices($request);

        $this->changeAccount($request, $user, function (User $locked) use ($data, $prices): void {
            if ($locked->account_status === 'archived') {
                $data['account_status'] = 'archived';
            }

            $this->saveAccount($locked, $data);
            $this->savePrices($locked, $prices);
        });

        return back()->with('notice', 'User updated.');
    }

    public function updatePrices(Request $request, User $user): RedirectResponse
    {
        $prices = $this->existingPrices($request);
        $catalog = $this->serviceCatalog();
        $new = $request->validate([
            'new_prices' => ['sometimes', 'array'],
            'new_prices.*' => ['array'],
            'new_prices.*.*' => self::PRICE_RULE,
        ])['new_prices'] ?? [];

        foreach ($new as $version => $services) {
            foreach (array_keys($services) as $service) {
                if (! isset($catalog[$version][$service])) {
                    throw ValidationException::withMessages(['prices' => 'Select a supported service and API version.']);
                }
            }
        }

        DB::transaction(function () use ($user, $prices, $new): void {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            foreach ($new as $version => $services) {
                foreach ($services as $service => $price) {
                    if ($price === null) {
                        continue;
                    }

                    $key = ['service' => $service, 'version' => $version];
                    DB::table('service_prices')->insertOrIgnore([...$key, 'price_minor' => null]);
                    $id = DB::table('service_prices')->where($key)->value('id');
                    $prices[$id] = $price;
                }
            }

            $this->savePrices($locked, $prices);
        }, 3);

        return back()->with('notice', 'Individual service prices updated.');
    }

    /** @return array<string, array<string, string>> */
    private function serviceCatalog(): array
    {
        $catalog = config('verification_catalog');
        $catalog['v1'] += ['instant-ipe-single' => 'Instant-ipe'];

        return $catalog;
    }

    /** @return array<int, string|null> */
    private function existingPrices(Request $request): array
    {
        $prices = $request->validate(['prices' => ['sometimes', 'array'], 'prices.*' => self::PRICE_RULE])['prices'] ?? [];
        $priceIds = DB::table('service_prices')->where('service', '<>', 'instant-ipe-bulk')->pluck('id')->flip();

        foreach (array_keys($prices) as $id) {
            if (! $priceIds->has($id)) {
                throw ValidationException::withMessages(['prices' => 'Select an existing service price.']);
            }
        }

        return $prices;
    }

    public function password(Request $request, User $user): RedirectResponse
    {
        $password = $this->validatedPassword($request);

        DB::transaction(function () use ($user, $password): void {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $locked->password = $password;
            $this->revokeSessions($locked);
            $locked->save();
        }, 3);

        return back()->with('notice', 'Password updated. Existing sessions were revoked.');
    }

    public function sendVerification(User $user): RedirectResponse
    {
        if (! $user->isActive() || $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['email' => 'Only active accounts with unverified emails can receive a verification link.']);
        }

        $user->sendEmailVerificationNotification();

        return back()->with('notice', 'Verification email sent.');
    }

    public function status(Request $request, User $user): RedirectResponse
    {
        $action = $request->validate(['action' => ['required', Rule::in(['suspend', 'reactivate', 'archive', 'restore'])]])['action'];

        $this->changeAccount($request, $user, function (User $locked) use ($action): void {
            [$expected, $next] = match ($action) {
                'suspend' => [['active'], 'suspended'],
                'reactivate' => [['suspended'], 'active'],
                'archive' => [['active', 'suspended'], 'archived'],
                'restore' => [['archived'], 'active'],
            };

            if (! in_array($locked->account_status, $expected, true)) {
                throw ValidationException::withMessages(['account_status' => 'This action is unavailable for the current account status.']);
            }

            $locked->account_status = $next;
        });

        return back()->with('notice', 'Account status updated.');
    }

    public function adjustBalance(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'transaction_type' => ['required', Rule::in(['credit', 'debit'])],
            'amount' => ['required', 'string', 'regex:'.self::MONEY],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        $amount = $this->minor($data['amount']);

        if ($amount === 0) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount greater than zero.']);
        }

        DB::transaction(function () use ($request, $user, $data, $amount): void {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $ledger = DB::table('billing_entries');
            $isDebit = $data['transaction_type'] === 'debit';

            if ($isDebit && (clone $ledger)->where('user_id', $locked->id)->sum('amount_minor') < $amount) {
                throw ValidationException::withMessages(['amount' => 'The debit exceeds the available balance.']);
            }

            $ledger->insert([
                'user_id' => $locked->id,
                'actor_id' => $request->user()->id,
                'amount_minor' => $isDebit ? -$amount : $amount,
                'description' => $data['reason'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 3);

        return back()->with('notice', 'Balance adjusted.');
    }

    private function accountData(Request $request, ?User $user = null): array
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => $request->str('email')->lower()->toString()]);
        }
        if (is_string($request->input('phone'))) {
            $request->merge(['phone' => $request->str('phone')->replaceMatches('/[\s().-]+/', '')->toString()]);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', $user ? Rule::unique('users')->ignore($user) : Rule::unique('users')],
            'business_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/\A\+?[0-9]{7,15}\z/', $user ? Rule::unique('users')->ignore($user) : Rule::unique('users')],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'account_status' => ['required', Rule::in(['active', 'suspended'])],
        ]);
    }

    private function validatedPassword(Request $request): string
    {
        return $request->validate([
            'password' => ['required', 'string', 'confirmed', Password::min(8), function (string $attribute, string $value, Closure $fail): void {
                if (strlen($value) > 72 || str_contains($value, "\0")) {
                    $fail('Use a password of up to 72 bytes without null characters.');
                }
            }],
        ])['password'];
    }

    private function saveAccount(User $user, array $data): void
    {
        $user->fill(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null]);
        $user->business_name = $data['business_name'] ?? null;
        $user->role = $data['role'];
        $user->is_admin = $data['role'] !== 'user';
        $user->account_status = $data['account_status'];

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
    }

    private function savePrices(User $user, array $prices): void
    {
        foreach ($prices as $id => $price) {
            $key = ['user_id' => $user->id, 'service_price_id' => $id];

            if ($price === null) {
                DB::table('user_service_prices')->where($key)->delete();
            } else {
                DB::table('user_service_prices')->upsert([
                    [...$key, 'price_minor' => $this->minor($price)],
                ], ['user_id', 'service_price_id'], ['price_minor']);
            }
        }
    }

    private function changeAccount(Request $request, User $user, Closure $change): void
    {
        DB::transaction(function () use ($request, $user, $change): void {
            $superAdmins = User::where('role', 'super_admin')->where('account_status', 'active')->orderBy('id')->lockForUpdate()->pluck('id');
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $change($locked);

            if ($superAdmins->contains($locked->id) && ($locked->role !== 'super_admin' || ! $locked->isActive())
                && ($superAdmins->count() === 1 || $request->user()->is($locked))) {
                throw ValidationException::withMessages(['role' => 'You cannot remove your own active super-admin access or disable the last active super admin.']);
            }

            if (($locked->isDirty('account_status') && ! $locked->isActive()) || $locked->isDirty('email')) {
                $this->revokeSessions($locked);
            }

            $locked->save();
        }, 3);
    }

    private function revokeSessions(User $user): void
    {
        $user->session_version++;
        $user->remember_token = null;

        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))->table(config('session.table'))->where('user_id', $user->id)->delete();
        }
    }

    private function minor(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
