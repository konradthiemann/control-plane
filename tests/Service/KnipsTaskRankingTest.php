<?php

namespace App\Tests\Service;

use App\Service\KnipsTaskRanking;
use PHPUnit\Framework\TestCase;

class KnipsTaskRankingTest extends TestCase
{
    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function task(array $overrides): array
    {
        return array_merge([
            'taskId' => 'task-default',
            'cat' => 'Der Klassiker',
            'text' => 'Mach ein Foto von etwas Rotem.',
            'playedCount' => 0,
            'skippedCount' => 0,
            'abandonedCount' => 0,
            'exposures' => 0,
            'playRate' => null,
            'abandonRate' => null,
        ], $overrides);
    }

    public function testTasksBelowMinExposuresAreExcludedFromAllRankings(): void
    {
        $analytics = [
            'taskStatsMinExposures' => 20,
            'taskStats' => [
                $this->task(['taskId' => 'below-threshold', 'exposures' => 19, 'playRate' => 0.1, 'abandonRate' => 0.9]),
            ],
        ];

        $result = (new KnipsTaskRanking())->build($analytics);

        self::assertSame([], $result['worstPlayRate']);
        self::assertSame([], $result['worstAbandonRate']);
        self::assertSame([], $result['bestPlayRate']);
        self::assertSame(0, $result['totalRanked']);
    }

    public function testWorstPlayRateIsSortedDescendingBySkipRate(): void
    {
        $analytics = [
            'taskStatsMinExposures' => 20,
            'taskStats' => [
                $this->task(['taskId' => 'high-play', 'exposures' => 50, 'playRate' => 0.9, 'abandonRate' => 0.1]),
                $this->task(['taskId' => 'low-play', 'exposures' => 50, 'playRate' => 0.2, 'abandonRate' => 0.1]),
                $this->task(['taskId' => 'mid-play', 'exposures' => 50, 'playRate' => 0.5, 'abandonRate' => 0.1]),
            ],
        ];

        $result = (new KnipsTaskRanking())->build($analytics);

        self::assertSame(
            ['low-play', 'mid-play', 'high-play'],
            array_column($result['worstPlayRate'], 'taskId'),
        );
    }

    public function testBestPlayRateIsSortedAscendingBySkipRate(): void
    {
        $analytics = [
            'taskStatsMinExposures' => 20,
            'taskStats' => [
                $this->task(['taskId' => 'high-play', 'exposures' => 50, 'playRate' => 0.9, 'abandonRate' => 0.1]),
                $this->task(['taskId' => 'low-play', 'exposures' => 50, 'playRate' => 0.2, 'abandonRate' => 0.1]),
                $this->task(['taskId' => 'mid-play', 'exposures' => 50, 'playRate' => 0.5, 'abandonRate' => 0.1]),
            ],
        ];

        $result = (new KnipsTaskRanking())->build($analytics);

        self::assertSame(
            ['high-play', 'mid-play', 'low-play'],
            array_column($result['bestPlayRate'], 'taskId'),
        );
    }

    public function testWorstAbandonRateIsItsOwnIndependentRankingSortedDescending(): void
    {
        $analytics = [
            'taskStatsMinExposures' => 20,
            'taskStats' => [
                // best play rate, but worst abandon rate — must NOT reuse the play-rate ordering.
                $this->task(['taskId' => 'popular-but-fatal', 'exposures' => 50, 'playRate' => 0.95, 'abandonRate' => 0.8]),
                $this->task(['taskId' => 'skipped-but-safe', 'exposures' => 50, 'playRate' => 0.1, 'abandonRate' => 0.05]),
                $this->task(['taskId' => 'middling', 'exposures' => 50, 'playRate' => 0.5, 'abandonRate' => 0.3]),
            ],
        ];

        $result = (new KnipsTaskRanking())->build($analytics);

        self::assertSame(
            ['popular-but-fatal', 'middling', 'skipped-but-safe'],
            array_column($result['worstAbandonRate'], 'taskId'),
        );
    }

    public function testEachRankingIsLimitedToTheGivenLimit(): void
    {
        $tasks = [];
        for ($i = 0; $i < 15; ++$i) {
            $tasks[] = $this->task(['taskId' => "task-{$i}", 'exposures' => 50, 'playRate' => $i / 15, 'abandonRate' => $i / 15]);
        }

        $analytics = ['taskStatsMinExposures' => 20, 'taskStats' => $tasks];

        $result = (new KnipsTaskRanking())->build($analytics, 5);

        self::assertCount(5, $result['worstPlayRate']);
        self::assertCount(5, $result['bestPlayRate']);
        self::assertCount(5, $result['worstAbandonRate']);
        self::assertSame(15, $result['totalRanked']);
    }

