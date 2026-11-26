<?php

namespace Tests\Unit;

use App\Support\ResponseTimes;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ResponseTimesTest extends TestCase
{
    protected function msg(int $conv, bool $fromPage, string $at): object
    {
        return (object) ['social_conversation_id' => $conv, 'from_page' => $fromPage, 'sent_at' => Carbon::parse($at)];
    }

    public function test_response_runs_from_first_unanswered_customer_message(): void
    {
        $since = Carbon::parse('2026-11-01');
        $stats = ResponseTimes::perConversation([
            // Two customer messages, then a reply 10 minutes after the first one.
            $this->msg(1, false, '2026-11-10 09:00'),
            $this->msg(1, false, '2026-11-10 09:05'),
            $this->msg(1, true, '2026-11-10 09:10'),
            // A second exchange answered after 2 hours.
            $this->msg(1, false, '2026-11-10 12:00'),
            $this->msg(1, true, '2026-11-10 14:00'),
            // A page message nobody asked for is not a response.
            $this->msg(1, true, '2026-11-10 15:00'),
        ], $since);

        $this->assertSame([600, 7200], $stats[1]['responses']);
        $this->assertSame(600, $stats[1]['first']);
        $this->assertTrue($stats[1]['inbound']);
        $this->assertNull($stats[1]['awaitingSince']);
    }

    public function test_awaiting_and_period_boundaries(): void
    {
        $since = Carbon::parse('2026-11-10');
        $stats = ResponseTimes::perConversation([
            // Wait started before the period: the reply isn't counted.
            $this->msg(1, false, '2026-11-09 23:00'),
            $this->msg(1, true, '2026-11-10 01:00'),
            // Last word is the customer's: waiting.
            $this->msg(2, true, '2026-11-10 08:00'),
            $this->msg(2, false, '2026-11-10 09:00'),
            // Messages arrive out of order: sorted by time first.
            $this->msg(3, true, '2026-11-11 10:30'),
            $this->msg(3, false, '2026-11-11 10:00'),
        ], $since);

        $this->assertSame([], $stats[1]['responses']);
        $this->assertFalse($stats[1]['inbound']);
        $this->assertEquals(Carbon::parse('2026-11-10 09:00'), $stats[2]['awaitingSince']);
        $this->assertSame([1800], $stats[3]['responses']);

        $summary = ResponseTimes::summary($stats);
        $this->assertSame(2, $summary['conversations']); // 2 and 3 had customer messages in the period
        $this->assertSame(1, $summary['replied']);
        $this->assertSame(50.0, $summary['replyRate']);
        $this->assertSame(1, $summary['awaiting']);
        $this->assertSame(1800, $summary['medianFirst']);
        $this->assertSame(100.0, $summary['within1h']);
        $this->assertSame(1, $summary['buckets']['15–60 min']);
    }

    public function test_a_failed_reply_does_not_answer(): void
    {
        $since = Carbon::parse('2026-11-01');
        $failed = (object) ['social_conversation_id' => 1, 'from_page' => true, 'status' => 'failed', 'sent_at' => Carbon::parse('2026-11-10 09:05')];
        $stats = ResponseTimes::perConversation([
            $this->msg(1, false, '2026-11-10 09:00'),
            $failed,
            $this->msg(1, true, '2026-11-10 09:30'), // the reply that got through
        ], $since);

        $this->assertSame([1800], $stats[1]['responses']);

        $onlyFailed = ResponseTimes::perConversation([$this->msg(2, false, '2026-11-10 09:00'), (object) (['social_conversation_id' => 2] + (array) $failed)], $since);
        $this->assertSame([], $onlyFailed[2]['responses']);
        $this->assertNotNull($onlyFailed[2]['awaitingSince']);
    }

    public function test_a_reaction_does_not_wait_for_an_answer(): void
    {
        $since = Carbon::parse('2026-11-01');
        $reaction = (object) ['social_conversation_id' => 1, 'from_page' => false, 'type' => 'reaction', 'sent_at' => Carbon::parse('2026-11-10 09:30')];
        $stats = ResponseTimes::perConversation([
            $reaction,
            $this->msg(1, true, '2026-11-10 10:15'), // an order update, not a reply to the 👍
        ], $since);

        $this->assertSame([], $stats[1]['responses']);
        $this->assertFalse($stats[1]['inbound']);

        $onlyReaction = ResponseTimes::perConversation([$reaction], $since);
        $this->assertNull($onlyReaction[1]['awaitingSince']);
    }

    public function test_summary_of_nothing(): void
    {
        $summary = ResponseTimes::summary([]);

        $this->assertSame(0, $summary['conversations']);
        $this->assertNull($summary['replyRate']);
        $this->assertNull($summary['medianFirst']);
        $this->assertNull($summary['average']);
    }

    public function test_format(): void
    {
        $this->assertSame('—', ResponseTimes::format(null));
        $this->assertSame('45s', ResponseTimes::format(45));
        $this->assertSame('12m', ResponseTimes::format(750));
        $this->assertSame('3h 20m', ResponseTimes::format(12000));
        $this->assertSame('2h', ResponseTimes::format(7200));
        $this->assertSame('2d 4h', ResponseTimes::format(187200));
    }
}
