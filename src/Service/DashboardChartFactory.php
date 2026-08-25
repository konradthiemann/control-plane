<?php

namespace App\Service;

use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

/**
 * Builds all Chart.js configs used across the dashboard, so every chart
 * shares the same palette/options instead of each caller reinventing them.
 *
 * Colors follow the workspace's dataviz palette (references/palette.md):
 * one sequential blue for magnitude comparisons, ordinal blue steps for
 * ordered stages (funnel), the first two categorical slots (blue/orange,
 * a pre-validated adjacent pair) where two distinct categories are shown.
 */
class DashboardChartFactory
{
    private const BLUE = '#2a78d6';
    private const BLUE_STEP_LIGHT = '#86b6ef';
    private const BLUE_STEP_MID = '#5598e7';
    private const ORANGE = '#eb6834';
    private const MUTED = '#898781';
    private const GRID = '#e1e0d9';

    public function __construct(private readonly ChartBuilderInterface $chartBuilder)
    {
    }

    /**
     * @param array<string, int> $countsByApp
     */
    public function issuesPerAppChart(array $countsByApp): Chart
    {
        arsort($countsByApp);

        $chart = $this->chartBuilder->createChart(Chart::TYPE_BAR);
        $chart->setData([
            'labels' => array_keys($countsByApp),
            'datasets' => [[
                'label' => 'Offene Issues',
                'backgroundColor' => self::BLUE,
                'borderRadius' => 4,
                'data' => array_values($countsByApp),
            ]],
        ]);
        $chart->setOptions($this->barOptions(horizontal: true, legend: false));

        return $chart;
    }

    /**
     * @param array{appOpen?: int, joinSuccess?: int, photoUpload?: int} $funnel
     */
    public function knipsFunnelChart(array $funnel): Chart
    {
        $chart = $this->chartBuilder->createChart(Chart::TYPE_BAR);
        $chart->setData([
            'labels' => ['App geöffnet', 'Beigetreten', 'Foto hochgeladen'],
            'datasets' => [[
                'label' => 'Funnel',
                'backgroundColor' => [self::BLUE_STEP_LIGHT, self::BLUE_STEP_MID, self::BLUE],
                'borderRadius' => 4,
                'data' => [
                    $funnel['appOpen'] ?? 0,
                    $funnel['joinSuccess'] ?? 0,
                    $funnel['photoUpload'] ?? 0,
                ],
            ]],
        ]);
        $chart->setOptions($this->barOptions(horizontal: true, legend: false));

        return $chart;
    }

    /**
     * @param list<array{cat: string, count: int}> $rows
     */
    public function knipsCategoryChart(array $rows, string $label): Chart
    {
        $chart = $this->chartBuilder->createChart(Chart::TYPE_BAR);
        $chart->setData([
            'labels' => array_column($rows, 'cat'),
            'datasets' => [[
                'label' => $label,
                'backgroundColor' => self::BLUE,
                'borderRadius' => 4,
                'data' => array_column($rows, 'count'),
            ]],
        ]);
        $chart->setOptions($this->barOptions(horizontal: true, legend: false));

        return $chart;
    }

    /**
     * @param list<array{device: string, count: int}> $rows
     */
    public function knipsDevicesChart(array $rows): Chart
    {
        $colors = [self::BLUE, self::ORANGE];

        $datasets = [];
        foreach (array_values($rows) as $i => $row) {
            $datasets[] = [
                'label' => ucfirst($row['device']),
                'backgroundColor' => $colors[$i % count($colors)],
                'data' => [$row['count']],
            ];
        }

        $chart = $this->chartBuilder->createChart(Chart::TYPE_BAR);
        $chart->setData([
            'labels' => ['Geräte'],
            'datasets' => $datasets,
        ]);
        $chart->setOptions($this->barOptions(horizontal: true, legend: true, stacked: true));

        return $chart;
    }

    /**
     * @return array<string, mixed>
     */
    private function barOptions(bool $horizontal, bool $legend, bool $stacked = false): array
    {
        $valueAxis = $horizontal ? 'x' : 'y';
        $categoryAxis = $horizontal ? 'y' : 'x';

        return [
            'indexAxis' => $horizontal ? 'y' : 'x',
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => $legend, 'labels' => ['color' => self::MUTED]],
                'tooltip' => ['mode' => 'nearest', 'intersect' => true],
            ],
            'scales' => [
                $valueAxis => [
                    'beginAtZero' => true,
                    'stacked' => $stacked,
                    'ticks' => ['color' => self::MUTED, 'precision' => 0],
                    'grid' => ['color' => self::GRID],
                ],
                $categoryAxis => [
                    'stacked' => $stacked,
                    'ticks' => ['color' => self::MUTED],
                    'grid' => ['display' => false],
                ],
            ],
        ];
    }
}
