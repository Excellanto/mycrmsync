<?php

namespace Tests\Unit;

use App\Services\Contacts\ContactNoteSummaryExtractor;
use PHPUnit\Framework\TestCase;

final class ContactNoteSummaryExtractorTest extends TestCase
{
    public function test_extracts_summary_section_from_note_body(): void
    {
        $body = "Summary: Prospect wants a call Friday at 11:30 AM.\n\nTranscription: hello there";

        $this->assertSame(
            'Prospect wants a call Friday at 11:30 AM.',
            ContactNoteSummaryExtractor::fromBody($body)
        );
    }

    public function test_returns_empty_when_no_summary_present(): void
    {
        $this->assertSame('', ContactNoteSummaryExtractor::fromBody('Manual note without summary section.'));
    }
}
