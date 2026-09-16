<?php

namespace Tests\Unit;

use App\Helpers\NumberHelper;
use Tests\TestCase;

class NumberHelperTest extends TestCase
{
    public function test_format_whole_amount_removes_decimal_places(): void
    {
        $this->assertSame('100', NumberHelper::formatWholeAmount(100.00));
        $this->assertSame('1500', NumberHelper::formatWholeAmount('1500.00'));
        $this->assertSame('1235', NumberHelper::formatWholeAmount(1234.56));
    }
}
