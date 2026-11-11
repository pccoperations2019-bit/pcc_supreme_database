<?php

namespace App\Services;

class BrowserShotEnvironmentService
{
    private array $nodePaths;

    private array $npmPaths;

    private array $chromeArguments;

    public function __construct()
    {
        $this->nodePaths = $this->getNodePaths();
        $this->npmPaths = $this->getNpmPaths();
        $this->chromeArguments = $this->getDefaultChromeArguments();
    }

    public function getNodePath(): string
    {
        foreach ($this->nodePaths as $path) {
            if ($this->isExecutable($path)) {
                return $path;
            }
        }

        return 'node';
    }

    public function getNpmPath(): string
    {
        foreach ($this->npmPaths as $path) {
            if ($this->isExecutable($path)) {
                return $path;
            }
        }

        return 'npm';
    }

    public function getChromePath(): ?string
    {
        if ($this->isDockerEnvironment()) {
            return '/usr/local/bin/chromium/chrome-linux/chrome';
        }

        if ($this->isWindowsEnvironment()) {
            return $this->findWindowsChromePath();
        }

        return null;
    }

    public function getChromeArguments(): array
    {
        return $this->chromeArguments;
    }

    private function isDockerEnvironment(): bool
    {
        return $this->getenv('LARAVEL_SAIL') || $this->fileExists('/.dockerenv');
    }

    private function isWindowsEnvironment(): bool
    {
        return PHP_OS_FAMILY === 'Windows' || str_starts_with(PHP_OS, 'WIN');
    }

    private function findWindowsChromePath(): ?string
    {
        $possiblePaths = [
            env('PUPPETEER_EXECUTABLE_PATH'),
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
        ];

        foreach ($possiblePaths as $path) {
            if ($path && $this->fileExists($path)) {
                return $path;
            }
        }

        // Check for Puppeteer bundled Chrome in user cache
        $userProfile = env('USERPROFILE') ?: getcwd();
        $puppeteerCachePath = $userProfile.'\\.cache\\puppeteer';

        if ($this->fileExists($puppeteerCachePath)) {
            $chromePath = $this->findPuppeteerChrome($puppeteerCachePath);
            if ($chromePath) {
                return $chromePath;
            }
        }

        return null;
    }

    private function findPuppeteerChrome(string $cachePath): ?string
    {
        $chromeDir = $cachePath.'\\chrome';
        if (! is_dir($chromeDir)) {
            return null;
        }

        // Find the latest win64 chrome installation
        $entries = glob($chromeDir.'\\win64-*', GLOB_ONLYDIR);
        if ($entries === false || $entries === []) {
            return null;
        }

        foreach ($entries as $entry) {
            $chromeExe = $entry.'\\chrome-win64\\chrome.exe';
            if ($this->fileExists($chromeExe)) {
                return $chromeExe;
            }
        }

        return null;
    }

    private function getNodePaths(): array
    {
        if ($this->isDockerEnvironment()) {
            return ['node'];
        }

        return [
            '/opt/homebrew/opt/node@22/bin/node',
            '/opt/homebrew/opt/node/bin/node',
            '/usr/local/bin/node',
            'node',
        ];
    }

    private function getNpmPaths(): array
    {
        if ($this->isDockerEnvironment()) {
            return ['npm'];
        }

        return [
            '/opt/homebrew/opt/node@22/bin/npm',
            '/opt/homebrew/opt/node/bin/npm',
            '/usr/local/bin/npm',
            'npm',
        ];
    }

    private function getDefaultChromeArguments(): array
    {
        if ($this->isDockerEnvironment()) {
            return [
                'no-sandbox',
                'disable-setuid-sandbox',
                'disable-dev-shm-usage',
                'disable-gpu',
            ];
        }

        return [];
    }

    private function isExecutable(string $path): bool
    {
        return $path === 'node' || $path === 'npm' || is_executable($path);
    }

    private function getenv(string $name): bool
    {
        return (bool) env($name);
    }

    private function fileExists(string $path): bool
    {
        return file_exists($path);
    }
}
