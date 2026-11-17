<?php

namespace Tests\Feature\Restaurant;

use App\Http\Requests\Restaurant\StoreRestaurantPosOrderRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PosManualDiscountReasonTest extends TestCase
{
    public function test_manual_discount_requires_reason_but_no_discount_does_not(): void
    {
        $cases = [
            [['discount_type' => 'fixed', 'discount_value' => 10], true],
            [['discount_type' => 'percentage', 'discount_value' => 5, 'discount_reason' => ''], true],
            [['discount_type' => 'percentage', 'discount_value' => 5, 'discount_reason' => 'تأخر الطلب وتضرر الزبون'], false],
            [['discount_type' => 'none', 'discount_value' => 0], false],
        ];

        foreach ($cases as [$data, $expectsReasonError]) {
            $request = StoreRestaurantPosOrderRequest::create('/restaurant/pos/orders', 'POST', array_merge([
                'service_type' => 'takeaway',
                'payment_arrangement' => 'pay_on_pickup',
            ], $data));
            $validator = Validator::make($request->all(), $request->rules());
            $request->withValidator($validator);
            $validator->fails();

            $this->assertSame(
                $expectsReasonError,
                $validator->errors()->has('discount_reason')
            );
        }
    }
}
