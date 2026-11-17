<?php

namespace Tests\Feature\Finance;

use App\Http\Requests\Finance\StoreExpenseRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ExpenseCostBehaviorTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_schema_keeps_historical_behavior_unclassified(): void
    {
        $this->assertTrue(Schema::hasColumn('expenses', 'cost_behavior'));
        $this->assertTrue(Schema::hasColumn('expenses', 'cost_center'));
    }

    public function test_new_expenses_require_supported_behavior_and_center(): void
    {
        foreach ([
            [['cost_behavior' => null, 'cost_center' => null], true],
            [['cost_behavior' => 'unexpected', 'cost_center' => 'kitchen'], true],
            [['cost_behavior' => 'temporary', 'cost_center' => 'kitchen'], false],
            [['cost_behavior' => 'variable', 'cost_center' => 'delivery'], false],
            [['cost_behavior' => 'fixed', 'cost_center' => 'admin'], false],
        ] as [$fields, $shouldFail]) {
            $request = StoreExpenseRequest::create('/costing/expenses', 'POST', $fields);
            $validator = Validator::make($request->all(), $request->rules());
            $validator->fails();

            $this->assertSame(
                $shouldFail,
                $validator->errors()->has('cost_behavior') || $validator->errors()->has('cost_center')
            );
        }
    }
}
