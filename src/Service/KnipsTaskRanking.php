<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Turns the per-task rows Knips exposes in `taskStats` (see
 * Foto-Challenge/docs/analytics-api.md) into three independent rankings for
 * the "Aufgaben-Performance" section: worst play rate (most skipped), worst
 * abandon rate (guest stops participating entirely) and best play rate (most
 * popular). Tasks below the minimum sample size are excluded everywhere, and
 * a row missing a rate stays out of the ranking that rate feeds — the two
 * signals are deliberately not conflated.
 */
class KnipsTaskRanking
{
    private const DEFAULT_MIN_EXPOSURES = 20;

    /**
     * @param array<string, mixed>|null $analytics raw payload from KnipsAnalyticsClient::fetchAnalytics()
     *
     * @return array{
     *     minExposures: int,
     *     totalTracked: int,
     *     totalRanked: int,
     *     worstPlayRate: list<array<string, mixed>>,
     *     worstAbandonRate: list<array<string, mixed>>,
     *     bestPlayRate: list<array<string, mixed>>,
     * }
     */
    public function build(?array $analytics, int $limit = 10): array
    {
        $minExposures = (int) ($analytics['taskStatsMinExposures'] ?? self::DEFAULT_MIN_EXPOSURES);
        /** @var list<array<string, mixed>> $taskStats */
        $taskStats = $analytics['taskStats'] ?? [];

        $rows = array_map($this->normalizeRow(...), $taskStats);
        $ranked = array_values(array_filter(
            $rows,
            static fn (array $row) => $row['exposures'] >= $minExposures,
        ));

        $playRateRows = array_values(array_filter(
            $ranked,
            static fn (array $row) => null !== $row['playRate'],
        ));
        $abandonRateRows = array_values(array_filter(
            $ranked,
            static fn (array $row) => null !== $row['abandonRate'],
        ));

        $worstPlayRate = $playRateRows;
        usort($worstPlayRate, static fn (array $a, array $b) => $b['skipRate'] <=> $a['skipRate']);

        $bestPlayRate = $playRateRows;
        usort($bestPlayRate, static fn (array $a, array $b) => $a['skipRate'] <=> $b['skipRate']);

        $worstAbandonRate = $abandonRateRows;
        usort($worstAbandonRate, static fn (array $a, array $b) => $b['abandonRate'] <=> $a['abandonRate']);

        return [
            'minExposures' => $minExposures,
            'totalTracked' => count($taskStats),
            'totalRanked' => count($ranked),
            'worstPlayRate' => array_slice($worstPlayRate, 0, $limit),
            'worstAbandonRate' => array_slice($worstAbandonRate, 0, $limit),
            'bestPlayRate' => array_slice($bestPlayRate, 0, $limit),
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{
     *     taskId: string,
     *     cat: string,
     *     text: string,
     *     playedCount: int,
     *     skippedCount: int,
     *     abandonedCount: int,
     *     exposures: int,
     *     playRate: float|null,
     *     skipRate: float|null,
     *     abandonRate: float|null,
     * }
     */
    private function normalizeRow(array $row): array
    {
        $playRate = $row['playRate'] ?? null;

        return [
            'taskId' => (string) ($row['taskId'] ?? ''),
            'cat' => (string) ($row['cat'] ?? ''),
            'text' => (string) ($row['text'] ?? ''),
            'playedCount' => (int) ($row['playedCount'] ?? 0),
            'skippedCount' => (int) ($row['skippedCount'] ?? 0),
            'abandonedCount' => (int) ($row['abandonedCount'] ?? 0),
            'exposures' => (int) ($row['exposures'] ?? 0),
            'playRate' => null !== $playRate ? (float) $playRate : null,
            'skipRate' => null !== $playRate ? 1 - (float) $playRate : null,
            'abandonRate' => null !== ($row['abandonRate'] ?? null) ? (float) $row['abandonRate'] : null,
        ];
    }
}
