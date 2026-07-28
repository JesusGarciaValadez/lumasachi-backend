<?php

declare(strict_types=1);

use App\Enums\UserType;

it('checks if user types are defined', function () {
    expect(UserType::INDIVIDUAL->value)->toEqual('Individual');
    expect(UserType::BUSINESS->value)->toEqual('Business');
});
it('checks if get types returns all values', function () {
    $types = UserType::getTypes();

    expect($types)->toBeArray();
    expect($types)->toHaveCount(2);
    expect($types)->toContain('Individual');
    expect($types)->toContain('Business');
});
it('checks if get label returns correct labels', function () {
    expect(UserType::INDIVIDUAL->getLabel())->toEqual('Individual');
    expect(UserType::BUSINESS->getLabel())->toEqual('Business');
});
it('checks if all enum values are unique', function () {
    $values = array_column(UserType::cases(), 'value');
    $uniqueValues = array_unique($values);

    expect($uniqueValues)->toHaveCount(count($values));
});
it('checks if enum can be created from string', function () {
    $individual = UserType::from('Individual');
    $business = UserType::from('Business');

    expect($individual)->toEqual(UserType::INDIVIDUAL);
    expect($business)->toEqual(UserType::BUSINESS);
});
it('checks if invalid string throws exception', function () {
    $this->expectException(ValueError::class);
    UserType::from('InvalidType');
});
it('checks if try from method', function () {
    $individual = UserType::tryFrom('Individual');
    $business = UserType::tryFrom('Business');
    $invalid = UserType::tryFrom('InvalidType');

    expect($individual)->toEqual(UserType::INDIVIDUAL);
    expect($business)->toEqual(UserType::BUSINESS);
    expect($invalid)->toBeNull();
});
it('checks if cases method returns all cases', function () {
    $cases = UserType::cases();

    expect($cases)->toBeArray();
    expect($cases)->toHaveCount(2);
    $this->assertContainsOnlyInstancesOf(UserType::class, $cases);
    expect($cases)->toContain(UserType::INDIVIDUAL);
    expect($cases)->toContain(UserType::BUSINESS);
});
it('checks if enum name property', function () {
    expect(UserType::INDIVIDUAL->name)->toEqual('INDIVIDUAL');
    expect(UserType::BUSINESS->name)->toEqual('BUSINESS');
});
it('checks if labels have correct format', function () {
    foreach (UserType::cases() as $type) {
        $label = $type->getLabel();

        // Check that label is not empty
        expect($label)->not->toBeEmpty();

        // Check that label starts with uppercase letter
        expect($label)->toMatch('/^[A-Z]/');

        // Check that label contains only letters and spaces
        expect($label)->toMatch('/^[A-Za-z\s]+$/');
    }
});
