<?php

namespace Tests\Unit;

use App\Support\HtmlText;
use PHPUnit\Framework\TestCase;

class HtmlTextTest extends TestCase
{
    public function test_it_keeps_links_next_to_their_text(): void
    {
        $html = '<style>.x{}</style><p>New job:</p><a href="https://www.linkedin.com/jobs/view/123?trk=abc">PHP Developer</a><br>Acme GmbH';

        $text = HtmlText::fromHtml($html);

        $this->assertStringContainsString('PHP Developer (https://www.linkedin.com/jobs/view/123?trk=abc)', $text);
        $this->assertStringContainsString('Acme GmbH', $text);
        $this->assertStringNotContainsString('.x{}', $text);
    }
}
