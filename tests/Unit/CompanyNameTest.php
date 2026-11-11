<?php

namespace Tests\Unit;

use App\Support\CompanyName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CompanyNameTest extends TestCase
{
    #[DataProvider('names')]
    public function test_it_normalizes_company_names(string $input, ?string $expected): void
    {
        $this->assertSame($expected, CompanyName::key($input));
    }

    public static function names(): array
    {
        return [
            ['ACME Software GmbH', 'acme software'],
            ['Acme Software GmbH & Co. KG', 'acme software'],
            ['acme-software', 'acme software'],
            ['Müller Digital AG', 'müller digital'],
            ['  ', null],
            ['null', null],
        ];
    }

    public function test_it_ignores_free_mail_and_ats_domains(): void
    {
        $this->assertSame('acme.de', CompanyName::domainFromEmail('jobs@careers.acme.de'));
        $this->assertNull(CompanyName::domainFromEmail('someone@gmail.com'));
        $this->assertNull(CompanyName::domainFromEmail('no-reply@acme.personio.de'));
        $this->assertNull(CompanyName::domainFromEmail('notifications@greenhouse.io'));
    }

    public function test_title_similarity(): void
    {
        $this->assertGreaterThan(80, CompanyName::titleSimilarity('Backend Developer (PHP/Laravel)', 'Backend Developer PHP Laravel'));
        $this->assertLessThan(45, CompanyName::titleSimilarity('Backend Developer PHP', 'Marketing Manager'));
    }
}
