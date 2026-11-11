<?php

namespace Tests\Unit;

use App\Models\JobLead;
use PHPUnit\Framework\TestCase;

class JobLeadFingerprintTest extends TestCase
{
    public function test_tracking_params_do_not_change_the_fingerprint(): void
    {
        $a = JobLead::fingerprintFor('https://www.linkedin.com/jobs/view/123/?trackingId=aaa&refId=bbb', 'PHP Dev', 'Acme');
        $b = JobLead::fingerprintFor('https://www.linkedin.com/jobs/view/123?trackingId=zzz', 'Other title', 'Acme');

        $this->assertSame($a, $b);
    }

    public function test_job_id_param_is_kept(): void
    {
        $this->assertNotSame(
            JobLead::fingerprintFor('https://de.indeed.com/viewjob?jk=111&from=mail', null, null),
            JobLead::fingerprintFor('https://de.indeed.com/viewjob?jk=222&from=mail', null, null),
        );
    }

    public function test_without_url_title_and_company_are_used(): void
    {
        $this->assertSame(
            JobLead::fingerprintFor(null, 'PHP Developer', 'Acme GmbH'),
            JobLead::fingerprintFor('', ' php developer ', 'ACME'),
        );
    }
}
