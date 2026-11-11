<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Normalization helpers to recognise the same company across emails:
 * "ACME Software GmbH & Co. KG" and "Acme Software" -> "acme software".
 */
class CompanyName
{
    private const LEGAL_FORMS = [
        'gmbh & co. kg', 'gmbh & co kg', 'gmbh & co. kgaa', 'ag & co. kg', 'se & co. kg',
        'gmbh', 'mbh', 'ggmbh', 'ag', 'se', 'kg', 'kgaa', 'ohg', 'ug (haftungsbeschränkt)', 'ug', 'e.k.', 'e.v.',
        'inc.', 'inc', 'ltd.', 'ltd', 'llc', 'plc', 'b.v.', 'bv', 'n.v.', 'sarl', 's.a.', 'sa', 'sp. z o.o.', 'oy', 'ab', 'as',
        'group', 'gruppe', 'holding',
    ];

    /** Free mailboxes, ATS platforms and job boards: their domain says nothing about the employer. */
    private const NON_COMPANY_DOMAINS = [
        'gmail.com', 'googlemail.com', 'outlook.com', 'hotmail.com', 'live.com', 'yahoo.com', 'icloud.com',
        'gmx.de', 'gmx.net', 'web.de', 't-online.de', 'mail.de', 'proton.me', 'protonmail.com',
        'personio.de', 'personio.com', 'greenhouse.io', 'lever.co', 'myworkday.com', 'workday.com', 'smartrecruiters.com',
        'recruitee.com', 'join.com', 'softgarden.io', 'softgarden.de', 'successfactors.com', 'successfactors.eu', 'workable.com',
        'bamboohr.com', 'teamtailor.com', 'teamtailor-mail.com', 'ashbyhq.com', 'rexx-systems.com', 'dvinci-hr.com', 'concludis.de',
        'onlyfy.jobs', 'prescreen.io', 'hr4you.org', 'jobylon.com', 'comeet.co', 'icims.com', 'taleo.net', 'jobvite.com',
        'linkedin.com', 'stepstone.de', 'stepstone.com', 'indeed.com', 'xing.com', 'arbeitsagentur.de', 'glassdoor.com',
    ];

    public static function key(?string $name): ?string
    {
        $name = Str::lower(trim((string) $name));
        if ($name === '' || in_array($name, ['null', 'unknown', 'неизвестная компания'], true)) {
            return null;
        }

        // Longest forms first, so "gmbh & co. kg" is removed before "gmbh".
        $forms = self::LEGAL_FORMS;
        usort($forms, fn ($a, $b) => strlen($b) <=> strlen($a));
        foreach ($forms as $form) {
            $name = preg_replace('/(^|\s)'.preg_quote($form, '/').'(?=\s|$)/u', ' ', $name);
        }

        $name = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name);
        $name = trim(preg_replace('/\s+/', ' ', $name));

        return $name !== '' ? $name : null;
    }

    /**
     * Employer domain from an email address, or null for free mail / ATS / job boards.
     */
    public static function domainFromEmail(?string $email): ?string
    {
        $host = Str::lower(Str::after((string) $email, '@'));
        if ($host === '' || ! str_contains($host, '.')) {
            return null;
        }

        // Reduce "jobs.mail.acme.de" to "acme.de" (good enough for .de/.com, the common case here).
        $parts = explode('.', $host);
        $domain = implode('.', array_slice($parts, -2));

        foreach (self::NON_COMPANY_DOMAINS as $generic) {
            if ($host === $generic || str_ends_with($host, '.'.$generic) || $domain === $generic) {
                return null;
            }
        }

        return $domain;
    }

    /**
     * 0..100 similarity between two job titles.
     */
    public static function titleSimilarity(?string $a, ?string $b): int
    {
        $a = self::normalizeTitle($a);
        $b = self::normalizeTitle($b);

        if ($a === '' || $b === '') {
            return 0;
        }

        similar_text($a, $b, $percent);

        return (int) round($percent);
    }

    private static function normalizeTitle(?string $title): string
    {
        $title = Str::lower((string) $title);
        // German postings: "(m/w/d)", "(w/m/d)", "(all genders)" carry no meaning for matching.
        $title = preg_replace('~\((?:[mwfdx]\s*/\s*)+[mwfdx]\)|\(all genders?\)|\(gn\)~u', ' ', $title);
        $title = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $title);

        return trim(preg_replace('/\s+/', ' ', $title));
    }
}
