<?php

namespace HardenedStacks\Maintenance;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;

class MaintenanceState
{
    public const MODE_OFF = 'off';
    public const MODE_BANNER = 'banner';
    public const MODE_READ_ONLY = 'read_only';
    public const MODE_CLOSED = 'closed';

    public function __construct(
        private SettingsRepositoryInterface $settings
    ) {
    }

    public function mode(): string
    {
        return $this->normalizeMode((string) $this->settings->get('hardened-stacks-maintenance.mode', self::MODE_OFF));
    }

    public function isActive(): bool
    {
        return $this->mode() !== self::MODE_OFF;
    }

    public function isClosed(): bool
    {
        return $this->mode() === self::MODE_CLOSED;
    }

    public function isReadOnly(): bool
    {
        return $this->mode() === self::MODE_READ_ONLY;
    }

    public function showBanner(): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($this->isClosed()) {
            return false;
        }

        return (bool) (int) $this->settings->get('hardened-stacks-maintenance.show_banner', 1);
    }

    public function allowLogin(): bool
    {
        return (bool) (int) $this->settings->get('hardened-stacks-maintenance.allow_login', 1);
    }

    public function title(): string
    {
        $title = trim((string) $this->settings->get(
            'hardened-stacks-maintenance.title',
            'Forum under maintenance'
        ));

        return $title !== '' ? $title : 'Forum under maintenance';
    }

    public function message(): string
    {
        $message = trim((string) $this->settings->get(
            'hardened-stacks-maintenance.message',
            'We are performing maintenance. Please check back soon.'
        ));

        return $message !== '' ? $message : 'We are performing maintenance. Please check back soon.';
    }

    public function isExempt(?User $actor): bool
    {
        return $actor !== null && $actor->isAdmin();
    }

    public function blocksReads(?User $actor): bool
    {
        return $this->isClosed() && ! $this->isExempt($actor);
    }

    public function blocksWrites(?User $actor): bool
    {
        if ($this->isExempt($actor)) {
            return false;
        }

        return $this->isClosed() || $this->isReadOnly();
    }

    /**
     * @return array<string, mixed>
     */
    public function forumAttributes(?User $actor): array
    {
        $mode = $this->mode();

        return [
            'hardened-stacksMaintenanceMode' => $mode,
            'hardened-stacksMaintenanceActive' => $mode !== self::MODE_OFF,
            'hardened-stacksMaintenanceTitle' => $this->title(),
            'hardened-stacksMaintenanceMessage' => $this->message(),
            'hardened-stacksMaintenanceShowBanner' => $this->showBanner(),
            'hardened-stacksMaintenanceAllowLogin' => $this->allowLogin(),
            'hardened-stacksMaintenanceBlocksReads' => $this->blocksReads($actor),
            'hardened-stacksMaintenanceBlocksWrites' => $this->blocksWrites($actor),
        ];
    }

    private function normalizeMode(string $mode): string
    {
        $mode = strtolower(trim($mode));

        return match ($mode) {
            self::MODE_BANNER, 'notice', 'info' => self::MODE_BANNER,
            self::MODE_READ_ONLY, 'readonly', 'read-only' => self::MODE_READ_ONLY,
            self::MODE_CLOSED, 'full', 'lockdown', 'maintenance' => self::MODE_CLOSED,
            default => self::MODE_OFF,
        };
    }
}
