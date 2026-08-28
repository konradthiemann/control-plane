<?php

namespace App\Config;

final class AppEntry
{
    public function __construct(
        public readonly string $slug,
        public readonly string $displayName,
        public readonly string $githubRepo,
        public readonly bool $hasKnipsAnalytics = false,
        public readonly bool $hasDoeweAnalytics = false,
        public readonly bool $hasDoeweCrm = false,
        public readonly bool $hasPrizedCrm = false,
        public readonly bool $hasPrizedAnalytics = false,
    ) {
    }
}