    public function testRowsWithNullPlayRateAreExcludedFromPlayRateRankingsButMayAppearInAbandonRanking(): void
    {
        $analytics = [
            'taskStatsMinExposures' => 20,
            'taskStats' => [
                $this->task(['taskId' => 'null-play-rate', 'exposures' => 50, 'playRate' => null, 'abandonRate' => 0.7]),
                $this->task(['taskId' => 'normal', 'exposures' => 50, 'playRate' => 0.5, 'abandonRate' => 0.1]),
            ],
        ];

        $result = (new KnipsTaskRanking())->build($analytics);

        self::assertNotContains('null-play-rate', array_column($result['worstPlayRate'], 'taskId'));
        self::assertNotContains('null-play-rate', array_column($result['bestPlayRate'], 'taskId'));
        self::assertContains('null-play-rate', array_column($result['worstAbandonRate'], 'taskId'));
    }

    public function testRowsWithNullAbandonRateAreExcludedFromAbandonRateRanking(): void
    {
        $analytics = [
            'taskStatsMinExposures' => 20,
            'taskStats' => [
                $this->task(['taskId' => 'null-abandon-rate', 'exposures' => 50, 'playRate' => 0.5, 'abandonRate' => null]),
                $this->task(['taskId' => 'normal', 'exposures' => 50, 'playRate' => 0.5, 'abandonRate' => 0.1]),
            ],
        ];

        $result = (new KnipsTaskRanking())->build($analytics);

        self::assertNotContains('null-abandon-rate', array_column($result['worstAbandonRate'], 'taskId'));
    }

    public function testNullAnalyticsYieldsEmptyRankingsAndDefaultMinExposures(): void
    {
        $result = (new KnipsTaskRanking())->build(null);

        self::assertSame([], $result['worstPlayRate']);
        self::assertSame([], $result['worstAbandonRate']);
        self::assertSame([], $result['bestPlayRate']);
        self::assertSame(0, $result['totalRanked']);
        self::assertSame(0, $result['totalTracked']);
        self::assertSame(20, $result['minExposures']);
    }

    public function testMissingTaskStatsKeyYieldsEmptyRankingsWithoutError(): void
    {
        $result = (new KnipsTaskRanking())->build(['funnel' => []]);

        self::assertSame([], $result['worstPlayRate']);
        self::assertSame([], $result['worstAbandonRate']);
        self::assertSame([], $result['bestPlayRate']);
        self::assertSame(0, $result['totalRanked']);
        self::assertSame(0, $result['totalTracked']);
        self::assertSame(20, $result['minExposures']);
    }

    public function testTotalTrackedCountsAllTaskStatsRegardlessOfThreshold(): void
    {
        $analytics = [
            'taskStatsMinExposures' => 20,
            'taskStats' => [
                $this->task(['taskId' => 'below', 'exposures' => 5, 'playRate' => 0.5, 'abandonRate' => 0.1]),
                $this->task(['taskId' => 'above', 'exposures' => 50, 'playRate' => 0.5, 'abandonRate' => 0.1]),
            ],
        ];

        $result = (new KnipsTaskRanking())->build($analytics);

        self::assertSame(2, $result['totalTracked']);
        self::assertSame(1, $result['totalRanked']);
    }

    public function testRowsAreNormalizedWithSkipRateDerivedFromPlayRate(): void
    {
        $analytics = [
            'taskStatsMinExposures' => 20,
            'taskStats' => [
                $this->task([
                    'taskId' => 'task-1',
                    'cat' => 'Der Klassiker',
                    'text' => 'Mach ein Foto von etwas Blauem.',
                    'playedCount' => 30,
                    'skippedCount' => 20,
                    'abandonedCount' => 5,
                    'exposures' => 50,
                    'playRate' => 0.6,
                    'abandonRate' => 0.1,
                ]),
            ],
        ];

        $result = (new KnipsTaskRanking())->build($analytics);

        $row = $result['worstPlayRate'][0];
        self::assertSame('task-1', $row['taskId']);
        self::assertSame('Der Klassiker', $row['cat']);
        self::assertSame('Mach ein Foto von etwas Blauem.', $row['text']);
        self::assertSame(30, $row['playedCount']);
        self::assertSame(20, $row['skippedCount']);
        self::assertSame(5, $row['abandonedCount']);
        self::assertSame(50, $row['exposures']);
        self::assertSame(0.6, $row['playRate']);
        self::assertEqualsWithDelta(0.4, $row['skipRate'], 0.0001);
        self::assertSame(0.1, $row['abandonRate']);
    }
}
