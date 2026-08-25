<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads open GitHub issues (pull requests excluded) across the configured
 * workspace app repos. One unreachable/misconfigured repo does not block
 * the others.
 */
class GithubIssuesClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'GITHUB_TOKEN')] private readonly string $token,
        #[Autowire(env: 'GITHUB_ISSUE_REPOS')] private readonly string $repoList,
    ) {
    }

    /**
     * @return list<array{repo: string, number: int, title: string, url: string, createdAt: string, updatedAt: string}>
     */
    public function fetchOpenIssues(): array
    {
        $issues = [];

        foreach ($this->repos() as $fullName) {
            foreach ($this->fetchOpenIssuesForRepo($fullName) as $issue) {
                $issues[] = $issue;
            }
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    private function repos(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $this->repoList))));
    }

    /**
     * @return list<array{repo: string, number: int, title: string, url: string, createdAt: string, updatedAt: string}>
     */
    private function fetchOpenIssuesForRepo(string $fullName): array
    {
        try {
            $response = $this->httpClient->request('GET', "https://api.github.com/repos/{$fullName}/issues", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->token,
                    'Accept' => 'application/vnd.github+json',
                    'User-Agent' => 'control-plane',
                ],
                'query' => ['state' => 'open', 'per_page' => 100],
            ]);

            $shortName = str_contains($fullName, '/') ? substr($fullName, strrpos($fullName, '/') + 1) : $fullName;

            $issues = [];
            foreach ($response->toArray() as $item) {
                if (isset($item['pull_request'])) {
                    continue;
                }

                $issues[] = [
                    'repo' => $shortName,
                    'number' => $item['number'],
                    'title' => $item['title'],
                    'url' => $item['html_url'],
                    'createdAt' => $item['created_at'],
                    'updatedAt' => $item['updated_at'],
                ];
            }

            return $issues;
        } catch (ExceptionInterface) {
            return [];
        }
    }
}
