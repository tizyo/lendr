<?php

use App\Enums\LoanStatus;

test('loan status has correct labels', function () {
    expect(LoanStatus::Active->label())->toBe('Active');
    expect(LoanStatus::Completed->label())->toBe('Completed');
    expect(LoanStatus::Denied->label())->toBe('Denied');
    expect(LoanStatus::WrittenOff->label())->toBe('Written Off');
});

test('active statuses are identified correctly', function () {
    expect(LoanStatus::Active->isActive())->toBeTrue();
    expect(LoanStatus::Disbursed->isActive())->toBeTrue();
    expect(LoanStatus::Completed->isActive())->toBeFalse();
    expect(LoanStatus::Denied->isActive())->toBeFalse();
});

test('valid status transitions are enforced', function () {
    // Submitted can move to Approved or Denied
    expect(LoanStatus::Submitted->canTransitionTo(LoanStatus::Approved))->toBeTrue();
    expect(LoanStatus::Submitted->canTransitionTo(LoanStatus::Denied))->toBeTrue();
    expect(LoanStatus::Submitted->canTransitionTo(LoanStatus::Active))->toBeFalse();

    // Approved can be Disbursed or Denied
    expect(LoanStatus::Approved->canTransitionTo(LoanStatus::Disbursed))->toBeTrue();
    expect(LoanStatus::Approved->canTransitionTo(LoanStatus::Denied))->toBeTrue();
    expect(LoanStatus::Approved->canTransitionTo(LoanStatus::Completed))->toBeFalse();

    // Active can Freeze, Complete, or Default
    expect(LoanStatus::Active->canTransitionTo(LoanStatus::Frozen))->toBeTrue();
    expect(LoanStatus::Active->canTransitionTo(LoanStatus::Completed))->toBeTrue();
    expect(LoanStatus::Active->canTransitionTo(LoanStatus::Defaulted))->toBeTrue();
    expect(LoanStatus::Active->canTransitionTo(LoanStatus::Submitted))->toBeFalse();

    // Completed is terminal
    expect(LoanStatus::Completed->canTransitionTo(LoanStatus::Active))->toBeFalse();
});

test('loan status colors are defined for all cases', function () {
    foreach (LoanStatus::cases() as $status) {
        expect($status->color())->toBeString()->not->toBeEmpty();
    }
});
