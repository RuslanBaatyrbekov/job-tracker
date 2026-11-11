<?php

namespace Tests\Unit;

use App\Enums\ApplicationStatus as S;
use PHPUnit\Framework\TestCase;

class ApplicationStatusTest extends TestCase
{
    public function test_transitions(): void
    {
        $this->assertTrue(S::Applied->canTransitionTo(S::Interview));
        $this->assertTrue(S::Applied->canTransitionTo(S::Rejected));
        $this->assertTrue(S::Interview->canTransitionTo(S::Rejected));
        $this->assertTrue(S::NoResponse->canTransitionTo(S::Interview));

        // Late auto-confirmation must not move an interview back to "applied".
        $this->assertFalse(S::Interview->canTransitionTo(S::Applied));
        // Closed processes stay closed.
        $this->assertFalse(S::Rejected->canTransitionTo(S::Interview));
        $this->assertFalse(S::Offer->canTransitionTo(S::Rejected));
    }
}
