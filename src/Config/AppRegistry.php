<?php

namespace App\Config;

/**
 * Static registry of the workspace apps the Control Plane knows about.
 * No database needed — this list changes rarely and only via a deploy.
 */
final class AppRegistry
{
    /**
     * @return list<AppEntry>
     */
    public static function all(): array
    {
        return [
            new AppEntry('doewe', 'Doewe', 'Doewe', hasDoeweAnalytics: true),
            new AppEntry('pokekon', 'Pokekon', 'Pokekon'),
            new AppEntry('prized', 'Prized', 'Pok-mon-TCG-Prize-Checker'),
            new AppEntry('knips', 'Knips', 'Foto-Challenge', hasKnipsAnalytics: true),
            new AppEntry('waldbingo', 'Waldbingo', 'Waldbingo'),
            new AppEntry('bilderraetsel', 'Bilderrätsel', 'bilderraetsel'),
            new AppEntry('portfolio2', 'Portfolio', 'portfolio2'),
        ];
    }

    public static function findBySlug(string $slug): ?AppEntry
    {
        foreach (self::all() as $app) {
            if ($app->slug === $slug) {
                return $app;
            }
        }

        return null;
    }
}
