<?php

declare(strict_types=1);

namespace Traffical\Tests\Unit\Engine;

use PHPUnit\Framework\TestCase;
use Traffical\Engine\Conditions;
use Traffical\Types\BundleCondition;

final class ConditionsTest extends TestCase
{
    public function testEmptyConditionsMatch(): void
    {
        self::assertTrue(Conditions::evaluateAll([], ['anything' => true]));
    }

    public function testEqAndNeq(): void
    {
        $context = ['country' => 'US'];
        self::assertTrue(Conditions::evaluate(new BundleCondition('country', 'eq', 'US'), $context));
        self::assertFalse(Conditions::evaluate(new BundleCondition('country', 'eq', 'CA'), $context));
        self::assertTrue(Conditions::evaluate(new BundleCondition('country', 'neq', 'CA'), $context));
    }

    public function testInAndNin(): void
    {
        $context = ['plan' => 'pro'];
        self::assertTrue(Conditions::evaluate(new BundleCondition('plan', 'in', null, ['pro', 'team']), $context));
        self::assertTrue(Conditions::evaluate(new BundleCondition('plan', 'nin', null, ['free']), $context));
    }

    public function testNumericComparisons(): void
    {
        $context = ['age' => 30];
        self::assertTrue(Conditions::evaluate(new BundleCondition('age', 'gt', 18), $context));
        self::assertTrue(Conditions::evaluate(new BundleCondition('age', 'gte', 30), $context));
        self::assertTrue(Conditions::evaluate(new BundleCondition('age', 'lt', 65), $context));
        self::assertFalse(Conditions::evaluate(new BundleCondition('age', 'lt', 30), $context));
    }

    public function testStringOperators(): void
    {
        $context = ['email' => 'user@example.com'];
        self::assertTrue(Conditions::evaluate(new BundleCondition('email', 'contains', 'example'), $context));
        self::assertTrue(Conditions::evaluate(new BundleCondition('email', 'startsWith', 'user@'), $context));
        self::assertTrue(Conditions::evaluate(new BundleCondition('email', 'endsWith', '.com'), $context));
    }

    public function testExistsAndNotExists(): void
    {
        $context = ['userId' => 'abc'];
        self::assertTrue(Conditions::evaluate(new BundleCondition('userId', 'exists'), $context));
        self::assertTrue(Conditions::evaluate(new BundleCondition('missing', 'notExists'), $context));
        self::assertFalse(Conditions::evaluate(new BundleCondition('missing', 'exists'), $context));
    }

    public function testNestedDotPathAccess(): void
    {
        $context = ['user' => ['profile' => ['tier' => 'gold']]];
        self::assertTrue(Conditions::evaluate(new BundleCondition('user.profile.tier', 'eq', 'gold'), $context));
        self::assertFalse(Conditions::evaluate(new BundleCondition('user.profile.tier', 'eq', 'silver'), $context));
    }

    public function testAndSemantics(): void
    {
        $context = ['country' => 'US', 'age' => 25];
        $all = [
            new BundleCondition('country', 'eq', 'US'),
            new BundleCondition('age', 'gte', 18),
        ];
        self::assertTrue(Conditions::evaluateAll($all, $context));

        $all[] = new BundleCondition('age', 'gte', 30);
        self::assertFalse(Conditions::evaluateAll($all, $context));
    }
}
