<?php

namespace Modules\Lumasachi\Tests\Unit\app\Enums;

use Modules\Lumasachi\app\Enums\UserType;
use PHPUnit\Framework\TestCase;

final class UserTypeTest extends TestCase
{
    /**
     * Test that all user types are defined correctly
     */
    public function test_user_types_are_defined(): void
    {
        $this->assertEquals('Individual', UserType::INDIVIDUAL->value);
        $this->assertEquals('Business', UserType::BUSINESS->value);
    }

    /**
     * Test that getTypes returns all user type values
     */
    public function test_get_types_returns_all_values(): void
    {
        $types = UserType::getTypes();

        $this->assertIsArray($types);
        $this->assertCount(2, $types);
        $this->assertContains('Individual', $types);
        $this->assertContains('Business', $types);
    }

    /**
     * Test that getLabel returns correct labels for each type
     */
    public function test_get_label_returns_correct_labels(): void
    {
        $this->assertEquals('Individual', UserType::INDIVIDUAL->getLabel());
        $this->assertEquals('Business', UserType::BUSINESS->getLabel());
    }

    /**
     * Test that all enum cases have unique values
     */
    public function test_all_enum_values_are_unique(): void
    {
        $values = array_column(UserType::cases(), 'value');
        $uniqueValues = array_unique($values);

        $this->assertCount(count($values), $uniqueValues, 'UserType enum values should be unique');
    }

    /**
     * Test that enum can be created from string value
     */
    public function test_enum_can_be_created_from_string(): void
    {
        $individual = UserType::from('Individual');
        $business = UserType::from('Business');

        $this->assertEquals(UserType::INDIVIDUAL, $individual);
        $this->assertEquals(UserType::BUSINESS, $business);
    }

    /**
     * Test that invalid string throws exception
     */
    public function test_invalid_string_throws_exception(): void
    {
        $this->expectException(\ValueError::class);
        UserType::from('InvalidType');
    }

    /**
     * Test tryFrom method with valid and invalid values
     */
    public function test_try_from_method(): void
    {
        $individual = UserType::tryFrom('Individual');
        $business = UserType::tryFrom('Business');
        $invalid = UserType::tryFrom('InvalidType');

        $this->assertEquals(UserType::INDIVIDUAL, $individual);
        $this->assertEquals(UserType::BUSINESS, $business);
        $this->assertNull($invalid);
    }

    /**
     * Test that enum cases can be retrieved
     */
    public function test_cases_method_returns_all_cases(): void
    {
        $cases = UserType::cases();

        $this->assertIsArray($cases);
        $this->assertCount(2, $cases);
        $this->assertContainsOnlyInstancesOf(UserType::class, $cases);
        $this->assertContains(UserType::INDIVIDUAL, $cases);
        $this->assertContains(UserType::BUSINESS, $cases);
    }

    /**
     * Test enum name property
     */
    public function test_enum_name_property(): void
    {
        $this->assertEquals('INDIVIDUAL', UserType::INDIVIDUAL->name);
        $this->assertEquals('BUSINESS', UserType::BUSINESS->name);
    }

    /**
     * Test that labels match the expected format
     */
    public function test_labels_have_correct_format(): void
    {
        foreach (UserType::cases() as $type) {
            $label = $type->getLabel();

            // Check that label is not empty
            $this->assertNotEmpty($label, "Label for {$type->name} should not be empty");

            // Check that label starts with uppercase letter
            $this->assertMatchesRegularExpression('/^[A-Z]/', $label, "Label should start with uppercase letter");

            // Check that label contains only letters and spaces
            $this->assertMatchesRegularExpression('/^[A-Za-z\s]+$/', $label, "Label should contain only letters and spaces");
        }
    }
}
