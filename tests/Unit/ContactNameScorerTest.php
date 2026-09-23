<?php

namespace Tests\Unit;

use App\Services\Contacts\ContactNameScorer;
use PHPUnit\Framework\TestCase;

final class ContactNameScorerTest extends TestCase
{
    public function test_exact_three_word_name_outranks_a_shorter_overlap(): void
    {
        $ranked = (new ContactNameScorer)->rank('Jane Marie Doe', [
            ['id' => 'short', 'first_name' => 'Jane', 'last_name' => 'Doe'],
            ['id' => 'exact', 'first_name' => 'Jane Marie', 'last_name' => 'Doe'],
            ['id' => 'other', 'first_name' => 'Marie', 'last_name' => 'Smith'],
        ], 5);

        $this->assertSame('exact', $ranked['matches'][0]['id']);
        $this->assertSame(100, $ranked['matches'][0]['score']);
        $this->assertTrue($ranked['confident']);
        $this->assertSame(['exact', 'short', 'other'], array_column($ranked['matches'], 'id'));
    }

    public function test_word_order_does_not_block_a_full_name_match(): void
    {
        $ranked = (new ContactNameScorer)->rank('Doe Jane', [
            ['id' => 'jane', 'first_name' => 'Jane', 'last_name' => 'Doe'],
            ['id' => 'doe-only', 'first_name' => 'Sam', 'last_name' => 'Doe'],
        ], 5);

        $this->assertSame('jane', $ranked['matches'][0]['id']);
        $this->assertGreaterThanOrEqual(70, $ranked['matches'][0]['score']);
        $this->assertLessThan(100, $ranked['matches'][0]['score']);
        $this->assertTrue($ranked['confident']);
        $this->assertGreaterThan($ranked['matches'][1]['score'], $ranked['matches'][0]['score']);
    }

    public function test_two_exact_names_are_not_a_confident_match(): void
    {
        $ranked = (new ContactNameScorer)->rank('Jane Doe', [
            ['id' => 'b', 'first_name' => 'Jane', 'last_name' => 'Doe'],
            ['id' => 'a', 'first_name' => 'Jane', 'last_name' => 'Doe'],
        ], 5);

        $this->assertFalse($ranked['confident']);
        $this->assertSame(['a', 'b'], array_column($ranked['matches'], 'id'));
    }

    public function test_hyphenated_stored_name_matches_spaced_query(): void
    {
        $ranked = (new ContactNameScorer)->rank('Mary Jane Doe', [
            ['id' => 'hyphen', 'first_name' => 'Mary-Jane', 'last_name' => 'Doe'],
        ], 5);

        $this->assertSame('hyphen', $ranked['matches'][0]['id']);
        $this->assertSame(100, $ranked['matches'][0]['score']);
        $this->assertTrue($ranked['confident']);
    }

    public function test_typo_still_prefers_the_close_name(): void
    {
        $ranked = (new ContactNameScorer)->rank('Jne Doe', [
            ['id' => 'close', 'first_name' => 'Jane', 'last_name' => 'Doe'],
            ['id' => 'far', 'first_name' => 'Marie', 'last_name' => 'Smith'],
        ], 5);

        $this->assertSame('close', $ranked['matches'][0]['id']);
        $this->assertNotContains('far', array_column($ranked['matches'], 'id'));
    }

    public function test_unique_single_word_name_is_confident_over_a_longer_name(): void
    {
        $ranked = (new ContactNameScorer)->rank('Jane', [
            ['id' => 'longer', 'first_name' => 'Jane', 'last_name' => 'Doe'],
            ['id' => 'exact', 'first_name' => 'Jane', 'last_name' => ''],
        ], 5);

        $this->assertSame('exact', $ranked['matches'][0]['id']);
        $this->assertTrue($ranked['confident']);
    }
}
