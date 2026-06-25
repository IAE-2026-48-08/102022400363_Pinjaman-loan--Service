<?php

namespace App\GraphQL\Mutations;

use App\Models\Loan;
use App\Models\Role;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CreateLoan
{
    protected LoanService $loanService;

    public function __construct(LoanService $loanService)
    {
        $this->loanService = $loanService;
    }

    /**
     * Apply for a new loan via GraphQL.
     *
     * @param  null  $_
     * @param  array<string, mixed>  $args
     * @return Loan
     */
    public function __invoke($_, array $args): Loan
    {
        $accountId      = $args['account_id'];
        $amount         = $args['amount'];
        $durationMonths = $args['duration_months'];

        // Find or create a user record for this account
        $user = User::firstOrCreate(
            ['email' => $accountId],
            [
                'name'     => 'GraphQL Applicant',
                'password' => bcrypt(Str::random(16)),
            ]
        );

        // Assign 'warga' role if not already assigned
        $role = Role::where('slug', 'warga')->first();
        if ($role && !$user->roles()->where('role_id', $role->id)->exists()) {
            $user->roles()->attach($role->id);
        }

        // Log user in so LoanService can reference authenticated user context
        Auth::login($user);

        return $this->loanService->applyForLoan($accountId, $amount, $durationMonths);
    }
}
